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

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

use Lyavon\TgBot\MediaCache\CachedFile;
use Lyavon\TgBot\MediaCache\MediaCache;

/**
 * MediaCache implementation that doesn't store any associations. Default
 * option in case no other cache is set for a bot.
 *
 * Cache interactions may be inspected by providing logger that doesn't supress
 * debug output (NullLogger is used by default).
 */
class NullMediaCache implements MediaCache, LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * Setup logger on object creation.
     *
     * @param LoggerInterface $logger Logger to use (NullLogger by default).
     */
    public function __construct(LoggerInterface $logger = new NullLogger())
    {
        $this->setLogger($logger);
    }

    /**
     * @inheritdoc
     */
    public function __invoke(string $localPath): string|null
    {
        $this->logger->debug(
            "Attempt to get {localPath} from cache",
            [
                'localPath' => $localPath,
            ],
        );
        return null;
    }

    /**
     * @inheritdoc
     */
    public function store(string $localPath, string $remoteId): void
    {
        $this->logger->debug(
            "Attempt to store {localPath} as {remoteId} to cache",
            [
                'localPath' => $localPath,
                'remoteId' => $remoteId,
            ],
        );
    }
}
