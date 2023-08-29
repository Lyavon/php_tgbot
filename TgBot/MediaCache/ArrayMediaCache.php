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

/**
 * ArrayMediaCache implementation stores all the remote ids inside an array
 * that only lasts during script invocation.
 *
 * Might be suitable for long running scripts (e.g. using {@link
 * https://core.telegram.org/bots/api#getupdates polling mechanism}).
 *
 * Cache interactions may be inspected by providing logger that doesn't supress
 * debug output (NullLogger is used by default).
 */
class ArrayMediaCache implements MediaCache, LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @var array $cache Internal associations storage.
     */
    protected array $cache = [];

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
    public function store(string $localPath, string $remoteId): void
    {
        if (!file_exists($localPath)) {
            $this->logger->warning(
                "Attempt to store non existing file {localPath} in cache as {remoteId}",
                [
                    'localPath' => $localPath,
                    'remoteId' => $remoteId,
                ],
            );
            return;
        }

        $stat = stat($localPath);
        $this->cache[$localPath] = [
            'remoteId' => $remoteId,
            'timestamp' => $stat['mtime'],
        ];
        $this->logger->debug(
            "File {localPath} is cached as {remoteId}",
            [
                'localPath' => $localPath,
                'remoteId' => $remoteId,
            ],
        );
    }

    /**
     * @inheritdoc
     */
    public function __invoke(string $localPath): string|null
    {
        if (!array_key_exists($id, $this->cache)) {
            return null;
        }

        if (!file_exists($id)) {
            $this->logger->warning(
                "Attempt to get file {localPath} that doesn't exist locally from cache",
                [
                    'localPath' => $localPath,
                ],
            );
            return null;
        }

        $stat = stat($id);
        $file = $this->cache[$id];
        if ($stat['mtime'] == $file['timestamp']) {
            $this->logger->debug(
                "Get {localPath} from cache as {remoteId}",
                [
                    'localPath' => $localPath,
                    'remoteId' => $file['remoteId'],
                ],
            );
            return $file['remoteId'];
        }

        unset($this->cache[$id]);
        $this->logger->debug(
            "Dropped cache for {localPath} due to local version change",
            [
                'localPath' => $localPath,
            ],
        );
        return null;
    }
}
