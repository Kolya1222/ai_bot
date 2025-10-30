<?php

namespace kolya2320\Ai_bot\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class McpServerController
{
    public function handleRequest(Request $request): JsonResponse
    {
        // CORS headers
        $corsHeaders = [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'POST, GET, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With',
        ];

        // Handle OPTIONS request
        if ($request->getMethod() === 'OPTIONS') {
            return response()->json([], 200, $corsHeaders);
        }

        // Для GET - информация о сервере
        if ($request->getMethod() === 'GET') {
            return response()->json([
                'message' => 'MCP Server',
                'version' => '1.0.0',
                'description' => 'Model Context Protocol Server for Yandex AI Studio',
                'endpoint' => 'POST /api/mcp for JSON-RPC calls'
            ], 200, $corsHeaders);
        }

        // Обработка POST запросов (основной MCP протокол)
        return $this->handleMcpCall($request, $corsHeaders);
    }

    private function handleMcpCall(Request $request, array $headers): JsonResponse
    {

        try {
            $data = json_decode($request->getContent(), true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid JSON: ' . json_last_error_msg());
            }

            $method = $data['method'] ?? null;
            $params = $data['params'] ?? [];
            $id = $data['id'] ?? uniqid();

            if (!$method) {
                throw new \Exception('Method parameter is required');
            }

            $result = match ($method) {
                'initialize' => $this->initialize($params),
                'tools/list' => $this->listTools($params),
                'tools/call' => $this->callTool($params),
                'notifications/initialized' => $this->handleInitialized($params),
                'resources/list' => $this->listResources($params),
                'resources/read' => $this->readResource($params),
                'prompts/list' => $this->listPrompts($params),
                'prompts/get' => $this->getPrompt($params),
                default => throw new \Exception("Method not found: {$method}")
            };

            $response = response()->json([
                'jsonrpc' => '2.0',
                'id' => $id,
                'result' => $result
            ], 200, $headers);

        } catch (\Exception $e) {
            $response = response()->json([
                'jsonrpc' => '2.0',
                'id' => $id ?? uniqid(),
                'error' => [
                    'code' => -32603,
                    'message' => $e->getMessage()
                ]
            ], 500, $headers);
        }
        return $response;
    }

    // MCP методы
    private function initialize(array $params): array
    {
        return [
            'protocolVersion' => '2024-11-05',
            'capabilities' => [
                'roots' => true,
                'resources' => [
                    'subscribe' => false,
                    'listChanged' => false,
                ],
                'tools' => [
                    'listChanged' => false,
                ],
                'prompts' => [
                    'listChanged' => false,
                ],
                'notifications' => true,
            ],
            'serverInfo' => [
                'name' => 'Laravel MCP Server',
                'version' => '1.0.0',
            ],
        ];
    }

    private function listTools(array $params): array
    {
        return [
            'tools' => [
                [
                    'name' => 'get_server_time',
                    'description' => 'Get current server time and information',
                    'inputSchema' => [
                        'type' => 'object',
                        'properties' => (object) [],
                        'required' => []
                    ]
                ],
                [
                    'name' => 'get_document',
                    'description' => 'Get document by ID from SiteContent',
                    'inputSchema' => [
                        'type' => 'object',
                        'properties' => [
                            'documentId' => [
                                'type' => 'integer',
                                'description' => 'ID of the document to retrieve'
                            ]
                        ],
                        'required' => ['documentId']
                    ]
                ]
            ]
        ];
    }

    private function callTool(array $params): array
    {
        $name = $params['name'] ?? '';
        $arguments = $params['arguments'] ?? [];

        switch ($name) {
            case 'get_server_time':
                return [
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => 'Текущее время сервера: ' . now()->format('Y-m-d H:i:s e') . 
                                    '\nTimestamp: ' . now()->toISOString() .
                                    '\nTimezone: ' . config('app.timezone')
                        ]
                    ]
                ];
                
            case 'get_document':
                return $this->getDocument($arguments);
                
            default:
                throw new \Exception("Tool not found: {$name}");
        }
    }

    private function getDocument(array $arguments): array
    {
        $documentId = $arguments['documentId'] ?? null;

        if (!$documentId) {
            throw new \Exception('documentId parameter is required');
        }

        if (!is_numeric($documentId) || $documentId <= 0) {
            throw new \Exception('documentId must be a positive integer');
        }

        try {
            // Предполагаем, что у вас есть модель SiteContent
            $document = \EvolutionCMS\Models\SiteContent::find($documentId);
            
            if (!$document) {
                throw new \Exception("Document with ID {$documentId} not found");
            }

            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => $this->formatDocumentContent($document)
                    ]
                ]
            ];

        } catch (\Exception $e) {
            throw new \Exception("Error retrieving document: " . $e->getMessage());
        }
    }

    private function formatDocumentContent($document): string
    {
        // Базовое форматирование - можно адаптировать под вашу модель
        $content = "📄 Document #{$document->id}\n";
        $content .= "📛 Title: {$document->title}\n";
        
        if (isset($document->content)) {
            $content .= "📝 Content: " . substr(strip_tags($document->content), 0, 1000) . "\n";
        }
        
        if (isset($document->created_at)) {
            $content .= "📅 Created: {$document->created_at}\n";
        }
        
        if (isset($document->updated_at)) {
            $content .= "✏️ Updated: {$document->updated_at}\n";
        }
        
        // Добавьте другие поля по необходимости
        if (isset($document->author)) {
            $content .= "👤 Author: {$document->author}\n";
        }
        
        if (isset($document->status)) {
            $content .= "🔍 Status: {$document->status}\n";
        }

        return $content;
    }

    private function handleInitialized(array $params): array
    {
        return [];
    }

    private function listResources(array $params): array
    {
        return ['resources' => []];
    }

    private function readResource(array $params): array
    {
        throw new \Exception('Resource not found');
    }

    private function listPrompts(array $params): array
    {
        return ['prompts' => []];
    }

    private function getPrompt(array $params): array
    {
        throw new \Exception('Prompt not found');
    }
}