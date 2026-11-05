@if ($block->formUuid !== null || $block->formHandle !== '')
<div class="membergy-contact-form" @if ($block->formUuid !== null) data-membergy-form-uuid="{{ $block->formUuid }}" @endif data-membergy-form="{{ $block->formUuid ?? $block->formHandle }}">
    @if ($block->topics !== [])
        <ul class="membergy-contact-topics">
            @foreach ($block->topics as $topic)
                <li data-topic="{{ $topic->key }}">{{ $topic->label }}</li>
            @endforeach
        </ul>
    @endif
</div>
@endif
