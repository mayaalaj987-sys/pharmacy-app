<?php

namespace App\Services;

final readonly class StoredDocument
{
    public function __construct(
        public string $storageKey,
        public string $mimeType,
        public int $byteSize,
        public string $sha256,
    ) {}
}
