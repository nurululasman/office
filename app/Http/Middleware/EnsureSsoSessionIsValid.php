<?php

namespace App\Http\Middleware;

use App\Services\Audit\AuditLogger;
use App\Services\Identity\SsoTokenSession;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureSsoSessionIsValid
{
    public function __construct(
        private readonly SsoTokenSession $tokens,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->is_active || ! $this->tokens->isValid($request->session())) {
            if ($user !== null) {
                $this->audit->record(
                    'auth.session.expired',
                    actor: $user,
                    subject: $user,
                    context: ['reason' => $user->is_active ? 'token_or_timeout' : 'local_user_inactive'],
                    request: $request,
                );
            }

            $this->tokens->forget($request->session());
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('welcome')->withErrors(['sso' => 'Sesi Office telah berakhir. Silakan masuk kembali.']);
        }

        return $next($request);
    }
}
