<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SecurityCheck extends Command
{
    protected $signature = 'office:security:check {--production : Enforce production-only HTTPS and runtime rules}';

    protected $description = 'Fail-closed security configuration check for Office';

    public function handle(): int
    {
        $production = (bool) $this->option('production');
        $checks = [
            'APP_KEY configured' => is_string(config('app.key')) && config('app.key') !== '',
            'session encrypted' => config('session.encrypt') === true,
            'session HTTP only' => config('session.http_only') === true,
            'session SameSite safe' => in_array(config('session.same_site'), ['lax', 'strict'], true),
            'SSO client secret configured' => is_string(config('sso.client_secret')) && strlen((string) config('sso.client_secret')) >= 16,
            'SSO tenant configured' => is_string(config('sso.tenant_id')) && config('sso.tenant_id') !== '',
            'document disk private' => config('filesystems.disks.'.config('office.documents.disk').'.serve') === false,
            'PDF queue after commit' => config('queue.connections.database.after_commit') === true,
        ];

        if ($production) {
            $checks += [
                'APP_DEBUG disabled' => config('app.debug') === false,
                'APP_URL uses HTTPS' => str_starts_with((string) config('app.url'), 'https://'),
                'session secure cookie' => config('session.secure') === true,
                'SSO base URL uses HTTPS' => str_starts_with((string) config('sso.base_url'), 'https://'),
                'SSO redirect URI uses HTTPS' => str_starts_with((string) config('sso.redirect_uri'), 'https://'),
            ];
        }

        foreach ($checks as $label => $passed) {
            $this->{$passed ? 'info' : 'error'}(($passed ? '[PASS] ' : '[FAIL] ').$label);
        }

        if (in_array(false, $checks, true)) {
            $this->error('Security configuration gate failed.');

            return self::FAILURE;
        }

        $this->info('Security configuration gate passed.');

        return self::SUCCESS;
    }
}
