<ul class="membergy-downloads">
    @foreach ($block->items as $item)
        @continue($item->file === null)
        <li><a href="{{ $mediaUrls->file($item->file->uuid) }}">{{ $item->label }}</a></li>
    @endforeach
</ul>
