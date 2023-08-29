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

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

use Lyavon\TgBot\MediaCache\ArrayMediaCache;

/**
 * TempfileMediaCache is a type of MediaCache that accumulates it during server
 * uptime.
 *
 * The latter is achieved by saving results inside a temporary directory on
 * script termination and restoring it on script startup.
 *
 * For perfomance reasons synchronisation between FS an RAM happens on instance
 * destruction. Therefore, abnormal script exit may result in cache loss.
 *
 * __N.B.! In case of abnormal exit cache woldn't be saved.__
 */
class TempfileMediaCache extends ArrayMediaCache
{
    /**
     * @var $id Unique cache storage indentifier (has to be valid filename).
     */
    protected string $id;

    /**
     * Restore current cache state on object creation.
     *
     * @param string $id Unique cache identifier (has to be valid filename).
     * @param LoggerInterface $logger Logger to be used (NullLogger by default).
     */
    public function __construct(string $id, LoggerInterface $logger = new NullLogger())
    {
        parent::__construct($logger);

        $this->id = $id;

        $path = sys_get_temp_dir();
        if (strpos($this->path, '/') !== false) {
            $path .= '/' . $id;
        } else {
            $path .= '\\' . $id;
        }

        if (!file_exist($path)) {
            $this->logger->debug(
                "Cache {id} ({path}) is currently empty",
                [
                    'id' => $id,
                    'path' => $path,
                ],
            );
            return;
        }

        $encodedCache = file_get_contents($this->path);
        if (!$encodedCache) {
            $this->logger->debug(
                "Cache {id} ({path}) is currently empty",
                [
                    'id' => $id,
                    'path' => $path,
                ],
            );
            return;
        }

        $decodedCache = json_decode($encodedCache, true);
        if (!$decodedCache) {
            $this->logger->error(
                "Cache {id} ({path}) can't be decoded",
                [
                    'id' => $id,
                    'path' => $path,
                ],
            );
            return;
        }

        $this->cache = $decodedCache;
    }

    /**
     * Save current cache state on object deletion.
     */
    public function __destruct()
    {
        $path = sys_get_temp_dir();
        if (strpos($this->path, '/') !== false) {
            $path .= '/' . $id;
        } else {
            $path .= '\\' . $id;
        }

        file_put_contents($path, json_encode($this->cache));
        parent::__destruct();
    }
}
