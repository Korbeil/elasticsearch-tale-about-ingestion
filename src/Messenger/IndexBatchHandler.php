<?php

namespace App\Messenger;

use App\Generated\Model\Artist;
use Elastica\Document;
use JoliCode\Elastically\Client;
use JoliCode\Elastically\Index;
use JoliCode\Elastically\Indexer;
use JoliCode\Elastically\ResultSetBuilder;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

class IndexBatchHandler implements MessageHandlerInterface
{
    public function __construct(
        private Client $client,
        private ResultSetBuilder $resultSetBuilder,
        private Indexer $indexer,
    ) {
    }

    public function __invoke(IndexBatch $indexBatch): void
    {
        $index = new Index($this->client, $indexBatch->indexName, $this->resultSetBuilder);

        foreach ($this->parseArtists($indexBatch->xmlArtists) as [$id, $artist]) {
            $this->indexer->scheduleCreate($index, new Document($id, $artist));
        }

        $this->indexer->flush();
    }

    private function parseArtists(array $xmlArtists): \Generator
    {
        foreach ($xmlArtists as $xmlArtist) {
            $simpleXml = simplexml_load_string($xmlArtist);
            $normalized = (array) $simpleXml;

            $artistId = $normalized['id'];
            unset($normalized['id']);
            unset($normalized['profile']);
            unset($normalized['images']);
            if ($normalized['name'] instanceof \SimpleXMLElement) {
                continue;
            }
            if (\array_key_exists('urls', $normalized)) {
                $normalized['urls'] = ((array) $normalized['urls'])['url'];

                if ($normalized['urls'] instanceof \SimpleXMLElement) {
                    unset($normalized['urls']);
                }
            }
            if (\array_key_exists('realname', $normalized) && $normalized['realname'] instanceof \SimpleXMLElement) {
                unset($normalized['realname']);
            }
            if (\array_key_exists('namevariations', $normalized)) {
                $normalized['namevariations'] = ((array) $normalized['namevariations'])['name'];
            }
            if (\array_key_exists('aliases', $normalized)) {
                if (0 === $normalized['aliases']->count()) {
                    unset($normalized['aliases']);
                } else {
                    $normalized['aliases'] = ((array) $normalized['aliases'])['name'];
                }
            }
            if (\array_key_exists('members', $normalized)) {
                $normalized['members'] = ((array) $normalized['members'])['name'];
            }
            if (\array_key_exists('groups', $normalized)) {
                if (0 === $normalized['groups']->count()) {
                    unset($normalized['groups']);
                } else {
                    $normalized['groups'] = ((array) $normalized['groups'])['name'];
                }
            }

            foreach ($normalized as $item) {
                if ($item instanceof \SimpleXMLElement) {
                    dd($normalized);
                }
            }

            $artist = new Artist();
            $artist->setName($normalized['name']);
            $artist->setNormalized($normalized);

            yield [$artistId, $artist];
        }
    }
}
