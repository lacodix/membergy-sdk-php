<ul class="membergy-downloads">
    @foreach ($block->items as $item)
        <li><a href="{{ $mediaUrls->file($item->file->uuid) }}">{{ $item->label }}</a></li>
    @endforeach
</ul>
