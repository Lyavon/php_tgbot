<?php

namespace Lyavon\TgBot\MediaCache;

use Lyavon\TgBot\MediaCache\ArrayMediaCache;

class TempfileMediaCache extends ArrayMediaCache
{
    protected string $path;

    public function __construct(string $id)
    {
        $this->path = sys_get_temp_dir();
        if (strpos($this->path, '/') !== false) {
            $this->path .= '/' . $id;
        } else {
            $this->path .= '\\' . $id;
        }

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
