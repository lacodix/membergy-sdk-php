<figure class="membergy-video">
    <video controls @if ($block->poster) poster="{{ $mediaUrls->image($block->poster->uuid)->url() }}" @endif>
        <source src="{{ $mediaUrls->file($block->video->uuid) }}">
    </video>
    @if ($block->caption)<figcaption>{{ $block->caption }}</figcaption>@endif
</figure>
