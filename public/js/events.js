// public/js/events.js
document.addEventListener('DOMContentLoaded', async () => {
    console.log('Events.js loaded');
    
    const user = AuthAPI.getCurrentUser();
    console.log('Current user:', user);
    
    if (!user) {
        console.log('No user found, redirecting to login');
        window.location.href = 'login.php';
        return;
    }

    // Загрузка мероприятий
    await loadEvents();

    // Обработчики фильтров
    const filterSelect = document.getElementById('filterStatus');
    const sortSelect = document.getElementById('sortBy');
    
    if (filterSelect) {
        filterSelect.addEventListener('change', loadEvents);
    }
    
    if (sortSelect) {
        sortSelect.addEventListener('change', loadEvents);
    }
});

async function loadEvents() {
    console.log('Loading events...');
    
    try {
        const filter = document.getElementById('filterStatus')?.value || 'all';
        const sort = document.getElementById('sortBy')?.value || 'date_desc';
        
        console.log(`Fetching events with filter: ${filter}, sort: ${sort}`);
        
        // Показываем индикатор загрузки
        showLoading(true);
        
        const response = await EventsAPI.getAll(filter, sort);
        console.log('Events loaded:', response);
        
        // Проверяем структуру ответа
        const events = response.data || response;
        console.log('Events to display:', events);
        
        displayEvents(events);
        showNextEvent(events);
        
    } catch (error) {
        console.error('Error loading events:', error);
        showError('Ошибка загрузки мероприятий: ' + (error.message || 'Неизвестная ошибка'));
    } finally {
        showLoading(false);
    }
}

function displayEvents(events) {
    console.log('Displaying events:', events);
    
    const container = document.getElementById('eventsContainer');
    if (!container) {
        console.error('Events container not found!');
        return;
    }

    if (!events || events.length === 0) {
        console.log('No events to display');
        container.innerHTML = `
            <div class="no-events" style="grid-column:1/-1; text-align:center; padding:50px; background:#eee; border-radius:16px;">
                <p style="margin-bottom:20px;">У вас пока нет мероприятий</p>
                <button class="btn create" onclick="location.href='event-create.php'">
                    Создать первое мероприятие
                </button>
            </div>
        `;
        return;
    }

    container.innerHTML = events.map(event => {
        console.log('Processing event:', event);
        return `
        <div class="event-card" onclick="location.href='event-detail.php?id=${event.id}'">
            <div class="event-card-header">
                <h3>${escapeHtml(event.title || 'Без названия')}</h3>
                <span class="status-badge status-${event.status || 'draft'}">
                    ${getStatusText(event.status)}
                </span>
            </div>
            <p>📅 ${formatDate(event.event_date)}</p>
            <p>📍 ${escapeHtml(event.location || 'Место не указано')}</p>
            <p>💰 ${event.budget ? Number(event.budget).toLocaleString() + ' ₽' : 'Бюджет не указан'}</p>
            <p class="desc">${escapeHtml(event.description || '')}</p>
            <div class="actions" onclick="event.stopPropagation()">
                <button class="btn edit" onclick="location.href='event-edit.php?id=${event.id}'">
                    ✏️ Редактировать
                </button>
                <button class="btn delete" onclick="deleteEvent(${event.id})">
                    🗑️ Удалить
                </button>
                <button class="btn report" onclick="location.href='report.php?id=${event.id}'">
                    📊 Отчет
                </button>
            </div>
        </div>
    `}).join('');
}

function showNextEvent(events) {
    console.log('Showing next event from:', events);
    
    const card = document.getElementById('nextEventCard');
    const content = document.getElementById('nextEventContent');
    
    if (!card || !content) {
        console.log('Next event elements not found');
        return;
    }

    const now = new Date();
    const futureEvents = events
        .filter(e => e.event_date && new Date(e.event_date) > now)
        .sort((a, b) => new Date(a.event_date) - new Date(b.event_date));

    if (futureEvents.length > 0) {
        const next = futureEvents[0];
        console.log('Next event:', next);
        
        card.style.display = 'block';
        content.innerHTML = `
            <div>
                <h2>${escapeHtml(next.title)}</h2>
                <p>📅 ${formatDate(next.event_date)}</p>
                <p>📍 ${escapeHtml(next.location || 'Место не указано')}</p>
            </div>
            <button class="btn pink" onclick="location.href='event-detail.php?id=${next.id}'">
                Открыть
            </button>
        `;
    } else {
        console.log('No future events');
        card.style.display = 'none';
    }
}

async function deleteEvent(id) {
    if (!confirm('Вы уверены, что хотите удалить мероприятие?')) return;
    
    try {
        await EventsAPI.delete(id);
        await loadEvents();
        showSuccess('Мероприятие удалено');
    } catch (error) {
        console.error('Error deleting event:', error);
        showError('Ошибка при удалении');
    }
}

function formatDate(dateString) {
    if (!dateString) return 'Дата не указана';
    
    try {
        return new Date(dateString).toLocaleDateString('ru-RU', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
    } catch (e) {
        console.error('Error formatting date:', e);
        return dateString;
    }
}

function getStatusText(status) {
    const statuses = { 
        'draft': 'Черновик', 
        'planned': 'Запланировано', 
        'in_progress': 'В работе', 
        'completed': 'Завершено',
        'cancelled': 'Отменено'
    };
    return statuses[status] || status || 'Черновик';
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function showLoading(show) {
    const container = document.getElementById('eventsContainer');
    if (!container) return;
    
    if (show) {
        container.style.opacity = '0.5';
        container.style.pointerEvents = 'none';
    } else {
        container.style.opacity = '1';
        container.style.pointerEvents = 'auto';
    }
}

function showError(message) {
    console.error(message);
    alert(message);
}

function showSuccess(message) {
    console.log(message);
    alert(message);
}

function logout() { 
    AuthAPI.logout(); 
}