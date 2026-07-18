<?php

namespace App\Http\Controllers\Auth;

use App\Contracts\IdentityProvider;
use App\Exceptions\IdentityProviderException;
use App\Http\Controllers\Controller;
use App\Services\Audit\AuditLogger;
use App\Services\Identity\SsoAuthorizationSession;
use App\Services\Identity\SsoTokenSession;
use App\Services\Identity\SsoUserProvisioner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

class SsoController extends Controller
{
    public function login(Request $request, SsoAuthorizationSession $authorization): RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('office.home');
        }

        try {
            return redirect()->away($authorization->begin($request->session())->url);
        } catch (IdentityProviderException $exception) {
            Log::warning('SSO authorization could not be started.', ['exception' => $exception::class]);

            return redirect()->route('welcome')->withErrors(['sso' => 'SSO belum dapat digunakan. Hubungi administrator.']);
        }
    }

    public function callback(
        Request $request,
        SsoAuthorizationSession $authorization,
        SsoUserProvisioner $provisioner,
        SsoTokenSession $tokenSession,
        AuditLogger $audit,
    ): RedirectResponse {
        if ($request->filled('error')) {
            $request->session()->forget('office.sso.authorization');
            $audit->record('auth.login.failed', context: ['reason' => 'provider_denied'], request: $request);

            return redirect()->route('welcome')->withErrors(['sso' => 'Login SSO dibatalkan atau ditolak.']);
        }

        try {
            $identity = $authorization->complete(
                $request->session(),
                $request->string('state')->toString(),
                $request->string('code')->toString(),
            );
            $user = $provisioner->provision($identity->profile);

            Auth::login($user);
            $request->session()->regenerate();
            $tokenSession->store($request->session(), $identity->tokens);
            $audit->record(
                'auth.login.succeeded',
                actor: $user,
                subject: $user,
                context: ['issuer' => $identity->profile->issuer],
                request: $request,
            );

            return redirect()->intended(route('office.home'));
        } catch (IdentityProviderException $exception) {
            Log::notice('SSO callback rejected.', ['exception' => $exception::class]);
            $audit->record('auth.login.failed', context: ['reason' => 'callback_rejected'], request: $request);

            return redirect()->route('welcome')->withErrors(['sso' => $exception->getMessage()]);
        }
    }

    public function logout(
        Request $request,
        IdentityProvider $identityProvider,
        SsoTokenSession $tokenSession,
        AuditLogger $audit,
    ): RedirectResponse {
        $refreshToken = $tokenSession->refreshToken($request->session());

        if ($refreshToken !== null) {
            try {
                $identityProvider->revoke($refreshToken);
            } catch (Throwable $exception) {
                Log::warning('SSO token revocation failed during local logout.', ['exception' => $exception::class]);
            }
        }

        $audit->record('auth.logout', actor: $request->user(), subject: $request->user(), request: $request);
        $tokenSession->forget($request->session());
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('welcome')->with('status', 'Anda telah keluar dari Office.');
    }
}
