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
    ],
    'text_ai_path' => [
        'caption' => 'Директория с данными для ИИ',
        'type' => 'text',
        'value' => 'assets/plugins/BotAI/base/bali.md',
        'desc' => 'Место откуда ИИ будет брать данные для ответов'
    ],
    'max_chunk_size' => [
        'caption' => 'Максимальный размер чанка при разбитии текста',
        'type' => 'text',
        'value' => '800',
        'desc' => 'В Яндексе 2 вида чанков основной и дополнительный для перекрытия большего объема текста'
    ],
    'chunk_overlap' => [
        'caption' => 'Перекрытие соседних чанков',
        'type' => 'text',
        'value' => '400',
        'desc' => 'Если при разбитии на чанк ответ разобъется на 2 части это должно помочь'
    ],
];