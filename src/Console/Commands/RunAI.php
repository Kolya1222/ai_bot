<?php 
namespace kolya2320\Ai_bot\Console\Commands;
 
use Illuminate\Console\Command;
 
class RunAI extends Command
{
    // Название и описание команды
    protected $signature = 'botai:run';
    protected $description = 'Run the AI bot';
 
    public function __construct()
    {
        parent::__construct();
    }
 
    public function handle()
    {
        // IAM-токен
        $iamTokenapi = config('services.yandex_cloud.iam_token.value') ?? '';
        $folderid = config('services.yandex_cloud.folder_id.value') ?? '';
        //Загрузка файлов для яндекс клауд
        // Путь к файлу bali.md
        $filePath = MODX_BASE_PATH.'./base/bali.md';

        // Чтение содержимого файла
        $fileContent = file_get_contents($filePath);

        // Кодирование содержимого файла в Base64
        $base64Content = base64_encode($fileContent);

        // Создание массива для JSON-запроса
        $requestBody = [
            'folderId' => $folderid, // Замените на ваш идентификатор каталога
            'content' => $base64Content
        ];
        // Преобразование массива в JSON
        $jsonData = json_encode($requestBody, JSON_UNESCAPED_SLASHES);

        // Инициализация cURL
        $ch = curl_init();

        // Установка URL и других необходимых параметров
        curl_setopt($ch, CURLOPT_URL, "https://rest-assistant.api.cloud.yandex.net/files/v1/files");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Api-Key ' . $iamTokenapi,
            'Content-Type: application/json'
        ]);

        // Выполнение запроса и получение ответа
        $response = curl_exec($ch);

        // Проверка на ошибки
        if (curl_errno($ch)) {
            echo 'Ошибка cURL: ' . curl_error($ch);
        } else {
            // Декодирование JSON-ответа
            $responseFile = json_decode($response, true);
        }
        $FileID = $responseFile["id"];
        //Создание поискового индекса
        // Создание массива для JSON-запроса
        $requestBody = [
            'folderId' => $folderid,
            'fileIds' => [$FileID],
            "textSearchIndex"=>[
                "chunkingStrategy"=>[
                    "staticStrategy"=>[
                        "maxChunkSizeTokens"=>"800",
                        "chunkOverlapTokens"=>"400"
                    ]
                ]
            ]
        ];
        // Преобразование массива в JSON
        $jsonData = json_encode($requestBody, JSON_UNESCAPED_SLASHES);

        // Инициализация cURL
        $ch = curl_init();

        // Установка URL и других необходимых параметров
        curl_setopt($ch, CURLOPT_URL, "https://rest-assistant.api.cloud.yandex.net/assistants/v1/searchIndex");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Api-Key ' . $iamTokenapi,
            'Content-Type: application/json'
        ]);
        // Выполнение запроса и получение ответа
        $response = curl_exec($ch);

        // Проверка на ошибки
        if (curl_errno($ch)) {
            echo 'Ошибка cURL: ' . curl_error($ch);
        } else {
            // Декодирование JSON-ответа
            $responsearch = json_decode($response, true);
        }
        // Закрытие cURL-сессии
        curl_close($ch);
        $SearchID = $responsearch["id"];
        sleep(10);
        $ch = curl_init();
        // Установка URL и других необходимых параметров
        curl_setopt($ch, CURLOPT_URL, "https://operation.api.cloud.yandex.net/operations/".$SearchID);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Api-Key ' . $iamTokenapi,
            'Content-Type: application/json'
        ]);
        // Выполнение запроса и получение ответа
        $response = curl_exec($ch);

        // Проверка на ошибки
        if (curl_errno($ch)) {
            echo 'Ошибка cURL: ' . curl_error($ch);
        } else {
            // Декодирование JSON-ответа
            $responfinalserch = json_decode($response, true);
        }

        // Закрытие cURL-сессии
        curl_close($ch);
        $searchIndex = $responfinalserch["response"]["id"];
        echo "Поисковый индекс:";
        print_r($searchIndex);
    }
}