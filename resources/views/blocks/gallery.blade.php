<div class="membergy-gallery" data-per-page="{{ $block->perPage }}" @if ($block->autoplay) data-autoplay @endif @if ($block->lightbox) data-lightbox @endif>
    @foreach ($block->items as $item)
        @continue($item->image === null)
        <figure class="membergy-gallery-item">
            @if ($item->link?->url)
                <a href="{{ $item->link->url }}" @if ($item->link->openInNewTab) target="_blank" @endif @if ($item->link->rel !== []) rel="{{ implode(' ', $item->link->rel) }}" @endif>
            @endif
            <img src="{{ $mediaUrls->image($item->image->uuid)->url() }}" alt="{{ $item->image->alt ?? '' }}">
            @if ($item->overlayText)
                <span class="membergy-gallery-overlay">{{ $item->overlayText }}</span>
            @endif
            @if ($item->link?->url)
                </a>
            @endif
            @if ($item->caption)
                <figcaption>{{ $item->caption }}</figcaption>
            @endif
        </figure>
    @endforeach
</div>
