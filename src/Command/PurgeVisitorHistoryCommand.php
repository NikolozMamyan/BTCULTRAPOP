<?php

namespace App\Command;

use App\Service\VisitorEventRecorder;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:visitors:purge-history', description: 'Delete visitor history older than 30 days.')]
final class PurgeVisitorHistoryCommand extends Command
{
    public function __construct(private readonly Connection $connection)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $threshold = new \DateTimeImmutable('-' . VisitorEventRecorder::RETENTION_DAYS . ' days', new \DateTimeZone('UTC'));
        $count = $this->connection->executeStatement('DELETE FROM site_visitor_event WHERE occurred_at < ?', [$threshold->format('Y-m-d H:i:s')]);
        (new SymfonyStyle($input, $output))->success(sprintf('Deleted %d visitor history entries.', $count));

        return Command::SUCCESS;
    }
}
