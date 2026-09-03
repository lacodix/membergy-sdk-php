<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Laravel\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Lacodix\MembergySdk\DataObjects\BlockDocument;
use Lacodix\MembergySdk\DataObjects\DynamicIncludes;
use Lacodix\MembergySdk\Laravel\Rendering\BladeContentRenderer;

final class Content extends Component
{
    public readonly string $html;

    public function __construct(
        BlockDocument $document,
        BladeContentRenderer $renderer,
        ?DynamicIncludes $included = null,
    ) {
        $this->html = $renderer->render($document, $included);
    }

    public function render(): View
    {
        return view('membergy::components.content');
    }
}
