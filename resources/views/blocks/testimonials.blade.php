<div class="membergy-testimonials" data-per-page="{{ $block->perPage }}" @if ($block->autoplay) data-autoplay @endif>
    @foreach ($block->items as $item)
        <figure>
            <blockquote>{!! $item->text->value !!}</blockquote>
            <figcaption>{{ $item->name }}</figcaption>
        </figure>
    @endforeach
</div>
