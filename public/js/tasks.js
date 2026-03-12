// public/js/tasks.js
async function addTask(eventId) {
    const name = document.getElementById('taskName')?.value;
    const cost = document.getElementById('taskCost')?.value;

    if (!name) {
        alert('Введите название задачи');
        return;
    }

    try {
        const token = localStorage.getItem('token');
        const response = await fetch('api.php?route=tasks/create', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${token}`
            },
            body: JSON.stringify({
                event_id: eventId,
                title: name,
                cost: cost ? parseFloat(cost) : 0,
                is_completed: 0
            })
        });

        const data = await response.json();
        
        if (data.success) {
            document.getElementById('taskName').value = '';
            document.getElementById('taskCost').value = '';
            location.reload();
        } else {
            alert('Ошибка: ' + (data.message || 'Неизвестная ошибка'));
        }
    } catch (error) {
        console.error('Error adding task:', error);
        alert('Ошибка при добавлении задачи');
    }
}

async function toggleTask(taskId, completed) {
    try {
        const token = localStorage.getItem('token');
        const response = await fetch('api.php?route=tasks/update', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${token}`
            },
            body: JSON.stringify({
                id: taskId,
                is_completed: completed ? 1 : 0
            })
        });

        const data = await response.json();
        
        if (data.success) {
            location.reload();
        }
    } catch (error) {
        console.error('Error toggling task:', error);
    }
}

async function deleteTask(taskId) {
    if (!confirm('Удалить задачу?')) return;

    try {
        const token = localStorage.getItem('token');
        const response = await fetch(`api.php?route=tasks/delete&id=${taskId}`, {
            method: 'DELETE',
            headers: {
                'Authorization': `Bearer ${token}`
            }
        });

        const data = await response.json();
        
        if (data.success) {
            location.reload();
        } else {
            alert('Ошибка при удалении');
        }
    } catch (error) {
        console.error('Error deleting task:', error);
        alert('Ошибка при удалении');
    }
}