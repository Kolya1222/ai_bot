<?php

namespace kolya2320\Ai_bot\Controllers;

use Illuminate\Http\JsonResponse;
use kolya2320\Ai_bot\Models\BotaiChat;
use kolya2320\Ai_bot\Models\BotaiSession;
use Illuminate\Support\Facades\Config;

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
        try {
            $sessions = BotaiSession::withCount('chats')
                ->with('latestChat')
                ->orderBy('created_at', 'desc')
                ->paginate(10);

            // Если нет сессий, возвращаем пустой массив
            if ($sessions->count() === 0) {
                return response()->json([
                    'success' => true,
                    'sessions' => [],
                    'pagination' => [
                        'current_page' => 1,
                        'last_page' => 1,
                        'total' => 0,
                        'per_page' => 10
                    ]
                ]);
            }

            $formattedSessions = $sessions->map(function($session) {
                $latestChat = $session->latestChat;
                
                // Исправляем обработку timestamp
                $timestamp = null;
                $time = null;
                $date = null;
                
                if ($latestChat && $latestChat->timestamp) {
                    // Проверяем, является ли timestamp объектом DateTime
                    if ($latestChat->timestamp instanceof \DateTime) {
                        $timestamp = $latestChat->timestamp;
                        $time = $timestamp->format('H:i:s');
                        $date = $timestamp->format('Y-m-d');
                    } else {
                        // Если это строка, пытаемся преобразовать
                        try {
                            $timestamp = \Carbon\Carbon::parse($latestChat->timestamp);
                            $time = $timestamp->format('H:i:s');
                            $date = $timestamp->format('Y-m-d');
                        } catch (\Exception $e) {
                            // Если не удается распарсить, используем как есть
                            $time = $latestChat->timestamp;
                            $date = substr($latestChat->timestamp, 0, 10);
                        }
                    }
                }
                
                return [
                    'session_id' => $session->session_id,
                    'assistant_id' => $session->assistant_id,
                    'thread_id' => $session->thread_id,
                    'created_at' => $session->created_at,
                    'chats_count' => $session->chats_count,
                    'latest_chat' => $latestChat ? [
                        'message' => $latestChat->message,
                        'timestamp' => $time,
                        'date' => $date,
                        'type' => $latestChat->type
                    ] : null
                ];
            });

            return response()->json([
                'success' => true,
                'sessions' => $formattedSessions,
                'pagination' => [
                    'current_page' => $sessions->currentPage(),
                    'last_page' => $sessions->lastPage(),
                    'total' => $sessions->total(),
                    'per_page' => $sessions->perPage(),
                    'from' => $sessions->firstItem(),
                    'to' => $sessions->lastItem()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Internal server error: ' . $e->getMessage(),
                'trace' => env('APP_DEBUG') ? $e->getTraceAsString() : null
            ], 500);
        }
    }

    /**
     * Получение детальной информации о сессии
     */
    public function getSessionDetail($sessionId)
    {
        try {
            $session = BotaiSession::where('session_id', $sessionId)->firstOrFail();
            $chats = BotaiChat::where('session_id', $sessionId)
                ->orderBy('timestamp', 'asc')
                ->orderBy('id', 'asc')
                ->get();

            $messages = $chats->map(function($chat) {
                $messages = [];
                
                // Добавляем сообщение пользователя, если есть
                if (!empty($chat->user_message)) {
                    $messages[] = [
                        'type' => 'user',
                        'message' => $chat->user_message,
                        'time' => $chat->timestamp ? $chat->timestamp->format('H:i:s') : '',
                        'date' => $chat->timestamp ? $chat->timestamp->format('Y-m-d') : ''
                    ];
                }
                
                // Добавляем ответ бота, если есть
                if (!empty($chat->bot_response)) {
                    $messages[] = [
                        'type' => 'bot',
                        'message' => $chat->bot_response,
                        'time' => $chat->timestamp ? $chat->timestamp->format('H:i:s') : '',
                        'date' => $chat->timestamp ? $chat->timestamp->format('Y-m-d') : ''
                    ];
                }
                
                return $messages;
            })->collapse();

            return response()->json([
                'success' => true,
                'session' => $session,
                'messages' => $messages
            ]);

        } catch (\Exception $e) {            
            return response()->json([
                'success' => false,
                'error' => 'Internal server error: ' . $e->getMessage()
            ], 500);
        }
    }
    /**
     * Получение статистики по чатам
     */
    public function getStatistics(): JsonResponse
    {
        try {
            $totalSessions = BotaiSession::count();
            $totalMessages = BotaiChat::count();
            $todayMessages = BotaiChat::whereDate('timestamp', today())->count();
            $activeSessionsToday = BotaiSession::whereHas('chats', function($query) {
                $query->whereDate('timestamp', today());
            })->count();

            return response()->json([
                'success' => true,
                'statistics' => [
                    'total_sessions' => $totalSessions,
                    'total_messages' => $totalMessages,
                    'today_messages' => $todayMessages,
                    'active_sessions_today' => $activeSessionsToday
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Internal server error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Удаление сессии чата
     */
    public function deleteSession($sessionId): JsonResponse
    {
        try {
            $session = BotaiSession::where('session_id', $sessionId)->first();

            if (!$session) {
                return response()->json([
                    'success' => false,
                    'error' => 'Session not found'
                ], 404);
            }

            // Получаем IAM-токен
            $iamToken = Config::get('services.yandex_cloud.iam_token.value', '');
            
            if (!empty($iamToken)) {
                // Удаляем ассистента и тред в Yandex Cloud
                $this->deleteYandexAssistant($session->assistant_id, $iamToken);
                $this->deleteYandexThread($session->thread_id, $iamToken);
            }

            // Удаляем все сообщения сессии
            BotaiChat::where('session_id', $sessionId)->delete();
            
            // Удаляем саму сессию
            $session->delete();

            return response()->json([
                'success' => true,
                'message' => 'Session deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Internal server error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Удаление ассистента в Yandex Cloud
     */
    private function deleteYandexAssistant(string $assistantId, string $iamToken): bool
    {
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "https://rest-assistant.api.cloud.yandex.net/assistants/v1/assistants/{$assistantId}");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Api-Key ' . $iamToken,
                'Content-Type: application/json'
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            return $httpCode === 200 || $httpCode === 204;
            
        } catch (\Exception $e) {
            error_log("Error deleting assistant: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Удаление треда в Yandex Cloud
     */
    private function deleteYandexThread(string $threadId, string $iamToken): bool
    {
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "https://rest-assistant.api.cloud.yandex.net/assistants/v1/threads/{$threadId}");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Api-Key ' . $iamToken,
                'Content-Type: application/json'
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            return $httpCode === 200 || $httpCode === 204;
            
        } catch (\Exception $e) {
            error_log("Error deleting thread: " . $e->getMessage());
            return false;
        }
    }
}