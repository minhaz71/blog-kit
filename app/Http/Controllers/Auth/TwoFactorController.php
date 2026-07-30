<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\Security\TotpService;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TwoFactorController extends Controller
{
    public function __construct(protected TotpService $totp) {}

    /**
     * A 2FA secret that no longer decrypts was encrypted under a DIFFERENT
     * APP_KEY (typically a backup restored onto a new server). It is
     * cryptographically dead: neither the user nor an attacker can ever
     * produce a valid code, and the recovery codes are sealed with the same
     * dead key — so leaving it in place permanently locks the account out.
     * Decrypt failure is not attacker-controllable, so the safe recovery is
     * to clear the dead 2FA state (audit-logged) and have the user re-enroll.
     */
    protected function resetUndecryptableTwoFactor(Request $request): void
    {
        $user = $request->user();

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        AuditLog::create([
            'user_id' => $user->id,
            'action' => '2fa_reset_undecryptable',
            'subject' => 'user:'.$user->id,
            'ip_address' => $request->ip(),
        ]);

        $request->session()->forget('two_factor_verified');
    }

    /** Show setup screen: generate a secret + QR code (secret persisted encrypted until confirmed). */
    public function show(Request $request)
    {
        $user = $request->user();
        abort_unless($user, 403);

        if (! $user->two_factor_secret) {
            $secret = $this->totp->generateSecret();
            $user->forceFill(['two_factor_secret' => Crypt::encryptString($secret)])->saveQuietly();
        } else {
            try {
                $secret = Crypt::decryptString($user->two_factor_secret);
            } catch (DecryptException) {
                // Dead cipher (APP_KEY changed) — start enrollment over.
                $this->resetUndecryptableTwoFactor($request);
                $secret = $this->totp->generateSecret();
                $user->forceFill(['two_factor_secret' => Crypt::encryptString($secret)])->saveQuietly();
            }
        }

        $issuer = setting('general.site_name', config('app.name', 'Site'));
        $uri = $this->totp->provisioningUri($secret, $user->email, $issuer);
        // outputBase64 => false is essential: the library defaults to
        // returning a "data:image/svg+xml;base64,…" STRING, which the view's
        // {!! !!} prints as literal text — the QR never shows. Raw <svg>
        // markup inlines correctly and scales crisply.
        $qrSvg = (new QRCode(new QROptions([
            'outputType' => QRCode::OUTPUT_MARKUP_SVG,
            'outputBase64' => false,
            'svgDefs' => '',
        ])))->render($uri);

        return view('auth.two-factor.setup', [
            'secret' => $secret,
            'qrSvg' => $qrSvg,
            'isConfirmed' => (bool) $user->two_factor_confirmed_at,
        ]);
    }

    /** Confirm the code the user typed matches the secret, persist recovery codes. */
    public function confirm(Request $request)
    {
        $data = $request->validate(['code' => ['required', 'string', 'size:6']]);

        $user = $request->user();
        abort_unless($user && $user->two_factor_secret, 403);

        try {
            $secret = Crypt::decryptString($user->two_factor_secret);
        } catch (DecryptException) {
            $this->resetUndecryptableTwoFactor($request);

            return redirect()->route('two-factor.show')
                ->with('error', 'Your previous 2FA setup could not be read (server key changed). Please scan the new QR code and try again.');
        }
        if (! $this->totp->verify($secret, $data['code'])) {
            throw ValidationException::withMessages(['code' => 'That code is incorrect. Please try again.']);
        }

        $recovery = collect(range(1, 8))
            ->map(fn () => strtoupper(Str::random(5).'-'.Str::random(5)))
            ->all();

        $user->forceFill([
            'two_factor_recovery_codes' => Crypt::encryptString(json_encode($recovery)),
            'two_factor_confirmed_at' => now(),
        ])->save();

        AuditLog::create([
            'user_id' => $user->id,
            'action' => '2fa_enabled',
            'subject' => 'user:'.$user->id,
            'ip_address' => $request->ip(),
        ]);

        $request->session()->put('two_factor_verified', true);

        return redirect()->route('two-factor.show')->with([
            'success' => 'Two-factor authentication is now enabled.',
            'recovery_codes' => $recovery,
        ]);
    }

    /** Verify a code during login (or after login, if 2FA challenge is required). */
    public function verify(Request $request)
    {
        $data = $request->validate(['code' => ['required', 'string']]);
        $user = $request->user();
        abort_unless($user && $user->two_factor_secret && $user->two_factor_confirmed_at, 403);

        try {
            $secret = Crypt::decryptString($user->two_factor_secret);
        } catch (DecryptException) {
            // Dead cipher (restored DB, new APP_KEY): no code — not even a
            // recovery code, sealed with the same key — can ever pass. The
            // user already proved their password, so clear the dead 2FA,
            // let this session through, and prompt re-enrollment.
            $this->resetUndecryptableTwoFactor($request);
            $request->session()->put('two_factor_verified', true);

            return redirect()->intended(url('/'))
                ->with('warning', 'Two-factor authentication was reset because the server encryption key changed. Please re-enable it from your security settings.');
        }
        $code = preg_replace('/\s+/', '', $data['code']);

        if ($this->totp->verify($secret, $code)) {
            $request->session()->put('two_factor_verified', true);

            return redirect()->intended(url('/'));
        }

        // Recovery-code fallback
        if ($user->two_factor_recovery_codes) {
            $codes = (array) json_decode(Crypt::decryptString($user->two_factor_recovery_codes), true);
            $normalizedCode = strtoupper($code);
            if (in_array($normalizedCode, $codes, true)) {
                $codes = array_values(array_diff($codes, [$normalizedCode]));
                $user->forceFill(['two_factor_recovery_codes' => Crypt::encryptString(json_encode($codes))])->save();
                $request->session()->put('two_factor_verified', true);

                return redirect()->intended(url('/'));
            }
        }

        throw ValidationException::withMessages(['code' => 'Invalid code. Try again.']);
    }

    /** Turn 2FA off — requires a valid current code. */
    public function disable(Request $request)
    {
        $data = $request->validate(['code' => ['required', 'string', 'size:6']]);
        $user = $request->user();
        abort_unless($user && $user->two_factor_secret, 403);

        try {
            $secret = Crypt::decryptString($user->two_factor_secret);
        } catch (DecryptException) {
            // Dead cipher — no code can validate it; clearing is the intent here.
            $this->resetUndecryptableTwoFactor($request);

            return redirect()->route('two-factor.show')->with('success', 'Two-factor authentication is disabled.');
        }
        if (! $this->totp->verify($secret, $data['code'])) {
            throw ValidationException::withMessages(['code' => 'Invalid code.']);
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        AuditLog::create([
            'user_id' => $user->id,
            'action' => '2fa_disabled',
            'subject' => 'user:'.$user->id,
            'ip_address' => $request->ip(),
        ]);

        $request->session()->forget('two_factor_verified');

        return redirect()->route('two-factor.show')->with('success', 'Two-factor authentication is disabled.');
    }

    /** Challenge screen — user is logged in but hasn't proved 2FA this session. */
    public function challenge(Request $request)
    {
        return view('auth.two-factor.challenge');
    }
}
