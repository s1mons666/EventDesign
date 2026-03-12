<?php
// public/event-create.php
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
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Создание мероприятия - EventDesign</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .create-container {
            max-width: 600px;
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
            background: white;
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
            padding: 30px;
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
        
        .btn-group .create {
            background: #f7d0d7;
            color: #6f6177;
        }
        
        .btn-group .create:hover {
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

    <div class="create-container">
        <h1 style="margin-bottom:30px; color:#6f6177; text-align:center;">Создание мероприятия</h1>

        <form id="eventForm" method="POST" enctype="multipart/form-data" onsubmit="return false;">
            <!-- Область загрузки фото -->
            <div class="photo-upload" id="photoUpload" onclick="document.getElementById('photoInput').click()">
                <div class="photo-icon">📷</div>
                <p>Нажмите чтобы загрузить фото мероприятия</p>
                <p class="photo-hint">Поддерживаются JPG, PNG, GIF (до 5 МБ)</p>
                <input type="file" id="photoInput" name="photo" accept="image/jpeg,image/png,image/gif" style="display:none;">
                <div class="photo-name" id="photoName"></div>
                <img class="photo-preview" id="photoPreview" src="#" alt="Preview">
                <div>
                    <button type="button" class="remove-photo" id="removePhoto" style="display:none;" onclick="removePhoto(event)">✕ Удалить фото</button>
                </div>
            </div>
            
            <!-- Прогресс бар загрузки -->
            <div class="progress-bar" id="progressBar">
                <div class="fill" id="progressFill"></div>
            </div>

            <div class="form-group">
                <label for="title">Название мероприятия *</label>
                <input type="text" id="title" name="title" required placeholder="Например: Свадьба Анны и Марка">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="date">Дата *</label>
                    <input type="date" id="date" name="date" required value="<?php echo date('Y-m-d', strtotime('+7 days')); ?>">
                </div>
                <div class="form-group">
                    <label for="time">Время</label>
                    <input type="time" id="time" name="time" value="18:00">
                </div>
            </div>

            <div class="form-group">
                <label for="location">Место проведения</label>
                <input type="text" id="location" name="location" placeholder="Адрес или название площадки">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="budget">Бюджет (₽)</label>
                    <input type="number" id="budget" name="budget" placeholder="0" min="0" step="1000">
                </div>
                <div class="form-group">
                    <label for="status">Статус</label>
                    <select id="status" name="status">
                        <option value="draft">Черновик</option>
                        <option value="planned" selected>Запланировано</option>
                        <option value="in_progress">В работе</option>
                        <option value="completed">Завершено</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="description">Описание</label>
                <textarea id="description" name="description" rows="4" placeholder="Программа мероприятия, заметки..."></textarea>
            </div>

            <div class="btn-group">
                <button type="submit" class="create" id="submitBtn">Создать мероприятие</button>
                <button type="button" class="cancel" onclick="confirmCancel()">Отмена</button>
            </div>

            <div id="message" class="message"></div>
        </form>
    </div>

    <script>
        // Предпросмотр фото
        document.getElementById('photoInput').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                // Проверка размера (5 МБ)
                if (file.size > 5 * 1024 * 1024) {
                    showMessage('Файл слишком большой. Максимальный размер 5 МБ', 'error');
                    this.value = '';
                    return;
                }
                
                // Проверка типа
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
        }

        // Отправка формы
        document.getElementById('eventForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const btn = document.getElementById('submitBtn');
            const messageDiv = document.getElementById('message');
            const progressBar = document.getElementById('progressBar');
            const progressFill = document.getElementById('progressFill');
            
            // Валидация
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
            
            // Формируем дату и время
            const time = document.getElementById('time').value || '18:00';
            const event_date = `${date} ${time}:00`;
            
            // Создаем FormData
            const formData = new FormData();
            formData.append('title', title);
            formData.append('event_date', event_date);
            formData.append('location', document.getElementById('location').value.trim());
            formData.append('budget', document.getElementById('budget').value || '0');
            formData.append('status', document.getElementById('status').value);
            formData.append('description', document.getElementById('description').value.trim());
            
            // Добавляем фото если есть
            const photoFile = document.getElementById('photoInput').files[0];
            if (photoFile) {
                formData.append('photo', photoFile);
            }
            
            // Показываем загрузку
            btn.textContent = 'Создание...';
            btn.disabled = true;
            document.querySelector('.create-container').classList.add('loading');
            messageDiv.style.display = 'none';
            progressBar.style.display = 'block';
            
            // Имитация прогресса
            let progress = 0;
            const interval = setInterval(() => {
                progress += 10;
                if (progress <= 90) {
                    progressFill.style.width = progress + '%';
                }
            }, 200);
            
            try {
                const response = await fetch('create-event-handler.php', {
                    method: 'POST',
                    body: formData
                });
                
                clearInterval(interval);
                progressFill.style.width = '100%';
                
                const data = await response.json();
                
                if (data.success) {
                    showMessage('Мероприятие успешно создано!', 'success');
                    setTimeout(() => {
                        window.location.href = 'event-detail.php?id=' + data.event_id;
                    }, 1000);
                } else {
                    progressBar.style.display = 'none';
                    showMessage(data.message || 'Ошибка при создании мероприятия', 'error');
                    btn.textContent = 'Создать мероприятие';
                    btn.disabled = false;
                    document.querySelector('.create-container').classList.remove('loading');
                }
            } catch (error) {
                clearInterval(interval);
                progressBar.style.display = 'none';
                console.error('Error:', error);
                showMessage('Ошибка соединения с сервером', 'error');
                btn.textContent = 'Создать мероприятие';
                btn.disabled = false;
                document.querySelector('.create-container').classList.remove('loading');
            }
        });

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

        function confirmCancel() {
            const title = document.getElementById('title').value;
            const date = document.getElementById('date').value;
            const photo = document.getElementById('photoInput').value;
            
            if (title || date || photo) {
                if (confirm('Отменить создание? Все данные будут потеряны.')) {
                    window.location.href = 'dashboard.php';
                }
            } else {
                window.location.href = 'dashboard.php';
            }
        }

        function logout() {
            if (confirm('Выйти из аккаунта?')) {
                window.location.href = 'logout.php';
            }
        }
    </script>
</body>
</html>