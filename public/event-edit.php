<?php
// public/event-edit.php
session_start();
require_once __DIR__ . '/../api/config/database.php';
require_once __DIR__ . '/../api/helpers/uuid.php';

$db = Database::getInstance();
$conn = $db->getConnection();

// Проверка авторизации
$user_id = null;

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
} elseif (isset($_COOKIE['auth_token'])) {
    $token = $_COOKIE['auth_token'];
    $stmt = $conn->prepare("SELECT user_id FROM sessions WHERE token = ? AND expires_at > datetime('now')");
    $stmt->execute([$token]);
    $session = $stmt->fetch();
    if ($session) {
        $user_id = $session['user_id'];
        $_SESSION['user_id'] = $user_id;
    }
}

if (!$user_id) {
    header('Location: login.php');
    exit;
}

// Получаем данные пользователя
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Получаем ID мероприятия
$event_id = $_GET['id'] ?? '';
if (empty($event_id)) {
    header('Location: dashboard.php');
    exit;
}

// Получаем данные мероприятия
$stmt = $conn->prepare("SELECT * FROM events WHERE event_id = ? AND user_id = ?");
$stmt->execute([$event_id, $user_id]);
$event = $stmt->fetch();

if (!$event) {
    header('Location: dashboard.php');
    exit;
}

// Получаем задачи мероприятия
$stmt = $conn->prepare("SELECT * FROM tasks WHERE event_id = ? ORDER BY created_at DESC");
$stmt->execute([$event_id]);
$tasks = $stmt->fetchAll();

// Разделяем дату и время для формы
$event_date = date('Y-m-d', strtotime($event['event_date']));
$event_time = date('H:i', strtotime($event['event_date']));

// Подсчет статистики
$total_cost = 0;
$completed_count = 0;
foreach ($tasks as $task) {
    $total_cost += $task['cost'];
    if ($task['is_completed']) {
        $completed_count++;
    }
}

function formatDate($dateString) {
    if (!$dateString) return 'Дата не указана';
    try {
        return date('d.m.Y H:i', strtotime($dateString));
    } catch (Exception $e) {
        return $dateString;
    }
}

function getStatusText($status) {
    $statuses = [
        'draft' => 'Черновик',
        'planned' => 'Запланировано',
        'in_progress' => 'В работе',
        'completed' => 'Завершено',
        'cancelled' => 'Отменено'
    ];
    return $statuses[$status] ?? $status;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Редактирование мероприятия - EventDesign</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .edit-container {
            max-width: 800px;
            margin: 30px auto;
            background: #eee;
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #6f6177;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #f5a7ff;
            box-shadow: 0 0 0 3px rgba(245,167,255,0.2);
        }
        
        .form-row {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .form-row .form-group {
            flex: 1;
            margin-bottom: 0;
        }
        
        /* Стили для загрузки фото */
        .photo-upload {
            border: 2px dashed #ccc;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            background: #f9f9f9;
            margin-bottom: 20px;
        }
        
        .photo-upload:hover {
            border-color: #f5a7ff;
            background: #f0f0f0;
        }
        
        .photo-upload.has-image {
            border-style: solid;
            border-color: #4CAF50;
            background: #e8f5e9;
        }
        
        .photo-preview {
            max-width: 100%;
            max-height: 200px;
            margin-top: 10px;
            border-radius: 8px;
            display: none;
        }
        
        .photo-preview.show {
            display: block;
        }
        
        .photo-icon {
            font-size: 48px;
            color: #999;
            margin-bottom: 10px;
        }
        
        .photo-name {
            color: #666;
            font-size: 14px;
            margin-top: 5px;
            word-break: break-all;
        }
        
        .remove-photo {
            background: #eacaca;
            color: #6f6177;
            border: none;
            padding: 5px 15px;
            border-radius: 20px;
            cursor: pointer;
            font-size: 12px;
            margin-top: 10px;
            display: inline-block;
        }
        
        .remove-photo:hover {
            background: #d4a5a5;
        }
        
        /* Стили для задач */
        .tasks-section {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid #ddd;
        }
        
        .tasks-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .add-task-form {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .add-task-form input {
            flex: 1;
            min-width: 200px;
        }
        
        .tasks-list {
            margin-bottom: 20px;
        }
        
        .task-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px;
            border-bottom: 1px solid #ddd;
            background: white;
            border-radius: 8px;
            margin-bottom: 5px;
        }
        
        .task-item.completed .task-title {
            text-decoration: line-through;
            color: #999;
        }
        
        .task-info {
            display: flex;
            align-items: center;
            gap: 15px;
            flex: 1;
        }
        
        .task-info input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .task-title {
            font-size: 15px;
        }
        
        .task-cost {
            font-weight: bold;
            color: #6f6177;
            min-width: 100px;
            text-align: right;
        }
        
        .task-actions {
            display: flex;
            gap: 5px;
        }
        
        .task-actions button {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 16px;
            padding: 5px 10px;
            border-radius: 4px;
            transition: all 0.3s;
        }
        
        .task-actions .edit-task {
            color: #cdd3e6;
        }
        
        .task-actions .delete-task {
            color: #eacaca;
        }
        
        .task-actions button:hover {
            transform: scale(1.1);
        }
        
        .tasks-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-top: 20px;
            padding: 20px;
            background: rgba(111,97,119,0.1);
            border-radius: 12px;
        }
        
        .stat-card {
            text-align: center;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #6f6177;
        }
        
        .stat-label {
            font-size: 12px;
            color: #666;
        }
        
        .total-cost {
            font-size: 18px;
            font-weight: bold;
            text-align: right;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 2px solid #ddd;
        }
        
        .btn-group {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }
        
        .btn-group button {
            flex: 1;
            padding: 14px;
            font-size: 16px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-group .save {
            background: #f7d0d7;
            color: #6f6177;
        }
        
        .btn-group .save:hover {
            background: #f0c0c8;
            transform: translateY(-2px);
        }
        
        .btn-group .cancel {
            background: white;
            color: #6f6177;
        }
        
        .btn-group .cancel:hover {
            background: #f0f0f0;
            transform: translateY(-2px);
        }
        
        .message {
            display: none;
            padding: 12px;
            border-radius: 8px;
            margin-top: 20px;
            text-align: center;
        }
        
        .message.error {
            background: #eacaca;
            color: #a13e3e;
        }
        
        .message.success {
            background: #cfe3d8;
            color: #2d5a3a;
        }
        
        .loading {
            opacity: 0.7;
            pointer-events: none;
        }
        
        .progress-bar {
            width: 100%;
            height: 4px;
            background: #ddd;
            border-radius: 2px;
            margin-top: 10px;
            display: none;
            overflow: hidden;
        }
        
        .progress-bar .fill {
            height: 100%;
            background: #f5a7ff;
            width: 0%;
            transition: width 0.3s;
        }
        
        .photo-hint {
            font-size: 12px;
            color: #999;
            margin-top: 5px;
        }
        
        .delete-section {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid #ddd;
            text-align: center;
        }
        
        .delete-btn {
            background: #eacaca;
            color: #6f6177;
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 15px;
            transition: all 0.3s;
        }
        
        .delete-btn:hover {
            background: #d4a5a5;
            transform: translateY(-2px);
        }
        
        /* Модальное окно подтверждения */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        
        .modal.show {
            display: flex;
        }
        
        .modal-content {
            background: #eee;
            padding: 30px;
            border-radius: 16px;
            max-width: 400px;
            width: 90%;
            text-align: center;
        }
        
        .modal-content h3 {
            color: #6f6177;
            margin-bottom: 15px;
        }
        
        .modal-content p {
            margin: 20px 0;
            color: #333;
        }
        
        .modal-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
        }
        
        .modal-buttons button {
            padding: 10px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .modal-buttons .confirm {
            background: #eacaca;
            color: #6f6177;
        }
        
        .modal-buttons .cancel {
            background: white;
            color: #6f6177;
        }
        
        @media (max-width: 768px) {
            .edit-container {
                padding: 20px;
            }
            
            .form-row {
                flex-direction: column;
            }
            
            .add-task-form {
                flex-direction: column;
            }
            
            .task-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .task-info {
                width: 100%;
            }
            
            .task-actions {
                width: 100%;
                justify-content: flex-end;
            }
            
            .tasks-stats {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body class="bg-city">
    <header class="header">
        <div class="container nav">
            <div class="logo">Event<span>Design</span></div>
            <nav class="menu">
                <a href="index.php">Главная</a>
                <a href="dashboard.php">Мои мероприятия</a>
                <a href="profile.php">Профиль</a>
            </nav>
            <div class="auth-buttons">
                <span style="color:white; margin-right:15px;">
                    <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                </span>
                <button class="btn pink" onclick="logout()">Выход</button>
            </div>
        </div>
    </header>

    <div class="edit-container">
        <h1 style="margin-bottom:30px; color:#6f6177; text-align:center;">Редактирование мероприятия</h1>

        <form id="editForm" method="POST" enctype="multipart/form-data" onsubmit="return false;">
            <input type="hidden" id="event_id" value="<?php echo $event_id; ?>">
            
            <!-- Область загрузки фото -->
            <div class="photo-upload" id="photoUpload" onclick="document.getElementById('photoInput').click()">
                <div class="photo-icon">📷</div>
                <p>Нажмите чтобы изменить фото мероприятия</p>
                <p class="photo-hint">Поддерживаются JPG, PNG, GIF (до 5 МБ)</p>
                <input type="file" id="photoInput" name="photo" accept="image/jpeg,image/png,image/gif" style="display:none;">
                <div class="photo-name" id="photoName">
                    <?php if (!empty($event['photo_name'])): ?>
                        <?php echo htmlspecialchars($event['photo_name']); ?>
                    <?php endif; ?>
                </div>
                <?php if (!empty($event['photo_path'])): ?>
                    <img class="photo-preview show" id="photoPreview" src="<?php echo htmlspecialchars($event['photo_path']); ?>" alt="Preview">
                <?php else: ?>
                    <img class="photo-preview" id="photoPreview" src="#" alt="Preview">
                <?php endif; ?>
                <div>
                    <button type="button" class="remove-photo" id="removePhoto" <?php echo empty($event['photo_path']) ? 'style="display:none;"' : ''; ?> onclick="removePhoto(event)">✕ Удалить фото</button>
                </div>
            </div>

            <div class="form-group">
                <label for="title">Название мероприятия *</label>
                <input type="text" id="title" name="title" required 
                       value="<?php echo htmlspecialchars($event['title']); ?>">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="date">Дата *</label>
                    <input type="date" id="date" name="date" required 
                           value="<?php echo $event_date; ?>">
                </div>
                <div class="form-group">
                    <label for="time">Время</label>
                    <input type="time" id="time" name="time" 
                           value="<?php echo $event_time; ?>">
                </div>
            </div>

            <div class="form-group">
                <label for="location">Место проведения</label>
                <input type="text" id="location" name="location" 
                       value="<?php echo htmlspecialchars($event['location'] ?? ''); ?>">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="budget">Бюджет (₽)</label>
                    <input type="number" id="budget" name="budget" min="0" step="1000"
                           value="<?php echo $event['budget'] ?? ''; ?>">
                </div>
                <div class="form-group">
                    <label for="status">Статус</label>
                    <select id="status" name="status">
                        <option value="draft" <?php echo $event['status'] == 'draft' ? 'selected' : ''; ?>>Черновик</option>
                        <option value="planned" <?php echo $event['status'] == 'planned' ? 'selected' : ''; ?>>Запланировано</option>
                        <option value="in_progress" <?php echo $event['status'] == 'in_progress' ? 'selected' : ''; ?>>В работе</option>
                        <option value="completed" <?php echo $event['status'] == 'completed' ? 'selected' : ''; ?>>Завершено</option>
                        <option value="cancelled" <?php echo $event['status'] == 'cancelled' ? 'selected' : ''; ?>>Отменено</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="description">Описание</label>
                <textarea id="description" name="description" rows="4"><?php echo htmlspecialchars($event['description'] ?? ''); ?></textarea>
            </div>

            <div class="btn-group">
                <button type="submit" class="save" id="saveBtn">Сохранить изменения</button>
                <button type="button" class="cancel" onclick="goToDashboard()">Отмена</button>
            </div>

            <div id="message" class="message"></div>
        </form>

        <!-- Секция задач -->
        <div class="tasks-section">
            <div class="tasks-header">
                <h2 style="color:#6f6177;">📋 Задачи и расходы</h2>
                <span class="status-badge status-planned">Всего: <?php echo count($tasks); ?></span>
            </div>

            <!-- Форма добавления задачи -->
            <div class="add-task-form">
                <input type="text" id="taskTitle" class="input" placeholder="Название задачи" required>
                <input type="number" id="taskCost" class="input" placeholder="Стоимость" min="0" step="100">
                <button class="btn add" id="addTaskBtn">➕ Добавить задачу</button>
            </div>

            <!-- Список задач -->
            <div class="tasks-list" id="tasksList">
                <?php if (empty($tasks)): ?>
                    <p style="text-align:center; color:#999; padding:20px;">Нет задач. Добавьте первую задачу!</p>
                <?php else: ?>
                    <?php foreach ($tasks as $task): ?>
                    <div class="task-item <?php echo $task['is_completed'] ? 'completed' : ''; ?>" id="task-<?php echo $task['task_id']; ?>">
                        <div class="task-info">
                            <input type="checkbox" <?php echo $task['is_completed'] ? 'checked' : ''; ?> 
                                   onchange="toggleTask('<?php echo $task['task_id']; ?>', this.checked)">
                            <span class="task-title"><?php echo htmlspecialchars($task['title']); ?></span>
                        </div>
                        <div class="task-cost"><?php echo number_format($task['cost'], 0, ',', ' '); ?> ₽</div>
                        <div class="task-actions">
                            <button class="edit-task" onclick="editTask('<?php echo $task['task_id']; ?>')" title="Редактировать">✏️</button>
                            <button class="delete-task" onclick="deleteTask('<?php echo $task['task_id']; ?>')" title="Удалить">🗑️</button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Статистика задач -->
            <div class="tasks-stats">
                <div class="stat-card">
                    <div class="stat-value"><?php echo count($tasks); ?></div>
                    <div class="stat-label">Всего задач</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" style="color:#4CAF50;"><?php echo $completed_count; ?></div>
                    <div class="stat-label">Выполнено</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" style="color:#f5a7ff;"><?php echo count($tasks) - $completed_count; ?></div>
                    <div class="stat-label">Осталось</div>
                </div>
            </div>

            <div class="total-cost">
                Общая сумма расходов: <?php echo number_format($total_cost, 0, ',', ' '); ?> ₽
            </div>
        </div>

        <!-- Кнопка удаления мероприятия -->
        <div class="delete-section">
            <button class="delete-btn" onclick="showDeleteModal()">🗑️ Удалить мероприятие</button>
        </div>
    </div>

    <!-- Модальное окно подтверждения отмены -->
    <div class="modal" id="cancelModal">
        <div class="modal-content">
            <h3>⚠️ Несохраненные изменения</h3>
            <p>У вас есть несохраненные изменения. Вы действительно хотите выйти?</p>
            <div class="modal-buttons">
                <button class="cancel" onclick="hideCancelModal()">Продолжить редактирование</button>
                <button class="confirm" onclick="forceGoToDashboard()">Выйти без сохранения</button>
            </div>
        </div>
    </div>

    <!-- Модальное окно подтверждения удаления -->
    <div class="modal" id="deleteModal">
        <div class="modal-content">
            <h3>🗑️ Подтверждение удаления</h3>
            <p>Вы уверены, что хотите удалить это мероприятие? Все задачи также будут удалены.</p>
            <div class="modal-buttons">
                <button class="cancel" onclick="hideDeleteModal()">Отмена</button>
                <button class="confirm" onclick="deleteEvent()">Удалить</button>
            </div>
        </div>
    </div>

    <!-- Модальное окно редактирования задачи -->
    <div class="modal" id="editTaskModal">
        <div class="modal-content">
            <h3>✏️ Редактирование задачи</h3>
            <input type="hidden" id="editTaskId">
            <div style="margin:20px 0;">
                <input type="text" id="editTaskTitle" class="input" placeholder="Название задачи" style="margin-bottom:10px;">
                <input type="number" id="editTaskCost" class="input" placeholder="Стоимость" min="0" step="100">
            </div>
            <div class="modal-buttons">
                <button class="cancel" onclick="hideEditTaskModal()">Отмена</button>
                <button class="confirm" onclick="updateTask()">Сохранить</button>
            </div>
        </div>
    </div>

    <script>
        // Сохраняем оригинальные данные для проверки изменений
        const originalData = {
            title: '<?php echo addslashes($event['title']); ?>',
            date: '<?php echo $event_date; ?>',
            time: '<?php echo $event_time; ?>',
            location: '<?php echo addslashes($event['location'] ?? ''); ?>',
            budget: '<?php echo $event['budget'] ?? ''; ?>',
            status: '<?php echo $event['status']; ?>',
            description: '<?php echo addslashes($event['description'] ?? ''); ?>'
        };

        const eventId = '<?php echo $event_id; ?>';

        // Функция проверки изменений
        function hasChanges() {
            const currentData = {
                title: document.getElementById('title').value,
                date: document.getElementById('date').value,
                time: document.getElementById('time').value,
                location: document.getElementById('location').value,
                budget: document.getElementById('budget').value,
                status: document.getElementById('status').value,
                description: document.getElementById('description').value
            };
            
            return JSON.stringify(currentData) !== JSON.stringify(originalData);
        }

        // Предпросмотр фото
        document.getElementById('photoInput').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                if (file.size > 5 * 1024 * 1024) {
                    showMessage('Файл слишком большой. Максимальный размер 5 МБ', 'error');
                    this.value = '';
                    return;
                }
                
                if (!file.type.match('image.*')) {
                    showMessage('Пожалуйста, выберите изображение', 'error');
                    this.value = '';
                    return;
                }
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById('photoPreview');
                    preview.src = e.target.result;
                    preview.classList.add('show');
                    
                    document.getElementById('photoName').textContent = file.name;
                    document.getElementById('photoUpload').classList.add('has-image');
                    document.getElementById('removePhoto').style.display = 'inline-block';
                }
                reader.readAsDataURL(file);
            }
        });

        // Удаление фото
        function removePhoto(event) {
            event.stopPropagation();
            document.getElementById('photoInput').value = '';
            document.getElementById('photoPreview').classList.remove('show');
            document.getElementById('photoPreview').src = '#';
            document.getElementById('photoName').textContent = '';
            document.getElementById('photoUpload').classList.remove('has-image');
            document.getElementById('removePhoto').style.display = 'none';
            
            // Отмечаем что фото было удалено
            const deletePhotoInput = document.createElement('input');
            deletePhotoInput.type = 'hidden';
            deletePhotoInput.name = 'delete_photo';
            deletePhotoInput.value = '1';
            document.getElementById('editForm').appendChild(deletePhotoInput);
        }

        // Обработка отправки формы
        document.getElementById('editForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const btn = document.getElementById('saveBtn');
            const messageDiv = document.getElementById('message');
            const form = this;
            
            const title = document.getElementById('title').value.trim();
            const date = document.getElementById('date').value;
            
            if (!title) {
                showMessage('Введите название мероприятия', 'error');
                return;
            }
            
            if (!date) {
                showMessage('Выберите дату мероприятия', 'error');
                return;
            }
            
            const time = document.getElementById('time').value || '18:00';
            const event_date = `${date} ${time}:00`;
            
            btn.textContent = 'Сохранение...';
            btn.disabled = true;
            form.classList.add('loading');
            messageDiv.style.display = 'none';
            
            const formData = new FormData();
            formData.append('id', eventId);
            formData.append('title', title);
            formData.append('event_date', event_date);
            formData.append('location', document.getElementById('location').value.trim());
            formData.append('budget', document.getElementById('budget').value || '0');
            formData.append('status', document.getElementById('status').value);
            formData.append('description', document.getElementById('description').value.trim());
            
            const photoFile = document.getElementById('photoInput').files[0];
            if (photoFile) {
                formData.append('photo', photoFile);
            }
            
            if (document.querySelector('input[name="delete_photo"]')) {
                formData.append('delete_photo', '1');
            }
            
            try {
                const response = await fetch('update-event-handler.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showMessage('Мероприятие успешно обновлено!', 'success');
                    setTimeout(() => {
                        window.location.href = 'event-detail.php?id=' + eventId;
                    }, 1000);
                } else {
                    showMessage(data.message || 'Ошибка при обновлении мероприятия', 'error');
                    btn.textContent = 'Сохранить изменения';
                    btn.disabled = false;
                    form.classList.remove('loading');
                }
            } catch (error) {
                console.error('Error:', error);
                showMessage('Ошибка соединения с сервером', 'error');
                btn.textContent = 'Сохранить изменения';
                btn.disabled = false;
                form.classList.remove('loading');
            }
        });

        // ========== ФУНКЦИИ ДЛЯ РАБОТЫ С ЗАДАЧАМИ ==========

        // Добавление задачи
        document.getElementById('addTaskBtn').addEventListener('click', addTask);

        async function addTask() {
            const title = document.getElementById('taskTitle').value.trim();
            const cost = document.getElementById('taskCost').value || '0';

            if (!title) {
                showMessage('Введите название задачи', 'error');
                return;
            }

            try {
                const formData = new FormData();
                formData.append('event_id', eventId);
                formData.append('title', title);
                formData.append('cost', cost);

                const response = await fetch('create-task-handler.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    document.getElementById('taskTitle').value = '';
                    document.getElementById('taskCost').value = '';
                    showMessage('Задача добавлена', 'success');
                    setTimeout(() => {
                        window.location.reload();
                    }, 500);
                } else {
                    showMessage(data.message || 'Ошибка при добавлении задачи', 'error');
                }
            } catch (error) {
                console.error('Error adding task:', error);
                showMessage('Ошибка соединения с сервером', 'error');
            }
        }

        // Переключение статуса задачи
        async function toggleTask(taskId, completed) {
            try {
                const formData = new FormData();
                formData.append('id', taskId);
                formData.append('is_completed', completed ? '1' : '0');

                const response = await fetch('update-task-handler.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    const taskElement = document.getElementById(`task-${taskId}`);
                    if (completed) {
                        taskElement.classList.add('completed');
                    } else {
                        taskElement.classList.remove('completed');
                    }
                    // Обновляем статистику
                    setTimeout(() => {
                        window.location.reload();
                    }, 300);
                }
            } catch (error) {
                console.error('Error toggling task:', error);
            }
        }

        // Редактирование задачи
        function editTask(taskId) {
            const taskElement = document.getElementById(`task-${taskId}`);
            const title = taskElement.querySelector('.task-title').textContent;
            const costText = taskElement.querySelector('.task-cost').textContent;
            const cost = parseInt(costText.replace(/[^\d]/g, ''));

            document.getElementById('editTaskId').value = taskId;
            document.getElementById('editTaskTitle').value = title;
            document.getElementById('editTaskCost').value = cost;

            document.getElementById('editTaskModal').classList.add('show');
        }

        async function updateTask() {
            const taskId = document.getElementById('editTaskId').value;
            const title = document.getElementById('editTaskTitle').value.trim();
            const cost = document.getElementById('editTaskCost').value || '0';

            if (!title) {
                showMessage('Введите название задачи', 'error');
                return;
            }

            try {
                const formData = new FormData();
                formData.append('id', taskId);
                formData.append('title', title);
                formData.append('cost', cost);

                const response = await fetch('update-task-handler.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    hideEditTaskModal();
                    showMessage('Задача обновлена', 'success');
                    setTimeout(() => {
                        window.location.reload();
                    }, 500);
                } else {
                    showMessage(data.message || 'Ошибка при обновлении задачи', 'error');
                }
            } catch (error) {
                console.error('Error updating task:', error);
                showMessage('Ошибка соединения с сервером', 'error');
            }
        }

        function hideEditTaskModal() {
            document.getElementById('editTaskModal').classList.remove('show');
        }

        // Удаление задачи
        async function deleteTask(taskId) {
            if (!confirm('Удалить эту задачу?')) return;

            try {
                const formData = new FormData();
                formData.append('id', taskId);

                const response = await fetch('delete-task-handler.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    showMessage('Задача удалена', 'success');
                    setTimeout(() => {
                        window.location.reload();
                    }, 500);
                } else {
                    showMessage(data.message || 'Ошибка при удалении задачи', 'error');
                }
            } catch (error) {
                console.error('Error deleting task:', error);
                showMessage('Ошибка соединения с сервером', 'error');
            }
        }

        // ========== ФУНКЦИИ ДЛЯ НАВИГАЦИИ ==========

        function goToDashboard() {
            if (hasChanges()) {
                document.getElementById('cancelModal').classList.add('show');
            } else {
                window.location.href = 'dashboard.php';
            }
        }

        function forceGoToDashboard() {
            window.location.href = 'dashboard.php';
        }

        function hideCancelModal() {
            document.getElementById('cancelModal').classList.remove('show');
        }

        function showDeleteModal() {
            document.getElementById('deleteModal').classList.add('show');
        }

        function hideDeleteModal() {
            document.getElementById('deleteModal').classList.remove('show');
        }

        async function deleteEvent() {
            hideDeleteModal();
            
            try {
                const formData = new FormData();
                formData.append('id', eventId);
                
                const response = await fetch('delete-event.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    alert('Мероприятие удалено');
                    window.location.href = 'dashboard.php';
                } else {
                    alert('Ошибка при удалении: ' + (data.message || 'Неизвестная ошибка'));
                }
            } catch (error) {
                console.error('Delete error:', error);
                alert('Ошибка при удалении');
            }
        }

        function showMessage(text, type) {
            const messageDiv = document.getElementById('message');
            messageDiv.textContent = text;
            messageDiv.className = `message ${type}`;
            messageDiv.style.display = 'block';
            
            setTimeout(() => {
                if (type !== 'success') {
                    messageDiv.style.display = 'none';
                }
            }, 3000);
        }

        function logout() {
            if (confirm('Выйти из аккаунта?')) {
                window.location.href = 'logout.php';
            }
        }

        // Закрытие модальных окон при клике вне их
        window.onclick = function(event) {
            const cancelModal = document.getElementById('cancelModal');
            const deleteModal = document.getElementById('deleteModal');
            const editTaskModal = document.getElementById('editTaskModal');
            
            if (event.target === cancelModal) {
                hideCancelModal();
            }
            if (event.target === deleteModal) {
                hideDeleteModal();
            }
            if (event.target === editTaskModal) {
                hideEditTaskModal();
            }
        }

        // Enter в поле задачи
        document.getElementById('taskTitle').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                addTask();
            }
        });
    </script>
</body>
</html>