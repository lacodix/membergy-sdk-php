<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Laravel\Rendering;

use Illuminate\Contracts\View\Factory;
use Lacodix\MembergySdk\Contracts\ContentRenderer;
use Lacodix\MembergySdk\DataObjects\BlockDocument;
use Lacodix\MembergySdk\DataObjects\Blocks\AnchorNavigationBlock;
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
            [],
            $this->navigationAnchors($document->blocks, includePage: true),
        );
    }

    /**
     * @param  list<Block>  $blocks
     * @param  list<array{id: string, title: string, level?: int}>  $anchors
     * @param  array<string, list<array{id: string, title: string, level?: int}>>|null  $navigationAnchors
     */
    public function renderBlocks(
        array $blocks,
        ?DynamicIncludes $included = null,
        array $anchors = [],
        ?array $navigationAnchors = null,
    ): string {
        $navigationAnchors ??= $this->navigationAnchors($blocks);

        return implode('', array_map(
            fn (Block $block): string => $this->renderBlock($block, $included, $anchors, $navigationAnchors),
            $blocks,
        ));
    }

    /**
     * @param  list<array{id: string, title: string, level?: int}>  $anchors
     * @param  array<string, list<array{id: string, title: string, level?: int}>>|null  $navigationAnchors
     */
    public function renderBlock(
        Block $block,
        ?DynamicIncludes $included = null,
        array $anchors = [],
        ?array $navigationAnchors = null,
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
            'anchors' => $block instanceof AnchorNavigationBlock
                ? ($navigationAnchors[$block->id()] ?? $anchors)
                : $anchors,
            'navigationAnchors' => $navigationAnchors,
            'mediaUrls' => $this->mediaUrls,
        ])->render();

        if ($content === '' && $this->anchor($block) === null) {
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

    /** @return array{id: string, title: string, level?: int}|null */
    private function anchor(Block $block): ?array
    {
        if ($block instanceof UnknownBlock || ! property_exists($block, 'settings')) {
            return null;
        }
        $anchor = $block->settings->anchor;
        if (! is_string($anchor) || trim($anchor) === '') {
            return null;
        }
        $title = $block instanceof TitleBlock ? $block->text
            : (property_exists($block, 'title') ? $block->title : null);

        return ['id' => $anchor, 'title' => is_string($title) && trim($title) !== '' ? $title : $anchor];
    }

    /**
     * @param  list<Block>  $blocks
     * @return array<string, list<array{id: string, title: string, level?: int}>>
     */
    private function navigationAnchors(array $blocks, bool $includePage = false): array
    {
        $stream = iterator_to_array($this->flatten($blocks), preserve_keys: false);
        $candidates = [];
        foreach ($stream as $index => $block) {
            $anchor = $this->anchor($block);
            if ($anchor !== null) {
                $candidates[$index] = $anchor;
            }
        }
        $ranges = [];
        $active = null;
        foreach ($stream as $index => $block) {
            if ($block instanceof AnchorNavigationBlock) {
                $active = $block->scope === 'following' ? $block->id() : null;
                if ($active !== null) {
                    $ranges[$active] = [];
                } elseif ($includePage) {
                    $page = $candidates;
                    unset($page[$index]);
                    $ranges[$block->id()] = array_values($page);
                }
            } elseif ($active !== null && isset($candidates[$index])) {
                $ranges[$active][] = $candidates[$index];
            }
        }

        return $ranges;
    }

    /**
     * @param  list<Block>  $blocks
     * @return \Generator<int, Block>
     */
    private function flatten(array $blocks): \Generator
    {
        foreach ($blocks as $block) {
            yield $block;
            if ($block instanceof ColumnsBlock) {
                foreach ($block->columns as $column) {
                    yield from $this->flatten($column->blocks);
                }
            }
        }
    }
}
