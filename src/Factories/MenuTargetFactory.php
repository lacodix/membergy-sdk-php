<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Factories;

use Lacodix\MembergySdk\DataObjects\MenuTargets\CustomLinkMenuTarget;
use Lacodix\MembergySdk\DataObjects\MenuTargets\MenuTarget;
use Lacodix\MembergySdk\DataObjects\MenuTargets\PageMenuTarget;
use Lacodix\MembergySdk\DataObjects\MenuTargets\PostCategoryMenuTarget;
use Lacodix\MembergySdk\DataObjects\MenuTargets\PostMenuTarget;
use Lacodix\MembergySdk\DataObjects\MenuTargets\UnknownMenuTarget;
use Lacodix\MembergySdk\Support\Data;

final class MenuTargetFactory
{
    /** @param array<string, mixed>|null $data */
    public function fromArray(?array $data): ?MenuTarget
    {
        if ($data === null) {
            return null;
        }

        $type = Data::string($data, 'type');

        if ($type === 'custom_link') {
            return new CustomLinkMenuTarget(
                url: Data::string($data, 'url'),
                extra: Data::extra($data, ['type', 'url']),
            );
        }

        if (! in_array($type, ['page', 'post', 'post_category'], true)) {
            return new UnknownMenuTarget($type, $data);
        }

        $arguments = [
            'uuid' => Data::string($data, 'uuid'),
            'slug' => Data::string($data, 'slug'),
            'title' => Data::string($data, 'title'),
            'extra' => Data::extra($data, ['type', 'uuid', 'slug', 'title']),
        ];

        return match ($type) {
            'page' => new PageMenuTarget(...$arguments),
            'post' => new PostMenuTarget(...$arguments),
            'post_category' => new PostCategoryMenuTarget(...$arguments),
        };
    }
}
