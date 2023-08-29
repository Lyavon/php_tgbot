<?php
/* Copyright 2023 Leonid Ragunovich
 *
 * This file is part of php_tgbot.
 *
 * php_tgbot is free software: you can redistribute it and/or modify it under
 * the terms of the GNU General Public License as published by the Free
 * Software Foundation, either version 3 of the License, or (at your option)
 * any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
 * FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for
 * more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * this program (see LICENSE file in parent directory). If not, see
 * <https://www.gnu.org/licenses/>.
 */

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
