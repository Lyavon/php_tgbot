<?php

namespace Lyavon\TgBot;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterFace;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;

use Lyavon\TgBot\TelegramBotError;

class TelegramBot implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    protected string $fileUrl;
    protected string $getFileUrl;
    protected string $getUpdatesUrl;
    protected string $sendMessageUrl;
    protected string $sendVideoUrl;


    protected ?int $updateOffset;
    protected string $allowedUpdates;

    protected array $messageFilters;
    protected array $callbackQueryFilters;
    protected array $chatMemberFilters;


    public function __construct(
        string $token,
        array $allowedUpdates = [],
        LoggerInterface $logger = new NullLogger(),
    )
    {
        $this->allowedUpdates = json_encode($allowedUpdates, JSON_OBJECT_AS_ARRAY);
        $this->getUpdatesUrl = 'https://api.telegram.org/bot' . $token . '/getUpdates';
        $this->sendMessageUrl = 'https://api.telegram.org/bot' . $token . '/sendMessage?';
        $this->sendVideoUrl = 'https://api.telegram.org/bot' . $token . '/sendVideo?';
        $this->getFileUrl = 'https://api.telegram.org/bot' . $token . '/getFile?';
        $this->fileDownloadUrl = 'https://api.telegram.org/file/bot' . $token . '/';

        $this->messageFilters = [];
        $this->callbackQueryFilters = [];
        $this->chatMemberFilters = [];
        $this->updateOffset = null;

        $this->logger = $logger;
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
                } elseif (array_key_exists('chat_member')) {
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


    public function sendMessage(
        string $chatId,
        string|Stringable $text,
        array $markup=[],
    ): void
    {
        $queryParameters = [
          'chat_id' => $chatId,
          'text' => $text,
        ];
        if ($markup) {
            $queryParameters['reply_markup'] =
              json_encode($markup, JSON_UNESCAPED_UNICODE);
        }

        $url = $this->sendMessageUrl . http_build_query($queryParameters);
        $response = file_get_contents($url);
        if (!$response)
            throw new TelegramBotError(
                "Can't send message ($text) to $chatId",
            );
        $decodedResponse = json_decode($response, JSON_OBJECT_AS_ARRAY);
        if ($decodedResponse['ok'] !== true)
            throw new TelegramBotError(
                "Can't send message ($text) to $chatId",
            );

        $this->logger->info(
            'Message ({text}) is successfully sent to {chatId}',
            [
              'text' => $text,
              'chatId' => $chatId,
            ],
        );
    }

    /*
     * videoId can be either url or file id on telegram servers. Only mp4.
     */
    public function sendVideo(string $chatId, string $videoId): void
    {
        $request = $this->sendVideoUrl . http_build_query(
            [
              'chat_id' => $chatId,
              'video' => $videoId,
            ],
        );
        $response = file_get_contents($request);
        if (!$response)
            throw new TelegramBotError(
                "Can't sent video $videoId to $chatId",
            );
        $decodedResponse = json_decode($response, JSON_OBJECT_AS_ARRAY);
        if ($decodedResponse['ok'] !== true)
            throw new TelegramBotError(
                "Can't sent video $videoId to $chatId",
            );

        $this->logger->info(
            'Video {videoId} is successfully sent to {chatId}',
            [
              'videoId' => $videoId,
              'chatId' => $chatId,
            ],
        );
    }

    public function downloadFile(string $fileId, string $where): void
    {
        $request = $this->getFileUrl . http_build_query(
            [
              'file_id' => $fileId,
            ],
        );
        $response = file_get_contents($request);
        if (!$response)
            throw new TelegramBotError(
                "Can't obtain info about file $fileId",
            );

        $decodedResponse = json_decode($response, JSON_OBJECT_AS_ARRAY);
        if ($decodedResponse['ok'] !== true)
            throw new TelegramBotError(
                "Can't obtain info about file $fileId",
            );

        $this->logger->info(
            "Obtained info about file {fileId} ({result})",
            [
              'fileId' => $fileId,
              'result' => print_r($file['result'], true),
            ],
        );

        $request = $this->fileDownloadUrl . $file['result']['file_path'];
        $rc = file_put_contents($where, fopen($request, 'r'));
        if (!$rc)
            throw new TelegramBotError(
                "Can't save file $fileId ($request) to $where",
            );

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
