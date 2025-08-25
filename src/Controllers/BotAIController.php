<?php

namespace kolya2320\Ai_bot\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use kolya2320\Ai_bot\Models\BotaiChat;
use kolya2320\Ai_bot\Models\BotaiSession;
use Illuminate\Support\Facades\Config;

class BotAIController
{
    /**
     * Отправка сообщения ассистенту
     */
    public function sendMessage(Request $request): JsonResponse
    {
        try {
            $sessionId = $request->input('session_id');
            $message = $request->input('message');
            $assistantId = $request->input('assistant_id');
            $threadId = $request->input('thread_id');

            // Валидация
            if (empty($sessionId) || empty($message)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Session ID and message are required'
                ], 400);
            }

            // Если нет assistant_id или thread_id, создаем их
            if (empty($assistantId) || empty($threadId)) {
                $assistantData = $this->createAssistantInternal($sessionId);
                if (!$assistantData['success']) {
                    return response()->json($assistantData, 500);
                }
                $assistantId = $assistantData['assistant_id'];
                $threadId = $assistantData['thread_id'];
            }

            // Получаем IAM-токен из конфигурации
            $iamToken = Config::get('services.yandex_cloud.iam_token.value', '');

            if (empty($iamToken)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Yandex Cloud IAM token not configured'
                ], 500);
            }

            // Сохраняем сообщение пользователя
            $this->saveMessage($sessionId, $message, 'user', $assistantId, $threadId);

            // Отправляем в Yandex Assistant
            $botResponse = $this->sendToYandex($message, $assistantId, $threadId, $iamToken);

            // Сохраняем ответ ассистента
            $this->saveMessage($sessionId, $botResponse, 'bot', $assistantId, $threadId);

            return response()->json([
                'success' => true,
                'bot_response' => $botResponse,
                'timestamp' => date('H:i'),
                'assistant_id' => $assistantId,
                'thread_id' => $threadId
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Internal server error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Загрузка истории чата
     */
    public function loadHistory(Request $request): JsonResponse
    {
        try {
            $sessionId = $request->input('session_id');
            
            if (empty($sessionId)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Session ID is required'
                ], 400);
            }

            $messages = BotaiChat::where('session_id', $sessionId)
                ->orderBy('id', 'ASC')
                ->get()
                ->map(function ($item) {
                    $result = [];
                    
                    if (!empty($item->user_message)) {
                        $result[] = [
                            'type' => 'user',
                            'message' => $item->user_message,
                            'time' => date('H:i', strtotime($item->timestamp))
                        ];
                    }
                    
                    if (!empty($item->bot_response)) {
                        $result[] = [
                            'type' => 'bot',
                            'message' => $item->bot_response,
                            'time' => date('H:i', strtotime($item->timestamp))
                        ];
                    }
                    
                    return $result;
                })
                ->flatten(1);

            return response()->json([
                'success' => true,
                'messages' => $messages
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Создание ассистента и треда
     */
    public function createAssistant(Request $request): JsonResponse
    {
        try {
            $sessionId = $request->input('session_id');
            
            if (empty($sessionId)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Session ID is required'
                ], 400);
            }

            $result = $this->createAssistantInternal($sessionId);
            
            return response()->json($result);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Internal server error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Внутренний метод создания ассистента
     */
    private function createAssistantInternal(string $sessionId): array
    {
        // Получаем настройки из конфига
        $folderId = Config::get('services.yandex_cloud.folder_id.value', '');
        $iamToken = Config::get('services.yandex_cloud.iam_token.value', '');
        $searchIndex = Config::get('services.yandex_cloud.search_index_id.value', '');
        $instruction = Config::get('services.yandex_cloud.instruction.value', 'Ты полезный AI помощник. Отвечай на вопросы пользователей вежливо и информативно.');
        $modelUriValue = Config::get('services.yandex_cloud.model_uri.value', 'yandexgpt-lite/latest');
        
        if (empty($folderId) || empty($iamToken)) {
            return [
                'success' => false,
                'error' => 'Yandex Cloud credentials not configured'
            ];
        }
        
        // Формируем modelUri
        $modelUri = "gpt://" . $folderId . "/" . $modelUriValue;
        
        // Создаем ассистента и тред
        $assistantData = $this->createYandexAssistant($folderId, $iamToken, $searchIndex, $instruction, $modelUri);
        
        if (!$assistantData || !isset($assistantData['assistant_id'])) {
            return [
                'success' => false,
                'error' => 'Failed to create Yandex assistant'
            ];
        }

        // Сохраняем сессию в базу
        BotaiSession::updateOrCreate(
            ['session_id' => $sessionId],
            [
                'assistant_id' => $assistantData['assistant_id'],
                'thread_id' => $assistantData['thread_id']
            ]
        );

        return [
            'success' => true,
            'assistant_id' => $assistantData['assistant_id'],
            'thread_id' => $assistantData['thread_id']
        ];
    }

    /**
     * Сохранение сообщения в базу данных
     */
    private function saveMessage(string $sessionId, string $message, string $type, string $assistantId = '', string $threadId = ''): void
    {
        if ($type === 'user') {
            BotaiChat::create([
                'session_id' => $sessionId,
                'user_message' => $message,
                'bot_response' => '',
                'assistant_id' => $assistantId,
                'thread_id' => $threadId,
                'timestamp' => now()
            ]);
        } else {
            // Для ответа бота создаем новую запись
            BotaiChat::create([
                'session_id' => $sessionId,
                'user_message' => '',
                'bot_response' => $message,
                'assistant_id' => $assistantId,
                'thread_id' => $threadId,
                'timestamp' => now()
            ]);
        }
    }

    private function createYandexAssistant($folderId, $iamToken, $searchIndex, $instruction, $modelUri)
    {
        if (empty($iamToken) || empty($folderId)) {
            error_log("Yandex Cloud credentials not configured");
            return false;
        }
        
        // 1. Создаем ассистента
        $requestBody = [
            'folderId' => $folderId,
            'modelUri' => $modelUri,
            'instruction' => $instruction,
            "tools" => [
                [
                    "searchIndex" => [
                        "searchIndexIds" => [$searchIndex]
                    ]
                ]
            ]
        ];
        
        $jsonData = json_encode($requestBody, JSON_UNESCAPED_SLASHES);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://rest-assistant.api.cloud.yandex.net/assistants/v1/assistants");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Api-Key ' . $iamToken,
            'Content-Type: application/json'
        ]);
        
        $response = curl_exec($ch);
        $assistantData = json_decode($response, true);
        curl_close($ch);
        
        if (!isset($assistantData['id'])) {
            error_log("Ошибка создания ассистента: " . $response);
            return false;
        }
        
        $assistantId = $assistantData['id'];
        
        // 2. Создаем тред
        $requestBody = [
            'folderId' => $folderId,
            "tools" => [
                [
                    "searchIndex" => [
                        "searchIndexIds" => [$searchIndex]
                    ]
                ]
            ]
        ];
        
        $jsonData = json_encode($requestBody, JSON_UNESCAPED_SLASHES);
        $ch = curl_init();
        
        curl_setopt($ch, CURLOPT_URL, "https://rest-assistant.api.cloud.yandex.net/assistants/v1/threads");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Api-Key ' . $iamToken,
            'Content-Type: application/json'
        ]);
        
        $response = curl_exec($ch);
        $threadData = json_decode($response, true);
        curl_close($ch);
        
        if (!isset($threadData['id'])) {
            error_log("Ошибка создания треда: " . $response);
            return false;
        }
        
        $threadId = $threadData['id'];
        
        return [
            'assistant_id' => $assistantId,
            'thread_id' => $threadId
        ];
    }

    private function sendToYandex($message, $assistantId, $threadId, $iamToken)
    {
        if (empty($iamToken)) {
            return 'Ошибка: не настроен API ключ';
        }

        // 1. Добавляем сообщение в тред
        $requestBody = [
            'threadId' => $threadId,
            'content' => [
                'content' => [
                    [
                        'text' => [
                            'content' => $message
                        ]
                    ]
                ]
            ]
        ];

        $jsonData = json_encode($requestBody, JSON_UNESCAPED_UNICODE);
        $ch = curl_init();
        
        $url = "https://rest-assistant.api.cloud.yandex.net/assistants/v1/messages";
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Api-Key ' . $iamToken,
            'Content-Type: application/json'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            error_log("Ошибка добавления сообщения: " . $response);
            return 'Ошибка отправки сообщения';
        }

        // 2. Запускаем ассистента
        $requestBody = [
            'assistantId' => $assistantId,
            'threadId' => $threadId
        ];
        
        $jsonData = json_encode($requestBody);
        $ch = curl_init();
        
        // Эндпоинт для запуска ассистента
        $url = "https://rest-assistant.api.cloud.yandex.net/assistants/v1/runs";
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Api-Key ' . $iamToken,
            'Content-Type: application/json'
        ]);
        
        $response = curl_exec($ch);
        $runData = json_decode($response, true);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200 || !isset($runData['id'])) {
            error_log("Ошибка запуска ассистента: " . $response);
            return 'Ошибка запуска ассистента';
        }
        
        $runId = $runData['id'];
        
        // 3. Ожидаем завершения и получаем результат
        $maxAttempts = 10;
        $attempt = 0;
        while ($attempt < $maxAttempts) {
            sleep(2); // Ждем 2 секунды между проверками
            $attempt++;
            $ch = curl_init();
            // Эндпоинт для проверки статуса run
            $url = "https://rest-assistant.api.cloud.yandex.net/assistants/v1/runs/".$runId;
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Api-Key ' . $iamToken,
                'Content-Type: application/json'
            ]);
            
            $response = curl_exec($ch);
            $statusData = json_decode($response, true);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            break;
        }
        
        return $statusData["state"]["completed_message"]["content"]["content"][0]["text"]["content"] ?? 'Нет ответа от ассистента';;
    }
}