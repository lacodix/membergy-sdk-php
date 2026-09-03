<div class="membergy-contact-form" data-membergy-form="{{ $block->formHandle }}">
    @if ($block->topics !== [])
        <ul class="membergy-contact-topics">
            @foreach ($block->topics as $topic)
                <li data-topic="{{ $topic->key }}">{{ $topic->label }}</li>
            @endforeach
        </ul>
    @endif
</div>
