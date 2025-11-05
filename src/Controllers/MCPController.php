<?php

namespace kolya2320\Ai_bot\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use EvolutionCMS\DocumentManager\Facades\DocumentManager;
use Illuminate\Support\Facades\Log;

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
				],
				[
					'name' => 'create_document',
					'description' => 'Create new document in EvolutionCMS',
					'inputSchema' => [
						'type' => 'object',
						'properties' => [
							'pagetitle' => [
								'type' => 'string',
								'description' => 'Page title (required)',
								'maxLength' => 255
							],
							'template' => [
								'type' => 'integer', 
								'description' => 'Template ID (required)'
							],
							'longtitle' => [
								'type' => 'string',
								'description' => 'Long title',
								'maxLength' => 255
							],
							'description' => [
								'type' => 'string', 
								'description' => 'Description',
								'maxLength' => 255
							],
							'alias' => [
								'type' => 'string',
								'description' => 'URL alias', 
								'maxLength' => 245
							],
							'menutitle' => [
								'type' => 'string',
								'description' => 'Menu title',
								'maxLength' => 255
							],
							'parent' => [
								'type' => 'integer',
								'description' => 'Parent document ID',
								'default' => 0
							],
							'content' => [
								'type' => 'string',
								'description' => 'Document content with HTML markup'
							],
							'introtext' => [
								'type' => 'string', 
								'description' => 'Quick summary of the document'
							],
							'published' => [
								'type' => 'integer',
								'description' => 'Publish status: 0 = No, 1 = Yes',
								'enum' => [0, 1],
								'default' => 0
							],
							'hidemenu' => [
								'type' => 'integer', 
								'description' => 'Hide from menu: 0 = No, 1 = Yes',
								'enum' => [0, 1],
								'default' => 0
							],
							'searchable' => [
								'type' => 'integer',
								'description' => 'Searchable: 0 = No, 1 = Yes', 
								'enum' => [0, 1],
								'default' => 1
							],
							'cacheable' => [
								'type' => 'integer',
								'description' => 'Cacheable: 0 = No, 1 = Yes',
								'enum' => [0, 1],
								'default' => 1
							],
							'richtext' => [
								'type' => 'integer',
								'description' => 'Use rich text editor: 0 = No, 1 = Yes',
								'enum' => [0, 1],
								'default' => 1
							],
							'isfolder' => [
								'type' => 'integer',
								'description' => 'Is folder: 0 = No, 1 = Yes',
								'enum' => [0, 1],
								'default' => 0
							],
							'menuindex' => [
								'type' => 'integer',
								'description' => 'Menu position',
								'default' => 0
							],
							'link_attributes' => [
								'type' => 'string',
								'description' => 'Link attributes',
								'maxLength' => 255
							],
							'template_variables' => [
								'type' => 'object',
								'description' => 'TV values (key-value pairs)',
								'additionalProperties' => true
							]
						],
						'required' => ['pagetitle', 'template']
					]
				],
				[
					'name' => 'edit_document',
					'description' => 'Edit existing document in EvolutionCMS',
					'inputSchema' => [
						'type' => 'object',
						'properties' => [
							'id' => [
								'type' => 'integer',
								'description' => 'Document ID (required)'
							],
							'pagetitle' => [
								'type' => 'string',
								'description' => 'Page title',
								'maxLength' => 255
							],
							'menutitle' => [
								'type' => 'string', 
								'description' => 'Menu title',
								'maxLength' => 255
							],
							'longtitle' => [
								'type' => 'string',
								'description' => 'Long title',
								'maxLength' => 255
							],
							'description' => [
								'type' => 'string',
								'description' => 'Description', 
								'maxLength' => 255
							],
							'alias' => [
								'type' => 'string',
								'description' => 'URL alias',
								'maxLength' => 245
							],
							'link_attributes' => [
								'type' => 'string',
								'description' => 'Link attributes',
								'maxLength' => 255
							],
							'template' => [
								'type' => 'integer',
								'description' => 'Template ID'
							],
							'parent' => [
								'type' => 'integer', 
								'description' => 'Parent document ID'
							],
							'isfolder' => [
								'type' => 'integer',
								'description' => 'Is folder: 0 = No, 1 = Yes',
								'enum' => [0, 1]
							],
							'content' => [
								'type' => 'string',
								'description' => 'Document content with HTML markup and formatting'
							],
							'introtext' => [
								'type' => 'string',
								'description' => 'Intro text'
							],
							'published' => [
								'type' => 'integer',
								'description' => 'Publish status: 0 = No, 1 = Yes', 
								'enum' => [0, 1]
							],
							'hidemenu' => [
								'type' => 'integer',
								'description' => 'Hide from menu: 0 = No, 1 = Yes',
								'enum' => [0, 1]
							],
							'searchable' => [
								'type' => 'integer',
								'description' => 'Searchable: 0 = No, 1 = Yes',
								'enum' => [0, 1]
							],
							'cacheable' => [
								'type' => 'integer',
								'description' => 'Cacheable: 0 = No, 1 = Yes',
								'enum' => [0, 1]
							],
							'richtext' => [
								'type' => 'integer',
								'description' => 'Use rich text editor: 0 = No, 1 = Yes',
								'enum' => [0, 1]
							],
							'menuindex' => [
								'type' => 'integer', 
								'description' => 'Menu index'
							],
							'template_variables' => [
								'type' => 'object',
								'description' => 'TV values (key-value pairs)',
								'additionalProperties' => true
							]
						],
						'required' => ['id']
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
			case 'get_document':
				return $this->getDocument($arguments);
			case 'create_document':
				return $this->createDocument($arguments);
			case 'edit_document':
				return $this->editDocument($arguments);
			default:
				throw new \Exception("Tool not found: {$name}");
		}
	}

	private function createDocument(array $arguments): array
	{
		// Валидация обязательных полей
		if (empty($arguments['pagetitle'])) {
			throw new \Exception('pagetitle parameter is required');
		}

		if (empty($arguments['template']) || !is_numeric($arguments['template'])) {
			throw new \Exception('template parameter is required and must be a number');
		}

		try {
			// ОЧИСТКА КОНТЕНТА
			if (!empty($arguments['content'])) {
				$arguments['content'] = $this->cleanContent($arguments['content']);
			}

			// Базовые данные документа
			$documentData = [
				'type' => 'document',
				'contentType' => 'text/html',
				'pagetitle' => $arguments['pagetitle'],
				'template' => (int)$arguments['template'],
				'longtitle' => $arguments['longtitle'] ?? '',
				'description' => $arguments['description'] ?? '',
				'alias' => $arguments['alias'] ?? '',
				'parent' => isset($arguments['parent']) ? (int)$arguments['parent'] : 0,
				'content' => $arguments['content'] ?? '',
				'introtext' => $arguments['introtext'] ?? '',
				'published' => isset($arguments['published']) ? (int)$arguments['published'] : 0,
				'hidemenu' => isset($arguments['hidemenu']) ? (int)$arguments['hidemenu'] : 0,
				'searchable' => isset($arguments['searchable']) ? (int)$arguments['searchable'] : 1,
				'cacheable' => isset($arguments['cacheable']) ? (int)$arguments['cacheable'] : 1,
				'richtext' => isset($arguments['richtext']) ? (int)$arguments['richtext'] : 1,
				'isfolder' => isset($arguments['isfolder']) ? (int)$arguments['isfolder'] : 0,
				'menuindex' => isset($arguments['menuindex']) ? (int)$arguments['menuindex'] : 0,
			];

			// Добавляем TV-параметры если есть
			if (!empty($arguments['template_variables']) && is_array($arguments['template_variables'])) {
				foreach ($arguments['template_variables'] as $tvName => $tvValue) {
					$documentData[$tvName] = $tvValue;
				}
			}

			// Создаем документ
			$newDocument = DocumentManager::create($documentData, true, true);

			if (!$newDocument) {
				throw new \Exception('Failed to create document');
			}

			// ВОЗВРАЩАЕМ ТОЛЬКО ID В СЛУЧАЕ УСПЕХА
			return [
				'content' => [
					[
						'type' => 'text',
						'text' => (string)$newDocument->id  // Просто ID как строка
					]
				],
				'documentId' => $newDocument->id,
				'status' => 'success'
			];
		} catch (\Exception $e) {
			throw new \Exception("Error creating document: " . $e->getMessage());
		}
	}

	private function editDocument(array $arguments): array
	{
		// Валидация обязательного поля
		if (empty($arguments['id']) || !is_numeric($arguments['id'])) {
			throw new \Exception('id parameter is required and must be a number');
		}

		$documentId = (int)$arguments['id'];

		try {
			// ОЧИСТКА КОНТЕНТА - добавляем эту строку
			if (!empty($arguments['content'])) {
				$arguments['content'] = $this->cleanContent($arguments['content']);
				Log::info('Cleaned content', ['content' => $arguments['content']]);
			}

			// Проверяем существование документа
			$existingDocument = \EvolutionCMS\Models\SiteContent::find($documentId);

			if (!$existingDocument) {
				throw new \Exception("Document with ID {$documentId} not found");
			}

			// Подготавливаем данные для обновления
			$documentData = ['id' => $documentId];

			// Все поля которые можно обновлять с правильными типами
			$updatableFields = [
				'pagetitle' => 'string',
				'menutitle' => 'string', 
				'longtitle' => 'string',
				'description' => 'string',
				'alias' => 'string',
				'link_attributes' => 'string',
				'template' => 'int',
				'parent' => 'int', 
				'isfolder' => 'int',
				'content' => 'string',
				'introtext' => 'string',
				'published' => 'int',
				'hidemenu' => 'int',
				'searchable' => 'int',
				'cacheable' => 'int',
				'richtext' => 'int',
				'menuindex' => 'int'
			];

			foreach ($updatableFields as $field => $type) {
				if (array_key_exists($field, $arguments)) {
					switch ($type) {
						case 'int':
							$documentData[$field] = (int)$arguments[$field];
							break;
						case 'bool': // для совместимости
							$documentData[$field] = (int)(bool)$arguments[$field];
							break;
						default:
							$documentData[$field] = $arguments[$field];
					}
				}
			}

			// Валидация enum полей
			$enumFields = [
				'published' => [0, 1],
				'hidemenu' => [0, 1],
				'searchable' => [0, 1],
				'cacheable' => [0, 1], 
				'richtext' => [0, 1],
				'isfolder' => [0, 1]
			];

			foreach ($enumFields as $field => $allowedValues) {
				if (isset($documentData[$field]) && !in_array($documentData[$field], $allowedValues)) {
					throw new \Exception("Field {$field} must be one of: " . implode(', ', $allowedValues));
				}
			}

			// Валидация длин строк
			$maxLengths = [
				'pagetitle' => 255,
				'menutitle' => 255,
				'longtitle' => 255, 
				'description' => 255,
				'alias' => 245,
				'link_attributes' => 255
			];

			foreach ($maxLengths as $field => $maxLength) {
				if (isset($documentData[$field]) && strlen($documentData[$field]) > $maxLength) {
					throw new \Exception("Field {$field} exceeds maximum length of {$maxLength} characters");
				}
			}

			// Добавляем TV-параметры если есть
			if (!empty($arguments['template_variables']) && is_array($arguments['template_variables'])) {
				foreach ($arguments['template_variables'] as $tvName => $tvValue) {
					$documentData[$tvName] = $tvValue;
				}
			}

			// Обновляем документ
			$updatedDocument = DocumentManager::edit($documentData, true, true);

			if (!$updatedDocument) {
				throw new \Exception('Failed to update document');
			}

			// Получаем обновленный документ для ответа
			$refreshedDocument = \EvolutionCMS\Models\SiteContent::find($documentId);

			$formattedContent = $this->formatDocumentContent($refreshedDocument);

			return [
				'content' => [
					[
						'type' => 'text',
						'text' => "Document updated successfully!\n\n" . $formattedContent
					]
				],
				'documentId' => $documentId,
				'updatedFields' => array_keys($documentData),
				'documentData' => $this->getDocumentData($refreshedDocument)
			];
		} catch (\Exception $e) {
			throw new \Exception("Error updating document: " . $e->getMessage());
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
		$content = "Document #{$document->id}\n";
		$content .= "Title: {$document->pagetitle}\n";

		if (!empty($document->menutitle)) {
			$content .= "Menu Title: {$document->menutitle}\n";
		}

		if (!empty($document->longtitle)) {
			$content .= "Long Title: {$document->longtitle}\n";
		}

		if (!empty($document->description)) {
			$content .= "Description: {$document->description}\n";
		}

		if (!empty($document->alias)) {
			$content .= "Alias: {$document->alias}\n";
		}

		if (!empty($document->link_attributes)) {
			$content .= "Link Attributes: {$document->link_attributes}\n";
		}

		if (isset($document->content)) {
			$preview = substr(strip_tags($document->content), 0, 200);
			if (strlen($document->content) > 200) {
				$preview .= '...';
			}
			$content .= "Content: {$preview}\n";
		}

		if (isset($document->template)) {
			$content .= "Template: {$document->template}\n";
		}

		if (isset($document->parent)) {
			$content .= "Parent: {$document->parent}\n";
		}

		if (isset($document->isfolder)) {
			$folderStatus = $document->isfolder ? 'Folder' : 'Document';
			$content .= "Type: {$folderStatus}\n";
		}

		if (isset($document->published)) {
			$status = $document->published ? 'Published' : 'Unpublished';
			$content .= "Status: {$status}\n";
		}

		if (isset($document->hidemenu)) {
			$menuStatus = $document->hidemenu ? 'Hidden' : 'Visible';
			$content .= "Menu: {$menuStatus}\n";
		}

		if (isset($document->menuindex)) {
			$content .= "Menu Index: {$document->menuindex}\n";
		}

		if (isset($document->created_at)) {
			$content .= "Created: {$document->created_at}\n";
		}

		return $content;
	}

	// Дополнительный метод для полных данных
	private function getDocumentData($document): array
	{
		return [
			'id' => $document->id,
			'pagetitle' => $document->pagetitle,
			'longtitle' => $document->longtitle,
			'description' => $document->description,
			'alias' => $document->alias,
			'template' => $document->template,
			'parent' => $document->parent,
			'published' => (bool)$document->published,
			'content' => $document->content,
			'created_at' => $document->created_at?->toISOString(),
			'updated_at' => $document->updated_at?->toISOString(),
		];
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
	
	private function cleanContent(string $content): string
	{
		// Просто проверяем кодировку и возвращаем как есть
		if (!mb_check_encoding($content, 'UTF-8')) {
			// Если не UTF-8, конвертируем
			$content = mb_convert_encoding($content, 'UTF-8', 'auto');
		}

		return $content;
	}
}
