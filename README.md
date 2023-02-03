# Telegram Bot

This repository contains lightweight telegram bot implementation.

Current implementation is capable of:
- Update requesing
- Message sending
- Message receiving (filters + default callback)
- Callback query receiving (filters + default callback)
- Chat member update receiving (filters + default callback)
- psr-3 compatible logger usage

## Installation

Add the following entries to the __composer.json__:
```json
"require": {
    "lyavon/tgbot": "dev-master"
},
"repositories": [
    {
        "url": "https://github.com/Lyavon/php_tgbot.git",
        "type": "vcs"
    }
],
```

## Usage

This repository provides __TelegramBot__ class inside the __Lyavon\TgBot__
namespace. The implementation aims to be as lightweight as possible, therefore
telegram objects such as messages are basically json-decoded entities. See
Telegram Bot API for their descripton.

```php
<?php

use Lyavon\TgBot\TelegramBot;
#use Psr\Log\NullLogger; // Or any other psr-3-compatible logger if needed

$token = 'yourSecretToken';
$bot = new TelegramBot($token); // $allowedUpdates is optional, an array of
                                // strings repreenting update types should be
                                // provided, defaults are described in the
                                // Telegram Bot API

# NullLogger is used if custom one is not provided. TelegramBot implements
# LoggerAwareInterface.
# $logger = new NullLogger();
# $bot->setLogger($logger);

```

### Sending messages

Messages can be sent with _public function sendMessage(string $chatId,
string|Stringable $text, array $markup = []): bool_. __$markup__ is an array to be
json-encoded with any content supported by telegram Bot API. True indicates success.

```php
<?php

$bot->sendMessage('myTelegramId', 'Hello bot world!');

```

### Sending videos

Messages can be sent with _public function sendVideo(string $chatId, string
$videoId): bool_. $videoId can be either URL or video id on the telegram server. Only
mp4 in supported for now in the Telegram API. True indicates success.

```php
<?php

$bot->sendVideo('myTelegramId', 'https://myserver.xyz/coolvideo.mp4');

```

### Downloading files from Telegram servers

Files from the Telegram servers can be downloaded with _public function
downloadFile(string $fileId, string $where): bool_. __$fileId__ is the resourse
id on a Telegram server. __$where__ represents local path and should end with
the file name. True indicates success.

```php
<?php

$bot->downloadFile('fileId', 'tmp/bot/new-file');

```

### Handling messages

Messages can be handled either by Extending the __TelegramBot__ class and
implementing _public function onMessage(array $message): bool_ or by
implementing filters. __onMessage__ should return true on success.

Filter is a callable that returns bool. True is returned if the filter has
successfully handled the message, false otherwise. If some error happened
during handling it should throw.

Filter can be added with the __registerMessageFilter__, e.g.:

```php
<?php
$bot->registerMessageFilter(function(array $message) use ($bot) {
    if (!$bot->sendMessage($message['from']['chat_id'], $message['text']))
        throw new Exception("Can't send message");
    return true;
});

```

### Handling Chat Members, Callback Queries

Handling the above updated is similar to message handling, except functions names:
- onCallbakQuery, registerCallbackQueryFilter for callback query updates
- onChanMember, registerChatMemberFilter for chat member updates

### Receiving events

__TelegramBot__ for now can only be used with polling mechanism. This method
checks and handles supported upates until external termination or uncaught
internal error.

```php
$bot->mainloop();
```
