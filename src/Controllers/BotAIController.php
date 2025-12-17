<?php

namespace kolya2320\Ai_bot\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use kolya2320\Ai_bot\Models\BotaiChat;
use Illuminate\Support\Facades\Config;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class BotAIController
{
    private $client;
    private $apiKey;
    private $folderId;
    private $vectorStoreId;

    public function __construct()
    {
        $this->apiKey = config('services.yandex_cloud.api_key.value');
        $this->folderId = config('services.yandex_cloud.folder_id.value');
        $this->vectorStoreId = config('services.yandex_cloud.search_index_id.value');
        
        $this->client = new Client([
            'timeout' => 30,
        ]);
    }

    /**
     * Отправка сообщения ассистенту
     */
    public function sendMessage(Request $request): JsonResponse
    {
        try {
            $sessionId = $request->input('session_id');
            $message = $request->input('message');

            if (empty($sessionId) || empty($message)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Session ID and message are required'
                ], 400);
            }

            // Получаем last_response_id из предыдущих сообщений
            $previousResponseId = BotaiChat::getLastResponseId($sessionId);

            // Сохраняем сообщение пользователя
            BotaiChat::saveUserMessage($sessionId, $message);

            // Отправляем в Yandex
            $responseData = $this->sendToYandex($message, $previousResponseId);

            if (!$responseData['success']) {
                return response()->json($responseData, 500);
            }

            $botResponse = $responseData['output_text'];
            $responseId = $responseData['response_id'];

            // Сохраняем ответ бота с response_id
            BotaiChat::saveBotResponse($sessionId, $botResponse, $responseId);

            return response()->json([
                'success' => true,
                'bot_response' => $botResponse,
                'timestamp' => date('H:i'),
                'response_id' => $responseId
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
                ->orderBy('timestamp', 'ASC')
                ->get()
                ->map(function ($item) {
                    if (!empty($item->user_message)) {
                        return [
                            'type' => 'user',
                            'message' => $item->user_message,
                            'time' => $item->timestamp->format('H:i')
                        ];
                    } else {
                        return [
                            'type' => 'bot',
                            'message' => $item->bot_response,
                            'time' => $item->timestamp->format('H:i'),
                            'response_id' => $item->last_response_id
                        ];
                    }
                });

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
     * Отправка запроса в Yandex Responses API
     */
    private function sendToYandex(string $message, ?string $previousResponseId = null): array
    {
        try {
            $modelUri = "gpt://" . $this->folderId . "/" . config('services.yandex_cloud.model_url.value', 'yandexgpt-lite/latest');
            $instruction = config('services.yandex_cloud.instruction.value', 'Ты полезный AI помощник. Отвечай на вопросы пользователей вежливо и информативно.');
            
            $requestData = [
                'model' => $modelUri,
                'input' => [[
                    'role' => 'user',
                    'content' => $message
                ]],
                'instructions' => $instruction,
            ];

            if ($previousResponseId) {
                $requestData['previous_response_id'] = $previousResponseId;
            }

            $tools = [];
            
            if ($this->vectorStoreId) {
                $tools[] = [
                    'type' => 'file_search',
                    'vector_store_ids' => [$this->vectorStoreId]
                ];
            }

            if (config('services.yandex_cloud.enable_web_search.value')) {
                $webSearchTool = [
                    'type' => 'web_search'
                ];
                
                $domains = config('services.yandex_cloud.web_search_domains.value');
                $region = config('services.yandex_cloud.web_search_region.value');
                
                if ($domains || $region) {
                    $webSearchTool['filters'] = [];
                    
                    if ($domains) {
                        $webSearchTool['filters']['allowed_domains'] = array_map('trim', explode(',', $domains));
                    }
                    
                    if ($region) {
                        $webSearchTool['filters']['user_location']['region'] = $region;
                    }
                }
                
                $tools[] = $webSearchTool;
            }

            if (!empty($tools)) {
                $requestData['tools'] = $tools;
            }

            $temperature = config('services.yandex_cloud.temperature.value');
            if ($temperature !== '') {
                $requestData['temperature'] = (float) $temperature;
            }

            $maxTokens = config('services.yandex_cloud.max_output_tokens.value');
            if ($maxTokens !== '') {
                $requestData['max_output_tokens'] = (int) $maxTokens;
            }

            $topP = config('services.yandex_cloud.top_p.value');
            if ($topP !== '') {
                $requestData['top_p'] = (float) $topP;
            }

            $response = $this->client->post('https://rest-assistant.api.cloud.yandex.net/v1/responses', [
                'json' => $requestData,
                'headers' => [
                    'Authorization' => 'Api-Key ' . $this->apiKey,
                    'OpenAI-Project' => $this->folderId,
                    'Content-Type' => 'application/json'
                ]
            ]);
            $data = json_decode($response->getBody()->getContents(), true);
            if (empty($data['id'])) {
                return [
                    'success' => false,
                    'error' => 'Invalid response from Yandex API: No response ID'
                ];
            }
            $outputText = $this->extractOutputText($data);

            if (empty($outputText)) {
                return [
                    'success' => false,
                    'error' => 'Не удалось получить ответ',
                    'debug' => $data
                ];
            }

            return [
                'success' => true,
                'response_id' => $data['id'],
                'output_text' => $outputText,
                'full_response' => $data
            ];

        } catch (RequestException $e) {
            $error = $this->getGuzzleError($e);
            return [
                'success' => false,
                'error' => $error
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    private function extractOutputText(array $responseData): string
    {
        if (!empty($responseData['output'])) {
            foreach ($responseData['output'] as $outputItem) {
                if (isset($outputItem['type']) && $outputItem['type'] === 'message' && 
                    isset($outputItem['content'])) {
                    foreach ($outputItem['content'] as $content) {
                        if (isset($content['type']) && $content['type'] === 'output_text' && 
                            isset($content['text'])) {
                            return $content['text'];
                        }
                    }
                }
            }
        }
        return '';
    }

    /**
     * Обработка ошибок Guzzle
     */
    private function getGuzzleError(RequestException $e): string
    {
        if (!$e->hasResponse()) {
            return $e->getMessage();
        }

        $response = $e->getResponse();
        $statusCode = $response->getStatusCode();
        $body = $response->getBody()->getContents();
        
        try {
            $data = json_decode($body, true);
            return "{$statusCode}: " . ($data['message'] ?? ($data['error'] ?? $body));
        } catch (\Exception $jsonError) {
            return "{$statusCode}: " . $response->getReasonPhrase();
        }
    }
}