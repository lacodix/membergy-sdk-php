<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk;

use Closure;
use Lacodix\MembergySdk\DataObjects\MenuTargets\CustomLinkMenuTarget;
use Lacodix\MembergySdk\DataObjects\MenuTargets\InternalMenuTarget;
use Lacodix\MembergySdk\DataObjects\MenuTargets\MenuTarget;

final class MenuUrlResolver
{
    /** @var array<string, Closure(InternalMenuTarget): (?string)> */
    private array $resolvers;

    /**
     * @param  array<string, callable(InternalMenuTarget): (?string)>  $resolvers
     */
    public function __construct(array $resolvers = [])
    {
        $this->resolvers = array_map(
            static fn (callable $resolver): Closure => Closure::fromCallable($resolver),
            $resolvers,
        );
    }

    public function resolve(?MenuTarget $target): ?string
    {
        if ($target === null) {
            return null;
        }

        if ($target instanceof CustomLinkMenuTarget) {
            return $target->url;
        }

        if (! $target instanceof InternalMenuTarget) {
            return null;
        }

        $resolver = $this->resolvers[$target->type()] ?? null;

        return $resolver === null ? null : $resolver($target);
    }
}
