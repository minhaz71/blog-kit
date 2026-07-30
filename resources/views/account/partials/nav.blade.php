<nav class="flex gap-1 overflow-x-auto border-b border-gray-200 text-sm font-medium lg:w-52 lg:flex-col lg:border-0" aria-label="Account">
    @foreach([
        ['route' => 'account.dashboard', 'label' => 'Dashboard'],
        ['route' => 'account.orders', 'label' => 'Orders'],
        ['route' => 'account.addresses', 'label' => 'Addresses'],
        ['route' => 'account.wishlist', 'label' => 'Wishlist'],
        ['route' => 'account.profile', 'label' => 'Profile'],
    ] as $link)
        <a href="{{ route($link['route']) }}"
           class="whitespace-nowrap rounded-md px-3 py-2 {{ request()->routeIs($link['route']) ? 'bg-indigo-50 text-indigo-700' : 'hover:bg-gray-50' }}">
            {{ $link['label'] }}
        </a>
    @endforeach
    <form action="{{ route('logout') }}" method="POST">
        @csrf
        <button class="w-full whitespace-nowrap rounded-md px-3 py-2 text-left text-red-600 hover:bg-red-50">Sign out</button>
    </form>
</nav>
