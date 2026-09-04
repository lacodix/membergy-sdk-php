<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Laravel\Rendering;

use Illuminate\Contracts\View\Factory;
use Lacodix\MembergySdk\Contracts\ContentRenderer;
use Lacodix\MembergySdk\DataObjects\BlockDocument;
use Lacodix\MembergySdk\DataObjects\Blocks\Block;
use Lacodix\MembergySdk\DataObjects\Blocks\ColumnsBlock;
use Lacodix\MembergySdk\DataObjects\Blocks\TitleBlock;
use Lacodix\MembergySdk\DataObjects\Blocks\UnknownBlock;
use Lacodix\MembergySdk\DataObjects\DynamicIncludes;
use Lacodix\MembergySdk\DataObjects\DynamicIncludes\DynamicInclude;
use Lacodix\MembergySdk\Media\MediaUrlFactory;

final readonly class BladeContentRenderer implements ContentRenderer
{
    private const VIEWS = [
        'agenda' => 'membergy::blocks.agenda',
        'anchor_navigation' => 'membergy::blocks.anchor-navigation',
        'columns' => 'membergy::blocks.columns',
        'contact_form' => 'membergy::blocks.contact-form',
        'downloads' => 'membergy::blocks.downloads',
        'gallery' => 'membergy::blocks.gallery',
        'image' => 'membergy::blocks.image',
        'logo_cloud' => 'membergy::blocks.logo-cloud',
        'post_category' => 'membergy::blocks.post-category',
        'raw_html' => 'membergy::blocks.raw-html',
        'testimonials' => 'membergy::blocks.testimonials',
        'text' => 'membergy::blocks.text',
        'text_image' => 'membergy::blocks.text-image',
        'title' => 'membergy::blocks.title',
        'video' => 'membergy::blocks.video',
    ];

    public function __construct(
        private Factory $views,
        private MediaUrlFactory $mediaUrls,
    ) {}

    public function render(BlockDocument $document, ?DynamicIncludes $included = null): string
    {
        return $this->renderBlocks(
            $document->blocks,
            $included,
            $this->anchors($document->blocks),
        );
    }

    /**
     * @param  list<Block>  $blocks
     * @param  list<array{id: string, title: string, level: int}>  $anchors
     */
    public function renderBlocks(
        array $blocks,
        ?DynamicIncludes $included = null,
        array $anchors = [],
    ): string {
        return implode('', array_map(
            fn (Block $block): string => $this->renderBlock($block, $included, $anchors),
            $blocks,
        ));
    }

    /**
     * @param  list<array{id: string, title: string, level: int}>  $anchors
     */
    public function renderBlock(
        Block $block,
        ?DynamicIncludes $included = null,
        array $anchors = [],
    ): string {
        if ($block instanceof UnknownBlock) {
            return '';
        }

        $view = self::VIEWS[$block->type()] ?? null;
        if ($view === null || ! $this->views->exists($view)) {
            return '';
        }

        $content = $this->views->make($view, [
            'block' => $block,
            'renderer' => $this,
            'dynamic' => $this->dynamicInclude($block, $included),
            'included' => $included,
            'anchors' => $anchors,
            'mediaUrls' => $this->mediaUrls,
        ])->render();

        if ($content === '') {
            return '';
        }

        return $this->views->make('membergy::blocks.wrapper', [
            'block' => $block,
            'content' => $content,
            'classes' => $this->classes($block),
            'mediaUrls' => $this->mediaUrls,
        ])->render();
    }

    private function dynamicInclude(Block $block, ?DynamicIncludes $included): ?DynamicInclude
    {
        if ($included === null) {
            return null;
        }

        foreach ($included->dynamic as $dynamic) {
            if ($dynamic->blockId() === $block->id() && $dynamic->type() === $block->type()) {
                return $dynamic;
            }
        }

        return null;
    }

    /** @return list<string> */
    private function classes(Block $block): array
    {
        if (! property_exists($block, 'settings')) {
            return ['membergy-block', 'membergy-block--'.$block->type()];
        }

        $settings = $block->settings;
        $classes = ['membergy-block', 'membergy-block--'.$block->type()];
        foreach ([
            'color' => $settings->color,
            'container' => $settings->container,
            'spacing' => $settings->spacing,
            'overlap' => $settings->overlap,
            'margin' => $settings->margin,
            'title-position' => $settings->titlePosition,
        ] as $name => $value) {
            if (is_string($value) && $value !== '') {
                $classes[] = 'membergy-'.$name.'--'.$value;
            }
        }

        return $classes;
    }

    /**
     * @param  list<Block>  $blocks
     * @return list<array{id: string, title: string, level: int}>
     */
    private function anchors(array $blocks): array
    {
        $anchors = [];
        foreach ($blocks as $block) {
            if (
                $block instanceof TitleBlock
                && is_string($block->settings->anchor)
                && $block->settings->anchor !== ''
            ) {
                $anchors[] = [
                    'id' => $block->settings->anchor,
                    'title' => $block->text,
                    'level' => $block->level,
                ];
            }

            if (! $block instanceof ColumnsBlock) {
                continue;
            }

            foreach ($block->columns as $column) {
                $anchors = [...$anchors, ...$this->anchors($column->blocks)];
            }
        }

        return $anchors;
    }
}
