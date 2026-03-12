<?php
// public/profile.php
session_start();
require_once __DIR__ . '/../api/config/database.php';

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

// Получаем статистику мероприятий
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM events WHERE user_id = ?");
$stmt->execute([$user_id]);
$total_events = $stmt->fetch()['total'];

// Получаем статистику завершенных мероприятий
$stmt = $conn->prepare("SELECT COUNT(*) as completed FROM events WHERE user_id = ? AND status = 'completed'");
$stmt->execute([$user_id]);
$completed_events = $stmt->fetch()['completed'];

// Получаем статистику задач
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_tasks,
        SUM(CASE WHEN is_completed = 1 THEN 1 ELSE 0 END) as completed_tasks
    FROM tasks t
    JOIN events e ON t.event_id = e.event_id
    WHERE e.user_id = ?
");
$stmt->execute([$user_id]);
$task_stats = $stmt->fetch();
$total_tasks = $task_stats['total_tasks'] ?? 0;
$completed_tasks = $task_stats['completed_tasks'] ?? 0;

// Получаем последние 3 мероприятия пользователя
$stmt = $conn->prepare("
    SELECT * FROM events 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 3
");
$stmt->execute([$user_id]);
$recent_events = $stmt->fetchAll();

// ========== ДОБАВЛЯЕМ НЕДОСТАЮЩИЕ ФУНКЦИИ ==========
function formatDate($dateString) {
    if (!$dateString) return 'Дата не указана';
    try {
        return date('d.m.Y', strtotime($dateString));
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

function getStatusColor($status) {
    $colors = [
        'draft' => '#eacaca',
        'planned' => '#cdd3e6',
        'in_progress' => '#f2c6c9',
        'completed' => '#cfe3d8',
        'cancelled' => '#ddd'
    ];
    return $colors[$status] ?? '#eee';
}

// ========== ДОБАВЛЯЕМ ФУНКЦИЮ truncateText ==========
function truncateText($text, $length = 100) {
    if (!$text) return '';
    if (mb_strlen($text) <= $length) return $text;
    return mb_substr($text, 0, $length) . '...';
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Мой профиль - EventDesign</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .profile-container {
            max-width: 900px;
            margin: 40px auto;
            background: #eee;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .profile-header {
            display: flex;
            align-items: center;
            gap: 30px;
            margin-bottom: 40px;
            flex-wrap: wrap;
        }
        
        .profile-avatar {
            width: 100px;
            height: 100px;
            background: #f5a7ff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 42px;
            color: #6f6177;
            font-weight: bold;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .profile-info h1 {
            color: #6f6177;
            font-size: 32px;
            margin-bottom: 5px;
        }
        
        .profile-info p {
            color: #666;
            font-size: 16px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 40px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 16px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .stat-number {
            font-size: 36px;
            font-weight: bold;
            color: #6f6177;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #666;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .stat-progress {
            margin-top: 10px;
            font-size: 14px;
            color: #f5a7ff;
        }
        
        .info-section {
            background: white;
            padding: 30px;
            border-radius: 16px;
            margin-bottom: 30px;
        }
        
        .info-section h2 {
            color: #6f6177;
            margin-bottom: 20px;
            font-size: 22px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .info-item {
            display: flex;
            padding: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .info-item:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: bold;
            color: #6f6177;
            width: 150px;
        }
        
        .info-value {
            color: #333;
            flex: 1;
        }
        
        .recent-events {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .event-card {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 16px;
            cursor: pointer;
            transition: all 0.3s;
            border-left: 4px solid #f5a7ff;
        }
        
        .event-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        
        .event-card h4 {
            color: #6f6177;
            margin-bottom: 10px;
            font-size: 18px;
        }
        
        .event-card p {
            color: #666;
            font-size: 13px;
            margin: 5px 0;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .event-status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 500;
            margin-top: 10px;
        }
        
        .event-photo-mini {
            width: 100%;
            height: 100px;
            object-fit: cover;
            border-radius: 12px;
            margin-bottom: 10px;
        }
        
        .no-photo-mini {
            height: 80px;
            background: #ddd;
            border-radius: 12px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #999;
            font-size: 12px;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }
        
        .action-buttons button {
            flex: 1;
            padding: 15px;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .btn-edit {
            background: #cdd3e6;
            color: #6f6177;
        }
        
        .btn-dashboard {
            background: #f7d0d7;
            color: #6f6177;
        }
        
        .btn-home {
            background: white;
            color: #6f6177;
            border: 1px solid #ddd;
        }
        
        .action-buttons button:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        
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
            padding: 40px;
            border-radius: 20px;
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .modal-content h3 {
            color: #6f6177;
            margin-bottom: 25px;
            font-size: 24px;
            text-align: center;
        }
        
        .modal-content input {
            width: 100%;
            padding: 12px;
            margin: 8px 0 20px;
            border: 1px solid #ddd;
            border-radius: 10px;
            font-size: 15px;
        }
        
        .modal-content input:focus {
            outline: none;
            border-color: #f5a7ff;
            box-shadow: 0 0 0 3px rgba(245,167,255,0.2);
        }
        
        .modal-content label {
            font-weight: bold;
            color: #6f6177;
            display: block;
            margin-bottom: 5px;
        }
        
        .modal-buttons {
            display: flex;
            gap: 15px;
            margin-top: 25px;
        }
        
        .modal-buttons button {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .btn-save {
            background: #f5a7ff;
            color: #6f6177;
        }
        
        .btn-cancel {
            background: white;
            color: #6f6177;
        }
        
        .message {
            display: none;
            padding: 15px;
            border-radius: 10px;
            margin-top: 20px;
            text-align: center;
        }
        
        .message.success {
            background: #cfe3d8;
            color: #2d5a3a;
        }
        
        .message.error {
            background: #eacaca;
            color: #a13e3e;
        }
        
        hr {
            border: none;
            border-top: 2px solid #ddd;
            margin: 30px 0;
        }
        
        .password-hint {
            font-size: 12px;
            color: #999;
            margin-top: -15px;
            margin-bottom: 15px;
        }
        
        @media (max-width: 768px) {
            .profile-container {
                padding: 20px;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .profile-header {
                flex-direction: column;
                text-align: center;
            }
            
            .info-item {
                flex-direction: column;
                gap: 5px;
            }
            
            .info-label {
                width: 100%;
            }
            
            .action-buttons {
                flex-direction: column;
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
                <a href="profile.php" style="color:#f5a7ff;">Профиль</a>
            </nav>
            <div class="auth-buttons">
                <span style="color:white; margin-right:15px;">
                    <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                </span>
                <button class="btn pink" onclick="logout()">Выход</button>
            </div>
        </div>
    </header>

    <div class="profile-container">
        <!-- Шапка профиля -->
        <div class="profile-header">
            <div class="profile-avatar">
                <?php 
                $initial = strtoupper(mb_substr($user['first_name'], 0, 1) . mb_substr($user['last_name'], 0, 1));
                echo $initial; 
                ?>
            </div>
            <div class="profile-info">
                <h1><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h1>
                <p><?php echo htmlspecialchars($user['email']); ?></p>
                <p style="color:#999; font-size:14px; margin-top:5px;">Участник с <?php echo date('d.m.Y', strtotime($user['created_at'])); ?></p>
            </div>
        </div>

        <!-- Статистика -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_events; ?></div>
                <div class="stat-label">Мероприятий</div>
                <?php if ($total_events > 0): ?>
                    <div class="stat-progress">Завершено: <?php echo $completed_events; ?></div>
                <?php endif; ?>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_tasks; ?></div>
                <div class="stat-label">Всего задач</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $completed_tasks; ?></div>
                <div class="stat-label">Выполнено задач</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">
                    <?php 
                    if ($total_tasks > 0) {
                        echo round(($completed_tasks / $total_tasks) * 100) . '%';
                    } else {
                        echo '0%';
                    }
                    ?>
                </div>
                <div class="stat-label">Прогресс</div>
            </div>
        </div>

        <!-- Информация о пользователе -->
        <div class="info-section">
            <h2>👤 Личная информация</h2>
            <div class="info-item">
                <span class="info-label">Имя:</span>
                <span class="info-value"><?php echo htmlspecialchars($user['first_name']); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Фамилия:</span>
                <span class="info-value"><?php echo htmlspecialchars($user['last_name']); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Email:</span>
                <span class="info-value"><?php echo htmlspecialchars($user['email']); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Телефон:</span>
                <span class="info-value"><?php echo htmlspecialchars($user['phone'] ?? 'Не указан'); ?></span>
            </div>
        </div>

        <!-- Последние мероприятия -->
        <?php if (!empty($recent_events)): ?>
        <div class="info-section">
            <h2>📅 Последние мероприятия</h2>
            <div class="recent-events">
                <?php foreach ($recent_events as $event): ?>
                <div class="event-card" onclick="location.href='event-detail.php?id=<?php echo $event['event_id']; ?>'">
                    <?php if (!empty($event['photo_path'])): ?>
                        <img src="<?php echo htmlspecialchars($event['photo_path']); ?>" class="event-photo-mini" alt="Фото">
                    <?php else: ?>
                        <div class="no-photo-mini">📷 Нет фото</div>
                    <?php endif; ?>
                    <h4><?php echo htmlspecialchars(truncateText($event['title'], 25)); ?></h4>
                    <p>📅 <?php echo formatDate($event['event_date']); ?></p>
                    <p>📍 <?php echo htmlspecialchars(truncateText($event['location'] ?? 'Место не указано', 20)); ?></p>
                    <span class="event-status" style="background: <?php echo getStatusColor($event['status']); ?>">
                        <?php echo getStatusText($event['status']); ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Кнопки действий -->
        <div class="action-buttons">
            <button class="btn-edit" onclick="showEditModal()">✏️ Редактировать профиль</button>
            <button class="btn-dashboard" onclick="location.href='dashboard.php'">📅 Мои мероприятия</button>
            <button class="btn-home" onclick="location.href='index.php'">🏠 На главную</button>
        </div>
    </div>

    <!-- Модальное окно редактирования профиля -->
    <div class="modal" id="editModal">
        <div class="modal-content">
            <h3>✏️ Редактирование профиля</h3>
            
            <form id="editForm" onsubmit="return false;">
                <label for="edit_first_name">Имя *</label>
                <input type="text" id="edit_first_name" value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                
                <label for="edit_last_name">Фамилия *</label>
                <input type="text" id="edit_last_name" value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                
                <label for="edit_email">Email *</label>
                <input type="email" id="edit_email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                
                <label for="edit_phone">Телефон</label>
                <input type="text" id="edit_phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" placeholder="+7 (999) 123-45-67">
                
                <hr>
                
                <h4 style="color:#6f6177; margin-bottom:15px;">🔐 Изменение пароля</h4>
                <p class="password-hint">Заполните только если хотите сменить пароль</p>
                
                <label for="current_password">Текущий пароль</label>
                <input type="password" id="current_password" placeholder="Введите текущий пароль">
                
                <label for="new_password">Новый пароль</label>
                <input type="password" id="new_password" placeholder="Минимум 6 символов">
                
                <label for="confirm_password">Подтверждение пароля</label>
                <input type="password" id="confirm_password" placeholder="Повторите новый пароль">
                
                <div class="modal-buttons">
                    <button type="button" class="btn-cancel" onclick="closeEditModal()">Отмена</button>
                    <button type="submit" class="btn-save" id="saveProfileBtn">Сохранить</button>
                </div>
            </form>
            
            <div id="profileMessage" class="message"></div>
        </div>
    </div>

    <script>
        function showEditModal() {
            document.getElementById('editModal').classList.add('show');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.remove('show');
            document.getElementById('current_password').value = '';
            document.getElementById('new_password').value = '';
            document.getElementById('confirm_password').value = '';
            document.getElementById('profileMessage').style.display = 'none';
        }

        document.getElementById('editForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const btn = document.getElementById('saveProfileBtn');
            const messageDiv = document.getElementById('profileMessage');
            
            btn.textContent = 'Сохранение...';
            btn.disabled = true;
            messageDiv.style.display = 'none';
            
            const formData = new FormData();
            formData.append('first_name', document.getElementById('edit_first_name').value);
            formData.append('last_name', document.getElementById('edit_last_name').value);
            formData.append('email', document.getElementById('edit_email').value);
            formData.append('phone', document.getElementById('edit_phone').value);
            formData.append('current_password', document.getElementById('current_password').value);
            formData.append('new_password', document.getElementById('new_password').value);
            formData.append('confirm_password', document.getElementById('confirm_password').value);
            
            try {
                const response = await fetch('update-profile-handler.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    messageDiv.className = 'message success';
                    messageDiv.textContent = '✅ ' + (data.message || 'Профиль успешно обновлен!');
                    messageDiv.style.display = 'block';
                    
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    messageDiv.className = 'message error';
                    messageDiv.textContent = '❌ ' + (data.message || 'Ошибка при обновлении');
                    messageDiv.style.display = 'block';
                    btn.textContent = 'Сохранить';
                    btn.disabled = false;
                }
            } catch (error) {
                console.error('Error:', error);
                messageDiv.className = 'message error';
                messageDiv.textContent = '❌ Ошибка соединения с сервером';
                messageDiv.style.display = 'block';
                btn.textContent = 'Сохранить';
                btn.disabled = false;
            }
        });

        function logout() {
            if (confirm('Выйти из аккаунта?')) {
                window.location.href = 'logout.php';
            }
        }

        window.onclick = function(event) {
            const modal = document.getElementById('editModal');
            if (event.target === modal) {
                closeEditModal();
            }
        }
    </script>
</body>
</html>