<?php

namespace Lyavon\TgBot\MediaCache;

use Lyavon\TgBot\MediaCache\CachedFile;
use Lyavon\TgBot\MediaCache\MediaCache;

class NullMediaCache implements MediaCache
{
    public function __invoke(string $id): string|null
    {
        return null;
    }

    public function write(string $localId, string $remoteId): void
    {
    }
}
