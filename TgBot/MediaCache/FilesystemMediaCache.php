<?php

namespace Lyavon\TgBot\MediaCache;

use Lyavon\TgBot\MediaCache\ArrayMediaCache;

class FilesystemMediaCache extends ArrayMediaCache
{
    protected string $path;

    public function __construct(string $path)
    {
        $this->path = $path;

        if (!file_exist($this->path)) {
            return;
        }

        $cache = file_get_contents($this->path);
        if (!$cache) {
            return;
        }

        $decodedCache = json_decode($cache, true);
        if (!$decodedCache) {
            return;
        }

        $this->cache = $decodedCache;
    }

    public function __destruct()
    {
        file_put_contents($this->path, json_encode($this->cache));
    }
}
