<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Laravel\Commands;

use Illuminate\Console\Command;

final class MembergyInstallCommand extends Command
{
    protected $signature = 'membergy:install
        {--force : Overwrite already published files}
        {--no-views : Keep package views vendor-owned instead of publishing them}';

    protected $description = 'Publish Membergy SDK configuration and optional Blade templates';

    public function handle(): int
    {
        $arguments = [
            '--tag' => 'membergy-config',
            '--force' => (bool) $this->option('force'),
        ];
        $this->call('vendor:publish', $arguments);

        if (! $this->option('no-views')) {
            $this->call('vendor:publish', [
                '--tag' => 'membergy-views',
                '--force' => (bool) $this->option('force'),
            ]);
        }

        $this->newLine();
        $this->components->info('Membergy SDK installed.');
        $this->components->bulletList([
            'Set MEMBERGY_URL to the Membergy instance root URL.',
            'Set MEMBERGY_TENANT to the tenant slug.',
            'Keep MEMBERGY_MEMBER_CACHE_ENABLED=false unless a private per-member store is configured.',
        ]);

        return self::SUCCESS;
    }
}
