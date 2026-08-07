<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\AdminMfa;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminMfaResetCommand extends Command
{
    protected $signature = 'admin:mfa-reset
                            {email : Admin account email}
                            {--force : Required confirmation flag}';

    protected $description = 'Clear MFA for an admin (lost device). Revokes sessions. Requires --force.';

    public function handle(AdminMfa $mfa): int
    {
        if (! $this->option('force')) {
            $this->error('Refusing to run without --force. Example: php artisan admin:mfa-reset admin@example.com --force');

            return self::FAILURE;
        }

        $email = strtolower(trim((string) $this->argument('email')));
        $user = User::query()->where('email', $email)->first();

        if (! $user || ! $user->is_admin) {
            $this->error('No admin user found for that email.');
            Log::warning('admin.mfa_reset_failed', ['email' => $email, 'reason' => 'not_found']);

            return self::FAILURE;
        }

        if (! $this->confirm("Clear MFA for {$user->email} (id {$user->id}) and revoke all sessions?")) {
            $this->warn('Aborted.');

            return self::FAILURE;
        }

        $mfa->disable($user);
        // Immediate re-enrollment required after ops reset.
        $user->forceFill(['two_factor_grace_ends_at' => now()])->save();

        $deleted = 0;
        if (DB::getSchemaBuilder()->hasTable('sessions')) {
            $deleted = DB::table('sessions')->where('user_id', $user->id)->delete();
        }

        Log::warning('admin.mfa_reset', [
            'user_id' => $user->id,
            'email' => $user->email,
            'sessions_revoked' => $deleted,
            'by' => 'artisan:admin:mfa-reset',
        ]);

        $this->info("MFA cleared for {$user->email}. Sessions revoked: {$deleted}. Admin must enroll MFA on next login.");

        return self::SUCCESS;
    }
}
