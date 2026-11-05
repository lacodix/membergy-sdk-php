<div class="membergy-logo-cloud">
    @foreach ($block->items as $item)
        @continue($item->image === null)
        @if ($item->link?->url)<a href="{{ $item->link->url }}" @if ($item->link->openInNewTab) target="_blank" @endif @if ($item->link->rel !== []) rel="{{ implode(' ', $item->link->rel) }}" @endif>@endif
        <img src="{{ $mediaUrls->image($item->image->uuid)->url() }}" alt="{{ $item->image->alt ?? '' }}">
        @if ($item->link?->url)</a>@endif
    @endforeach
</div>
