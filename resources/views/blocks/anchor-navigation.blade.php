<nav class="membergy-anchor-navigation" aria-label="{{ __('Page sections') }}">
    <ul>
        @foreach ($anchors as $anchor)
            <li><a href="#{{ $anchor['id'] }}">{{ $anchor['title'] }}</a></li>
        @endforeach
    </ul>
</nav>
