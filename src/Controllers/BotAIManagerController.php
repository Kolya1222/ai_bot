<?php

namespace kolya2320\Ai_bot\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use kolya2320\Ai_bot\Models\BotaiChat;
use kolya2320\Ai_bot\Models\AiBotSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class BotAIManagerController
{
    /**
     * Главная страница менеджера чатов
     */
    public function index()
    {
        return response()->view('ai_bot::manager.index');
    }

    /**
     * Получение списка всех сессий чатов
     */
    public function getSessions()
    {
        $sessionsQuery = BotaiChat::select([
            'session_id',
            DB::raw('COUNT(*) as messages_count'),
            DB::raw('MIN(timestamp) as first_message_at'),
            DB::raw('MAX(timestamp) as last_message_at'),
            DB::raw('SUM(CASE WHEN user_message IS NOT NULL THEN 1 ELSE 0 END) as user_messages_count'),
            DB::raw('SUM(CASE WHEN bot_response IS NOT NULL THEN 1 ELSE 0 END) as bot_messages_count')
        ])
        ->groupBy('session_id')
        ->orderBy('last_message_at', 'desc');
        $page = request()->get('page', 1);
        $perPage = 15;
        $offset = ($page - 1) * $perPage;
        
        $totalSessions = DB::table('botai_chats')
            ->select(DB::raw('COUNT(DISTINCT session_id) as total'))
            ->first()->total ?? 0;
        
        $sessions = $sessionsQuery->offset($offset)->limit($perPage)->get();
        $formattedSessions = $sessions->map(function($session) {
            $firstMessageAt = $session->first_message_at ? 
                Carbon::parse($session->first_message_at) : null;
            $lastMessageAt = $session->last_message_at ? 
                Carbon::parse($session->last_message_at) : null;
            $lastMessage = BotaiChat::where('session_id', $session->session_id)
                ->orderBy('timestamp', 'desc')
                ->first();
            $lastMessageText = null;
            $lastMessageType = null;
            if ($lastMessage) {
                if (!empty($lastMessage->user_message)) {
                    $lastMessageText = $this->truncateMessage($lastMessage->user_message, 80);
                    $lastMessageType = 'user';
                } elseif (!empty($lastMessage->bot_response)) {
                    $lastMessageText = $this->truncateMessage($lastMessage->bot_response, 80);
                    $lastMessageType = 'bot';
                }
            }

            return [
                'session_id' => $session->session_id,
                'messages_count' => (int)$session->messages_count,
                'user_messages_count' => (int)$session->user_messages_count,
                'bot_messages_count' => (int)$session->bot_messages_count,
                'first_message_at' => $firstMessageAt ? 
                    $firstMessageAt->format('Y-m-d H:i:s') : null,
                'last_message_at' => $lastMessageAt ? 
                    $lastMessageAt->format('Y-m-d H:i:s') : null,
                'last_message' => $lastMessageText,
                'last_message_type' => $lastMessageType
            ];
        });

        $totalPages = ceil($totalSessions / $perPage);

        return response()->json([
            'success' => true,
            'sessions' => $formattedSessions,
            'pagination' => [
                'current_page' => (int)$page,
                'last_page' => $totalPages,
                'total' => $totalSessions,
                'per_page' => $perPage,
                'from' => $offset + 1,
                'to' => min($offset + $perPage, $totalSessions)
            ]
        ]);
    }

    /**
     * Получение детальной информации о сессии
     */
    public function getSessionDetail($sessionId)
    {
        $sessionExists = BotaiChat::where('session_id', $sessionId)->exists();
        
        if (!$sessionExists) {
            return response()->json([
                'success' => false,
                'error' => 'Сессия не найдена'
            ], 404);
        }

        $sessionStats = BotaiChat::select([
            DB::raw('COUNT(*) as total_messages'),
            DB::raw('MIN(timestamp) as first_message'),
            DB::raw('MAX(timestamp) as last_message'),
            DB::raw('SUM(CASE WHEN user_message IS NOT NULL THEN 1 ELSE 0 END) as user_messages'),
            DB::raw('SUM(CASE WHEN bot_response IS NOT NULL THEN 1 ELSE 0 END) as bot_messages')
        ])
        ->where('session_id', $sessionId)
        ->first();
        $firstMessage = $sessionStats->first_message ? 
            Carbon::parse($sessionStats->first_message) : null;
        $lastMessage = $sessionStats->last_message ? 
            Carbon::parse($sessionStats->last_message) : null;

        $messages = BotaiChat::where('session_id', $sessionId)
            ->orderBy('timestamp', 'asc')
            ->orderBy('id', 'asc')
            ->get()
            ->map(function($chat) {
                $messageData = [];
                
                if (!empty($chat->user_message)) {
                    $timestamp = $chat->timestamp ? Carbon::parse($chat->timestamp) : null;
                    $messageData = [
                        'type' => 'user',
                        'message' => $chat->user_message,
                        'time' => $timestamp ? $timestamp->format('H:i:s') : '',
                        'date' => $timestamp ? $timestamp->format('Y-m-d') : '',
                        'timestamp_full' => $timestamp ? $timestamp->toIso8601String() : null
                    ];
                } elseif (!empty($chat->bot_response)) {
                    $timestamp = $chat->timestamp ? Carbon::parse($chat->timestamp) : null;
                    $messageData = [
                        'type' => 'bot',
                        'message' => $chat->bot_response,
                        'time' => $timestamp ? $timestamp->format('H:i:s') : '',
                        'date' => $timestamp ? $timestamp->format('Y-m-d') : '',
                        'timestamp_full' => $timestamp ? $timestamp->toIso8601String() : null,
                        'response_id' => $chat->last_response_id
                    ];
                }
                
                return $messageData;
            })
            ->filter()
            ->values();

        return response()->json([
            'success' => true,
            'session' => [
                'session_id' => $sessionId,
                'total_messages' => (int)$sessionStats->total_messages,
                'user_messages' => (int)$sessionStats->user_messages,
                'bot_messages' => (int)$sessionStats->bot_messages,
                'first_message' => $firstMessage ? 
                    $firstMessage->format('Y-m-d H:i:s') : null,
                'last_message' => $lastMessage ? 
                    $lastMessage->format('Y-m-d H:i:s') : null,
                'duration_minutes' => $firstMessage && $lastMessage ? 
                    round($lastMessage->diffInMinutes($firstMessage), 1) : null
            ],
            'messages' => $messages
        ]);
    }

    /**
     * Получение статистики по чатам
     */
    public function getStatistics(): JsonResponse
    {
        $totalMessages = BotaiChat::count();
        $uniqueSessions = DB::table('botai_chats')
            ->select(DB::raw('COUNT(DISTINCT session_id) as total'))
            ->first()->total ?? 0;
        $today = now()->startOfDay();
        $todayMessages = BotaiChat::where('timestamp', '>=', $today)->count();
        $activeSessionsToday = DB::table('botai_chats')
            ->select(DB::raw('COUNT(DISTINCT session_id) as total'))
            ->where('timestamp', '>=', $today)
            ->first()->total ?? 0;
        $userMessagesCount = BotaiChat::whereNotNull('user_message')->count();
        $botMessagesCount = BotaiChat::whereNotNull('bot_response')->count();
        $avgMessagesPerSession = $uniqueSessions > 0 ? 
            round($totalMessages / $uniqueSessions, 2) : 0;
        $dailyStats = BotaiChat::select([
                DB::raw('DATE(timestamp) as date'),
                DB::raw('COUNT(*) as total_messages'),
                DB::raw('COUNT(DISTINCT session_id) as unique_sessions')
            ])
            ->where('timestamp', '>=', now()->subDays(7))
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->get()
            ->map(function($item) {
                return [
                    'date' => $item->date,
                    'total_messages' => (int)$item->total_messages,
                    'unique_sessions' => (int)$item->unique_sessions
                ];
            });
        $uniqueResponseIds = BotaiChat::whereNotNull('last_response_id')
            ->distinct('last_response_id')
            ->count('last_response_id');

        return response()->json([
            'success' => true,
            'statistics' => [
                'unique_sessions' => (int)$uniqueSessions,
                'total_messages' => $totalMessages,
                'user_messages' => $userMessagesCount,
                'bot_messages' => $botMessagesCount,
                'today_messages' => $todayMessages,
                'active_sessions_today' => (int)$activeSessionsToday,
                'avg_messages_per_session' => $avgMessagesPerSession,
                'unique_response_ids' => $uniqueResponseIds,
                'daily_stats' => $dailyStats
            ]
        ]);
    }

    /**
     * Удаление сессии чата
     */
    public function deleteSession($sessionId): JsonResponse
    {
        $sessionExists = BotaiChat::where('session_id', $sessionId)->exists();
        if (!$sessionExists) {
            return response()->json([
                'success' => false,
                'error' => 'Сессия не найдена'
            ], 404);
        }
        $deletedCount = BotaiChat::where('session_id', $sessionId)->delete();
        return response()->json([
            'success' => true,
            'message' => 'Session deleted successfully',
            'deleted_messages' => $deletedCount
        ]);
    }

    /**
     * Обрезание сообщения для предпросмотра
     */
    private function truncateMessage(string $message, int $length = 100): string
    {
        $message = trim($message);
        
        if (mb_strlen($message) <= $length) {
            return $message;
        }
        
        $truncated = mb_substr($message, 0, $length);
        $lastSpace = mb_strrpos($truncated, ' ');
        
        if ($lastSpace !== false && $lastSpace > 0) {
            $truncated = mb_substr($truncated, 0, $lastSpace);
        }
        
        return $truncated . '...';
    }

    /**
     * Получение текущей конфигурации
     */
    public function getConfig(): JsonResponse
    {
        try {
            if (!Schema::hasTable('ai_bot_settings')) {
                return response()->json([
                    'success' => false,
                    'error' => 'Таблица настроек не существует'
                ], 500);
            }

            $settings = AiBotSetting::getForDisplay();

            $groupedSettings = [];
            foreach ($settings as $setting) {
                $groupedSettings[] = [
                    'key' => $setting['key'],
                    'caption' => $setting['caption'],
                    'type' => $setting['type'],
                    'value' => $setting['value'],
                    'desc' => $setting['description'],
                    'category' => $setting['category'],
                    'sort_order' => $setting['sort_order']
                ];
            }
            
            usort($groupedSettings, function($a, $b) {
                if ($a['category'] === $b['category']) {
                    return $a['sort_order'] <=> $b['sort_order'];
                }
                return strcmp($a['category'], $b['category']);
            });

            return response()->json([
                'success' => true,
                'config' => $groupedSettings,
                'config_storage' => 'database',
                'settings_count' => count($groupedSettings)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Ошибка загрузки конфигурации: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Сохранение конфигурации
     */
    public function saveConfig(Request $request): JsonResponse
    {
        try {
            if (!Schema::hasTable('ai_bot_settings')) {
                return response()->json([
                    'success' => false,
                    'error' => 'Таблица настроек не существует. Перезагрузите страницу.'
                ], 500);
            }

            $data = $request->input('data');
            
            if (empty($data)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Нет данных для сохранения'
                ], 400);
            }

            $decodedData = json_decode($data, true);
            
            $configData = $decodedData['config'] ?? [];
            
            if (empty($configData)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Нет настроек в данных'
                ], 400);
            }

            $savedCount = 0;
            $errors = [];

            foreach ($configData as $item) {
                $key = $item['key'] ?? null;
                $value = $item['value'] ?? '';
                
                if ($key) {
                    try {
                        $setting = AiBotSetting::where('key', $key)->first();
                        
                        if ($setting) {
                            $setting->value = $value;
                            if ($setting->save()) {
                                $savedCount++;
                            } else {
                                $errors[] = "Не удалось сохранить настройку: $key";
                            }
                        } else {
                            $category = $this->getConfigCategory($key);
                            $sortOrder = $this->getSortOrder($key);
                            
                            $newSetting = new AiBotSetting([
                                'key' => $key,
                                'value' => $value,
                                'caption' => $this->getDefaultCaption($key),
                                'type' => $this->getDefaultType($key),
                                'description' => $this->getDefaultDescription($key),
                                'category' => $category,
                                'sort_order' => $sortOrder
                            ]);
                            
                            if ($newSetting->save()) {
                                $savedCount++;
                            } else {
                                $errors[] = "Не удалось создать настройку: $key";
                            }
                        }
                    } catch (\Exception $e) {
                        $errors[] = "Ошибка для настройки $key: " . $e->getMessage();
                    }
                }
            }

            $response = [
                'success' => true,
                'message' => 'Конфигурация успешно сохранена в базу данных',
                'saved_count' => $savedCount,
                'storage_type' => 'database'
            ];
            
            if (!empty($errors)) {
                $response['warnings'] = $errors;
            }

            return response()->json($response);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Ошибка сохранения конфигурации: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Сброс конфигурации к значениям по умолчанию
     */
    public function resetConfig(): JsonResponse
    {
        try {
            if (!Schema::hasTable('ai_bot_settings')) {
                return response()->json([
                    'success' => false,
                    'error' => 'Таблица настроек не существует. Перезагрузите страницу.'
                ], 500);
            }
            AiBotSetting::truncate();

            $createdCount = $this->createDefaultSettings();

            return response()->json([
                'success' => true,
                'message' => 'Конфигурация сброшена к значениям по умолчанию',
                'created_count' => $createdCount,
                'storage_type' => 'database'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Ошибка сброса конфигурации: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Получение информации о конфигурационном хранилище
     */
    public function getConfigInfo(): JsonResponse
    {
        try {
            $tableExists = Schema::hasTable('ai_bot_settings');
            
            if (!$tableExists) {
                return response()->json([
                    'success' => false,
                    'error' => 'Таблица настроек не существует'
                ], 404);
            }
            
            $settingsCount = AiBotSetting::count();
            
            $info = [
                'storage_type' => 'database',
                'table_name' => 'ai_bot_settings',
                'table_exists' => true,
                'settings_count' => $settingsCount,
                'encryption_enabled' => true,
                'encrypted_keys' => ['api_key']
            ];

            return response()->json([
                'success' => true,
                'info' => $info
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Ошибка получения информации: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Создание настроек по умолчанию
     */
    private function createDefaultSettings(): int
    {
        $defaultSettings = $this->getDefaultSettingsArray();
        $createdCount = 0;
        
        foreach ($defaultSettings as $key => $data) {
            $setting = new AiBotSetting([
                'key' => $key,
                'value' => $data['value'],
                'caption' => $data['caption'],
                'type' => $data['type'],
                'description' => $data['description'],
                'category' => $data['category'],
                'sort_order' => $data['sort_order']
            ]);
            
            if ($setting->save()) {
                $createdCount++;
            }
        }
        
        return $createdCount;
    }

    /**
     * Получение массива настроек по умолчанию
     */
    private function getDefaultSettingsArray(): array
    {
        return [
            'folder_id' => [
                'value' => '',
                'caption' => 'Folder ID',
                'type' => 'text',
                'description' => 'Идентификатор каталога в Yandex Cloud',
                'category' => 'yandex',
                'sort_order' => 10
            ],
            'api_key' => [
                'value' => '',
                'caption' => 'API Key',
                'type' => 'password',
                'description' => 'Ключ аутентификации для Yandex Cloud API',
                'category' => 'yandex',
                'sort_order' => 20
            ],
            'search_index_id' => [
                'value' => '',
                'caption' => 'Search Index ID',
                'type' => 'text',
                'description' => 'Идентификатор поискового индекса (опционально)',
                'category' => 'general',
                'sort_order' => 30
            ],
            'instruction' => [
                'value' => 'Ты полезный AI помощник. Отвечай на вопросы пользователей вежливо и информативно. Будь дружелюбным и помогай решать проблемы.',
                'caption' => 'Инструкция для AI',
                'type' => 'textarea',
                'description' => 'Системная инструкция для поведения AI ассистента',
                'category' => 'general',
                'sort_order' => 40
            ],
            'model_url' => [
                'value' => 'yandexgpt-lite/latest',
                'caption' => 'Модель AI',
                'type' => 'text',
                'description' => 'Идентификатор модели Yandex GPT (yandexgpt-lite/latest, yandexgpt/latest)',
                'category' => 'yandex',
                'sort_order' => 50
            ],
            'temperature' => [
                'value' => '0.3',
                'caption' => 'Temperature',
                'type' => 'text',
                'description' => 'Креативность ответов (0.0-1.0).',
                'category' => 'ai',
                'sort_order' => 80
            ],
            'max_output_tokens' => [
                'value' => '1000',
                'caption' => 'Максимальное количество токенов',
                'type' => 'text',
                'description' => 'Максимальное количество токенов в ответе модели (1000-7000)',
                'category' => 'ai',
                'sort_order' => 90
            ],
            'top_p' => [
                'value' => '',
                'caption' => 'Top-P',
                'type' => 'text',
                'description' => 'Альтернатива temperature для контроля случайности (0.0-1.0).',
                'category' => 'ai',
                'sort_order' => 100
            ],
            'enable_web_search' => [
                'value' => '0',
                'caption' => 'Включить веб-поиск',
                'type' => 'checkbox',
                'description' => 'Включить инструмент web_search для поиска в интернете',
                'category' => 'web',
                'sort_order' => 110
            ],
            'web_search_domains' => [
                'value' => 'habr.ru',
                'caption' => 'Домены для веб-поиска',
                'type' => 'text',
                'description' => 'Разрешенные домены для веб-поиска через запятую (например: habr.ru,vc.ru)',
                'category' => 'web',
                'sort_order' => 120
            ],
            'web_search_region' => [
                'value' => '213',
                'caption' => 'Регион для веб-поиска',
                'type' => 'text',
                'description' => 'Регион для веб-поиска (213 - Москва, 2 - Санкт-Петербург, и т.д.)',
                'category' => 'web',
                'sort_order' => 130
            ],
            'assistant_name' => [
                'value' => 'Помощник поддержки',
                'caption' => 'Название ассистента',
                'type' => 'text',
                'description' => 'Имя для ассистента и векторного хранилища',
                'category' => 'general',
                'sort_order' => 140
            ],
            'base_url' => [
                'value' => 'https://rest-assistant.api.cloud.yandex.net/v1/responses',
                'caption' => 'Базовый URL API',
                'type' => 'text',
                'description' => 'Базовый URL для API Yandex Assistant',
                'category' => 'yandex',
                'sort_order' => 160
            ],
        ];
    }

    /**
     * Получение заголовка по умолчанию
     */
    private function getDefaultCaption(string $key): string
    {
        $defaults = $this->getDefaultSettingsArray();
        return $defaults[$key]['caption'] ?? ucfirst(str_replace('_', ' ', $key));
    }

    /**
     * Получение типа по умолчанию
     */
    private function getDefaultType(string $key): string
    {
        $defaults = $this->getDefaultSettingsArray();
        return $defaults[$key]['type'] ?? 'text';
    }

    /**
     * Получение описания по умолчанию
     */
    private function getDefaultDescription(string $key): string
    {
        $defaults = $this->getDefaultSettingsArray();
        return $defaults[$key]['description'] ?? '';
    }

    /**
     * Получение категории по умолчанию
     */
    private function getConfigCategory(string $key): string
    {
        $settings = $this->getDefaultSettingsArray();
        return $settings[$key]['category'] ?? 'general';
    }

    /**
     * Получение номера сортировки по умолчанию
     */
    private function getSortOrder(string $key): int
    {
        $settings = $this->getDefaultSettingsArray();
        return $settings[$key]['sort_order'] ?? 999;
    }
}