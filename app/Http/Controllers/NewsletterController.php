<?php

namespace App\Http\Controllers;

use App\Models\Subscriber;
use Illuminate\Http\Request;

class NewsletterController extends Controller
{
    public function subscribe(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);

        Subscriber::updateOrCreate(
            ['email' => strtolower($data['email'])],
            ['consented_at' => now(), 'unsubscribed_at' => null],
        );

        return back()->with('success', 'You are subscribed!');
    }
}
