<?php

namespace App\Http\Controllers;

use App\Models\ContactMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class ContactController extends Controller
{
    public function store(Request $request)
    {
        // Honeypot: bots fill every field; humans never see this one.
        if ($request->filled('company_website')) {
            return back()->with('success', 'Thanks! Your message has been sent.');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'subject' => ['nullable', 'string', 'max:150'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
        ]);

        $message = ContactMessage::create($data + ['ip_address' => $request->ip()]);

        // Notify the store owner — best-effort, the message is already saved.
        try {
            $adminEmail = (string) setting('general.admin_email', config('mail.from.address'));

            if ($adminEmail) {
                Mail::raw(
                    "New contact message from {$message->name} <{$message->email}>"
                    .($message->phone ? " ({$message->phone})" : '')."\n"
                    .($message->subject ? "Subject: {$message->subject}\n" : '')
                    ."\n{$message->message}\n\nReply to: {$message->email}",
                    fn ($mail) => $mail->to($adminEmail)
                        ->replyTo($message->email, $message->name)
                        ->subject('Contact form: '.($message->subject ?: $message->name)),
                );
            }
        } catch (\Throwable) {
            // Mail transport issues must not lose the enquiry — it's in the DB.
        }

        return back()->with('success', 'Thanks! Your message has been sent. We usually reply within a few hours.');
    }
}
