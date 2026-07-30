@props(['crumbs' => []])
@if(count($crumbs) > 1)
    <nav aria-label="Breadcrumb" class="text-sm text-gray-500">
        <ol class="flex flex-wrap items-center gap-1">
            @foreach($crumbs as $crumb)
                <li class="flex items-center gap-1">
                    @if(!$loop->first) <span aria-hidden="true">/</span> @endif
                    @if($crumb['url'] && !$loop->last)
                        <a href="{{ $crumb['url'] }}" class="hover:text-indigo-600">{{ $crumb['name'] }}</a>
                    @else
                        <span class="text-gray-900" @if($loop->last) aria-current="page" @endif>{{ $crumb['name'] }}</span>
                    @endif
                </li>
            @endforeach
        </ol>
    </nav>
@endif
