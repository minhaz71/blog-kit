<?php

namespace App\Services\Mail;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * WP Mail SMTP-style "1-click" Gmail connection. The admin pastes a Google
 * OAuth Client ID/Secret, clicks Connect Gmail, approves the consent
 * screen, and order email flows through their Gmail account via the Gmail
 * API (no app passwords, no SMTP credentials).
 *
 * The redirect URI is fixed at /hmmail/callback so it can be copied into
 * the Google Cloud Console once and never touched again.
 */
class GmailOAuth
{
    public const SCOPES = 'https://www.googleapis.com/auth/gmail.send openid email';

    public static function clientId(): string
    {
        return trim((string) setting('emails.gmail_client_id', ''));
    }

    public static function clientSecret(): string
    {
        return trim((string) setting('emails.gmail_client_secret', ''));
    }

    public static function redirectUri(): string
    {
        return route('hmmail.callback');
    }

    public static function configured(): bool
    {
        return self::clientId() !== '' && self::clientSecret() !== '';
    }

    public static function connected(): bool
    {
        return trim((string) setting('emails.gmail_refresh_token', '')) !== '';
    }

    public static function connectedEmail(): string
    {
        return (string) setting('emails.gmail_account', '');
    }

    /** The Google consent screen URL (offline access → refresh token). */
    public function authUrl(string $state): string
    {
        return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => self::clientId(),
            'redirect_uri' => self::redirectUri(),
            'response_type' => 'code',
            'scope' => self::SCOPES,
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ]);
    }

    /** Exchange the callback code; store the refresh token + account email. */
    public function exchange(string $code): void
    {
        $response = Http::asForm()->timeout(15)->post('https://oauth2.googleapis.com/token', [
            'code' => $code,
            'client_id' => self::clientId(),
            'client_secret' => self::clientSecret(),
            'redirect_uri' => self::redirectUri(),
            'grant_type' => 'authorization_code',
        ])->throw()->json();

        if (empty($response['refresh_token'])) {
            throw new RuntimeException('Google did not return a refresh token — remove the app from your Google account permissions and connect again.');
        }

        Setting::set('emails.gmail_refresh_token', $response['refresh_token']);
        Setting::set('emails.gmail_account', $this->emailFromIdToken((string) ($response['id_token'] ?? '')));

        Cache::forget('gmail-access-token');
    }

    /** Short-lived API token from the stored refresh token, cached ~50 min. */
    public function accessToken(): string
    {
        $refreshToken = (string) setting('emails.gmail_refresh_token', '');

        if ($refreshToken === '') {
            throw new RuntimeException('Gmail is not connected — open Email settings and click "Connect Gmail".');
        }

        return Cache::remember('gmail-access-token', 3000, function () use ($refreshToken): string {
            $response = Http::asForm()->timeout(15)->post('https://oauth2.googleapis.com/token', [
                'refresh_token' => $refreshToken,
                'client_id' => self::clientId(),
                'client_secret' => self::clientSecret(),
                'grant_type' => 'refresh_token',
            ])->throw()->json();

            return (string) $response['access_token'];
        });
    }

    public static function disconnect(): void
    {
        Setting::set('emails.gmail_refresh_token', '');
        Setting::set('emails.gmail_account', '');
        Cache::forget('gmail-access-token');
    }

    /** The account email rides in the id_token payload (direct from Google over TLS). */
    protected function emailFromIdToken(string $idToken): string
    {
        $parts = explode('.', $idToken);

        if (count($parts) !== 3) {
            return '';
        }

        $payload = (array) json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);

        return (string) ($payload['email'] ?? '');
    }
}
