<?php
// public/index.php
session_start();
require_once __DIR__ . '/../api/config/database.php';

$db = Database::getInstance();
$conn = $db->getConnection();

$user_id = null;
$user = null;
$user_events = [];
$next_event = null;

// Проверяем, авторизован ли пользователь
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

// Если пользователь авторизован, получаем его данные и мероприятия
if ($user_id) {
    // Получаем данные пользователя
    $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if ($user) {
        // Получаем последние 3 мероприятия пользователя для отображения
        $stmt = $conn->prepare("
            SELECT * FROM events 
            WHERE user_id = ? 
            ORDER BY 
                CASE 
                    WHEN event_date > datetime('now') THEN 0 
                    ELSE 1 
                END,
                event_date DESC 
            LIMIT 3
        ");
        $stmt->execute([$user_id]);
        $user_events = $stmt->fetchAll();
        
        // Получаем ближайшее мероприятие
        $stmt = $conn->prepare("
            SELECT * FROM events 
            WHERE user_id = ? AND event_date > datetime('now') 
            ORDER BY event_date ASC LIMIT 1
        ");
        $stmt->execute([$user_id]);
        $next_event = $stmt->fetch();
    }
}

// Функция для форматирования даты
function formatDate($dateString) {
    if (!$dateString) return 'Дата не указана';
    try {
        return date('d.m.Y', strtotime($dateString));
    } catch (Exception $e) {
        return $dateString;
    }
}

// Функция для обрезания текста
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
    <title>EventDesign - Организация мероприятий</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .dashboard {
            display: flex;
            gap: 30px;
            margin: 50px auto;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .card {
            background: #eee;
            padding: 25px;
            border-radius: 16px;
            width: calc(33.333% - 20px);
            min-width: 280px;
            max-width: 350px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }
        
        .card h3 {
            color: #6f6177;
            margin-bottom: 15px;
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .card h4 {
            color: #6f6177;
            margin: 10px 0 5px;
            font-size: 20px;
        }
        
        .card .img {
            height: 150px;
            background-size: cover;
            background-position: center;
            border-radius: 12px;
            margin: 15px 0;
            background-color: #ddd;
        }
        
        .card .desc {
            color: #666;
            font-size: 14px;
            margin-top: 5px;
        }
        
        .tabs {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            border-bottom: 2px solid #ddd;
            padding-bottom: 10px;
        }
        
        .tabs span {
            color: #666;
            cursor: pointer;
            font-size: 14px;
            padding: 5px 0;
            transition: all 0.3s;
        }
        
        .tabs span.active {
            color: #6f6177;
            font-weight: bold;
            border-bottom: 2px solid #f5a7ff;
            margin-bottom: -12px;
        }
        
        .tabs span:hover {
            color: #f5a7ff;
        }
        
        .card ul {
            list-style: none;
        }
        
        .card li {
            padding: 12px 0;
            border-bottom: 1px solid #ddd;
            color: #333;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card li:hover {
            padding-left: 10px;
            color: #6f6177;
        }
        
        .event-status {
            font-size: 12px;
            padding: 3px 8px;
            border-radius: 12px;
            background: #f5a7ff;
            color: #6f6177;
        }
        
        .empty-message {
            color: #999;
            text-align: center;
            padding: 20px;
            font-style: italic;
        }
        
        .view-all {
            display: block;
            text-align: center;
            margin-top: 15px;
            color: #f5a7ff;
            text-decoration: none;
            font-weight: bold;
        }
        
        .view-all:hover {
            text-decoration: underline;
        }
        
        .examples {
            margin: 80px auto;
        }
        
        .examples h2 {
            text-align: center;
            color: white;
            margin-bottom: 40px;
            font-size: 32px;
            position: relative;
        }
        
        .examples h2::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 60px;
            height: 3px;
            background: #f5a7ff;
        }
        
        .grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 25px;
        }
        
        .example {
            background: #eee;
            padding: 15px;
            border-radius: 16px;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .example:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }
        
        .example .img {
            height: 140px;
            background-size: cover;
            background-position: center;
            border-radius: 12px;
            margin-bottom: 10px;
            background-color: #ddd;
        }
        
        .example p {
            color: #333;
            text-align: center;
            font-size: 14px;
            padding: 10px 5px;
            line-height: 1.5;
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
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        
        .modal-content h3 {
            color: #6f6177;
            margin-bottom: 20px;
            font-size: 24px;
        }
        
        .modal-content p {
            margin-bottom: 20px;
            color: #333;
            line-height: 1.6;
        }
        
        .modal-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 20px;
        }
        
        .greeting {
            color: white;
            margin-right: 15px;
            font-size: 14px;
        }
        
        .greeting span {
            color: #f5a7ff;
            font-weight: bold;
        }
        
        .auth-prompt {
            text-align: center;
            margin-top: 20px;
            padding: 15px;
            background: rgba(255,255,255,0.1);
            border-radius: 8px;
        }
        
        .auth-prompt p {
            color: white;
            margin-bottom: 10px;
        }
        
        .auth-prompt .btn {
            margin: 0 5px;
        }
        
        /* Стили для фото на главной */
        .event-photo-small {
            width: 100%;
            height: 100px;
            object-fit: cover;
            border-radius: 8px;
            margin: 10px 0;
        }
        
        .mini-photo {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            object-fit: cover;
            margin-right: 10px;
        }
        
        .event-item {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        @media (max-width: 992px) {
            .grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .dashboard {
                flex-direction: column;
                align-items: center;
            }
            
            .card {
                width: 100%;
                max-width: 400px;
            }
            
            .grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body class="main-page">
    <!-- Модальное окно предпросмотра -->
    <div class="modal" id="previewModal">
        <div class="modal-content">
            <h3>👀 Предпросмотр мероприятия</h3>
            <p id="modalText">Загрузка...</p>
            <div class="modal-buttons">
                <button class="btn white" onclick="closeModal()">Закрыть</button>
                <?php if (!$user_id): ?>
                    <button class="btn pink" onclick="location.href='register.php'">Зарегистрироваться</button>
                <?php else: ?>
                    <button class="btn pink" onclick="location.href='event-create.php'">Создать такое</button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Шапка сайта -->
    <header class="header">
        <div class="container nav">
            <div class="logo">Event<span>Design</span></div>
            <nav class="menu">
                <a href="index.php" style="color:#f5a7ff;">Главная</a>
                <?php if ($user_id): ?>
                    <a href="dashboard.php">Мои мероприятия</a>
                    <a href="profile.php">Профиль</a>
                <?php endif; ?>
            </nav>
            <div class="auth-buttons">
                <?php if ($user_id && $user): ?>
                    <span class="greeting">
                        Привет, <span><?php echo htmlspecialchars($user['first_name']); ?></span>!
                    </span>
                    <button class="btn pink" onclick="location.href='logout.php'">Выход</button>
                <?php else: ?>
                    <button class="btn white" onclick="location.href='login.php'">Вход</button>
                    <button class="btn pink" onclick="location.href='register.php'">Регистрация</button>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Герой секция с main.jpg фоном -->
    <section class="hero">
        <div class="hero-content">
            <h1>Добро пожаловать в <span>EventDesign</span></h1>
            <p>Удобный инструмент для организации мероприятий. Планируйте события, управляйте задачами и создавайте отчеты в одном месте.</p>
        </div>
    </section>

    <!-- Карточки мероприятий -->
    <section class="dashboard container">
        <?php if ($user_id): ?>
            <!-- Карточка для авторизованного пользователя - Ближайшее мероприятие -->
            <div class="card" onclick="<?php if ($next_event): ?>location.href='event-detail.php?id=<?php echo $next_event['event_id']; ?>'<?php else: ?>showPreview('Нет ближайших мероприятий', 'Создайте свое первое мероприятие', '', 'Начните планировать уже сегодня!')<?php endif; ?>">
                <h3>📅 Ближайшее мероприятие</h3>
                <?php if ($next_event): ?>
                    <!-- Фото ближайшего мероприятия -->
                    <?php if (!empty($next_event['photo_path'])): ?>
                        <img src="<?php echo htmlspecialchars($next_event['photo_path']); ?>" 
                             alt="Фото" class="event-photo-small">
                    <?php else: ?>
                        <div class="img" style="background-image: url('images/wedding.jpg'); opacity:0.8;"></div>
                    <?php endif; ?>
                    <h4><?php echo htmlspecialchars($next_event['title']); ?></h4>
                    <p>Дата: <?php echo formatDate($next_event['event_date']); ?></p>
                    <p class="desc"><?php echo htmlspecialchars(truncateText($next_event['description'] ?? 'Нет описания', 50)); ?></p>
                    <p style="margin-top:10px; color:#f5a7ff;">💰 <?php echo $next_event['budget'] ? number_format($next_event['budget'], 0, ',', ' ') . ' ₽' : 'Бюджет не указан'; ?></p>
                <?php else: ?>
                    <div class="img" style="background-image: url('images/wedding.jpg'); opacity:0.5;"></div>
                    <h4>У вас нет мероприятий</h4>
                    <p>Создайте свое первое мероприятие!</p>
                    <p class="desc" style="color:#f5a7ff;">👆 Нажмите чтобы начать</p>
                <?php endif; ?>
            </div>

            <!-- Карточка со списком мероприятий пользователя -->
            <div class="card">
                <h3>📋 Мои мероприятия</h3>
                <div class="tabs">
                    <span class="active" onclick="filterEvents('upcoming')">Предстоящие</span>
                    <span onclick="filterEvents('past')">Прошедшие</span>
                </div>
                
                <?php if (empty($user_events)): ?>
                    <div class="empty-message">
                        <p>У вас пока нет мероприятий</p>
                        <p style="margin-top:10px;">👆 Создайте первое!</p>
                    </div>
                <?php else: ?>
                    <ul id="eventsList">
                        <?php foreach ($user_events as $event): 
                            $is_upcoming = strtotime($event['event_date']) > time();
                        ?>
                        <li onclick="location.href='event-detail.php?id=<?php echo $event['event_id']; ?>'">
                            <div class="event-item">
                                <!-- Миниатюра фото -->
                                <?php if (!empty($event['photo_path'])): ?>
                                    <img src="<?php echo htmlspecialchars($event['photo_path']); ?>" 
                                         alt="Фото" class="mini-photo">
                                <?php else: ?>
                                    <span style="font-size:20px;">📷</span>
                                <?php endif; ?>
                                <span><?php echo htmlspecialchars(truncateText($event['title'], 20)); ?></span>
                            </div>
                            <span class="event-status <?php echo $is_upcoming ? 'status-planned' : 'status-completed'; ?>">
                                <?php echo $is_upcoming ? 'Предстоит' : 'Прошло'; ?>
                            </span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php if (count($user_events) > 0): ?>
                        <a href="dashboard.php" class="view-all">Все мероприятия →</a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <!-- Карточки для неавторизованного пользователя -->
            <div class="card" onclick="showPreview('Ближайшее мероприятие', 'Свадьба Анны и Марка', '12 июня 2026', 'Небольшое семейное торжество на 50 гостей. Бюджет: 300 000 ₽')">
                <h3>📅 Ближайшее мероприятие</h3>
                <div class="img" style="background-image: url('images/wedding.jpg');"></div>
                <h4>Свадьба Анны и Марка</h4>
                <p>Дата: 12 июня 2026</p>
                <p class="desc">Небольшое семейное торжество на 50 гостей.</p>
            </div>

            <div class="card">
                <h3>📋 Список мероприятий</h3>
                <div class="tabs">
                    <span class="active">Предстоящие</span>
                    <span>Прошедшие</span>
                </div>
                <ul>
                    <li onclick="showPreview('IT Future Conference', 'Конференция', '15 июля 2026', 'Конференция для разработчиков и IT компаний. Участие 300 человек. Бюджет: 500 000 ₽')">
                        Конференция IT Future — 15 июля
                    </li>
                    <li onclick="showPreview('День рождения Софии', 'Детский праздник', '22 августа 2026', 'Детский праздник с аниматорами и сладким столом. Бюджет: 150 000 ₽')">
                        День рождения Софии — 22 августа
                    </li>
                    <li onclick="showPreview('Корпоратив Nova', 'Корпоратив', '10 сентября 2026', 'Ежегодный корпоратив для сотрудников компании Nova. Бюджет: 800 000 ₽')">
                        Корпоратив компании Nova — 10 сентября
                    </li>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Карточка с рекомендацией (показывается всем) -->
        <div class="card" onclick="showPreview('Корпоратив Nova', 'Пример мероприятия', '10 сентября 2026', 'Ежегодный корпоратив для сотрудников. Бюджет: 500 000 ₽. Программа: фуршет, живая музыка, конкурсы.')">
            <h3>✨ Рекомендуем</h3>
            <div class="img" style="background-image: url('images/corporate.jpg');"></div>
            <h4>Корпоратив Nova</h4>
            <p>Дата: 10 сентября 2026</p>
            <p class="desc">Идеальный шаблон для корпоратива</p>
        </div>
    </section>

    <!-- Призыв к регистрации для неавторизованных -->
    <?php if (!$user_id): ?>
    <div class="container">
        <div class="auth-prompt">
            <p>Хотите создавать свои мероприятия? Присоединяйтесь к EventDesign!</p>
            <button class="btn pink" onclick="location.href='register.php'">Зарегистрироваться</button>
            <button class="btn white" onclick="location.href='login.php'">Войти</button>
        </div>
    </div>
    <?php endif; ?>

    <!-- Примеры мероприятий (для всех пользователей) -->
    <section class="examples container">
        <h2>Примеры мероприятий</h2>
        <div class="grid">
            <div class="example" onclick="showPreview('Свадьба', 'Свадьба на 120 гостей', 'Организовано за 3 месяца', 'Полная организация свадьбы: подбор места (ресторан Престиж), кейтеринг (европейская кухня), декор (цветы пионы), фотограф, ведущий, торт. Бюджет: 450 000 ₽')">
                <div class="img" style="background-image: url('images/block1.jpg');"></div>
                <p>Свадьба на 120 гостей.<br>Организовано за 3 месяца.</p>
            </div>

            <div class="example" onclick="showPreview('Конференция', 'Бизнес конференция', '300 участников', 'Организация бизнес конференции: аренда зала (Крокус Экспо), оборудование (звук, проекторы), кофе-брейки, трансфер для спикеров, раздаточные материалы. Бюджет: 600 000 ₽')">
                <div class="img" style="background-image: url('images/block2.jpg');"></div>
                <p>Бизнес конференция<br>с участием 300 человек.</p>
            </div>

            <div class="example" onclick="showPreview('День рождения', 'День рождения в стиле ретро', '50 гостей', 'Тематическая вечеринка в стиле 80-х: декор (виниловые пластинки, ретро-постеры), фотозона, караоке, фуршет, диджей. Бюджет: 200 000 ₽')">
                <div class="img" style="background-image: url('images/block3.jpg');"></div>
                <p>День рождения<br>в стиле ретро.</p>
            </div>

            <div class="example" onclick="showPreview('Корпоратив', 'IT вечеринка', '200 сотрудников', 'Корпоратив для IT компании: киберспортивная зона, VR очки, фуршет, живая музыка, розыгрыш призов. Бюджет: 700 000 ₽')">
                <div class="img" style="background-image: url('images/block4.jpg');"></div>
                <p>Корпоративная вечеринка<br>IT компании.</p>
            </div>

            <div class="example" onclick="showPreview('Камерная свадьба', 'Небольшая свадьба', '30 гостей', 'Уютная свадьба в кругу близких: ресторан Династия, фотограф, ведущий, живая музыка (саксофон), свадебный торт. Бюджет: 250 000 ₽')">
                <div class="img" style="background-image: url('images/block5.jpg');"></div>
                <p>Небольшая камерная<br>свадьба.</p>
            </div>

            <div class="example" onclick="showPreview('Фестиваль', 'Фестиваль на открытом воздухе', '1000+ гостей', 'Организация фестиваля: сцена, звук, свет, фудкорты (10 точек), зоны отдыха, парковка, безопасность, волонтеры. Бюджет: 1 500 000 ₽')">
                <div class="img" style="background-image: url('images/block6.jpg');"></div>
                <p>Фестиваль<br>на открытом воздухе.</p>
            </div>
        </div>
    </section>

    <script>
        function showPreview(title, subtitle, date, description) {
            const modal = document.getElementById('previewModal');
            const modalText = document.getElementById('modalText');
            const isLoggedIn = <?php echo $user_id ? 'true' : 'false'; ?>;

            modalText.innerHTML = `
                <strong style="font-size:18px; color:#6f6177;">${title}</strong>
                ${subtitle ? `<p style="margin:10px 0;"><strong>${subtitle}</strong></p>` : ''}
                ${date ? `<p style="margin:5px 0;">📅 ${date}</p>` : ''}
                <p style="margin:15px 0; color:#555;">${description}</p>
                ${!isLoggedIn ? '<p style="margin-top:15px; font-style:italic; color:#888;">Зарегистрируйтесь чтобы создать такое мероприятие!</p>' : ''}
            `;
            modal.classList.add('show');
        }

        function closeModal() {
            document.getElementById('previewModal').classList.remove('show');
        }

        function filterEvents(type) {
            const tabs = document.querySelectorAll('.tabs span');
            tabs.forEach(tab => tab.classList.remove('active'));
            event.target.classList.add('active');
            
            <?php if ($user_id): ?>
            // Здесь можно добавить AJAX для фильтрации
            console.log('Filter events:', type);
            <?php endif; ?>
        }

        window.onclick = function(event) {
            const modal = document.getElementById('previewModal');
            if (event.target === modal) closeModal();
        }
    </script>
</body>
</html>