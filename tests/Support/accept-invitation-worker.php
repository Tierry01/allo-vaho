<?php

/*
 * Test worker: accepts an invitation in its own PHP process, with its own
 * database connection. Used to check real concurrency on allo_vaho_test.
 *
 * Usage: php accept-invitation-worker.php <token> <name> <start-at> <hold-ms>
 *   start-at  Unix time (float) at which to start, so that workers run together
 *   hold-ms   if > 0, waits this long while holding the invitation lock
 */

use App\Domain\Admin\Actions\AcceptAdminInvitation;
use App\Domain\Admin\Exceptions\InvitationNotUsable;
use App\Domain\Admin\Models\Admin;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (DB::selectOne('select current_database() as name')->name !== 'allo_vaho_test') {
    fwrite(STDERR, "Refused: this worker only runs on allo_vaho_test.\n");
    exit(3);
}

[, $token, $name, $startAt, $holdMs] = $argv;

if ((int) $holdMs > 0) {
    // Runs inside the acceptance transaction, after the row lock is taken.
    Admin::creating(fn () => usleep((int) $holdMs * 1000));
}

while (microtime(true) < (float) $startAt) {
    usleep(200);
}

$started = microtime(true);

try {
    $admin = app(AcceptAdminInvitation::class)->handle($token, $name, 'phrase-de-passe-concurrence');
    $result = ['result' => 'accepted', 'admin_id' => $admin->id];
} catch (InvitationNotUsable $exception) {
    $result = ['result' => 'refused', 'state' => $exception->state->value];
} catch (Throwable $exception) {
    $result = ['result' => 'error', 'error' => $exception::class.': '.$exception->getMessage()];
}

echo json_encode([...$result, 'name' => $name, 'elapsed_ms' => (int) round((microtime(true) - $started) * 1000)]);
