@extends('ai_bot::manager.layout')

@section('title', 'Управление чатами')

@section('content')
<div id="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Управление AI чатами</h2>
        <div class="btn-group">
            <button class="btn btn-outline-primary" onclick="showSettings()">
                <i class="bi bi-gear"></i> Настройки
            </button>
            <button class="btn btn-outline-secondary" onclick="loadSessions()">
                <i class="bi bi-arrow-clockwise"></i> Обновить
            </button>
        </div>
    </div>

    <div id="sessions-section">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Сессии чатов</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID сессии</th>
                                <th>Сообщений</th>
                                <th>Последнее сообщение</th>
                                <th>Дата создания</th>
                                <th>Последняя активность</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody id="sessions-table">
                            <tr>
                                <td colspan="6" class="text-center">
                                    <div class="spinner-border" role="status">
                                        <span class="visually-hidden">Загрузка...</span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <nav>
                    <ul class="pagination justify-content-center" id="sessions-pagination"></ul>
                </nav>
            </div>
        </div>
    </div>

    <div class="modal fade" id="sessionDetailModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Детали сессии</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="session-detail-content">
                        <div class="text-center">
                            <div class="spinner-border" role="status">
                                <span class="visually-hidden">Загрузка...</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="settingsModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-gear me-2"></i> Настройки AI бота
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="settings-content">
                        <div class="text-center">
                            <div class="spinner-border" role="status">
                                <span class="visually-hidden">Загрузка...</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-warning" onclick="resetConfig()">
                        <i class="bi bi-arrow-clockwise"></i> Сбросить
                    </button>
                    <button type="button" class="btn btn-primary" onclick="saveConfig()">
                        <i class="bi bi-save"></i> Сохранить
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    const MODULE_API_BASE = '{{ route("ai-bot.manager.index") }}'.replace(/\/$/, '') + '/api';

    let currentSessionId = null;
    let currentPage = 1;

    async function loadSessions(page = 1) {
        showLoading();
        currentPage = page;
        
        const response = await apiManager.get(`${MODULE_API_BASE}/sessions?page=${page}`);
        
        if (response && response.success) {
            renderSessionsTable(response.sessions || []);
            
            if (response.pagination) {
                renderPagination(response.pagination);
            }
        } else {
            showError('Ошибка загрузки сессий: ' + (response?.error || 'Неизвестная ошибка'));
        }
    }

    function showLoading() {
        document.getElementById('sessions-table').innerHTML = `
            <tr><td colspan="6" class="text-center"><div class="spinner-border"></div></td></tr>
        `;
    }

    function showError(message) {
        document.getElementById('sessions-table').innerHTML = `
            <tr><td colspan="6" class="text-center text-danger">${message}</td></tr>
        `;
    }

    function renderSessionsTable(sessions) {
        const tbody = document.getElementById('sessions-table');
        
        if (sessions.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center">Сессии не найдены</td></tr>';
            return;
        }

        tbody.innerHTML = sessions.map(session => `
            <tr class="session-item" onclick="viewSessionDetail('${session.session_id}')">
                <td>
                    <code title="${session.session_id}">${session.session_id.substring(0, 12)}...</code>
                </td>
                <td>
                    <span class="badge bg-primary" title="Всего сообщений">${session.messages_count}</span>
                    <br>
                    <small class="text-muted">
                        П ${session.user_messages_count} | Б ${session.bot_messages_count}
                    </small>
                </td>
                <td>
                    ${session.last_message ? `
                        <div class="small">
                            <span class="badge ${session.last_message_type === 'user' ? 'bg-primary' : 'bg-success'}">
                                ${session.last_message_type === 'user' ? 'П' : 'Б'}
                            </span>
                            <div class="text-truncate" style="max-width: 200px;" title="${session.last_message}">
                                ${session.last_message}
                            </div>
                            <small class="text-muted">${session.last_message_at ? formatDateTime(session.last_message_at) : ''}</small>
                        </div>
                    ` : 'Нет сообщений'}
                </td>
                <td>
                    ${session.first_message_at ? formatDateTime(session.first_message_at) : 'Нет данных'}
                </td>
                <td>
                    ${session.last_message_at ? formatDateTime(session.last_message_at) : 'Нет данных'}
                </td>
                <td>
                    <button class="btn btn-sm btn-outline-danger" 
                            onclick="event.stopPropagation(); deleteSession('${session.session_id}')"
                            title="Удалить сессию">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
            </tr>
        `).join('');
    }

    function formatDateTime(dateTimeString) {
        if (!dateTimeString) return '';
        
        try {
            const date = new Date(dateTimeString);
            return date.toLocaleString('ru-RU', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        } catch (e) {
            return dateTimeString;
        }
    }

    function renderPagination(pagination) {
        const paginationEl = document.getElementById('sessions-pagination');
        
        if (!pagination || !pagination.last_page || pagination.last_page <= 1) {
            paginationEl.innerHTML = '';
            return;
        }

        let html = '';
        const currentPage = pagination.current_page || 1;
        const lastPage = pagination.last_page || 1;

        if (currentPage > 1) {
            html += `<li class="page-item">
                        <a class="page-link" href="#" onclick="loadSessions(${currentPage - 1}); return false;">
                            &laquo;
                        </a>
                    </li>`;
        }

        for (let i = 1; i <= lastPage; i++) {
            html += `<li class="page-item ${i === currentPage ? 'active' : ''}">
                        <a class="page-link" href="#" onclick="loadSessions(${i}); return false;">
                            ${i}
                        </a>
                    </li>`;
        }

        if (currentPage < lastPage) {
            html += `<li class="page-item">
                        <a class="page-link" href="#" onclick="loadSessions(${currentPage + 1}); return false;">
                            &raquo;
                        </a>
                    </li>`;
        }

        paginationEl.innerHTML = html;
    }

    async function viewSessionDetail(sessionId) {
        currentSessionId = sessionId;
        const modal = new bootstrap.Modal(document.getElementById('sessionDetailModal'));

        const response = await apiManager.get(`${MODULE_API_BASE}/sessions/${sessionId}`);
        
        if (response.success) {
            renderSessionDetail(response);
            modal.show();
        } else {
            alert('Ошибка загрузки деталей сессии: ' + (response.error || 'Неизвестная ошибка'));
        }
    }

    function renderSessionDetail(data) {
        const content = document.getElementById('session-detail-content');
        
        const sessionInfo = `
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">Информация о сессии</h5>
                    <dl class="row mb-0">
                        <dt class="col-sm-3">ID сессии:</dt>
                        <dd class="col-sm-9"><code>${data.session.session_id}</code></dd>
                        
                        <dt class="col-sm-3">Создана:</dt>
                        <dd class="col-sm-9">${data.session.first_message || 'Нет данных'}</dd>
                        
                        <dt class="col-sm-3">Последнее сообщение:</dt>
                        <dd class="col-sm-9">${data.session.last_message || 'Нет данных'}</dd>
                        
                        <dt class="col-sm-3">Продолжительность:</dt>
                        <dd class="col-sm-9">${data.session.duration_minutes ? data.session.duration_minutes + ' минут' : 'Нет данных'}</dd>
                        
                        <dt class="col-sm-3">Сообщений:</dt>
                        <dd class="col-sm-9">
                            <span class="badge bg-primary">Всего: ${data.session.total_messages}</span>
                            <span class="badge bg-info ms-1">П: ${data.session.user_messages}</span>
                            <span class="badge bg-success ms-1">Б: ${data.session.bot_messages}</span>
                        </dd>
                    </dl>
                    <button class="btn btn-sm btn-outline-danger mt-2" onclick="deleteSession('${data.session.session_id}')">
                        <i class="bi bi-trash"></i> Удалить сессию
                    </button>
                </div>
            </div>
            
            <h5>История сообщений</h5>
        `;

        const messagesHtml = data.messages.length > 0 ? data.messages.map(msg => `
            <div class="chat-message ${msg.type === 'user' ? 'user-message' : 'bot-message'} mb-3 p-3 border rounded">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="fw-bold">${msg.type === 'user' ? 'Пользователь' : 'AI Бот'}</div>
                    <small class="text-muted">${msg.time} (${msg.date})</small>
                </div>
                <div class="mt-2">${escapeHtml(msg.message)}</div>
                ${msg.response_id ? `
                    <small class="text-muted d-block mt-1">
                        Response ID: <code>${msg.response_id.substring(0, 20)}...</code>
                    </small>
                ` : ''}
            </div>
        `).join('') : '<p class="text-center text-muted">Сообщений нет</p>';

        content.innerHTML = sessionInfo + `
            <div class="chat-history" style="max-height: 500px; overflow-y: auto;">
                ${messagesHtml}
            </div>
        `;
    }

    async function deleteSession(sessionId) {
        if (!confirm('Вы уверены, что хотите удалить эту сессию и все её сообщения?')) return;
        const response = await apiManager.delete(`${MODULE_API_BASE}/sessions/${sessionId}`);
        
        if (response.success) {
            alert('Сессия успешно удалена. Удалено сообщений: ' + (response.deleted_messages || 0));

            const modal = bootstrap.Modal.getInstance(document.getElementById('sessionDetailModal'));
            if (modal) {
                modal.hide();
            }

            loadSessions(currentPage);
            updateQuickStats();
        } else {
            alert('Ошибка удаления сессии: ' + (response.error || 'Неизвестная ошибка'));
        }
    }

    //  Настройки
    async function showSettings() {
        const response = await apiManager.get(`${MODULE_API_BASE}/config`);
        
        if (response.success) {
            renderSettingsModal(response.config);
            const modal = new bootstrap.Modal(document.getElementById('settingsModal'));
            modal.show();
        } else {
            alert('Ошибка загрузки настроек: ' + response.error);
        }
    }

    function renderSettingsModal(config) {
        const content = document.getElementById('settings-content');
        
        if (!config || config.length === 0) {
            content.innerHTML = '<p class="text-center">Настройки не найдены</p>';
            return;
        }

        const groupedConfig = {};
        config.forEach(item => {
            if (!groupedConfig[item.category]) {
                groupedConfig[item.category] = [];
            }
            groupedConfig[item.category].push(item);
        });

        const categoryNames = {
            'general': 'Основные настройки',
            'yandex': 'Yandex Cloud API',
            'search': 'Поиск по файлам',
            'ai': 'Настройки AI',
            'web': 'Веб-поиск'
        };

        let html = `
            <div class="accordion" id="configAccordion">
        `;

        Object.keys(categoryNames).forEach((category, index) => {
            if (groupedConfig[category] && groupedConfig[category].length > 0) {
                const items = groupedConfig[category];
                const accordionId = `accordion${category}`;
                const collapseId = `collapse${category}`;
                
                html += `
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button ${index === 0 ? '' : 'collapsed'}" type="button" 
                                    data-bs-toggle="collapse" data-bs-target="#${collapseId}" 
                                    aria-expanded="${index === 0 ? 'true' : 'false'}">
                                <strong>${categoryNames[category]}</strong>
                                <span class="badge bg-secondary ms-2">${items.length}</span>
                            </button>
                        </h2>
                        <div id="${collapseId}" class="accordion-collapse collapse ${index === 0 ? 'show' : ''}" 
                            data-bs-parent="#configAccordion">
                            <div class="accordion-body">
                                <div class="row g-3">
                `;

                items.forEach(item => {
                    const inputId = `config-${item.key}`;
                    let inputHtml = '';
                    const fieldType = (item.key === 'api_key') ? 'password' : item.type;
                    
                    if (fieldType === 'textarea') {
                        inputHtml = `
                            <textarea class="form-control" id="${inputId}" 
                                    rows="4" placeholder="${item.caption}">${escapeHtml(item.value)}</textarea>
                        `;
                    } else if (fieldType === 'checkbox') {
                        const checked = item.value === '1' ? 'checked' : '';
                        inputHtml = `
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" 
                                    id="${inputId}" ${checked}>
                                <label class="form-check-label" for="${inputId}"></label>
                            </div>
                        `;
                    } else if (fieldType === 'password') {
                        const isEncrypted = item.value === '••••••••';
                        inputHtml = `
                            <div class="input-group">
                                <input type="password" class="form-control" id="${inputId}" 
                                    value="" placeholder="${isEncrypted ? 'Пароль сохранен (введите новый для изменения)' : 'Введите пароль'}"
                                    autocomplete="new-password"
                                    data-original-value="${item.value}">
                                <button class="btn btn-outline-secondary" type="button" 
                                        onclick="togglePassword('${inputId}')">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            ${isEncrypted ? '<small class="text-muted">Оставьте поле пустым, чтобы сохранить текущий пароль</small>' : ''}
                        `;
                    } else {
                        inputHtml = `
                            <input type="text" class="form-control" id="${inputId}" 
                                value="${escapeHtml(item.value)}" placeholder="${item.caption}">
                        `;
                    }

                    html += `
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="${inputId}" class="form-label fw-bold">
                                    ${item.caption}
                                    ${item.desc ? `<small class="text-muted d-block mt-1">${item.desc}</small>` : ''}
                                </label>
                                ${inputHtml}
                            </div>
                        </div>
                    `;
                });

                html += `
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            }
        });

        html += '</div>';
        content.innerHTML = html;
    }

    function togglePassword(inputId) {
        const input = document.getElementById(inputId);
        const button = input.parentNode.querySelector('button');
        const icon = button.querySelector('i');
        
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('bi-eye');
            icon.classList.add('bi-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('bi-eye-slash');
            icon.classList.add('bi-eye');
        }
    }

    async function saveConfig() {
        const configElements = document.querySelectorAll('#settings-content [id^="config-"]');
        const configData = [];
        
        configElements.forEach(element => {
            const key = element.id.replace('config-', '');
            let value = '';
            
            if (element.type === 'checkbox') {
                value = element.checked ? '1' : '0';
            } else if (element.type === 'password') {
                const originalValue = element.getAttribute('data-original-value');
                value = element.value || '';
                
                if (value === '' && originalValue === '••••••••') {
                    return;
                }
            } else {
                value = element.value || '';
            }
            
            configData.push({
                key: key,
                value: value
            });
        });
        
        const response = await apiManager.post(`${MODULE_API_BASE}/config`, {
            config: configData
        });
        
        if (response.success) {
            alert('Настройки успешно сохранены!\n' + (response.message || ''));
            const modal = bootstrap.Modal.getInstance(document.getElementById('settingsModal'));
            if (modal) {
                modal.hide();
            }
        } else {
            alert('Ошибка сохранения настроек: ' + response.error);
        }
    }

    async function resetConfig() {
        if (!confirm('Вы уверены, что хотите сбросить все настройки к значениям по умолчанию?\nВсе текущие настройки будут потеряны.')) {
            return;
        }

        const response = await apiManager.post(`${MODULE_API_BASE}/config/reset`, {});
        
        if (response.success) {
            alert('Настройки успешно сброшены к значениям по умолчанию!\n' + (response.message || ''));
            const modal = bootstrap.Modal.getInstance(document.getElementById('settingsModal'));
            if (modal) {
                modal.hide();
                showSettings();
            }
        } else {
            alert('Ошибка сброса настроек: ' + response.error);
        }
    }

    async function updateQuickStats() {
        try {
            const response = await apiManager.get(`${MODULE_API_BASE}/statistics`);
            
            if (response.success) {
                const stats = response.statistics;
                document.getElementById('quick-stats').innerHTML = `
                    <div class="small">
                        <div>Сессий: <strong>${stats.unique_sessions}</strong></div>
                        <div>Сообщений: <strong>${stats.total_messages}</strong></div>
                        <div>Пользователь: <strong>${stats.user_messages}</strong></div>
                        <div>Бот: <strong>${stats.bot_messages}</strong></div>
                        <div>Сегодня: <strong>${stats.today_messages}</strong></div>
                        <div>Активных: <strong>${stats.active_sessions_today}</strong></div>
                        <div>Среднее/сессия: <strong>${stats.avg_messages_per_session}</strong></div>
                    </div>
                `;
            }
        } catch (error) {
            console.error('Error loading statistics:', error);
        }
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    document.addEventListener('DOMContentLoaded', function() {
        loadSessions();
        updateQuickStats();
    });
</script>
@endsection