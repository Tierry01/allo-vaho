<?php

use App\Domain\Admin\Actions\InviteAdmin;
use App\Domain\Admin\Enums\AdminRole;
use App\Domain\Admin\Models\Admin;
use App\Domain\Admin\Models\AdminInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\Process\Process;

/*
 * Real concurrency: two independent PHP processes, each with its own database
 * connection, accept the same invitation at the same time. This test cannot
 * run inside a rolled-back transaction (the workers would not see its data),
 * so it commits to allo_vaho_test and removes everything it created.
 */

const CONCURRENCY_EMAIL = 'concurrence@concurrency.invalid';

function cleanUpConcurrency(): void
{
    $invitations = AdminInvitation::query()->where('email', CONCURRENCY_EMAIL)->pluck('id');
    $admins = Admin::withTrashed()->where('email', CONCURRENCY_EMAIL)->pluck('id');

    Activity::query()
        ->where(fn ($query) => $query
            ->where(fn ($q) => $q->where('subject_type', (new AdminInvitation)->getMorphClass())->whereIn('subject_id', $invitations))
            ->orWhere(fn ($q) => $q->where('causer_type', (new Admin)->getMorphClass())->whereIn('causer_id', $admins)))
        ->delete();

    DB::table('model_has_roles')->where('model_type', (new Admin)->getMorphClass())->whereIn('model_id', $admins)->delete();
    AdminInvitation::query()->whereIn('id', $invitations)->delete();
    Admin::withTrashed()->whereIn('id', $admins)->forceDelete();
}

beforeEach(function () {
    expect(DB::selectOne('select current_database() as name')->name)->toBe('allo_vaho_test');

    cleanUpConcurrency();
    Notification::fake();
});

afterEach(function () {
    cleanUpConcurrency();
});

/**
 * @return array<int, array<string, mixed>>
 */
function acceptConcurrently(string $token, int $holdMs): array
{
    $startAt = microtime(true) + 3.0; // leaves time for both processes to boot
    $environment = ['DB_DATABASE' => 'allo_vaho_test', 'APP_ENV' => 'testing', 'MAIL_MAILER' => 'array', 'CACHE_STORE' => 'array'];

    $workers = collect(['Processus A', 'Processus B'])->map(function (string $name) use ($token, $startAt, $holdMs, $environment): Process {
        $process = new Process([PHP_BINARY, base_path('tests/Support/accept-invitation-worker.php'), $token, $name, (string) $startAt, (string) $holdMs], base_path(), $environment, null, 60);
        $process->start();

        return $process;
    });

    return $workers->map(function (Process $process): array {
        $process->wait();

        return json_decode($process->getOutput(), true) ?? ['result' => 'error', 'error' => $process->getErrorOutput()];
    })->all();
}

it('lets only one of two simultaneous processes accept an invitation', function (int $holdMs) {
    ['url' => $url] = app(InviteAdmin::class)->handle(CONCURRENCY_EMAIL, AdminRole::Support);

    $results = acceptConcurrently(Str::afterLast($url, '/'), $holdMs);

    $accepted = collect($results)->where('result', 'accepted');
    $refused = collect($results)->where('result', 'refused');

    expect(collect($results)->where('result', 'error')->all())->toBe([])
        ->and($accepted)->toHaveCount(1)
        ->and($refused)->toHaveCount(1)
        ->and($refused->first()['state'])->toBe('accepted')
        ->and(Admin::withTrashed()->where('email', CONCURRENCY_EMAIL)->count())->toBe(1)
        ->and(Admin::where('email', CONCURRENCY_EMAIL)->firstOrFail()->name)->toBe($accepted->first()['name'])
        ->and(AdminInvitation::where('email', CONCURRENCY_EMAIL)->firstOrFail()->isAccepted())->toBeTrue();

    if ($holdMs > 0) {
        // The refused process waited for the lock held by the other one,
        // instead of reading a state that was about to change.
        expect($refused->first()['elapsed_ms'])->toBeGreaterThanOrEqual((int) ($holdMs * 0.8));
    }
})->with([
    'started at the same instant' => [0],
    'first process holds the lock for 1.5 s' => [1500],
]);
