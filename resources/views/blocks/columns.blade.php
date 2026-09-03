<div class="membergy-columns">
    @foreach ($block->columns as $column)
        <div class="membergy-column membergy-column--{{ $column->width }}">
            {!! $renderer->renderBlocks($column->blocks, $included, $anchors) !!}
        </div>
    @endforeach
</div>
