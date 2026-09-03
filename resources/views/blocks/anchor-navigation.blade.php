<nav class="membergy-anchor-navigation" aria-label="{{ __('Page sections') }}">
    <ul>
        @foreach ($anchors as $anchor)
            @if (in_array($anchor['level'], $block->levels, true))
                <li><a href="#{{ $anchor['id'] }}">{{ $anchor['title'] }}</a></li>
            @endif
        @endforeach
    </ul>
</nav>
