<?php

namespace App\Command;

use App\Service\WebComponentManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:media:optimize-banners', description: 'Generate responsive WebP variants for existing home banners.')]
final class OptimizeBannersCommand extends Command
{
    public function __construct(private readonly WebComponentManager $webComponents)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $count = $this->webComponents->optimizeImages();
        (new SymfonyStyle($input, $output))->success(sprintf('%d banners processed.', $count));

        return Command::SUCCESS;
    }
}
