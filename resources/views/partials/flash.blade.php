@if(session('success') || session('error') || $errors->any())
    <div class="mx-auto max-w-7xl px-4 pt-4 sm:px-6" x-data="{ show: true }" x-show="show">
        @if(session('success'))
            <div class="flex items-center justify-between rounded-md bg-green-50 px-4 py-3 text-sm text-green-800 ring-1 ring-green-200">
                <span>{{ session('success') }}</span>
                <button @click="show = false" aria-label="Dismiss">✕</button>
            </div>
        @endif
        @if(session('error'))
            <div class="flex items-center justify-between rounded-md bg-red-50 px-4 py-3 text-sm text-red-800 ring-1 ring-red-200">
                <span>{{ session('error') }}</span>
                <button @click="show = false" aria-label="Dismiss">✕</button>
            </div>
        @endif
        @if($errors->any())
            <div class="rounded-md bg-red-50 px-4 py-3 text-sm text-red-800 ring-1 ring-red-200">
                <ul class="list-disc pl-4">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
@endif
