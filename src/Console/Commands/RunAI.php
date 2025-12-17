<?php
namespace kolya2320\Ai_bot\Console\Commands;

use Illuminate\Console\Command;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class RunAI extends Command
{
    protected $signature = 'botai:run';
    protected $description = 'Run the AI bot';
    
    private $client;
    private $apiKey;
    private $folderId;

    public function handle()
    {
        $this->apiKey = config('services.yandex_cloud.api_key.value');
        $this->folderId = config('services.yandex_cloud.folder_id.value');
        
        $baseDir = MODX_BASE_PATH . config('services.yandex_cloud.text_ai_path.value');
        $filesToUpload = config('services.yandex_cloud.files_to_upload.value');
        
        $fileNames = array_map('trim', explode(',', $filesToUpload));
        $assistantName = config('services.yandex_cloud.assistant_name.value');
        $expirationDays = (int) config('services.yandex_cloud.index_expiration_days.value');

        $this->client = new Client(['timeout' => 30]);

        try {
            $this->info('Загружаем файлы...');
            $fileIds = $this->uploadFiles($baseDir, $fileNames);
            
            if (empty($fileIds)) {
                $this->error('Не удалось загрузить ни одного файла');
                return Command::FAILURE;
            }
            
            $this->info('Создаем Vector Store...');
            $vectorStoreId = $this->createVectorStore($fileIds, $assistantName, $expirationDays);
            
            $this->info('Ожидаем готовности индекса...');
            $finalStatus = $this->waitForVectorStoreReady($vectorStoreId);
            
            if ($finalStatus === 'completed') {
                $this->info("Vector Store готов к работе! ID: " . $vectorStoreId);
                return Command::SUCCESS;
            } else {
                $this->error("Vector Store завершился со статусом: " . $finalStatus);
                return Command::FAILURE;
            }
            
        } catch (\Exception $e) {
            $this->error('Ошибка: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function uploadFiles(string $baseDir, array $fileNames): array
    {
        $fileIds = [];
        
        foreach ($fileNames as $fileName) {
            $filePath = $baseDir . $fileName;
            
            if (!file_exists($filePath)) {
                $this->error("Файл не найден: " . $filePath);
                continue;
            }
            
            try {
                $response = $this->client->post('https://rest-assistant.api.cloud.yandex.net/v1/files', [
                    'multipart' => [
                        [
                            'name' => 'file',
                            'contents' => fopen($filePath, 'r'),
                            'filename' => $fileName
                        ],
                        [
                            'name' => 'purpose',
                            'contents' => 'assistants'
                        ]
                    ],
                    'headers' => [
                        'Authorization' => 'Api-Key ' . $this->apiKey,
                        'OpenAI-Project' => $this->folderId,
                    ]
                ]);

                $data = json_decode($response->getBody()->getContents(), true);
                
                if (empty($data['id'])) {
                    $this->error("Не получен ID для файла " . $fileName);
                    continue;
                }
                
                $fileIds[] = $data['id'];
                $this->info("Файл " . $fileName . " загружен, ID: " . $data['id']);
                
            } catch (RequestException $e) {
                $error = $this->getGuzzleError($e);
                $this->error("Ошибка загрузки файла " . $fileName . ": " . $error);
                continue;
            }
        }
        
        return $fileIds;
    }

    private function createVectorStore(array $fileIds, string $name, int $expirationDays): string
    {
        try {
            $requestData = [
                'name' => $name,
                'file_ids' => $fileIds,
            ];
            
            if ($expirationDays > 0) {
                $requestData['expires_after'] = [
                    'anchor' => 'last_active_at',
                    'days' => $expirationDays
                ];
            }
            
            $response = $this->client->post('https://rest-assistant.api.cloud.yandex.net/v1/vector_stores', [
                'json' => $requestData,
                'headers' => [
                    'Authorization' => 'Api-Key ' . $this->apiKey,
                    'OpenAI-Project' => $this->folderId,
                    'Content-Type' => 'application/json'
                ]
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            
            if (empty($data['id'])) {
                throw new \Exception('Не получен ID Vector Store');
            }

            return $data['id'];

        } catch (RequestException $e) {
            $error = $this->getGuzzleError($e);
            throw new \Exception('Ошибка создания Vector Store: ' . $error);
        }
    }

    private function waitForVectorStoreReady(string $vectorStoreId): string
    {
        $maxAttempts = 90;
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            sleep(2);
            $attempt++;

            try {
                $response = $this->client->get("https://rest-assistant.api.cloud.yandex.net/v1/vector_stores/{$vectorStoreId}", [
                    'headers' => [
                        'Authorization' => 'Api-Key ' . $this->apiKey,
                        'OpenAI-Project' => $this->folderId
                    ]
                ]);
                
                $data = json_decode($response->getBody()->getContents(), true);
                $status = $data['status'] ?? 'unknown';

                if (in_array($status, ['completed', 'failed'])) {
                    return $status;
                }

            } catch (RequestException $e) {
                if ($attempt < $maxAttempts - 1) {
                    continue;
                }
            }
        }

        return 'timeout';
    }

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