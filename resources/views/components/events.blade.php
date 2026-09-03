@props(['events'])
<div {{ $attributes->class('membergy-events') }}>
    @foreach ($events as $event)
        <article class="membergy-event">
            <h2>{{ $event->title }}</h2>
            @if ($event->startAt)<time datetime="{{ $event->startAt->format(DATE_ATOM) }}">{{ $event->startAt->format('d.m.Y H:i') }}</time>@endif
            @if ($event->description)<p>{{ $event->description }}</p>@endif
        </article>
    @endforeach
</div>
