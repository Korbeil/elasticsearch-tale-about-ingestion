<?php

declare(strict_types=1);

namespace App\Command;

use App\Generated\Model\Artist;
use Elastica\Document;
use JoliCode\Elastically\IndexBuilder;
use JoliCode\Elastically\Indexer;
use Prewk\XmlStringStreamer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class IndexCommand extends Command
{
    public function __construct(
        private IndexBuilder $indexBuilder,
        private Indexer $indexer,
        private string $projectRoot,
    ) {
        parent::__construct(null);
    }

    protected function configure(): void
    {
        $this
            ->setName('app:index')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $sfStyle = new SymfonyStyle($input, $output);
        $start = microtime(true);

        $index = $this->indexBuilder->createIndex('artist');
//        $index->getSettings()->setRefreshInterval('-1');
//        $replicas = $index->getSettings()->getNumberOfReplicas();
//        $index->getSettings()->setNumberOfReplicas(0);

        $count = 0;
        $progress = $sfStyle->createProgressBar();
        $progress->start();

        foreach ($this->collectArtists() as [$id, $artist]) {
            $this->indexer->scheduleCreate($index, new Document($id, $artist));
            $progress->advance();
            ++$count;
        }

        $this->indexBuilder->markAsLive($index, 'artist');
//        $index->getSettings()->setNumberOfReplicas($replicas);

        $sfStyle->success(sprintf('Indexed %d documents in %d seconds !', $count, microtime(true) - $start));

        return 0;
    }

    private function collectArtists(): \Generator
    {
        $streamer = XmlStringStreamer::createUniqueNodeParser(sprintf('%s/data/discogs_artists.xml', $this->projectRoot), ['uniqueNode' => 'artist']);

        while ($node = $streamer->getNode()) {
            $simpleXml = simplexml_load_string($node);

            $normalized = (array) $simpleXml;

            $artistId = $normalized['id'];
            unset($normalized['id']);
            unset($normalized['profile']);
            unset($normalized['images']);
            if (array_key_exists('urls', $normalized)) {
                $normalized['urls'] = ((array) $normalized['urls'])['url'];

                if ($normalized['urls'] instanceof \SimpleXMLElement) {
                    unset($normalized['urls']);
                }
            }
            if (array_key_exists('realname', $normalized) && $normalized['realname'] instanceof \SimpleXMLElement) {
                unset($normalized['realname']);
            }
            if (array_key_exists('namevariations', $normalized)) {
                $normalized['namevariations'] = ((array) $normalized['namevariations'])['name'];
            }
            if (array_key_exists('aliases', $normalized)) {
                if (0 === $normalized['aliases']->count()) {
                    unset($normalized['aliases']);
                } else {
                    $normalized['aliases'] = ((array) $normalized['aliases'])['name'];
                }
            }
            if (array_key_exists('members', $normalized)) {
                $normalized['members'] = ((array) $normalized['members'])['name'];
            }
            if (array_key_exists('groups', $normalized)) {
                if (0 === $normalized['groups']->count()) {
                    unset($normalized['groups']);
                } else {
                    $normalized['groups'] = ((array)$normalized['groups'])['name'];
                }
            }

            if (\count($normalized) <= 3) {
                continue;
            }

            $artist = new Artist();
            $artist->setName($normalized['name']);
            $artist->setNormalized($normalized);

            yield [$artistId, $artist];
        }
    }
}
