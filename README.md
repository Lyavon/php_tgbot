# Telegram Bot

This repository contains lightweight telegram bot implementation.

Current implementation is capable of:
- Update requesing
- Message sending
- Message deleting
- Video sending
- Photo sending
- Message receiving (filters + default callback)
- Callback query receiving (filters + default callback)
- Chat member update receiving (filters + default callback)
- psr-3 compatible logger usage
- Caching file_id of sent attachments

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
# use Lyavon\TgBot\ArrayMediaCache; // See later explanation
# use Psr\Log\NullLogger; // Or any other psr-3-compatible logger if needed

$token = 'yourSecretToken';
$bot = new TelegramBot(
    token: $token, # String given by the BotFather
    allowedUpdates: [], # Supported by bot are used by default
    logger: new NullLogger(), # Any psr-3-compatible logger, NullLogger by
                              # default
    mediaCache: new ArrayMediaCache(), # See below, ArrayMediaCache by default

# NullLogger is used if custom one is not provided. TelegramBot implements
# LoggerAwareInterface.
# $logger = new NullLogger();
# $bot->setLogger($logger);

```

### Sending Messages

Messages can be sent with _public function sendMessage(string $chatId, string
$text, array|string $markup = [], array $options = [],): array_.
- __$markup__ is an array to be json-encoded or already encoded strings with
  any content supported by telegram Bot API.
- __$options__ are any other options supported by the Bot API.
- Returns array of everything telegram sends as the result.
- Throws __TelegramBotError__ on error (__RuntimeError__).

```php
<?php

$bot->sendMessage('myTelegramId', 'Hello bot world!');

```

### Deleting Messages

Messages can be deleted with _public function sendMessage(string $chatId, string
$messageId,): void_.
- Throws __TelegramBotError__ on error (__RuntimeError__).

```php
<?php

$bot->deleteMessage('myTelegramId', 'myMessageId');

```

### Sending Photos

Photos can be sent with _ public function sendPhoto( string $chatId, string
$imgId, string $caption = null, array|string $markup = [], array $options =
[],): array_
- __$markup__ is an array to be json-encoded or already encoded strings with
  any content supported by telegram Bot API.
- __$options__ are any other options supported by the Bot API.
- Returns array of everything telegram sends as the result.
- Throws __TelegramBotError__ on error (__RuntimeError__).

```php
<?php

$bot->sendPhoto('myTelegramId', 'url|photoId|localPath', 'caption');

```

### Sending Videos

Videos can be sent with _public function sendVideo( string $chatId, string
$videoId, string $caption = null, string|array $markup = [], array $options =
[],): array_
- __$markup__ is an array to be json-encoded or already encoded strings with
  any content supported by telegram Bot API.
- __$options__ are any other options supported by the Bot API.
- Returns array of everything telegram sends as the result.
- Throws __TelegramBotError__ on error (__RuntimeError__).

```php
<?php

$bot->sendVideo('myTelegramId', 'url|videoId|localPath', 'caption');

```

### Downloading files from Telegram servers

Files from the Telegram servers can be downloaded with _public function
downloadFile(string $fileId, string $where): void_. __$fileId__ is the resourse
id on a Telegram server. __$where__ represents local path and should end with
the file name. Throws __TelegramBotError__ on error (__RuntimeError__).

```php
<?php

$bot->downloadFile('fileId', 'tmp/bot/new-file');

```

### Handling Messages

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
    $bot->sendMessage($message['from']['chat_id'], $message['text']);
    return true;
});

```

### Handling Chat Members, Callback Queries

Handling the above updated is similar to message handling, except functions names:
- onCallbakQuery, registerCallbackQueryFilter for callback query updates
- onChanMember, registerChatMemberFilter for chat member updates

### Receiving Events

__TelegramBot__ for now can only be used with polling mechanism. This method
checks and handles supported upates until external termination or uncaught
internal error.

```php
<?php

$bot->mainloop();

```

### Caching Sent Media
__TelegramBot__ by attempts to cache file_id of photos and video sent, yet by
default it is done by __ArrayMediaCache__ that stores them only during while
the script is runnning.

__ArrayMediaCache__ implements the __MediaCache__ interface, and other
__MediaCache__ implementations may be passed on bot construction.

There's also a straightforward implementations of the __TmpfileMediaCache__ and
__FilesystemMediaCache__. Allowing to save _file_id_ cache in the temporary
directory or on the filesystem respectively. __TmpfileMediaCache__ accepts its
id as a string, __FilesystemMediaCache__ accepts path where to store.

The major downside to use __TmpfileMediaCache__ and __FilesystemMediaCache__ is
that files are synced only during objects contruction and destruction. If sript
cannot perform cleanup, cache will be lost.


