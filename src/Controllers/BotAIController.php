<?php

namespace kolya2320\Ai_bot\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use kolya2320\Ai_bot\Models\BotaiChat;
use kolya2320\Ai_bot\Models\AiBotSetting;
use GuzzleHttp\Client;

class BotAIController
{
    private $client;
    private $apiKey;
    private $folderId;
    private $vectorStoreId;
    private $baseUrl;
    private $modelUri;
    private $instruction;
    private $temperature;
    private $maxTokens;
    private $topP;
    private $enableWebSearch;
    private $webSearchDomains;
    private $webSearchRegion;
    
    public function __construct()
    {
        $this->loadSettingsFromDatabase();
        
        $this->client = new Client([
            'timeout' => 30,
        ]);
    }
    
    /**
     * Загрузка настроек из базы данных
     */
    private function loadSettingsFromDatabase(): void
    {
        $this->apiKey = AiBotSetting::getValue('api_key', '');
        $this->folderId = AiBotSetting::getValue('folder_id', '');
        $this->vectorStoreId = AiBotSetting::getValue('search_index_id', '');
        $modelUrl = AiBotSetting::getValue('model_url', 'yandexgpt-lite/latest');
        $this->modelUri = "gpt://" . $this->folderId . "/" . $modelUrl;
        $this->instruction = AiBotSetting::getValue('instruction', 'Ты полезный AI помощник. Отвечай на вопросы пользователей вежливо и информативно.');
        $this->baseUrl = AiBotSetting::getValue('base_url', 'https://rest-assistant.api.cloud.yandex.net/v1/responses');
        $this->temperature = AiBotSetting::getValue('temperature', '0.3');
        $this->maxTokens = AiBotSetting::getValue('max_output_tokens', '1000');
        $this->topP = AiBotSetting::getValue('top_p', '');
        $this->enableWebSearch = AiBotSetting::getValue('enable_web_search', '0') === '1';
        $this->webSearchDomains = AiBotSetting::getValue('web_search_domains', 'community.evocms.ru');
        $this->webSearchRegion = AiBotSetting::getValue('web_search_region', '213');
    }

    /**
     * Отправка сообщения ассистенту
     */
    public function sendMessage(Request $request): JsonResponse
    {
        $sessionId = $request->input('session_id');
        $message = $request->input('message');

        if (empty($sessionId) || empty($message)) {
            return response()->json([
                'success' => false,
                'error' => 'Идентификатор сессии и сообщение обязательны для ввода.'
            ], 400);
        }

        if (empty($this->apiKey) || empty($this->folderId)) {
            return response()->json([
                'success' => false,
                'error' => 'Настройка API не завершена. Пожалуйста, проверьте параметры.'
            ], 500);
        }

        $previousResponseId = BotaiChat::getLastResponseId($sessionId);

        BotaiChat::saveUserMessage($sessionId, $message);

        $responseData = $this->sendToYandex($message, $previousResponseId);

        if (!$responseData['success']) {
            return response()->json($responseData, 500);
        }

        $botResponse = $responseData['output_text'];
        $responseId = $responseData['response_id'];
        $annotations = $responseData['annotations'] ?? [];

        BotaiChat::saveBotResponse($sessionId, $botResponse, $responseId);

        return response()->json([
            'success' => true,
            'bot_response' => $botResponse,
            'annotations' => $annotations,
            'timestamp' => date('H:i'),
            'response_id' => $responseId
        ]);
    }

    /**
     * Загрузка истории чата
     */
    public function loadHistory(Request $request): JsonResponse
    {
        $sessionId = $request->input('session_id');
        
        if (empty($sessionId)) {
            return response()->json([
                'success' => false,
                'error' => 'Требуется идентификатор сеанса.'
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
    }

    /**
     * Отправка запроса в Yandex Responses API
     */
    private function sendToYandex(string $message, ?string $previousResponseId = null): array
    {
        if (empty($this->apiKey) || empty($this->folderId)) {
            return [
                'success' => false,
                'error' => 'Настройка API не завершена. Пожалуйста, проверьте параметры.'
            ];
        }
        
        $requestData = [
            'model' => $this->modelUri,
            'input' => [[
                'role' => 'user',
                'content' => $message
            ]],
            'instructions' => $this->instruction,
        ];

        if ($previousResponseId) {
            $requestData['previous_response_id'] = $previousResponseId;
        }

        $tools = [];
        
        if (!empty($this->vectorStoreId)) {
            $tools[] = [
                'type' => 'file_search',
                'vector_store_ids' => [$this->vectorStoreId]
            ];
        }

        if ($this->enableWebSearch) {
            $webSearchTool = [
                'type' => 'web_search'
            ];
            
            if (!empty($this->webSearchDomains) || !empty($this->webSearchRegion)) {
                $webSearchTool['filters'] = [];
                
                if (!empty($this->webSearchDomains)) {
                    $webSearchTool['filters']['allowed_domains'] = array_map('trim', explode(',', $this->webSearchDomains));
                }
                
                if (!empty($this->webSearchRegion)) {
                    $webSearchTool['filters']['user_location']['region'] = $this->webSearchRegion;
                }
            }
            
            $tools[] = $webSearchTool;
        }

        if (!empty($tools)) {
            $requestData['tools'] = $tools;
        }

        if ($this->temperature !== '') {
            $requestData['temperature'] = (float) $this->temperature;
        }

        if ($this->maxTokens !== '') {
            $requestData['max_output_tokens'] = (int) $this->maxTokens;
        }

        if ($this->topP !== '') {
            $requestData['top_p'] = (float) $this->topP;
        }

        $response = $this->client->post($this->baseUrl, [
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
                'error' => 'Неверный ответ от API Яндекса: отсутствует идентификатор ответа.'
            ];
        }
        
        $outputData = $this->extractOutputText($data);
        $outputText = $outputData['text'] ?? '';
        $annotations = $outputData['annotations'] ?? [];
        
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
            'annotations' => $annotations,
            'full_response' => $data
        ];
    }

    private function extractOutputText(array $responseData): array
    {
        $outputText = '';
        $annotations = [];
        
        if (!empty($responseData['output'])) {
            foreach ($responseData['output'] as $outputItem) {
                if (isset($outputItem['type']) && $outputItem['type'] === 'message' && 
                    isset($outputItem['content'])) {
                    foreach ($outputItem['content'] as $content) {
                        if (isset($content['type']) && $content['type'] === 'output_text' && 
                            isset($content['text'])) {
                            $outputText = $content['text'];
                            if (!empty($content['annotations'])) {
                                foreach ($content['annotations'] as $annotation) {
                                    if (isset($annotation['type']) && $annotation['type'] === 'url_citation' && 
                                        isset($annotation['url'])) {
                                        $title = $annotation['title'] ?? '';
                                        
                                        $annotations[] = [
                                            'url' => $annotation['url'],
                                            'title' => $title,
                                            'domain' => parse_url($annotation['url'], PHP_URL_HOST)
                                        ];
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        
        return [
            'text' => $outputText,
            'annotations' => $annotations
        ];
    }
}