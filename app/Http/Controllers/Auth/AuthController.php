<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Cart\CartService;
use App\Services\Security\LoginSecurityService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(protected LoginSecurityService $security) {}

    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request, CartService $cart)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $ip = (string) $request->ip();

        if ($this->security->isLockedOut($ip)) {
            throw ValidationException::withMessages(['email' => 'Too many failed attempts. Try again later.']);
        }

        if ($this->security->isBlockedUsername($credentials['email'])) {
            $this->security->recordAttempt($credentials['email'], $ip, $request->userAgent(), false);
            throw ValidationException::withMessages(['email' => 'These credentials do not match our records.']);
        }

        $guestSessionId = $request->session()->getId();
        $remember = $request->boolean('remember');

        if (! Auth::attempt($credentials + ['is_active' => true], $remember)) {
            $this->security->recordAttempt($credentials['email'], $ip, $request->userAgent(), false);

            // No account at all → guide them to register (with the email
            // pre-filled) rather than the generic credentials error. An
            // existing-but-wrong-password (or inactive) account still gets the
            // neutral message.
            if (! User::where('email', $credentials['email'])->exists()) {
                return back()->withInput($request->only('email'))->with('no_account', true);
            }

            throw ValidationException::withMessages(['email' => 'These credentials do not match our records.']);
        }

        $request->session()->regenerate();
        // Fresh session — must re-verify 2FA if the user has it enabled.
        $request->session()->forget('two_factor_verified');
        $this->security->recordAttempt($credentials['email'], $ip, $request->userAgent(), true);

        $user = Auth::user();
        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $ip])->saveQuietly();

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'login',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
            'subject' => 'user:'.$user->id,
            'ip_address' => $ip,
        ]);

        $cart->mergeGuestCart($guestSessionId);

        return redirect()->intended(route('account.dashboard'));
    }

    public function showRegister()
    {
        return view('auth.register');
    }

    public function register(Request $request, CartService $cart)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => $this->security->passwordRules(),
            'accepts_marketing' => ['nullable', 'boolean'],
        ]);

        if ($this->security->isBlockedUsername($data['email'])) {
            throw ValidationException::withMessages(['email' => 'This email address cannot be used.']);
        }

        $guestSessionId = $request->session()->getId();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'accepts_marketing' => (bool) ($data['accepts_marketing'] ?? false),
            'password_changed_at' => now(),
        ]);

        event(new Registered($user));

        Auth::login($user);
        $request->session()->regenerate();
        $cart->mergeGuestCart($guestSessionId);

        return redirect()->route('account.dashboard')->with('success', 'Welcome! Please check your email to verify your account.');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    public function verifyEmail(Request $request, int $id, string $hash)
    {
        $user = User::findOrFail($id);

        abort_unless(hash_equals($hash, sha1($user->getEmailForVerification())), 403);
        abort_unless($user->id === $request->user()->id, 403);

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        return redirect()->route('account.dashboard')->with('success', 'Email verified.');
    }

    public function resendVerification(Request $request)
    {
        if (! $request->user()->hasVerifiedEmail()) {
            $request->user()->sendEmailVerificationNotification();
        }

        return back()->with('success', 'Verification link sent.');
    }
}
