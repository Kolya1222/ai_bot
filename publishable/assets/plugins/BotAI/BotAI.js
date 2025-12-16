class BotAIChat {
    constructor() {
        this.sessionId = this.getCookie('botai_session') || '';
        this.assistantId = this.getCookie('botai_assistant_id') || '';
        this.threadId = this.getCookie('botai_thread_id') || '';
        this.isOpen = false;
        
        this.initializeChat();
        this.loadChatHistory();
    }

    initializeChat() {
        // Элементы интерфейса
        this.chatButton = document.getElementById('chatButton');
        this.chatWindow = document.getElementById('chatWindow');
        this.chatClose = document.getElementById('chatClose');
        this.chatMessages = document.getElementById('chatMessages');
        this.chatInput = document.getElementById('chatInput');
        this.chatSend = document.getElementById('chatSend');

        // Обработчики событий
        this.chatButton.addEventListener('click', () => this.toggleChat());
        this.chatClose.addEventListener('click', () => this.closeChat());
        this.chatSend.addEventListener('click', () => this.sendMessage());
        
        this.chatInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.sendMessage();
            }
        });

        this.chatInput.addEventListener('input', () => {
            this.adjustTextareaHeight();
        });

        // Закрытие по клику вне чата
        document.addEventListener('click', (e) => {
            if (this.isOpen && 
                !this.chatWindow.contains(e.target) && 
                !this.chatButton.contains(e.target)) {
                this.closeChat();
            }
        });
    }

    toggleChat() {
        if (this.isOpen) {
            this.closeChat();
        } else {
            this.openChat();
        }
    }

    openChat() {
        this.chatWindow.style.display = 'flex'; // меняем на flex
        this.chatButton.style.display = 'none';
        this.isOpen = true;
        this.chatInput.focus();
        this.scrollToBottom();
        
        // Предотвращаем прокрутку body при открытом чате
        document.body.style.overflow = 'hidden';
    }

    closeChat() {
        this.chatWindow.style.display = 'none';
        this.chatButton.style.display = 'flex';
        this.isOpen = false;
        
        // Возвращаем прокрутку body
        document.body.style.overflow = '';
    }

    adjustTextareaHeight() {
        this.chatInput.style.height = 'auto';
        this.chatInput.style.height = Math.min(this.chatInput.scrollHeight, 120) + 'px';
    }

    async sendMessage() {
        const message = this.chatInput.value.trim();
        
        if (!message) return;

        // Проверяем наличие необходимых данных
        if (!this.sessionId) {
            this.showError('Ошибка инициализации чата. Перезагрузите страницу.');
            return;
        }

        // Очищаем поле ввода
        this.chatInput.value = '';
        this.chatInput.style.height = 'auto';

        // Добавляем сообщение пользователя в чат
        this.addMessage('user', message);

        // Показываем индикатор загрузки
        const loadingMessage = this.addMessage('bot', 'Думаю...', true);

        try {
            const response = await this.makeRequest('send_message', {
                session_id: this.sessionId,
                message: message,
                assistant_id: this.assistantId,
                thread_id: this.threadId
            });

            if (response.success) {
                // Обновляем сообщение с ответом
                loadingMessage.querySelector('.message-content').textContent = response.bot_response;
                loadingMessage.querySelector('.message-time').textContent = response.timestamp;
                loadingMessage.classList.remove('loading');
            } else {
                throw new Error(response.error || 'Ошибка отправки сообщения');
            }
        } catch (error) {
            loadingMessage.querySelector('.message-content').textContent = 'Ошибка: ' + error.message;
            loadingMessage.classList.remove('loading');
            console.error('Ошибка отправки сообщения:', error);
        }

        this.scrollToBottom();
    }

    async loadChatHistory() {
        if (!this.sessionId) return;

        try {
            const response = await this.makeRequest('load_history', {
                session_id: this.sessionId
            });

            if (response.messages && response.messages.length > 0) {
                // Очищаем стандартное приветственное сообщение
                this.chatMessages.innerHTML = '';
                
                // Добавляем историю сообщений
                response.messages.forEach(msg => {
                    this.addMessage(msg.type, msg.message, false, msg.time);
                });
                
                this.scrollToBottom();
            }
        } catch (error) {
            console.error('Ошибка загрузки истории:', error);
        }
    }

    addMessage(type, message, isLoading = false, timestamp = null) {
        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${type}-message ${isLoading ? 'loading' : ''}`;
        
        const messageContent = document.createElement('div');
        messageContent.className = 'message-content';
        messageContent.textContent = message;

        const messageTime = document.createElement('div');
        messageTime.className = 'message-time';
        messageTime.textContent = timestamp || this.getCurrentTime();

        messageDiv.appendChild(messageContent);
        messageDiv.appendChild(messageTime);
        
        this.chatMessages.appendChild(messageDiv);
        this.scrollToBottom();

        return messageDiv;
    }

    showError(message) {
        const errorMessage = this.addMessage('bot', message);
        setTimeout(() => {
            errorMessage.remove();
        }, 5000);
    }

    scrollToBottom() {
        this.chatMessages.scrollTop = this.chatMessages.scrollHeight;
    }

    getCurrentTime() {
        return new Date().toLocaleTimeString('ru-RU', {
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    async makeRequest(action, data) {
        try {
            // Получаем CSRF-токен
            const csrfToken = this.getCsrfToken();
            
            // Добавляем CSRF-токен в данные запроса
            const requestData = {
                ...data,
                _token: csrfToken
            };

            const response = await fetch(`/bot-ai/${action.replace('_', '-')}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify(requestData)
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            return await response.json();
        } catch (error) {
            console.error('Ошибка запроса:', error);
            throw error;
        }
    }

    getCsrfToken() {
        // Пытаемся получить токен из meta-тега
        const metaTag = document.querySelector('meta[name="csrf-token"]');
        if (metaTag) {
            return metaTag.content;
        }
        
        // Ищем в форме
        const formToken = document.querySelector('input[name="_token"]');
        if (formToken) {
            return formToken.value;
        }
        
        // Ищем в window (если был установлен глобально)
        if (window.Laravel && window.Laravel.csrfToken) {
            return window.Laravel.csrfToken;
        }
        
        console.error('CSRF token not found');
        return '';
    }

    getCookie(name) {
        const matches = document.cookie.match(new RegExp(
            "(?:^|; )" + name.replace(/([\.$?*|{}\(\)\[\]\\\/\+^])/g, '\\$1') + "=([^;]*)"
        ));
        return matches ? decodeURIComponent(matches[1]) : undefined;
    }
}

// Инициализация чата после загрузки DOM
document.addEventListener('DOMContentLoaded', () => {
    // Проверяем, что элементы чата существуют на странице
    if (document.getElementById('chatButton')) {
        new BotAIChat();
    }
});