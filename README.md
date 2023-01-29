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
namespace.

```php
<?php

use Lyavon\TgBot\TelegramBot;
#use Psr\Log\NullLogger; // Or any other psr-3-compatible logger if needed

$token = 'yourSecretToken';
$bot = new TelegramBot($token);

# NullLogger is used if custom one is not provided. TelegramBot implements
# LoggerAwareInterface.
# $logger = new NullLogger();
# $bot->setLogger($logger);

```

__TelegramBot__ for now can only be used with polling mechanism. This method
checks and handles supported upates until external termination or uncaught
internal error.

```php
$bot->mainloop();
```
