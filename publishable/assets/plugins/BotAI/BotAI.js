class BotAIChat {
    constructor() {
        this.sessionId = this.getCookie('botai_session') || '';
        this.isOpen = false;
        this.isLoading = false;
        
        this.initializeChat();
        this.loadChatHistory();
    }

    initializeChat() {
        this.chatButton = document.getElementById('chatButton');
        this.chatWindow = document.getElementById('chatWindow');
        this.chatClose = document.getElementById('chatClose');
        this.chatMessages = document.getElementById('chatMessages');
        this.chatInput = document.getElementById('chatInput');
        this.chatSend = document.getElementById('chatSend');

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
        this.chatWindow.style.display = 'flex';
        this.chatButton.style.display = 'none';
        this.isOpen = true;
        this.chatInput.focus();
        this.scrollToBottom();
        document.body.style.overflow = 'hidden';
    }

    closeChat() {
        this.chatWindow.style.display = 'none';
        this.chatButton.style.display = 'flex';
        this.isOpen = false;
        document.body.style.overflow = '';
    }

    adjustTextareaHeight() {
        this.chatInput.style.height = 'auto';
        this.chatInput.style.height = Math.min(this.chatInput.scrollHeight, 120) + 'px';
    }

    async sendMessage() {
        const message = this.chatInput.value.trim();
        
        if (!message || this.isLoading) return;

        this.chatInput.value = '';
        this.chatInput.style.height = 'auto';

        this.addMessage('user', message);

        const loadingMessage = this.addMessage('bot', 'Думаю...', true);
        this.isLoading = true;

        try {
            const response = await this.makeRequest('send', {
                session_id: this.sessionId,
                message: message
            });

            if (response.success) {
                loadingMessage.querySelector('.message-content').textContent = response.bot_response;
                loadingMessage.querySelector('.message-time').textContent = response.timestamp;
                loadingMessage.classList.remove('loading');
                // Добавляем ссылки, если они есть
                if (response.annotations && response.annotations.length > 0) {
                    this.addAnnotationsToMessage(loadingMessage, response.annotations);
                }
            } else {
                throw new Error(response.error || 'Ошибка отправки сообщения');
            }
        } catch (error) {
            loadingMessage.querySelector('.message-content').textContent = 'Ошибка: ' + error.message;
            loadingMessage.classList.remove('loading');
            console.error('Ошибка отправки сообщения:', error);
        } finally {
            this.isLoading = false;
        }

        this.scrollToBottom();
    }

    addAnnotationsToMessage(messageElement, annotations) {
        const annotationsContainer = document.createElement('div');
        annotationsContainer.className = 'annotations-container';
        
        const annotationsTitle = document.createElement('div');
        annotationsTitle.className = 'annotations-title';
        annotationsTitle.textContent = 'Источники информации:';
        annotationsContainer.appendChild(annotationsTitle);
        
        const annotationsList = document.createElement('ul');
        annotationsList.className = 'annotations-list';
        
        annotations.forEach(annotation => {
            const listItem = document.createElement('li');
            listItem.className = 'annotation-item';
            
            const link = document.createElement('a');
            link.href = annotation.url;
            link.target = '_blank';
            link.rel = 'noopener noreferrer';
            link.className = 'annotation-link';
            if (annotation.title && annotation.title.trim() !== '') {
                link.textContent = annotation.title;
            } else if (annotation.domain) {
                link.textContent = annotation.domain;
            } else {
                const url = new URL(annotation.url);
                link.textContent = url.hostname;
            }
            const externalIcon = document.createElement('span');
            externalIcon.className = 'external-icon';
            externalIcon.innerHTML = ' ↗';
            link.appendChild(externalIcon);
            
            listItem.appendChild(link);
            annotationsList.appendChild(listItem);
        });
        
        annotationsContainer.appendChild(annotationsList);
        messageElement.appendChild(annotationsContainer);
    }

    async loadChatHistory() {
        if (!this.sessionId) return;

        try {
            const response = await this.makeRequest('history', {
                session_id: this.sessionId
            });

            if (response.success && response.messages && response.messages.length > 0) {
                this.chatMessages.innerHTML = '';
                
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

    scrollToBottom() {
        this.chatMessages.scrollTop = this.chatMessages.scrollHeight;
    }

    getCurrentTime() {
        return new Date().toLocaleTimeString('ru-RU', {
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    async makeRequest(endpoint, data) {
        try {
            const csrfToken = this.getCsrfToken();
            
            const response = await fetch(`/botai/${endpoint}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    ...data,
                    _token: csrfToken
                })
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
        const metaTag = document.querySelector('meta[name="csrf-token"]');
        if (metaTag) {
            return metaTag.content;
        }
        
        const formToken = document.querySelector('input[name="_token"]');
        if (formToken) {
            return formToken.value;
        }
        
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

document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('chatButton')) {
        new BotAIChat();
    }
});