@extends('ai_bot::manager.layout')

@section('title', 'Управление чатами')

@section('content')
<div id="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Управление AI чатами</h2>
        <div class="btn-group">
            <button class="btn btn-outline-secondary" onclick="loadSessions()">
                <i class="bi bi-arrow-clockwise"></i> Обновить
            </button>
        </div>
    </div>

    <!-- Сессии чатов -->
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
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody id="sessions-table">
                            <tr>
                                <td colspan="5" class="text-center">
                                    <div class="spinner-border" role="status">
                                        <span class="visually-hidden">Loading...</span>
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

    <!-- Детали сессии (модальное окно) -->
    <div class="modal fade" id="sessionDetailModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Детали сессии</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="session-detail-content">
                        <div class="text-center">
                            <div class="spinner-border" role="status">
                                <span class="visually-hidden">Loading...</span>
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
</div>
@endsection

@section('scripts')
<script>
// Базовый URL для API модуля
const MODULE_API_BASE = '{{ route("ai-bot.manager.index") }}'.replace(/\/$/, '') + '/api';
</script>
<script>
let currentSessionId = null;
let currentPage = 1;

// Загрузка сессий
async function loadSessions(page = 1) {
    try {
        showLoading();
        const response = await apiManager.get(`${MODULE_API_BASE}/sessions?page=${page}`);
        
        console.log('API Response:', response);
        
        if (response && response.success) {
            renderSessionsTable(response.sessions || []);
            
            if (response.pagination) {
                renderPagination(response.pagination);
            }
        } else {
            showError('Ошибка загрузки сессий: ' + (response?.error || 'Неизвестная ошибка'));
        }
    } catch (error) {
        console.error('Error loading sessions:', error);
        showError('Ошибка загрузки: ' + error.message);
    }
}

function showLoading() {
    document.getElementById('sessions-table').innerHTML = `
        <tr><td colspan="5" class="text-center"><div class="spinner-border"></div></td></tr>
    `;
}

function showError(message) {
    document.getElementById('sessions-table').innerHTML = `
        <tr><td colspan="5" class="text-center text-danger">${message}</td></tr>
    `;
}

// Рендер таблицы сессий
function renderSessionsTable(sessions) {
    const tbody = document.getElementById('sessions-table');
    
    if (sessions.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center">Сессии не найдены</td></tr>';
        return;
    }

    tbody.innerHTML = sessions.map(session => `
        <tr class="session-item" onclick="viewSessionDetail('${session.session_id}')">
            <td><code>${session.session_id}</code></td>
            <td><span class="badge bg-primary">${session.chats_count}</span></td>
            <td>${session.latest_chat ? session.latest_chat.timestamp : 'Нет сообщений'}</td>
            <td>${new Date(session.created_at).toLocaleString()}</td>
            <td>
                <button class="btn btn-sm btn-outline-danger" onclick="event.stopPropagation(); deleteSession('${session.session_id}')">
                    <i class="bi bi-trash"></i>
                </button>
            </td>
        </tr>
    `).join('');
}

// Пагинация
function renderPagination(pagination) {
    const paginationEl = document.getElementById('sessions-pagination');
    
    // Дополнительные проверки
    if (!pagination || !pagination.last_page || pagination.last_page <= 1) {
        paginationEl.innerHTML = '';
        return;
    }

    let html = '';
    const currentPage = pagination.current_page || 1;
    const lastPage = pagination.last_page || 1;

    // Previous button
    if (currentPage > 1) {
        html += `<li class="page-item"><a class="page-link" href="#" onclick="loadSessions(${currentPage - 1}); return false;">&laquo;</a></li>`;
    }

    // Page numbers
    for (let i = 1; i <= lastPage; i++) {
        html += `<li class="page-item ${i === currentPage ? 'active' : ''}"><a class="page-link" href="#" onclick="loadSessions(${i}); return false;">${i}</a></li>`;
    }

    // Next button
    if (currentPage < lastPage) {
        html += `<li class="page-item"><a class="page-link" href="#" onclick="loadSessions(${currentPage + 1}); return false;">&raquo;</a></li>`;
    }

    paginationEl.innerHTML = html;
}

// Просмотр деталей сессии
async function viewSessionDetail(sessionId) {
    currentSessionId = sessionId;
    const modal = new bootstrap.Modal(document.getElementById('sessionDetailModal'));
    
    try {
        const response = await apiManager.get(`${MODULE_API_BASE}/sessions/${sessionId}`);
        
        if (response.success) {
            renderSessionDetail(response);
            modal.show();
        } else {
            alert('Ошибка загрузки деталей сессии');
        }
    } catch (error) {
        console.error('Error loading session detail:', error);
        alert('Ошибка загрузки деталей сессии');
    }
}

// Рендер деталей сессии
function renderSessionDetail(data) {
    const content = document.getElementById('session-detail-content');
    
    content.innerHTML = `
        <div class="mb-3">
            <strong>ID сессии:</strong> <code>${data.session.session_id}</code><br>
            <strong>Assistant ID:</strong> <code>${data.session.assistant_id}</code><br>
            <strong>Thread ID:</strong> <code>${data.session.thread_id}</code><br>
            <strong>Создана:</strong> ${new Date(data.session.created_at).toLocaleString()}
        </div>
        
        <h6>История сообщений:</h6>
        <div class="chat-history" style="max-height: 400px; overflow-y: auto;">
            ${data.messages.length > 0 ? data.messages.map(msg => `
                <div class="chat-message ${msg.type === 'user' ? 'user-message' : 'bot-message'} mb-3 p-3 border rounded">
                    <div class="fw-bold">${msg.type === 'user' ? '👤 Пользователь' : '🤖 Бот'}</div>
                    <div class="mt-2">${msg.message}</div>
                    <small class="text-muted mt-1 d-block">${msg.time} (${msg.date})</small>
                </div>
            `).join('') : '<p class="text-center">Сообщений нет</p>'}
        </div>
    `;
}

// Удаление сессии
async function deleteSession(sessionId) {
    if (!confirm('Вы уверены, что хотите удалить эту сессию?')) return;

    try {
        const response = await apiManager.delete(`${MODULE_API_BASE}/sessions/${sessionId}`);
        
        if (response.success) {
            alert('Сессия удалена успешно');
            loadSessions(currentPage);
            updateQuickStats();
        } else {
            alert('Ошибка удаления сессии: ' + response.error);
        }
    } catch (error) {
        console.error('Error deleting session:', error);
        alert('Ошибка удаления сессии: ' + error.message);
    }
}

// Обновление быстрой статистики
async function updateQuickStats() {
    try {
        const response = await apiManager.get(`${MODULE_API_BASE}/statistics`);
        
        if (response.success) {
            document.getElementById('quick-stats').innerHTML = `
                <div class="small">
                    <div>Сессий: <strong>${response.statistics.total_sessions}</strong></div>
                    <div>Сообщений: <strong>${response.statistics.total_messages}</strong></div>
                    <div>Сегодня: <strong>${response.statistics.today_messages}</strong></div>
                    <div>Активных: <strong>${response.statistics.active_sessions_today}</strong></div>
                </div>
            `;
        }
    } catch (error) {
        console.error('Error loading statistics:', error);
    }
}

// Загрузка при старте
document.addEventListener('DOMContentLoaded', function() {
    loadSessions();
    updateQuickStats();
});
</script>
@endsection