<?php

namespace Lyavon\TgBot;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterFace;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;

use Lyavon\TgBot\TelegramBotError;
use Lyavon\TgBot\MediaCache\MediaCache;
use Lyavon\TgBot\MediaCache\ArrayMediaCache;

class TelegramBot implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    protected string $fileUrl;
    protected string $getFileUrl;
    protected string $getUpdatesUrl;
    protected string $sendMessageUrl;
    protected string $deleteMessageUrl;
    protected string $sendVideoUrl;


    protected ?int $updateOffset;
    protected string $allowedUpdates;

    protected array $messageFilters;
    protected array $callbackQueryFilters;
    protected array $chatMemberFilters;

    protected MediaCache $mediaCache;

    public function __construct(
        string $token,
        array $allowedUpdates = [],
        LoggerInterface $logger = new NullLogger(),
        MediaCache $mediaCache = new ArrayMediaCache(),
    ) {
        $this->allowedUpdates = json_encode($allowedUpdates, JSON_OBJECT_AS_ARRAY);
        $this->getUpdatesUrl = 'https://api.telegram.org/bot' . $token . '/getUpdates';
        $this->sendMessageUrl = 'https://api.telegram.org/bot' . $token . '/sendMessage';
        $this->deleteMessageUrl = 'https://api.telegram.org/bot' . $token . '/deleteMessage';
        $this->sendVideoUrl = 'https://api.telegram.org/bot' . $token . '/sendVideo';
        $this->getFileUrl = 'https://api.telegram.org/bot' . $token . '/getFile?';
        $this->fileDownloadUrl = 'https://api.telegram.org/file/bot' . $token . '/';
        $this->sendPhotoUrl = 'https://api.telegram.org/bot' . $token . '/sendPhoto';

        $this->messageFilters = [];
        $this->callbackQueryFilters = [];
        $this->chatMemberFilters = [];
        $this->updateOffset = null;

        $this->logger = $logger;
        $this->mediaCache = $mediaCache;
    }

    protected function fetchUpdates(): array|false
    {
        $queryParameters = [];
        if ($this->allowedUpdates) {
            $queryParameters ['allowedUpdates'] = $this->allowedUpdates;
        }
        if (!is_null($this->updateOffset)) {
            $queryParameters['offset'] = strval($this->updateOffset);
        }

        $request = $this->getUpdatesUrl
          . ($queryParameters ? '?' . http_build_query($queryParameters) : '');
        $response = file_get_contents($request);
        if (!$response) {
            $this->logger->error(
                "Can't fetch updates for {request}",
                ['request' => $request],
            );
            return false;
        }

        $decodedResponse = json_decode($response, JSON_OBJECT_AS_ARRAY);
        if (!$decodedResponse['ok']) {
            $this->logger->error(
                "Can't fetch updates for {request}, got {response}",
                [
                  'request' => $request,
                  'response' => $response,
                ]
            );
            return false;
        }

        return $decodedResponse['result'];
    }

    public function mainloop()
    {
        while (1) {
            $updates = $this->fetchUpdates();
            if (!$updates) {
                sleep(1);
                continue;
            }

            foreach ($updates as $update) {
                $this->updateOffset = $update['update_id'] + 1;
                $this->logger->info(
                    "Got update {update}",
                    ['update' => print_r($update, true)]
                );
                $type = 'update';

                if (array_key_exists('message', $update)) {
                    $type = 'message';
                    $update = $update['message'];
                    $rc = $this->dispatchMessage($update);
                } elseif (array_key_exists('callback_query', $update)) {
                    $type = 'callback_query';
                    $update = $update['callback_query'];
                    $rc = $this->dispatchCallbackQuery($update);
                } elseif (array_key_exists('chat_member', $update)) {
                    $type = 'chat_member';
                    $update = $update['chat_member'];
                    $rc = $this->dispatchChatMember($update);
                }

                if (!$rc) {
                    $this->logger->error(
                        "Can't dispatch {type} {update}",
                        [
                          'type' => $type,
                          'update' => print_r($update, true),
                        ],
                    );
                }
            }
        }
    }


    public function registerMessageFilter(callable $filter): void
    {
        array_push($this->messageFilters, $filter);
    }

    protected function dispatchMessage(array $message): bool
    {
        try {
            foreach ($this->messageFilters as $filter) {
                if ($filter($message)) {
                    return true;
                }
            }
            return $this->onMessage($message);
        } catch (\Exception $e) {
            $this->logger->error(
                "Exception during message filtering occured ({exception})",
                [
                  'exception' => $e,
                ],
            );
            return false;
        }
    }

    public function onMessage(array $message): bool
    {
        return false;
    }


    public function registerCallbackQueryFilter(callable $filter): void
    {
        array_push($this->callbackQueryFilters, $filter);
    }

    protected function dispatchCallbackQuery(array $message): bool
    {
        try {
            foreach ($this->callbackQueryFilters as $filter) {
                if ($filter($message)) {
                    return true;
                }
            }
            return $this->onCallbackQuery($message);
        } catch (\Exception $e) {
            $this->logger->error(
                "Exception during calback query filtering occured ({exception})",
                [
                  'exception' => $e,
                ],
            );
            return false;
        }
    }

    public function onCallbackQuery(array $message): bool
    {
        return false;
    }


    public function registerChatMemberFilter(callable $filter): void
    {
        array_push($this->chatMemberFilters, $filter);
    }

    protected function dispatchChatMember(array $message): bool
    {
        try {
            foreach ($this->chatMemberFilters as $filter) {
                if ($filter($message)) {
                    return true;
                }
            }
            return $this->onChatMember($message);
        } catch (\Exception $e) {
            $this->logger->error(
                "Exception during chat member filtering occured ({exception})",
                [
                  'exception' => $e,
                ],
            );
            return false;
        }
    }

    public function onChatMember(array $message): bool
    {
        return false;
    }


    protected function send(string $url, array $args): array
    {
        $handle = curl_init($url);
        if (!$handle) {
            throw new TelegramBotError(
                "Can't use $url",
            );
        }

        if (!curl_setopt(
            $handle,
            CURLOPT_RETURNTRANSFER,
            true,
        )) {
            throw new TelegramBotError(
                "Won't be able to acquire response",
            );
        }

        if (!curl_setopt(
            $handle,
            CURLOPT_POSTFIELDS,
            $args,
        )) {
            throw new TelegramBotError(
                "Can't setup request for the args "
                . print_r($args, true)
            );
        }

        $response = curl_exec($handle);
        curl_close($handle);
        if (!$response) {
            throw new TelegramBotError(
                "Can't get response for $url => "
                . print_r($args, true),
            );
        }

        $decodedResponse = json_decode($response, JSON_OBJECT_AS_ARRAY);
        if ($decodedResponse['ok'] !== true) {
            throw new TelegramBotError(
                "Error response "
                . print_r($decodedResponse, true)
                . " For $url => "
                . print_r($args, true),
            );
        }
        return is_array($decodedResponse['result'])
            ? $decodedResponse['result']
            : [];
    }

    public function sendMessage(
        string $chatId,
        string $text,
        array|string $markup = [],
        array $options = [
            'parse_mode' => 'MarkdownV2',
        ],
    ): array {
        $options['chat_id'] = $chatId;
        $options['text'] = $text;
        if ($markup) {
            if (is_array($markup))
                $options['reply_markup'] = json_encode(
                    $markup,
                    JSON_UNESCAPED_UNICODE,
                );
            else
                $options['reply_markup'] = $markup;
        }

        $response = $this->send(
            $this->sendMessageUrl,
            $options,
        );
        $this->logger->info(
            'Message ({text}) is successfully sent to {chatId}',
            [
              'text' => $text,
              'chatId' => $chatId,
            ],
        );
        return $response;
    }

    public function deleteMessage(string $chatId, string $messageId): void {
        $response = $this->send(
            $this->deleteMessageUrl,
            [
                'chat_id' => $chatId,
                'message_id' => $messageId,
            ],
        );
    }

    public function sendPhoto(
        string $chatId,
        string $imgId,
        string $caption = null,
        array|string $markup = [],
        array $options = [
            'parse_mode' => 'MarkdownV2',
        ],
    ): array {
        $options['chat_id'] = $chatId;
        if ($caption) {
            $options['caption'] = $caption;
        }

        if ($markup) {
            if (is_array($markup))
                $options['reply_markup'] = json_encode(
                    $markup,
                    JSON_UNESCAPED_UNICODE,
                );
            else
                $options['reply_markup'] = $markup;
        }

        $cachedId = ($this->mediaCache)($imgId);
        if ($cachedId) {
            $options['photo'] = $cachedId;
        } elseif (!file_exists($imgId)) {
            $options['photo'] = $imgId;
        } else {
            $options['photo'] = new \CURLFile(
                $imgId,
                mime_content_type($imgId),
                basename($imgId),
            );
        }

        $response = $this->send(
            $this->sendPhotoUrl,
            $options,
        );
        if (is_a($options['photo'], \CURLFile::class)) {
            $this->mediaCache->write($imgId, $response['photo'][0]['file_id']);
        }

        $this->logger->info(
            'Photo ({img}) is successfully sent to {chatId}',
            [
              'img' => $imgId,
              'chatId' => $chatId,
            ],
        );
        return $response;
    }

    public function sendVideo(
        string $chatId,
        string $videoId,
        string $caption = null,
        string|array $markup = [],
        array $options = [
            'parse_mode' => 'MarkdownV2',
        ],
    ): array {
        $options['chat_id'] = $chatId;
        if ($caption) {
            $options['caption'] = $caption;
        }

        if ($markup) {
            if (is_array($markup))
                $options['reply_markup'] = json_encode(
                    $markup,
                    JSON_UNESCAPED_UNICODE,
                );
            else
                $options['reply_markup'] = $markup;
        }

        $cachedId = ($this->mediaCache)($videoId);
        if ($cachedId) {
            $options['video'] = $cachedId;
        } elseif (!file_exists($videoId)) {
            $options['video'] = $videoId;
        } else {
            $options['video'] = new \CURLFile(
                $videoId,
                mime_content_type($videoId),
                basename($videoId),
            );
        }

        $response = $this->send(
            $this->sendVideoUrl,
            $options,
        );
        if (is_a($options['video'], \CURLFile::class)) {
            $this->mediaCache->write($videoId, $response['video']['file_id']);
        }
        $this->logger->info(
            'Video ({video}) is successfully sent to {chatId}',
            [
              'video' => $videoId,
              'chatId' => $chatId,
            ],
        );
        return $response;
    }

    public function downloadFile(string $fileId, string $where): void
    {
        $request = $this->getFileUrl . http_build_query(
            [
              'file_id' => $fileId,
            ],
        );
        $response = file_get_contents($request);
        if (!$response) {
            throw new TelegramBotError(
                "Can't obtain info about file $fileId",
            );
        }

        $decodedResponse = json_decode($response, JSON_OBJECT_AS_ARRAY);
        if ($decodedResponse['ok'] !== true) {
            throw new TelegramBotError(
                "Can't obtain info about file $fileId",
            );
        }

        $this->logger->info(
            "Obtained info about file {fileId} ({result})",
            [
              'fileId' => $fileId,
              'result' => print_r($file['result'], true),
            ],
        );

        $request = $this->fileDownloadUrl . $file['result']['file_path'];
        $rc = file_put_contents($where, fopen($request, 'r'));
        if (!$rc) {
            throw new TelegramBotError(
                "Can't save file $fileId ($request) to $where",
            );
        }

        $this->logger->info(
            "Saved file {fileId} {fileDownloadUrl} to {where}",
            [
              'fileId' => $fileId,
              'fileDownloadUrl' => $request,
              'where' => $where,
            ],
        );
    }
}
