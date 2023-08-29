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

/**
 * MediaCache is an interface for storing identifiers for files that are
 * already uploaded to a server by a Telergam bot.
 *
 * Since cache might expire (i.e. local file is changed during script lifetime
 * or in between invokations), MediaCache treats local paths as unique local
 * identifiers.
 *
 * It doesn't seem meaningful to restrict MediaCache usage as singleton:
 * - It is meant to be internal for the telegram bot.
 * - Unlikely, but there might be several bots used in a script.
 *
 * @link https://core.telegram.org/api/files Uploading and Downloading Files.
 */
interface MediaCache
{
    /**
     * Store new media association.
     *
     * @param string $localPath Local path for the media.
     * @param string $remoteId Media identifier for the Telegram.
     */
    public function store(string $localPath, string $remoteId): void;

    /**
     * Obtain Remote identifier for the given media.
     *
     * @param string $localPath Local path for the media.
     * @return string|null Media identifier for the Telegram or null if the
     * media is not uploaded yet or expired.
     */
    public function __invoke(string $localPath): string|null;
}
