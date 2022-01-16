<?php

declare(strict_types=1);

namespace App\Command;

use App\Messenger\IndexBatch;
use Doctrine\ORM\EntityManagerInterface;
use JoliCode\Elastically\IndexBuilder;
use Prewk\XmlStringStreamer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

class IndexCommand extends Command
{
    public function __construct(
        private IndexBuilder $indexBuilder,
        private string $projectRoot,
        private MessageBusInterface $messageBus,
        private EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('app:index')
            ->addOption('refreshInterval')
            ->addOption('replica')
            ->addOption('workers')
            ->addOption('bulkSize', mode: InputOption::VALUE_REQUIRED, default: 1000)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $sfStyle = new SymfonyStyle($input, $output);
        $start = microtime(true);

        $index = $this->indexBuilder->createIndex('artist');
        $sfStyle->writeln(sprintf('Indexing `%s` with %d bulk size.', $index->getName(), $input->getOption('bulkSize')));

        if ($input->getOption('refreshInterval')) {
            $sfStyle->writeln('RefreshInterval optimization');
            $refreshInterval = $index->getSettings()->getRefreshInterval();
            $index->getSettings()->setRefreshInterval('-1');
        }

        if ($input->getOption('replica')) {
            $sfStyle->writeln('Replica optimization');
            $replicas = $index->getSettings()->getNumberOfReplicas();
            $index->getSettings()->setNumberOfReplicas(0);
        }

        $count = 0;
        $progress = $sfStyle->createProgressBar();
        $progress->start();

        $stamps = [];
        if (!$input->getOption('workers')) {
            $stamps[] = new ReceivedStamp('async');
        } else {
            $sfStyle->writeln('Using workers');
        }

        foreach ($this->collectArtists($index->getName(), $input->getOption('bulkSize')) as $message) {
            $this->messageBus->dispatch($message, $stamps);

            $progress->advance(\count($message->xmlArtists));
            $count += \count($message->xmlArtists);
        }

        $progress->finish();

        if ($input->getOption('workers')) {
            $reverseProgress = $sfStyle->createProgressBar($count);

            while (($messages = $this->remainingMessages()) > 0) {
                $reverseProgress->setProgress($messages * $input->getOption('bulkSize'));
            }

            $reverseProgress->finish();
        }

        $this->indexBuilder->markAsLive($index, 'artist');

        if ($input->getOption('refreshInterval')) {
            $index->getSettings()->setRefreshInterval($refreshInterval);
        }

        if ($input->getOption('replica')) {
            $index->getSettings()->setNumberOfReplicas($replicas);
        }

        $sfStyle->success(sprintf('Indexed %d documents in %d seconds !', $count, microtime(true) - $start));

        return 0;
    }

    private function collectArtists(string $indexName, int $bulkSize): \Generator
    {
        $streamer = XmlStringStreamer::createUniqueNodeParser(sprintf('%s/data/discogs_artists.xml', $this->projectRoot), ['uniqueNode' => 'artist']);

        $count = 0;
        $batch = [];
        while ($node = $streamer->getNode()) {
            $batch[] = $node;
            ++$count;

            if ($bulkSize === $count) {
                yield new IndexBatch($indexName, $batch);

                $count = 0;
                $batch = [];
            }
        }

        if (\count($batch) > 0) {
            yield new IndexBatch($indexName, $batch);
        }
    }

    private function remainingMessages(): int
    {
        $count = $this->entityManager->getConnection()->fetchNumeric('SELECT COUNT(*) FROM messenger_messages;');

        return $count[0];
    }
}
