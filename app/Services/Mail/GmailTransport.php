<?php

namespace App\Services\Mail;

use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;

/**
 * Symfony mail transport that delivers through the Gmail API
 * (users.messages.send) using the OAuth connection — the raw RFC-2822
 * message is base64url-encoded and posted with a bearer token. Gmail
 * handles DKIM/SPF for the connected account, so deliverability matches
 * sending from Gmail itself.
 */
class GmailTransport extends AbstractTransport
{
    public function __construct(protected GmailOAuth $oauth)
    {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        Http::withToken($this->oauth->accessToken())
            ->timeout(30)
            ->post('https://gmail.googleapis.com/gmail/v1/users/me/messages/send', [
                'raw' => rtrim(strtr(base64_encode($message->toString()), '+/', '-_'), '='),
            ])
            ->throw();
    }

    public function __toString(): string
    {
        return 'gmail';
    }
}
