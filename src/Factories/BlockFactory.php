<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Factories;

use Lacodix\MembergySdk\DataObjects\Blocks\AgendaBlock;
use Lacodix\MembergySdk\DataObjects\Blocks\AgendaItem;
use Lacodix\MembergySdk\DataObjects\Blocks\AnchorNavigationBlock;
use Lacodix\MembergySdk\DataObjects\Blocks\Block;
use Lacodix\MembergySdk\DataObjects\Blocks\BlockColumn;
use Lacodix\MembergySdk\DataObjects\Blocks\BlockMediaReference;
use Lacodix\MembergySdk\DataObjects\Blocks\BlockSettings;
use Lacodix\MembergySdk\DataObjects\Blocks\ColumnsBlock;
use Lacodix\MembergySdk\DataObjects\Blocks\ContactFormBlock;
use Lacodix\MembergySdk\DataObjects\Blocks\ContactTopic;
use Lacodix\MembergySdk\DataObjects\Blocks\DownloadItem;
use Lacodix\MembergySdk\DataObjects\Blocks\DownloadsBlock;
use Lacodix\MembergySdk\DataObjects\Blocks\GalleryBlock;
use Lacodix\MembergySdk\DataObjects\Blocks\GalleryItem;
use Lacodix\MembergySdk\DataObjects\Blocks\ImageBlock;
use Lacodix\MembergySdk\DataObjects\Blocks\Link;
use Lacodix\MembergySdk\DataObjects\Blocks\LogoCloudBlock;
use Lacodix\MembergySdk\DataObjects\Blocks\LogoItem;
use Lacodix\MembergySdk\DataObjects\Blocks\PostCategoryBlock;
use Lacodix\MembergySdk\DataObjects\Blocks\RawHtmlBlock;
use Lacodix\MembergySdk\DataObjects\Blocks\RichText;
use Lacodix\MembergySdk\DataObjects\Blocks\TestimonialItem;
use Lacodix\MembergySdk\DataObjects\Blocks\TestimonialsBlock;
use Lacodix\MembergySdk\DataObjects\Blocks\TextBlock;
use Lacodix\MembergySdk\DataObjects\Blocks\TextImageBlock;
use Lacodix\MembergySdk\DataObjects\Blocks\TitleBlock;
use Lacodix\MembergySdk\DataObjects\Blocks\UnknownBlock;
use Lacodix\MembergySdk\DataObjects\Blocks\UuidReference;
use Lacodix\MembergySdk\DataObjects\Blocks\VideoBlock;
use Lacodix\MembergySdk\Support\Data;

final class BlockFactory
{
    /**
     * @return array<string, int>
     */
    public static function knownTypes(): array
    {
        return [
            'title' => 1,
            'text' => 1,
            'image' => 1,
            'text_image' => 1,
            'gallery' => 1,
            'columns' => 1,
            'raw_html' => 1,
            'downloads' => 1,
            'agenda' => 1,
            'logo_cloud' => 1,
            'video' => 1,
            'testimonials' => 1,
            'post_category' => 1,
            'contact_form' => 1,
            'anchor_navigation' => 1,
        ];
    }

    /** @param array<string, mixed> $payload */
    public function fromArray(array $payload): Block
    {
        $id = Data::string($payload, 'id');
        $type = Data::string($payload, 'type');
        $version = Data::int($payload, 'version');
        $data = Data::object($payload, 'data');
        $settings = BlockSettings::fromArray(Data::object($payload, 'settings'));
        $extra = Data::extra($payload, ['id', 'type', 'version', 'data', 'settings']);

        if ((self::knownTypes()[$type] ?? null) !== $version) {
            return new UnknownBlock($id, $type, $version, $settings, $data, $payload, $extra);
        }

        return match ($type) {
            'title' => new TitleBlock(
                $id,
                $version,
                $settings,
                Data::string($data, 'text'),
                Data::int($data, 'level'),
                Data::extra($data, ['text', 'level']),
                $extra,
            ),
            'text' => new TextBlock(
                $id,
                $version,
                $settings,
                Data::nullableString($data, 'title'),
                RichText::fromArray(Data::object($data, 'body')),
                Data::extra($data, ['title', 'body']),
                $extra,
            ),
            'image' => $this->image($id, $version, $settings, $data, $extra),
            'text_image' => new TextImageBlock(
                $id,
                $version,
                $settings,
                Data::nullableString($data, 'title'),
                RichText::fromArray(Data::object($data, 'body')),
                BlockMediaReference::fromArray(Data::object($data, 'image')),
                Data::string($data, 'position'),
                Data::string($data, 'width'),
                Data::extra($data, ['title', 'body', 'image', 'position', 'width']),
                $extra,
            ),
            'gallery' => new GalleryBlock(
                $id,
                $version,
                $settings,
                Data::nullableString($data, 'title'),
                array_map(GalleryItem::fromArray(...), Data::objectList($data, 'items')),
                Data::int($data, 'per_page'),
                Data::bool($data, 'pagination'),
                Data::bool($data, 'autoplay'),
                Data::bool($data, 'lightbox'),
                Data::extra($data, ['title', 'items', 'per_page', 'pagination', 'autoplay', 'lightbox']),
                $extra,
            ),
            'columns' => new ColumnsBlock(
                $id,
                $version,
                $settings,
                Data::nullableString($data, 'title'),
                array_map($this->column(...), Data::objectList($data, 'columns')),
                Data::extra($data, ['title', 'columns']),
                $extra,
            ),
            'raw_html' => new RawHtmlBlock(
                $id,
                $version,
                $settings,
                Data::nullableString($data, 'title'),
                Data::string($data, 'html'),
                Data::extra($data, ['title', 'html']),
                $extra,
            ),
            'downloads' => new DownloadsBlock(
                $id,
                $version,
                $settings,
                Data::nullableString($data, 'title'),
                array_map(DownloadItem::fromArray(...), Data::objectList($data, 'items')),
                Data::extra($data, ['title', 'items']),
                $extra,
            ),
            'agenda' => $this->agenda($id, $version, $settings, $data, $extra),
            'logo_cloud' => new LogoCloudBlock(
                $id,
                $version,
                $settings,
                Data::nullableString($data, 'title'),
                array_map(LogoItem::fromArray(...), Data::objectList($data, 'items')),
                Data::extra($data, ['title', 'items']),
                $extra,
            ),
            'video' => $this->video($id, $version, $settings, $data, $extra),
            'testimonials' => new TestimonialsBlock(
                $id,
                $version,
                $settings,
                Data::nullableString($data, 'title'),
                Data::int($data, 'per_page'),
                Data::bool($data, 'autoplay'),
                array_map(TestimonialItem::fromArray(...), Data::objectList($data, 'items')),
                Data::extra($data, ['title', 'per_page', 'autoplay', 'items']),
                $extra,
            ),
            'post_category' => new PostCategoryBlock(
                $id,
                $version,
                $settings,
                Data::string($data, 'category_uuid'),
                Data::int($data, 'limit'),
                Data::string($data, 'order'),
                Data::bool($data, 'include_teaser'),
                Data::extra($data, ['category_uuid', 'limit', 'order', 'include_teaser']),
                $extra,
            ),
            'contact_form' => new ContactFormBlock(
                $id,
                $version,
                $settings,
                Data::string($data, 'form_handle'),
                array_map(ContactTopic::fromArray(...), Data::objectList($data, 'topics')),
                Data::extra($data, ['form_handle', 'topics']),
                $extra,
            ),
            'anchor_navigation' => new AnchorNavigationBlock(
                $id,
                $version,
                $settings,
                Data::intList($data, 'levels'),
                Data::extra($data, ['levels']),
                $extra,
            ),
            default => new UnknownBlock($id, $type, $version, $settings, $data, $payload, $extra),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $extra
     */
    private function image(
        string $id,
        int $version,
        BlockSettings $settings,
        array $data,
        array $extra,
    ): ImageBlock {
        $link = Data::nullableObject($data, 'link');

        return new ImageBlock(
            $id,
            $version,
            $settings,
            Data::nullableString($data, 'title'),
            BlockMediaReference::fromArray(Data::object($data, 'image')),
            Data::nullableString($data, 'caption'),
            $link === null ? null : Link::fromArray($link),
            Data::extra($data, ['title', 'image', 'caption', 'link']),
            $extra,
        );
    }

    /** @param array<string, mixed> $data */
    private function column(array $data): BlockColumn
    {
        return new BlockColumn(
            width: Data::string($data, 'width'),
            blocks: array_map($this->fromArray(...), Data::objectList($data, 'blocks')),
            extra: Data::extra($data, ['width', 'blocks']),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $extra
     */
    private function agenda(
        string $id,
        int $version,
        BlockSettings $settings,
        array $data,
        array $extra,
    ): AgendaBlock {
        $intro = Data::nullableObject($data, 'intro');

        return new AgendaBlock(
            $id,
            $version,
            $settings,
            Data::nullableString($data, 'title'),
            $intro === null ? null : RichText::fromArray($intro),
            array_map(AgendaItem::fromArray(...), Data::objectList($data, 'items')),
            Data::extra($data, ['title', 'intro', 'items']),
            $extra,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $extra
     */
    private function video(
        string $id,
        int $version,
        BlockSettings $settings,
        array $data,
        array $extra,
    ): VideoBlock {
        $poster = Data::nullableObject($data, 'poster');

        return new VideoBlock(
            $id,
            $version,
            $settings,
            Data::nullableString($data, 'title'),
            UuidReference::fromArray(Data::object($data, 'video')),
            $poster === null ? null : BlockMediaReference::fromArray($poster),
            Data::nullableString($data, 'caption'),
            Data::extra($data, ['title', 'video', 'poster', 'caption']),
            $extra,
        );
    }
}
