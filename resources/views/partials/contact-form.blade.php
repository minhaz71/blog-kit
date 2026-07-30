{{-- Rendered by the {{contact_form}} shortcode — drop it into any CMS page. --}}
<div class="not-prose my-8 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm sm:p-8">
    <h2 class="text-xl font-bold text-gray-900">Send us a message</h2>
    <p class="mt-1 text-sm text-gray-500">We usually reply within a few hours, every day of the week.</p>

    @if(session('success'))
        <div class="mt-4 rounded-lg bg-green-50 px-4 py-3 text-sm font-medium text-green-800 ring-1 ring-green-200">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="mt-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700 ring-1 ring-red-200">
            {{ $errors->first() }}
        </div>
    @endif

    <form action="{{ route('contact.submit') }}" method="POST" class="mt-5 grid gap-4 sm:grid-cols-2">
        @csrf
        {{-- Honeypot — hidden from humans, irresistible to bots. --}}
        <input type="text" name="company_website" value="" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">

        <div>
            <label for="cf-name" class="text-sm font-medium text-gray-700">Name *</label>
            <input id="cf-name" type="text" name="name" required maxlength="100" value="{{ old('name') }}"
                   class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>
        <div>
            <label for="cf-email" class="text-sm font-medium text-gray-700">Email *</label>
            <input id="cf-email" type="email" name="email" required maxlength="255" value="{{ old('email') }}"
                   class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>
        <div>
            <label for="cf-phone" class="text-sm font-medium text-gray-700">Phone / WhatsApp</label>
            <input id="cf-phone" type="tel" name="phone" maxlength="30" value="{{ old('phone') }}" placeholder="+971 …"
                   class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>
        <div>
            <label for="cf-subject" class="text-sm font-medium text-gray-700">Subject</label>
            <input id="cf-subject" type="text" name="subject" maxlength="150" value="{{ old('subject') }}" placeholder="Order question, delivery area…"
                   class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>
        <div class="sm:col-span-2">
            <label for="cf-message" class="text-sm font-medium text-gray-700">Message *</label>
            <textarea id="cf-message" name="message" rows="5" required minlength="10" maxlength="5000"
                      class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('message') }}</textarea>
        </div>
        <div class="sm:col-span-2">
            <button type="submit"
                    class="w-full rounded-lg bg-indigo-600 px-8 py-3 font-semibold text-white hover:bg-indigo-500 sm:w-auto">
                Send message
            </button>
        </div>
    </form>
</div>
