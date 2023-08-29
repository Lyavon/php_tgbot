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
use Lyavon\TgBot\TelegramBotError;

/**
 * FilesystemMediaCache is designed to be used as persistent cache that may
 * last up to filesystem lifetime.
 *
 * Obviously, server is expected to have write access to a filesystem at the
 * given location.
 *
 * For perfomance reasons synchronisation between FS an RAM happens on instance
 * destruction. Therefore, abnormal script exit may result in cache loss.
 *
 * __N.B.! In case of abnormal exit cache woldn't be saved.__
 */
class FilesystemMediaCache extends ArrayMediaCache
{
    /**
     * @var string $path Cache file path in local filesystem.
     */
    protected string $path;

    /**
     * Create FilesystemMediaCache.
     *
     * @param string $path Cache file path in local filesystem.
     * @param LoggerInterface $logger Logger to be used (NullLogger by default).
     * @throws TelegramBotError On cache decode error.
     */
    public function __construct(string $path, LoggerInterface $logger = new NullLogger())
    {
        parent::__construct($logger);

        $this->path = $path;
        if (!file_exist($this->path)) {
            $this->logger->debug(
                "Cache {path} is currently empty",
                [
                    'path' => $path,
                ],
            );
            return;
        }

        $encodedCache = file_get_contents($this->path);
        if (!$encodedCache) {
            $this->logger->debug(
                "Cache {path} is currently empty",
                [
                    'path' => $path,
                ],
            );
            return;
        }

        $decodedCache = json_decode($encodedCache, true);
        if (!$decodedCache) {
            $this->logger->error(
                "Cache {path} is either damaged or incorrect. Can't decode it.",
                [
                    'path' => $path,
                ],
            );
            throw new TelegramBotError("Can't decode supposed cache ($path)");
        }

        $this->cache = $decodedCache;
    }

    /**
     * Update cache on object destuction.
     */
    public function __destruct()
    {
        file_put_contents($this->path, json_encode($this->cache));
        $this->logger->debug(
            "Cache {path} is successfully synced",
            [
                'path' => $this->path,
            ],
        );
    }
}
