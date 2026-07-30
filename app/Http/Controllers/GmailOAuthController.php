<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Services\Mail\GmailOAuth;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * The /hmmail OAuth endpoints for the 1-click Gmail connection.
 * Staff-only: connecting a mailbox is an admin action.
 */
class GmailOAuthController extends Controller
{
    /** Kick off the Google consent screen. */
    public function connect(Request $request, GmailOAuth $oauth)
    {
        $this->authorizeStaff($request);

        abort_unless(GmailOAuth::configured(), 400, 'Save the Gmail Client ID and Secret in Email settings first.');

        $state = Str::random(40);
        $request->session()->put('hmmail.state', $state);

        return redirect()->away($oauth->authUrl($state));
    }

    /** Google redirects here — the URI you paste into Google Cloud Console. */
    public function callback(Request $request, GmailOAuth $oauth)
    {
        $this->authorizeStaff($request);

        $settingsUrl = \App\Filament\Pages\EmailSettings::getUrl();

        // CSRF: the state must match what connect() planted in the session.
        if (! hash_equals((string) $request->session()->pull('hmmail.state'), (string) $request->query('state'))) {
            return redirect($settingsUrl)->with('hmmail_error', 'Sign-in session expired — click Connect Gmail again.');
        }

        if ($request->query('error') || ! $request->query('code')) {
            return redirect($settingsUrl)->with('hmmail_error', 'Google authorization was cancelled ('.($request->query('error') ?: 'no code').').');
        }

        try {
            $oauth->exchange((string) $request->query('code'));
        } catch (\Throwable $e) {
            return redirect($settingsUrl)->with('hmmail_error', mb_substr($e->getMessage(), 0, 300));
        }

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'gmail_connected',
            'subject' => 'settings:emails',
            'new_values' => ['account' => GmailOAuth::connectedEmail()],
            'ip_address' => $request->ip(),
        ]);

        return redirect($settingsUrl)->with('hmmail_success', 'Gmail connected: '.GmailOAuth::connectedEmail());
    }

    protected function authorizeStaff(Request $request): void
    {
        $user = $request->user();

        abort_unless($user && $user->is_active && $user->isStaff(), 403);
    }
}
