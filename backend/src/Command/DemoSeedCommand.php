<?php

namespace App\Command;

use App\Demo\DemoSeed;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:demo:seed', description: 'Replaces the demo Workspace "Gr8r Studio" with a fresh copy dated relative to today')]
final readonly class DemoSeedCommand
{
    public function __construct(private DemoSeed $seed)
    {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $this->seed->load();
        $io->success(\sprintf('Demo Workspace loaded. Sign in as hello@gr8rstudio.com with password "%s".', DemoSeed::PASSWORD));

        return 0;
    }
}
