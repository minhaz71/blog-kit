@if(count($toc) > 1)
    <nav class="bd-toc bd-toc--{{ \App\Support\TocStyle::active() }} {{ ($sidebar ?? false) ? 'bd-toc--side' : '' }}" aria-label="Table of contents">
        <p class="bd-toc__title">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 5.25h16.5M3.75 12h16.5m-16.5 6.75h16.5"/>
            </svg>
            In this article
        </p>
        <ol class="bd-toc__list">
            @foreach($toc as $item)
                <li class="bd-toc__item--{{ $item['level'] }}"><a href="#{{ $item['anchor'] }}">{{ $item['text'] }}</a></li>
            @endforeach
        </ol>
    </nav>
@endif
