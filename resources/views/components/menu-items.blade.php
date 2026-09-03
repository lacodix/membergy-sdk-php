<ul>
    @foreach ($items as $item)
        @php
            $url = $item->target === null
                ? null
                : ($resolve instanceof \Lacodix\MembergySdk\MenuUrlResolver
                    ? $resolve->resolve($item->target)
                    : $resolve($item->target));
        @endphp
        <li>
            @if ($url)
                <a href="{{ $url }}" @if ($item->openInNewTab) target="_blank" @endif @if ($item->rel !== []) rel="{{ implode(' ', $item->rel) }}" @endif>{{ $item->label }}</a>
            @else
                <span>{{ $item->label }}</span>
            @endif
            @if ($item->children !== [])
                @include('membergy::components.menu-items', ['items' => $item->children, 'resolve' => $resolve])
            @endif
        </li>
    @endforeach
</ul>
