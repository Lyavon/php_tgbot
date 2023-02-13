<?php

namespace Lyavon\TgBot\MediaCache;

use Psr\Log\LoggerInterface;

class ArrayMediaCache implements MediaCache
{
    protected array $cache = [];

    public function write(string $localId, string $remoteId): void
    {
        if (!file_exists($localId)) {
            return;
        }

        $stat = stat($localId);
        $this->cache[$localId] = [
            'remoteId' => $remoteId,
            'timestamp' => $stat['mtime'],
        ];
    }

    public function __invoke(string $id): string|null
    {
        if (!array_key_exists($id, $this->cache)) {
            return null;
        }

        if (!file_exists($id)) {
            return null;
        }

        $stat = stat($id);
        $file = $this->cache[$id];
        if ($stat['mtime'] == $file['timestamp']) {
            return $file['remoteId'];
        }

        unset($this->cache[$id]);
        return null;
    }
}
