<div class="membergy-text-image membergy-text-image--{{ $block->position }} membergy-text-image-width--{{ $block->width }}">
    <div class="membergy-rich-text">{!! $block->body->value !!}</div>
    <img src="{{ $mediaUrls->image($block->image->uuid)->url() }}" alt="{{ $block->image->alt ?? '' }}">
</div>
