<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Laravel\Commands;

use Illuminate\Console\Command;
use Lacodix\MembergySdk\MembergyClient;

final class MembergyCacheClearCommand extends Command
{
    protected $signature = 'membergy:cache-clear {resource? : Optional resource name such as pages or posts}';

    protected $description = 'Invalidate Membergy SDK response-cache generations';

    public function handle(MembergyClient $client): int
    {
        $resource = $this->argument('resource');
        $client->flush(is_string($resource) && $resource !== '' ? $resource : null);

        $this->components->info(
            is_string($resource) && $resource !== ''
                ? "Membergy '{$resource}' cache invalidated."
                : 'All Membergy SDK cache generations invalidated.',
        );

        return self::SUCCESS;
    }
}
