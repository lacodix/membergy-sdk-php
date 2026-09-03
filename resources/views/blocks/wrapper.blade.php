@php
    $settings = property_exists($block, 'settings') ? $block->settings : null;
    $backgroundImage = $settings?->background?->image;
    $localTitle = property_exists($block, 'title') ? $block->title : null;
@endphp
<section
    @if ($settings?->anchor) id="{{ $settings->anchor }}" @endif
    class="{{ implode(' ', $classes) }}"
    @if ($settings?->background?->type) data-membergy-background="{{ $settings->background->type }}" @endif
    @if ($settings?->background?->color) data-membergy-background-color="{{ $settings->background->color }}" @endif
    @if ($backgroundImage) data-membergy-background-image="{{ $mediaUrls->image($backgroundImage->uuid)->url() }}" @endif
>
    @if ($localTitle)
        <h2 class="membergy-block-title">{{ $localTitle }}</h2>
    @endif
    {!! $content !!}
</section>
