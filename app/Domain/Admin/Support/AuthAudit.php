<?php

namespace App\Domain\Admin\Support;

use App\Domain\Admin\Models\Admin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use SensitiveParameter;
use Spatie\Activitylog\Models\Activity;

/**
 * Writes the security events of the back-office to the activity log, under
 * the "auth" log name. Only identifiers and context are recorded: never a
 * password, a code, a two-factor secret, a token or a full link.
 */
class AuthAudit
{
    public const LOG_NAME = 'auth';

    /**
     * Set by the server commands, so their entries are attributed to the console.
     */
    protected bool $console = false;

    public function fromConsole(): static
    {
        $this->console = true;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    public function record(string $event, string $description, ?Admin $causer = null, ?Model $subject = null, array $properties = []): Activity
    {
        $logger = activity(self::LOG_NAME)
            ->event($event)
            ->withProperties([...$this->context(), ...$properties]);

        if ($causer) {
            $logger->causedBy($causer);
        }

        if ($subject) {
            $logger->performedOn($subject);
        }

        return $logger->log($description);
    }

    /**
     * A keyed hash of an e-mail address: lets attempts on the same unknown
     * address be correlated without storing the address itself.
     */
    public static function emailFingerprint(#[SensitiveParameter] ?string $email): ?string
    {
        if (blank($email)) {
            return null;
        }

        return hash_hmac('sha256', Str::lower(trim($email)), (string) config('app.key'));
    }

    /**
     * @return array<string, mixed>
     */
    protected function context(): array
    {
        if ($this->console || (app()->runningInConsole() && ! app()->runningUnitTests())) {
            return ['channel' => 'console', 'console_user' => get_current_user() ?: null];
        }

        return [
            'channel' => 'web',
            'ip' => request()->ip(),
            'user_agent' => Str::limit((string) request()->userAgent(), 200, ''),
        ];
    }
}
