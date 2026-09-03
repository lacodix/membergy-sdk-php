@props(['items', 'image'])
<div {{ $attributes->class('membergy-gallery') }}>
    @foreach ($items as $item)
        <img src="{{ $image($item)->url() }}" alt="{{ $item->alt ?? '' }}">
    @endforeach
</div>
