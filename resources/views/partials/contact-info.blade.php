{{-- Rendered by the {{contact_info}} shortcode. Values come from
     General settings (contact email/phone) — edit them there once,
     every page using this block updates. --}}
@php
    $email = (string) setting('general.contact_email', '');
    $phone = (string) setting('general.contact_phone', '');
    $whatsapp = preg_replace('/[^0-9+]/', '', $phone);
@endphp
<div class="not-prose my-8 grid gap-3 sm:grid-cols-3">
    @if($phone)
        <a href="https://wa.me/{{ ltrim($whatsapp, '+') }}" target="_blank" rel="noopener"
           class="group rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-indigo-600 hover:shadow-md">
            <span class="flex h-10 w-10 items-center justify-center rounded-full bg-indigo-600 text-white">
                <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a10 10 0 00-8.6 15.1L2 22l5-1.3A10 10 0 1012 2zm5 14.2c-.2.6-1.2 1.2-1.7 1.2-.4.1-1 .1-1.6-.1-.4-.1-.9-.3-1.5-.5-2.6-1.1-4.3-3.7-4.4-3.9-.1-.2-1-1.4-1-2.6s.6-1.8.9-2c.2-.3.5-.3.7-.3h.5c.2 0 .4 0 .6.4l.9 2.1c.1.2.1.4 0 .6l-.4.6-.4.5c-.1.1-.3.3-.1.6.1.3.7 1.1 1.4 1.8 1 .9 1.8 1.2 2.1 1.3.3.1.4.1.6-.1l.9-1c.2-.3.4-.2.7-.1l2 1c.3.1.5.2.6.4 0 .1 0 .7-.2 1.1z"/></svg>
            </span>
            <p class="mt-3 font-bold text-gray-900">WhatsApp</p>
            <p class="mt-0.5 text-sm text-gray-600 group-hover:text-indigo-600">{{ $phone }}</p>
            <p class="mt-1 text-xs text-gray-400">Fastest reply, 7 days a week</p>
        </a>
        <a href="tel:{{ $whatsapp }}"
           class="group rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-indigo-600 hover:shadow-md">
            <span class="flex h-10 w-10 items-center justify-center rounded-full bg-indigo-600 text-white">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z"/></svg>
            </span>
            <p class="mt-3 font-bold text-gray-900">Call us</p>
            <p class="mt-0.5 text-sm text-gray-600 group-hover:text-indigo-600">{{ $phone }}</p>
            <p class="mt-1 text-xs text-gray-400">For urgent delivery questions</p>
        </a>
    @endif
    @if($email)
        <a href="mailto:{{ $email }}"
           class="group rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-indigo-600 hover:shadow-md">
            <span class="flex h-10 w-10 items-center justify-center rounded-full bg-indigo-600 text-white">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/></svg>
            </span>
            <p class="mt-3 font-bold text-gray-900">Email</p>
            <p class="mt-0.5 break-all text-sm text-gray-600 group-hover:text-indigo-600">{{ $email }}</p>
            <p class="mt-1 text-xs text-gray-400">We reply within a few hours</p>
        </a>
    @endif
</div>
