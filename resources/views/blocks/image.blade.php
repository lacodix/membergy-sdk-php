<figure class="membergy-image">
    @if ($block->link)
        <a href="{{ $block->link->url }}" @if ($block->link->openInNewTab) target="_blank" @endif @if ($block->link->rel !== []) rel="{{ implode(' ', $block->link->rel) }}" @endif>
    @endif
    <img src="{{ $mediaUrls->image($block->image->uuid)->url() }}" alt="{{ $block->image->alt ?? '' }}">
    @if ($block->link)
        </a>
    @endif
    @if ($block->caption)
        <figcaption>{{ $block->caption }}</figcaption>
    @endif
</figure>
