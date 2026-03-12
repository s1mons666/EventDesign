<?php
// public/report.php
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

$event_id = $_GET['id'] ?? '';
if (!$event_id) {
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

// Получаем задачи
$stmt = $conn->prepare("SELECT * FROM tasks WHERE event_id = ? ORDER BY created_at");
$stmt->execute([$event_id]);
$tasks = $stmt->fetchAll();

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
        return date('d.m.Y', strtotime($dateString));
    } catch (Exception $e) {
        return $dateString;
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Отчет - <?php echo htmlspecialchars($event['title']); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/style.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>
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
                <button class="btn pink" onclick="logout()">Выход</button>
            </div>
        </div>
    </header>

    <section class="container" style="margin-top:30px;">
        <div style="background:#eee; padding:30px; border-radius:16px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:30px; flex-wrap:wrap; gap:15px;">
                <div>
                    <h1 style="color:#6f6177;">Отчет: <?php echo htmlspecialchars($event['title']); ?></h1>
                    <p style="color:#666;">Дата формирования: <?php echo date('d.m.Y'); ?></p>
                </div>
                <div style="display:flex; gap:10px;">
                    <button class="btn white" onclick="window.print()">🖨️ Печать</button>
                    <button class="btn pink" onclick="generatePDF()">📥 Скачать PDF</button>
                </div>
            </div>

            <div style="margin-bottom:30px; padding:20px; background:white; border-radius:12px;">
                <h2 style="margin-bottom:15px; color:#6f6177;">Информация о мероприятии</h2>
                <table style="width:100%;">
                    <tr><td style="padding:8px 0;"><strong>Название:</strong></td><td><?php echo htmlspecialchars($event['title']); ?></td></tr>
                    <tr><td style="padding:8px 0;"><strong>Дата проведения:</strong></td><td><?php echo formatDate($event['event_date']); ?></td></tr>
                    <tr><td style="padding:8px 0;"><strong>Место:</strong></td><td><?php echo htmlspecialchars($event['location'] ?? 'Не указано'); ?></td></tr>
                    <tr><td style="padding:8px 0;"><strong>Бюджет:</strong></td><td><?php echo $event['budget'] ? number_format($event['budget'], 0, ',', ' ') . ' ₽' : 'Не указан'; ?></td></tr>
                </table>
            </div>

            <div style="margin-bottom:30px;">
                <h2 style="margin-bottom:15px; color:#6f6177;">Финансовая сводка</h2>
                <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:20px;">
                    <div style="background:white; padding:20px; border-radius:12px; text-align:center;">
                        <div style="color:#666;">Всего задач</div>
                        <div style="font-size:32px; font-weight:bold;"><?php echo count($tasks); ?></div>
                    </div>
                    <div style="background:white; padding:20px; border-radius:12px; text-align:center;">
                        <div style="color:#666;">Выполнено</div>
                        <div style="font-size:32px; font-weight:bold; color:#4CAF50;"><?php echo $completed_count; ?></div>
                    </div>
                    <div style="background:white; padding:20px; border-radius:12px; text-align:center;">
                        <div style="color:#666;">Общая сумма</div>
                        <div style="font-size:32px; font-weight:bold; color:#f5a7ff;"><?php echo number_format($total_cost, 0, ',', ' '); ?> ₽</div>
                    </div>
                </div>
            </div>

            <div>
                <h2 style="margin-bottom:15px; color:#6f6177;">Детализация расходов</h2>
                <table style="width:100%; border-collapse:collapse; background:white; border-radius:12px; overflow:hidden;">
                    <thead>
                        <tr style="background:#6f6177; color:white;">
                            <th style="padding:12px; text-align:left;">№</th>
                            <th style="padding:12px; text-align:left;">Задача</th>
                            <th style="padding:12px; text-align:left;">Статус</th>
                            <th style="padding:12px; text-align:right;">Стоимость</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($tasks)): ?>
                            <tr><td colspan="4" style="padding:20px; text-align:center;">Нет данных</td></tr>
                        <?php else: ?>
                            <?php foreach ($tasks as $index => $task): ?>
                            <tr style="border-bottom:1px solid #eee;">
                                <td style="padding:10px;"><?php echo $index + 1; ?></td>
                                <td style="padding:10px;"><?php echo htmlspecialchars($task['title']); ?></td>
                                <td style="padding:10px;">
                                    <span style="color:<?php echo $task['is_completed'] ? '#4CAF50' : '#FF9800'; ?>">
                                        <?php echo $task['is_completed'] ? '✓ Выполнено' : '○ В процессе'; ?>
                                    </span>
                                </td>
                                <td style="padding:10px; text-align:right;"><?php echo number_format($task['cost'], 0, ',', ' '); ?> ₽</td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <tfoot>
                        <tr style="background:#f5a7ff; font-weight:bold;">
                            <td colspan="3" style="padding:12px; text-align:right;">ИТОГО:</td>
                            <td style="padding:12px; text-align:right;"><?php echo number_format($total_cost, 0, ',', ' '); ?> ₽</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div style="margin-top:30px; text-align:right;">
                <button class="btn edit" onclick="location.href='event-detail.php?id=<?php echo $event_id; ?>'">
                    ← Вернуться к мероприятию
                </button>
            </div>
        </div>
    </section>

    <script>
        function generatePDF() {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();
            
            doc.setFontSize(20);
            doc.text('Отчет по мероприятию', 20, 20);
            doc.setFontSize(16);
            doc.text('<?php echo htmlspecialchars($event['title']); ?>', 20, 30);
            doc.setFontSize(12);
            doc.text('Дата: <?php echo formatDate($event['event_date']); ?>', 20, 45);
            doc.text('Место: <?php echo htmlspecialchars($event['location'] ?? 'Не указано'); ?>', 20, 52);
            doc.text('Бюджет: <?php echo $event['budget'] ? number_format($event['budget'], 0, ',', ' ') . " ₽" : "Не указан"; ?>', 20, 59);
            
            const tableData = [];
            <?php foreach ($tasks as $task): ?>
            tableData.push([
                '<?php echo addslashes($task['title']); ?>',
                '<?php echo $task['is_completed'] ? "Выполнено" : "В процессе"; ?>',
                '<?php echo number_format($task['cost'], 0, ",", " "); ?> ₽'
            ]);
            <?php endforeach; ?>
            
            doc.autoTable({
                startY: 70,
                head: [['Задача', 'Статус', 'Стоимость']],
                body: tableData,
                foot: [['', 'ИТОГО:', '<?php echo number_format($total_cost, 0, ",", " "); ?> ₽']],
                theme: 'striped',
                headStyles: { fillColor: [111, 97, 119] },
                footStyles: { fillColor: [245, 167, 255], textColor: [0, 0, 0] }
            });
            
            doc.save('report_<?php echo date('Y-m-d'); ?>.pdf');
        }

        function logout() {
            if (confirm('Выйти из аккаунта?')) {
                document.cookie = 'auth_token=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
                window.location.href = 'logout.php';
            }
        }
    </script>
</body>
</html>