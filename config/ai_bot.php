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
    'iam_token' => [
        'caption' => 'API Key',
        'type' => 'text',
        'value' => '',
        'desc' => 'Токен аутентификации для Yandex Cloud API'
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
    ]
];