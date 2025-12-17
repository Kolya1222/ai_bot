<?php
return [
    'url' => [
        'caption' => 'Route',
        'type' => 'text',
        'value' => '',
        'desc' => ''
    ],
    'folder_id' => [
        'caption' => 'Folder ID',
        'type' => 'text',
        'value' => '',
        'desc' => 'Идентификатор каталога в Yandex Cloud'
    ],
    'api_key' => [
        'caption' => 'API Key',
        'type' => 'text',
        'value' => '',
        'desc' => 'Ключ аутентификации для Yandex Cloud API'
    ],
    'search_index_id' => [
        'caption' => 'Search Index ID',
        'type' => 'text',
        'value' => '',
        'desc' => 'Идентификатор поискового индекса (опционально)'
    ],
    'instruction' => [
        'caption' => 'Инструкция для AI',
        'type' => 'textarea',
        'value' => 'Ты полезный AI помощник. Отвечай на вопросы пользователей вежливо и информативно. Будь дружелюбным и помогай решать проблемы.',
        'desc' => 'Системная инструкция для поведения AI ассистента'
    ],
    'model_uri' => [
        'caption' => 'Модель AI',
        'type' => 'text',
        'value' => 'yandexgpt-lite/latest',
        'desc' => 'Идентификатор модели Yandex GPT (yandexgpt-lite/latest, yandexgpt/latest)'
    ],
    'text_ai_path' => [
        'caption' => 'Директория с данными для ИИ',
        'type' => 'text',
        'value' => 'assets/plugins/BotAI/base/',
        'desc' => 'Место откуда ИИ будет брать данные для ответов'
    ],
    'files_to_upload' => [
        'caption' => 'Файлы для загрузки',
        'type' => 'text',
        'value' => 'bali.md',
        'desc' => 'Список файлов для загрузки через запятую (например: bali.md,kazakhstan.md)'
    ],
    'temperature' => [
        'caption' => 'Temperature',
        'type' => 'text',
        'value' => '0.3',
        'desc' => 'Креативность ответов (0.0-1.0). Чем выше значение, тем более креативными и случайными будут ответы'
    ],
    'max_output_tokens' => [
        'caption' => 'Максимальное количество токенов',
        'type' => 'text',
        'value' => '1000',
        'desc' => 'Максимальное количество токенов в ответе модели'
    ],
    'top_p' => [
        'caption' => 'Top-P',
        'type' => 'text',
        'value' => '',
        'desc' => 'Альтернатива temperature для контроля случайности (0.0-1.0). Оставьте пустым для использования значения по умолчанию'
    ],
    'enable_web_search' => [
        'caption' => 'Включить веб-поиск',
        'type' => 'checkbox',
        'value' => '0',
        'desc' => 'Включить инструмент web_search для поиска в интернете'
    ],
    'web_search_domains' => [
        'caption' => 'Домены для веб-поиска',
        'type' => 'text',
        'value' => 'habr.ru',
        'desc' => 'Разрешенные домены для веб-поиска через запятую (например: habr.ru,vc.ru)'
    ],
    'web_search_region' => [
        'caption' => 'Регион для веб-поиска',
        'type' => 'text',
        'value' => '213',
        'desc' => 'Регион для веб-поиска (213 - Москва, 2 - Санкт-Петербург, и т.д.)'
    ],
    'enable_streaming' => [
        'caption' => 'Включить стриминг',
        'type' => 'checkbox',
        'value' => '0',
        'desc' => 'Включить потоковую передачу ответов (Server-Sent Events)'
    ],
    'assistant_name' => [
        'caption' => 'Название ассистента',
        'type' => 'text',
        'value' => 'Помощник поддержки',
        'desc' => 'Имя для ассистента и векторного хранилища'
    ],
    'index_expiration_days' => [
        'caption' => 'Срок жизни индекса (дней)',
        'type' => 'text',
        'value' => '3',
        'desc' => 'Через сколько дней индекс будет удален'
    ],
    'base_url' => [
        'caption' => 'Базовый URL API',
        'type' => 'text',
        'value' => 'https://rest-assistant.api.cloud.yandex.net/v1',
        'desc' => 'Базовый URL для API Yandex Assistant'
    ],
];