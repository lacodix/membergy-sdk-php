<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Tests\Integration;

use Lacodix\MembergySdk\Contracts\ContentRenderer;
use Lacodix\MembergySdk\DataObjects\BlockDocument;
use Lacodix\MembergySdk\DataObjects\Blocks\TableBlock;
use Lacodix\MembergySdk\Laravel\MembergyServiceProvider;
use Orchestra\Testbench\TestCase;

final class TableBlockTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [MembergyServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('membergy.tenant', 'table-test');
    }

    public function test_known_sanitized_raw_html_renders_while_unknown_blocks_and_editorial_markers_stay_inert(): void
    {
        $payload = ['schema_version' => 1, 'blocks' => [
            ['id' => 'nav', 'type' => 'anchor_navigation', 'version' => 1, 'settings' => [], 'data' => ['scope' => 'page']],
            ['id' => 'marker', 'type' => 'member_continuation', 'version' => 1, 'settings' => ['anchor' => 'inert-marker'], 'data' => ['future' => 'private-marker-data']],
            ['id' => 'unknown', 'type' => 'future_widget', 'version' => 1, 'settings' => ['anchor' => 'unknown-target'], 'data' => ['html' => '<p>unknown-payload</p>']],
            ['id' => 'future-raw', 'type' => 'raw_html', 'version' => 2, 'settings' => ['anchor' => 'future-raw-target'], 'data' => ['html' => '<p>future-raw-payload</p>']],
            ['id' => 'raw', 'type' => 'raw_html', 'version' => 1, 'settings' => ['anchor' => 'raw-target'], 'data' => ['html' => '<p>Sanitized raw HTML</p>']],
        ]];
        $html = $this->app->make(ContentRenderer::class)->render(BlockDocument::fromArray($payload));
        self::assertStringContainsString('<div class="membergy-raw-html"><p>Sanitized raw HTML</p></div>', $html);
        self::assertStringNotContainsString('unknown-payload', $html);
        self::assertStringNotContainsString('unknown-target', $html);
        self::assertStringNotContainsString('future-raw-payload', $html);
        self::assertStringNotContainsString('future-raw-target', $html);
        self::assertStringNotContainsString('inert-marker', $html);
        self::assertStringNotContainsString('private-marker-data', $html);
        self::assertSame(1, substr_count($html, 'id="raw-target"'));
        self::assertStringContainsString('href="#raw-target"', $html);
    }

    public function test_server_reader_projections_render_only_the_supplied_page_or_post_content(): void
    {
        foreach (['pages', 'posts'] as $family) {
            foreach (['guest', 'member'] as $reader) {
                $data = \contractFixture($family.'/member-continuation-'.$reader.'.json')['data'];
                $document = BlockDocument::fromArray($data['content']);
                $html = $this->app->make(ContentRenderer::class)->render($document);
                self::assertStringContainsString('Dieser Einstieg ist öffentlich sichtbar.', $html);
                self::assertSame($reader === 'member', str_contains($html, 'Diese Fortsetzung lesen berechtigte Mitglieder.'));
                self::assertStringNotContainsString('member_continuation', $html);
            }
        }
    }

    public function test_table_common_anchors_use_the_existing_single_wrapper_identity(): void
    {
        $payload = \contractFixture('table-document.json');
        $payload['blocks'][0]['settings'] = ['anchor' => 'table-target'];
        $payload['blocks'][0]['data']['title'] = '<Title>';
        array_unshift($payload['blocks'], ['id' => 'nav', 'type' => 'anchor_navigation', 'version' => 1, 'settings' => [], 'data' => ['scope' => 'following']]);
        $html = $this->app->make(ContentRenderer::class)->render(BlockDocument::fromArray($payload));
        self::assertSame(1, substr_count($html, 'id="table-target"'));
        self::assertStringContainsString('href="#table-target">&lt;Title&gt;</a>', $html);
        self::assertStringContainsString('<th scope="col">Heading</th>', $html);
    }

    public function test_backend_header_and_headerless_tables_preserve_literal_cells_and_empty_rows(): void
    {
        foreach (['table-document.json' => true, 'table-no-header-document.json' => false] as $path => $hasHeader) {
            $payload = \contractFixture($path);
            $document = BlockDocument::fromArray($payload);
            self::assertStringContainsString('<table', $this->app->make(ContentRenderer::class)->render($document));
            self::assertInstanceOf(TableBlock::class, $document->blocks[0]);
            $table = $document->blocks[0];
            self::assertSame($hasHeader, $table->hasHeader);
            self::assertSame($payload['blocks'][0]['data']['rows'], $table->rows);
            $html = $this->app->make(ContentRenderer::class)->render($document);
            self::assertSame(3, substr_count($html, '<tr>') - ($hasHeader ? 1 : 0));
            self::assertSame(9, substr_count($html, '<td>'));
            self::assertSame($hasHeader, str_contains($html, '<thead>'));
            self::assertStringNotContainsString('<b>text</b>', $html);
            self::assertStringContainsString($hasHeader ? 'Literal &lt;b&gt;text&lt;/b&gt;' : 'Last', $html);
        }
    }
}
