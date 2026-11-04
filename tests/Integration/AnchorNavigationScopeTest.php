<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Tests\Integration;

use DOMDocument;
use DOMXPath;
use Lacodix\MembergySdk\Contracts\ContentRenderer;
use Lacodix\MembergySdk\DataObjects\BlockDocument;
use Lacodix\MembergySdk\Laravel\MembergyServiceProvider;
use Lacodix\MembergySdk\Laravel\Rendering\BladeContentRenderer;
use Orchestra\Testbench\TestCase;

final class AnchorNavigationScopeTest extends TestCase
{
    /** @return list<class-string> */
    protected function getPackageProviders($app): array
    {
        return [MembergyServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('membergy.tenant', 'scope-test');
        $app['config']->set('membergy.cache.enabled', false);
    }

    public function test_the_paired_candidate_contract_example_renders_both_modes_and_the_legacy_default(): void
    {
        $payload = json_decode(
            (string) file_get_contents(dirname(__DIR__).'/Fixtures/Contracts/AnchorNavigation/v1/block-document.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        self::assertIsArray($payload);
        $html = $this->app->make(ContentRenderer::class)->render(BlockDocument::fromArray($payload));
        self::assertSame([
            '#before|before', '#middle|middle', '#menu-following|menu-following', '#left|left',
            '#menu-boundary|menu-boundary', '#after-boundary|after-boundary', '#menu-last|menu-last',
            '#end|end', '#menu-legacy|menu-legacy',
        ], $this->links($html, 'menu-page'));
        self::assertSame([
            '#before|before', '#menu-page|menu-page', '#middle|middle', '#menu-following|menu-following',
            '#left|left', '#menu-boundary|menu-boundary', '#after-boundary|after-boundary', '#menu-last|menu-last', '#end|end',
        ], $this->links($html, 'menu-legacy'));
        self::assertSame(['#left|left'], $this->links($html, 'menu-following'));
        self::assertSame(['#end|end'], $this->links($html, 'menu-last'));
        self::assertSame([
            '#before|before', '#menu-page|menu-page', '#middle|middle', '#menu-following|menu-following',
            '#left|left', '#after-boundary|after-boundary', '#menu-last|menu-last', '#end|end', '#menu-legacy|menu-legacy',
        ], $this->links($html, 'menu-boundary'));
    }

    public function test_each_marker_obeys_its_scope_and_depth_first_column_boundaries_with_inert_levels(): void
    {
        $document = BlockDocument::fromArray(['schema_version' => 1, 'blocks' => [
            $this->heading('before', 2),
            $this->menu('page', 'page'),
            $this->heading('early', 3),
            $this->menu('first', 'following', [2]),
            $this->block('text', 'rich', ['body' => ['format' => 'html', 'value' => '<h2>Rich heading</h2>']], ['anchor' => 'rich-anchor']),
            $this->block('title', 'unanchored', ['text' => 'Unanchored', 'level' => 2]),
            $this->block('future_widget', 'unknown', ['secret' => 'must-not-render']),
            $this->columns('outer', [
                [
                    $this->heading('left', 2),
                    $this->menu('column', 'following', [3]),
                    $this->columns('inner', [[$this->heading('inner', 3)], [$this->heading('filtered', 2)]]),
                ],
                [
                    $this->heading('right', 3),
                    $this->menu('boundary', 'page', [6]),
                    $this->heading('after-boundary', 3),
                ],
            ]),
            $this->heading('after-columns', 2),
            $this->menu('last', 'following'),
            $this->heading('final', 3),
            $this->menu('empty', 'following'),
            $this->menu('next-page', 'page', [2]),
            $this->heading('after-empty', 2),
            $this->menu('end', 'following'),
        ]]);
        $html = $this->app->make(ContentRenderer::class)->render($document);

        self::assertSame(['#rich-anchor|rich-anchor', '#left|left'], $this->links($html, 'first'));
        self::assertSame(['#inner|inner', '#filtered|filtered', '#right|right'], $this->links($html, 'column'));
        self::assertSame(['#final|final'], $this->links($html, 'last'));
        self::assertSame([], $this->links($html, 'empty'));
        self::assertSame([], $this->links($html, 'end'));
        self::assertSame([
            '#before|before', '#early|early', '#first|first', '#rich-anchor|rich-anchor',
            '#left|left', '#column|column', '#inner|inner', '#filtered|filtered', '#right|right',
            '#boundary|boundary', '#after-boundary|after-boundary', '#after-columns|after-columns',
            '#last|last', '#final|final', '#empty|empty', '#next-page|next-page', '#after-empty|after-empty', '#end|end',
        ], $this->links($html, 'page'));
        self::assertSame([
            '#before|before', '#page|page', '#early|early', '#first|first', '#rich-anchor|rich-anchor',
            '#left|left', '#column|column', '#inner|inner', '#filtered|filtered', '#right|right',
            '#after-boundary|after-boundary', '#after-columns|after-columns',
            '#last|last', '#final|final', '#empty|empty', '#next-page|next-page', '#after-empty|after-empty', '#end|end',
        ], $this->links($html, 'boundary'));
        self::assertSame([
            '#before|before', '#page|page', '#early|early', '#first|first', '#rich-anchor|rich-anchor',
            '#left|left', '#column|column', '#inner|inner', '#filtered|filtered', '#right|right',
            '#boundary|boundary', '#after-boundary|after-boundary', '#after-columns|after-columns',
            '#last|last', '#final|final', '#empty|empty', '#after-empty|after-empty', '#end|end',
        ], $this->links($html, 'next-page'));
        self::assertStringNotContainsString('must-not-render', $html);
    }

    public function test_omitted_scope_keeps_page_behavior_and_last_following_marker_reaches_document_end(): void
    {
        $legacy = $this->menu('legacy', 'page');
        unset($legacy['data']['scope']);
        $document = BlockDocument::fromArray(['schema_version' => 1, 'blocks' => [
            $this->heading('before', 2), $legacy,
            $this->menu('last', 'following'), $this->heading('after', 3),
        ]]);
        $html = $this->app->make(ContentRenderer::class)->render($document);

        self::assertSame(['#before|before', '#last|last', '#after|after'], $this->links($html, 'legacy'));
        self::assertSame(['#after|after'], $this->links($html, 'last'));
    }

    public function test_following_scope_preserves_escaped_heading_labels_and_anchor_ids(): void
    {
        $title = $this->heading('escaped', 2);
        $title['data']['text'] = '<script>unsafe()</script> & "label"';
        $title['settings']['anchor'] = 'odd"&<anchor>';
        $document = BlockDocument::fromArray(['schema_version' => 1, 'blocks' => [
            $this->menu('escaped-menu', 'following'), $title,
        ]]);
        $html = $this->app->make(ContentRenderer::class)->render($document);

        self::assertSame(['#odd"&<anchor>|<script>unsafe()</script> & "label"'], $this->links($html, 'escaped-menu'));
        self::assertStringNotContainsString('<script>', $html);
        self::assertStringNotContainsString('href="#odd"&', $html);
    }

    public function test_lower_level_methods_remain_usable_with_caller_supplied_anchors(): void
    {
        $document = BlockDocument::fromArray(['schema_version' => 1, 'blocks' => [
            $this->menu('direct', 'following'), $this->heading('local', 2),
        ]]);
        $renderer = $this->app->make(ContentRenderer::class);
        self::assertInstanceOf(BladeContentRenderer::class, $renderer);
        $anchors = [['id' => 'supplied', 'title' => 'Supplied', 'level' => 2]];

        self::assertSame(['#supplied|Supplied'], $this->links($renderer->renderBlock($document->blocks[0], anchors: $anchors), 'direct'));
        self::assertSame(['#local|local'], $this->links($renderer->renderBlocks($document->blocks), 'direct'));
        self::assertSame(['#local|local'], $this->links($renderer->renderBlocks($document->blocks, anchors: $anchors), 'direct'));

        $page = BlockDocument::fromArray(['schema_version' => 1, 'blocks' => [$this->menu('page-direct', 'page')]]);
        self::assertSame([], $this->links($renderer->renderBlocks($page->blocks), 'page-direct'));
        self::assertSame(['#supplied|Supplied'], $this->links($renderer->renderBlocks($page->blocks, anchors: $anchors), 'page-direct'));
        self::assertSame(['#supplied|Supplied'], $this->links($renderer->renderBlock($page->blocks[0], anchors: $anchors), 'page-direct'));
    }

    public function test_common_text_anchor_is_included_and_legacy_levels_are_inert(): void
    {
        $html = $this->app->make(ContentRenderer::class)->render(BlockDocument::fromArray(['schema_version' => 1, 'blocks' => [
            $this->menu('inert', 'following', [6]),
            $this->block('text', 'text', ['body' => ['format' => 'html', 'value' => '']], ['anchor' => 'text']),
            $this->heading('level-one', 1),
        ]]));
        self::assertSame(['#text|text', '#level-one|level-one'], $this->links($html, 'inert'));
    }

    public function test_arbitrary_common_anchors_preserve_identity_duplicates_and_whole_document_boundaries(): void
    {
        $blocks = [
            $this->block('text', 'before', ['title' => 'Plain <title>', 'body' => ['format' => 'html', 'value' => '<h2 id="rich-only">Rich</h2>']], ['anchor' => ' before ']),
            $this->menu('page-all', 'page', [6]),
            $this->menu('range', 'following', []),
            $this->block('image', 'picture', ['image' => null, 'caption' => null, 'link' => null], ['anchor' => 'duplicate']),
            $this->columns('container', [
                [$this->heading('duplicate', 1), $this->block('text', 'blank', ['body' => ['format' => 'html', 'value' => '']], ['anchor' => '  '])],
                [$this->menu('stop', 'page'), $this->heading('after', 6)],
            ]),
        ];
        $blocks[4]['settings'] = ['anchor' => 'container'];
        $html = $this->app->make(ContentRenderer::class)->render(BlockDocument::fromArray(['schema_version' => 1, 'blocks' => $blocks]));
        self::assertSame(['#duplicate|duplicate', '#container|container', '#duplicate|duplicate'], $this->links($html, 'range'));
        self::assertSame(['# before |Plain <title>', '#range|range', '#duplicate|duplicate', '#container|container', '#duplicate|duplicate', '#stop|stop', '#after|after'], $this->links($html, 'page-all'));
        self::assertStringContainsString('Plain &lt;title&gt;', $html);
    }

    public function test_form_reference_and_testimonial_description_are_safe_non_submission_output(): void
    {
        $uuid = '11111111-1111-4111-8111-111111111111';
        $document = BlockDocument::fromArray(['schema_version' => 1, 'blocks' => [
            $this->block('contact_form', 'selected', ['form_uuid' => $uuid]),
            $this->block('contact_form', 'empty', ['form_uuid' => null]),
            $this->block('testimonials', 'quotes', ['title' => null, 'per_page' => 1, 'autoplay' => false, 'items' => [
                ['name' => '<Name>', 'text' => ['format' => 'html', 'value' => '<p>Trusted quote</p>'], 'description' => '<script>bad</script> & Chair'],
                ['name' => 'Old', 'text' => ['format' => 'html', 'value' => ''], 'description' => null],
                ['name' => 'Absent', 'text' => ['format' => 'html', 'value' => '']],
            ]]),
        ]]);
        $html = $this->app->make(ContentRenderer::class)->render($document);
        self::assertSame(1, substr_count($html, 'data-membergy-form-uuid='));
        self::assertStringContainsString('data-membergy-form-uuid="'.$uuid.'"', $html);
        self::assertStringNotContainsString('<form', $html);
        self::assertStringNotContainsString('action=', $html);
        self::assertStringNotContainsString('<script>', $html);
        self::assertStringContainsString('&lt;Name&gt;', $html);
        self::assertStringContainsString('&lt;script&gt;bad&lt;/script&gt; &amp; Chair', $html);
        self::assertStringContainsString('<p>Trusted quote</p>', $html);
    }

    public function test_unfinished_all_block_example_renders_without_empty_media_requests(): void
    {
        $payload = json_decode((string) file_get_contents(dirname(__DIR__).'/Fixtures/Contracts/BlockWire/v1/unfinished-block-document.json'), true, flags: JSON_THROW_ON_ERROR);
        $html = $this->app->make(ContentRenderer::class)->render(BlockDocument::fromArray($payload));
        self::assertStringNotContainsString('<img', $html);
        self::assertStringNotContainsString('<video', $html);
        self::assertStringNotContainsString('src=""', $html);
        self::assertStringNotContainsString('<form', $html);
        self::assertStringContainsString('membergy-anchor-navigation', $html);
    }

    public function test_every_supported_block_kind_can_be_a_common_anchor_target(): void
    {
        $payload = json_decode((string) file_get_contents(dirname(__DIR__).'/Fixtures/Contracts/BlockWire/v1/unfinished-block-document.json'), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);
        foreach ($payload['blocks'] as &$block) {
            $block['settings'] = ['anchor' => 'target-'.$block['type']];
        }
        unset($block);
        $payload['blocks'][14]['data'] = ['scope' => 'page'];
        $html = $this->app->make(ContentRenderer::class)->render(BlockDocument::fromArray($payload));
        self::assertSame([
            '#target-title|target-title', '#target-text|target-text', '#target-image|target-image',
            '#target-text_image|target-text_image', '#target-gallery|target-gallery', '#target-columns|target-columns',
            '#target-raw_html|target-raw_html', '#target-downloads|target-downloads', '#target-agenda|target-agenda',
            '#target-logo_cloud|target-logo_cloud', '#target-video|target-video', '#target-testimonials|target-testimonials',
            '#target-post_category|target-post_category', '#target-contact_form|target-contact_form',
        ], $this->links($html, 'target-anchor_navigation'));
        foreach (['image', 'text_image', 'video', 'contact_form', 'raw_html', 'columns'] as $type) {
            self::assertStringContainsString('id="target-'.$type.'"', $html);
        }
    }

    public function test_richtext_ids_and_unknown_types_are_not_common_anchor_targets_and_null_links_are_inert(): void
    {
        $html = $this->app->make(ContentRenderer::class)->render(BlockDocument::fromArray(['schema_version' => 1, 'blocks' => [
            $this->block('anchor_navigation', 'only', ['scope' => 'page'], ['anchor' => 'only']),
            $this->block('text', 'rich', ['body' => ['format' => 'html', 'value' => '<h2 id="rich-only">Rich</h2>']]),
            $this->block('future', 'unknown', [], ['anchor' => 'unknown']),
            $this->block('image', 'image', ['image' => ['uuid' => '11111111-1111-4111-8111-111111111111', 'alt' => null], 'link' => ['url' => null, 'open_in_new_tab' => false, 'rel' => []], 'caption' => null]),
        ]]));
        self::assertSame([], $this->links($html, 'only'));
        self::assertStringNotContainsString('href=""', $html);
        self::assertStringContainsString('<img', $html);
    }

    public function test_published_three_argument_columns_overrides_keep_document_context_without_worker_state(): void
    {
        $root = sys_get_temp_dir().'/membergy-columns-'.bin2hex(random_bytes(6));
        mkdir($root.'/blocks', 0700, true);
        file_put_contents($root.'/blocks/columns.blade.php', '@foreach ($block->columns as $column){!! $renderer->renderBlocks($column->blocks, $included, $anchors) !!}@endforeach');
        $this->app['view']->prependNamespace('membergy', $root);
        try {
            $renderer = $this->app->make(ContentRenderer::class);
            $document = BlockDocument::fromArray(['schema_version' => 1, 'blocks' => [
                $this->heading('before-override', 2),
                $this->menu('outer-following', 'following'),
                $this->columns('override', [[
                    $this->menu('nested-page', 'page'),
                    $this->menu('nested-following', 'following'),
                    $this->heading('inside-override', 2),
                ], [$this->heading('sibling-override', 2)]]),
                $this->heading('after-override', 2),
            ]]);
            $html = $renderer->render($document);
            self::assertSame(['#inside-override|inside-override', '#sibling-override|sibling-override', '#after-override|after-override'], $this->links($html, 'nested-following'));
            self::assertContains('#before-override|before-override', $this->links($html, 'nested-page'));
            self::assertContains('#after-override|after-override', $this->links($html, 'nested-page'));
            self::assertSame([], $this->links($html, 'outer-following'));
            $next = $renderer->render(BlockDocument::fromArray(['schema_version' => 1, 'blocks' => [$this->menu('fresh-page', 'page'), $this->heading('fresh', 2)]]));
            self::assertSame(['#fresh|fresh'], $this->links($next, 'fresh-page'));
            self::assertStringNotContainsString('override', $next);
        } finally {
            unlink($root.'/blocks/columns.blade.php');
            rmdir($root.'/blocks');
            rmdir($root);
        }
    }

    /** @return array<string, mixed> */
    private function heading(string $id, int $level): array
    {
        return $this->block('title', $id, ['text' => $id, 'level' => $level], ['anchor' => $id]);
    }

    /** @param list<int> $levels
     * @return array<string, mixed>
     */
    private function menu(string $id, string $scope, array $levels = [2, 3]): array
    {
        return $this->block('anchor_navigation', $id, ['levels' => $levels, 'scope' => $scope], ['anchor' => $id]);
    }

    /** @param list<list<array<string, mixed>>> $columns
     * @return array<string, mixed>
     */
    private function columns(string $id, array $columns): array
    {
        return $this->block('columns', $id, ['columns' => array_map(
            static fn (array $blocks): array => ['width' => 'one_half', 'blocks' => $blocks], $columns,
        )]);
    }

    /** @param array<string, mixed> $data
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function block(string $type, string $id, array $data, array $settings = []): array
    {
        return ['id' => $id, 'type' => $type, 'version' => 1, 'data' => $data, 'settings' => $settings];
    }

    /** @return list<string> */
    private function links(string $html, string $marker): array
    {
        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $xpath = new DOMXPath($dom);
        $menus = $xpath->query('//section[@id="'.$marker.'"]/nav');
        self::assertNotFalse($menus);
        self::assertSame(1, $menus->length, 'Expected exactly one rendered menu for '.$marker);
        $links = $xpath->query('.//a', $menus->item(0));
        self::assertNotFalse($links);
        $result = [];
        foreach ($links as $link) {
            self::assertInstanceOf(\DOMElement::class, $link);
            $result[] = $link->getAttribute('href').'|'.$link->textContent;
        }

        return $result;
    }
}
