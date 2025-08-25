# AI Bot Plugin for Evolution CMS

Плагин для интеграции YandexGPT AI Assistant в Evolution CMS. Добавляет функциональность AI-ассистента на ваш сайт.

## ✨ Возможности

- 💬 Всплывающий чат с AI-ассистентом
- 📚 Сохранение истории переписки
- 🧠 Интеграция с YandexGPT Assistant API
- 🎨 Готовый UI интерфейс

## 📦 Установка
Выполните команды из директории `/core`:
1. Установка пакета
```
php artisan package:installrequire kolya2320/botai "*"
```
2. Публикация стилей и скриптов
```
php artisan vendor:publish --provider="kolya2320\Ai_bot\Ai_botServiceProvider"
```
3. Создание таблиц
```
Для создания необходимых таблиц в базе данных зайдите в админ панель (при входе будут созданы таблицы в БД)
4. Настройте конфиг
```
В /core/vendor/kolya2320/botai/config/ai_bot.php заполните поля Folder ID, API Key
5. Выполните команду и результат запишите в конфиг
```
php artisan botai:run

Перейдите на сайт там появится окно чата
