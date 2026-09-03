@if ($block->intro)
    <div class="membergy-rich-text">{!! $block->intro->value !!}</div>
@endif
<ol class="membergy-agenda">
    @foreach ($block->items as $item)
        <li @class(['membergy-agenda-item', 'is-highlighted' => $item->highlight])>
            @if ($item->time)<time>{{ $item->time }}</time>@endif
            <strong>{{ $item->title }}</strong>
            @if ($item->text)<div class="membergy-rich-text">{!! $item->text->value !!}</div>@endif
            @if ($item->link)<a href="{{ $item->link->url }}" @if ($item->link->openInNewTab) target="_blank" @endif @if ($item->link->rel !== []) rel="{{ implode(' ', $item->link->rel) }}" @endif>{{ $item->link->label ?? $item->link->url }}</a>@endif
            @if ($item->file)<a href="{{ $mediaUrls->file($item->file->uuid) }}">{{ __('Download') }}</a>@endif
        </li>
    @endforeach
</ol>
