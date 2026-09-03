@props(['files'])
<ul {{ $attributes->class('membergy-downloads') }}>
    @foreach ($files as $file)
        <li><a href="{{ $file->url }}">{{ $file->title ?? $file->filename }}</a></li>
    @endforeach
</ul>
