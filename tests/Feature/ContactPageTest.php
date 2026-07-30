<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactPageTest extends TestCase
{
    use RefreshDatabase;

    protected function makeContactPage(): Page
    {
        return Page::create([
            'title' => 'Contact Us',
            'slug' => 'contact-us',
            'content' => '<p>Talk to us.</p>{{contact_info}}{{contact_form}}',
            'status' => 'published',
        ]);
    }

    public function test_shortcodes_render_the_form_and_info_cards(): void
    {
        \App\Models\Setting::set('general.contact_email', 'help@tereahub.test');
        \App\Models\Setting::set('general.contact_phone', '+971 50 123 4567');
        $this->makeContactPage();

        $response = $this->get('/contact-us')->assertOk();

        $response->assertSee('Send us a message')                       // form partial
            ->assertSee('action="'.route('contact.submit').'"', false)
            ->assertSee('company_website', false)                       // honeypot present
            ->assertSee('help@tereahub.test')                           // info card from settings
            ->assertSee('wa.me/971501234567', false);                   // WhatsApp deep link
    }

    public function test_submission_is_stored_and_flashes_success(): void
    {
        $this->makeContactPage();

        $this->from('/contact-us')->post('/contact', [
            'name' => 'Ahmed', 'email' => 'ahmed@example.com',
            'phone' => '+971501234567', 'subject' => 'Delivery to JLT?',
            'message' => 'Do you deliver to JLT cluster D in the evening?',
        ])->assertRedirect('/contact-us')->assertSessionHas('success');

        $this->assertDatabaseHas('contact_messages', [
            'email' => 'ahmed@example.com', 'subject' => 'Delivery to JLT?', 'is_read' => false,
        ]);
    }

    public function test_honeypot_drops_bots_without_storing(): void
    {
        $this->makeContactPage();

        $this->from('/contact-us')->post('/contact', [
            'name' => 'Bot', 'email' => 'bot@spam.com',
            'message' => 'Buy my backlinks now please thanks.',
            'company_website' => 'https://spam.example',
        ])->assertRedirect('/contact-us')->assertSessionHas('success'); // bot sees "success"

        $this->assertSame(0, ContactMessage::count());                  // but nothing saved
    }

    public function test_validation_rejects_garbage(): void
    {
        $this->makeContactPage();

        $this->from('/contact-us')->post('/contact', [
            'name' => '', 'email' => 'not-an-email', 'message' => 'short',
        ])->assertRedirect('/contact-us')->assertSessionHasErrors(['name', 'email', 'message']);

        $this->assertSame(0, ContactMessage::count());
    }
}
