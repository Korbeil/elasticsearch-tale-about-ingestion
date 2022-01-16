<?php

namespace App\Messenger;

class IndexBatch
{
    public function __construct(
        public string $indexName,
        public array $xmlArtists,
    ) {
    }
}
