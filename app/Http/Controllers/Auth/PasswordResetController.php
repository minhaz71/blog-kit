<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Security\LoginSecurityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

class PasswordResetController extends Controller
{
    public function request()
    {
        return view('auth.forgot-password');
    }

    public function email(Request $request)
    {
        $request->validate(['email' => ['required', 'email']]);

        // Owner preference: tell the visitor plainly when there's no account
        // yet and point them to registration (instead of the generic
        // "if that email exists" message). This trades a small
        // email-enumeration signal for a clearer customer experience.
        if (! User::where('email', $request->email)->exists()) {
            return back()->withInput($request->only('email'))->with('no_account', true);
        }

        Password::sendResetLink($request->only('email'));

        return back()->with('success', 'A password reset link has been sent to '.$request->email.'. Please check your inbox (and spam folder).');
    }

    public function reset(Request $request, string $token)
    {
        return view('auth.reset-password', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function update(Request $request, LoginSecurityService $security)
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => $security->passwordRules(),
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => $password,
                    'password_changed_at' => now(),
                ])->save();
            },
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('success', 'Password updated. Please sign in.')
            : back()->withErrors(['email' => __($status)]);
    }
}
