@props(['posts', 'resolve' => null])
<div {{ $attributes->class('membergy-post-list') }}>
    @foreach ($posts as $post)
        @php($url = is_callable($resolve) ? $resolve($post) : null)
        <article class="membergy-post-card">
            @if ($post->featuredMedia)
                <img src="{{ $post->featuredMedia->url }}" alt="{{ $post->featuredMedia->alt ?? '' }}">
            @endif
            <h2>
                @if ($url)<a href="{{ $url }}">{{ $post->title }}</a>@else{{ $post->title }}@endif
            </h2>
            @if ($post->teaser)<p>{{ $post->teaser }}</p>@endif
        </article>
    @endforeach
</div>
