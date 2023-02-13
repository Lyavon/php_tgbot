<?php

namespace Lyavon\TgBot\MediaCache;

interface MediaCache
{
    public function write(string $localId, string $remoteId): void;
    public function __invoke(string $id): string|null;
}
