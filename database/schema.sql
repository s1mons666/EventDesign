-- =====================================================
-- База данных Event Design по ER-диаграмме
-- Использование UUID в качестве первичных ключей
-- =====================================================


PRAGMA foreign_keys = ON;


-- =====================================================
-- 1. ТАБЛИЦА ПОЛЬЗОВАТЕЛЕЙ (User)
-- =====================================================
CREATE TABLE IF NOT EXISTS users (
    user_id TEXT PRIMARY KEY,  -- UUID в текстовом формате
    email TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    name TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Триггер для обновления updated_at
CREATE TRIGGER IF NOT EXISTS update_users_timestamp 
AFTER UPDATE ON users
BEGIN
    UPDATE users SET updated_at = CURRENT_TIMESTAMP WHERE user_id = NEW.user_id;
END;

-- Индекс для быстрого поиска по email
CREATE INDEX IF NOT EXISTS idx_users_email ON users(email);

-- =====================================================
-- 2. ТАБЛИЦА МЕРОПРИЯТИЙ (Event)
-- =====================================================
CREATE TABLE IF NOT EXISTS events (
    event_id TEXT PRIMARY KEY,  -- UUID
    user_id TEXT NOT NULL,
    title TEXT NOT NULL,
    event_date DATETIME NOT NULL,
    location TEXT,
    budget DECIMAL(10, 2),
    description TEXT,
    status TEXT CHECK(status IN ('draft', 'planned', 'in_progress', 'completed', 'cancelled')) DEFAULT 'draft',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Триггер для обновления updated_at
CREATE TRIGGER IF NOT EXISTS update_events_timestamp 
AFTER UPDATE ON events
BEGIN
    UPDATE events SET updated_at = CURRENT_TIMESTAMP WHERE event_id = NEW.event_id;
END;

-- Индексы для оптимизации запросов
CREATE INDEX IF NOT EXISTS idx_events_user_id ON events(user_id);
CREATE INDEX IF NOT EXISTS idx_events_event_date ON events(event_date);
CREATE INDEX IF NOT EXISTS idx_events_status ON events(status);

-- =====================================================
-- 3. ТАБЛИЦА ЗАДАЧ (Task)
-- =====================================================
CREATE TABLE IF NOT EXISTS tasks (
    task_id TEXT PRIMARY KEY,  -- UUID
    event_id TEXT NOT NULL,
    title TEXT NOT NULL,
    cost DECIMAL(10, 2) DEFAULT 0,
    is_done BOOLEAN DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(event_id) ON DELETE CASCADE
);

-- Триггер для обновления updated_at
CREATE TRIGGER IF NOT EXISTS update_tasks_timestamp 
AFTER UPDATE ON tasks
BEGIN
    UPDATE tasks SET updated_at = CURRENT_TIMESTAMP WHERE task_id = NEW.task_id;
END;

-- Индексы для задач
CREATE INDEX IF NOT EXISTS idx_tasks_event_id ON tasks(event_id);
CREATE INDEX IF NOT EXISTS idx_tasks_is_done ON tasks(is_done);

-- =====================================================
-- 4. ТАБЛИЦА ОТЧЕТОВ (Report)
-- =====================================================
CREATE TABLE IF NOT EXISTS reports (
    report_id TEXT PRIMARY KEY,  -- UUID
    event_id TEXT NOT NULL,
    generated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    total_cost DECIMAL(10, 2) DEFAULT 0,
    pdf_url TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(event_id) ON DELETE CASCADE
);

-- Индекс для отчетов
CREATE INDEX IF NOT EXISTS idx_reports_event_id ON reports(event_id);
CREATE INDEX IF NOT EXISTS idx_reports_generated_at ON reports(generated_at);

-- =====================================================
-- 5. ТАБЛИЦА ФИЛЬТРОВ (Filter)
-- =====================================================
CREATE TABLE IF NOT EXISTS filters (
    filter_id TEXT PRIMARY KEY,  -- UUID
    user_id TEXT NOT NULL,
    filter_name TEXT NOT NULL,
    filter_params TEXT NOT NULL,  -- JSON данные
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Индексы для фильтров
CREATE INDEX IF NOT EXISTS idx_filters_user_id ON filters(user_id);

-- =====================================================
-- ФУНКЦИЯ ДЛЯ ГЕНЕРАЦИИ UUID (для SQLite)
-- =====================================================

-- Функция для генерации UUID v4
CREATE TEMPORARY FUNCTION IF NOT EXISTS generate_uuid() 
RETURNS TEXT 
DETERMINISTIC 
AS $$
    SELECT lower(
        hex(randomblob(4)) || '-' || 
        hex(randomblob(2)) || '-' || 
        '4' || substr(hex(randomblob(2)), 2) || '-' || 
        substr('89ab', 1 + (abs(random()) % 4), 1) || substr(hex(randomblob(2)), 2) || '-' || 
        hex(randomblob(6))
    );
$$;

-- =====================================================
-- ПРЕДСТАВЛЕНИЯ ДЛЯ УДОБНОЙ ВЫБОРКИ
-- =====================================================

-- 1. Статистика по мероприятиям (с задачами)
CREATE VIEW IF NOT EXISTS event_statistics AS
SELECT 
    e.event_id,
    e.title,
    e.user_id,
    e.event_date,
    e.status,
    e.budget AS planned_budget,
    COUNT(t.task_id) AS total_tasks,
    SUM(CASE WHEN t.is_done = 1 THEN 1 ELSE 0 END) AS completed_tasks,
    IFNULL(SUM(t.cost), 0) AS actual_cost,
    CASE 
        WHEN e.budget IS NOT NULL THEN e.budget - IFNULL(SUM(t.cost), 0)
        ELSE NULL
    END AS budget_remaining
FROM events e
LEFT JOIN tasks t ON e.event_id = t.event_id
GROUP BY e.event_id;

-- 2. Детальная информация о пользователе
CREATE VIEW IF NOT EXISTS user_details AS
SELECT 
    u.user_id,
    u.name,
    u.email,
    u.created_at AS registered_at,
    COUNT(DISTINCT e.event_id) AS total_events,
    COUNT(DISTINCT t.task_id) AS total_tasks,
    IFNULL(SUM(t.cost), 0) AS total_spent,
    COUNT(DISTINCT f.filter_id) AS saved_filters
FROM users u
LEFT JOIN events e ON u.user_id = e.user_id
LEFT JOIN tasks t ON e.event_id = t.event_id
LEFT JOIN filters f ON u.user_id = f.user_id
GROUP BY u.user_id;

-- 3. Последние отчеты
CREATE VIEW IF NOT EXISTS recent_reports AS
SELECT 
    r.report_id,
    r.generated_at,
    r.total_cost,
    r.pdf_url,
    e.title AS event_title,
    e.event_id,
    u.name AS user_name
FROM reports r
JOIN events e ON r.event_id = e.event_id
JOIN users u ON e.user_id = u.user_id
ORDER BY r.generated_at DESC;

-- =====================================================
-- ТРИГГЕРЫ ДЛЯ АВТОМАТИЧЕСКИХ ДЕЙСТВИЙ
-- =====================================================

-- 1. Автоматическое создание отчета при завершении мероприятия
CREATE TRIGGER IF NOT EXISTS auto_create_report_on_complete
AFTER UPDATE OF status ON events
WHEN NEW.status = 'completed' AND OLD.status != 'completed'
BEGIN
    INSERT INTO reports (report_id, event_id, total_cost, pdf_url)
    SELECT 
        generate_uuid(),
        NEW.event_id,
        IFNULL(SUM(t.cost), 0),
        NULL
    FROM tasks t
    WHERE t.event_id = NEW.event_id;
END;

-- 2. Обновление total_cost в отчете при изменении задач
CREATE TRIGGER IF NOT EXISTS update_report_on_task_change
AFTER INSERT ON tasks
BEGIN
    UPDATE reports 
    SET total_cost = (
        SELECT IFNULL(SUM(cost), 0) 
        FROM tasks 
        WHERE event_id = NEW.event_id
    )
    WHERE event_id = NEW.event_id;
END;

-- 3. Логирование удаления мероприятий
CREATE TABLE IF NOT EXISTS deleted_events_log (
    log_id INTEGER PRIMARY KEY AUTOINCREMENT,
    event_id TEXT,
    event_title TEXT,
    user_id TEXT,
    deleted_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TRIGGER IF NOT EXISTS log_event_deletion
AFTER DELETE ON events
BEGIN
    INSERT INTO deleted_events_log (event_id, event_title, user_id)
    VALUES (OLD.event_id, OLD.title, OLD.user_id);
END;

-- =====================================================
-- ПРОЦЕДУРЫ ДЛЯ ЧАСТЫХ ОПЕРАЦИЙ
-- =====================================================

-- 1. Создание нового мероприятия с задачами
CREATE PROCEDURE IF NOT EXISTS CreateEventWithTasks(
    p_user_id TEXT,
    p_title TEXT,
    p_event_date DATETIME,
    p_location TEXT,
    p_budget DECIMAL,
    p_description TEXT,
    p_status TEXT,
    p_tasks_json TEXT  -- JSON массив задач
)
BEGIN
    DECLARE v_event_id TEXT;
    DECLARE v_counter INTEGER DEFAULT 0;
    DECLARE v_task_count INTEGER;
    
    -- Генерируем UUID для мероприятия
    SET v_event_id = generate_uuid();
    
    -- Начинаем транзакцию
    BEGIN TRANSACTION;
    
    -- Создаем мероприятие
    INSERT INTO events (event_id, user_id, title, event_date, location, budget, description, status)
    VALUES (v_event_id, p_user_id, p_title, p_event_date, p_location, p_budget, p_description, p_status);
    
    -- Если есть задачи, добавляем их
    IF p_tasks_json IS NOT NULL AND json_valid(p_tasks_json) THEN
        SET v_task_count = json_array_length(p_tasks_json);
        
        WHILE v_counter < v_task_count DO
            INSERT INTO tasks (task_id, event_id, title, cost)
            VALUES (
                generate_uuid(),
                v_event_id,
                json_extract(p_tasks_json, '$[' || v_counter || '].title'),
                json_extract(p_tasks_json, '$[' || v_counter || '].cost')
            );
            SET v_counter = v_counter + 1;
        END WHILE;
    END IF;
    
    -- Завершаем транзакцию
    COMMIT;
    
    -- Возвращаем созданное мероприятие
    SELECT * FROM events WHERE event_id = v_event_id;
END;

-- 2. Получение полного отчета по мероприятию
CREATE PROCEDURE IF NOT EXISTS GetFullEventReport(p_event_id TEXT)
BEGIN
    SELECT 
        e.*,
        (
            SELECT json_group_array(
                json_object(
                    'task_id', t.task_id,
                    'title', t.title,
                    'cost', t.cost,
                    'is_done', t.is_done,
                    'created_at', t.created_at
                )
            )
            FROM tasks t
            WHERE t.event_id = e.event_id
        ) AS tasks_json,
        (
            SELECT json_object(
                'report_id', r.report_id,
                'generated_at', r.generated_at,
                'total_cost', r.total_cost,
                'pdf_url', r.pdf_url
            )
            FROM reports r
            WHERE r.event_id = e.event_id
            ORDER BY r.generated_at DESC
            LIMIT 1
        ) AS last_report
    FROM events e
    WHERE e.event_id = p_event_id;
END;

-- =====================================================
-- ТЕСТОВЫЕ ДАННЫЕ (UUID версия)
-- =====================================================

-- Очищаем существующие данные (если нужно)
-- DELETE FROM tasks;
-- DELETE FROM reports;
-- DELETE FROM events;
-- DELETE FROM filters;
-- DELETE FROM users;

-- Добавляем тестовых пользователей
INSERT OR IGNORE INTO users (user_id, email, password_hash, name) VALUES
('11111111-1111-1111-1111-111111111111', 'anna@example.com', '$2y$10$YourHashHere12345678901234567890', 'Анна Иванова'),
('22222222-2222-2222-2222-222222222222', 'petr@example.com', '$2y$10$YourHashHere12345678901234567890', 'Петр Сидоров');

-- Добавляем тестовые мероприятия
INSERT OR IGNORE INTO events (event_id, user_id, title, event_date, location, budget, description, status) VALUES
('aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa', '11111111-1111-1111-1111-111111111111', 'Свадьба Анны и Марка', '2026-06-12 16:00:00', 'Москва, Ресторан "Националь"', 300000.00, 'Небольшое семейное торжество на 50 гостей', 'in_progress'),
('bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb', '11111111-1111-1111-1111-111111111111', 'IT Future Conference', '2026-07-15 10:00:00', 'Санкт-Петербург, КЦ "ПетроКонгресс"', 500000.00, 'Конференция для разработчиков и IT компаний', 'planned'),
('cccccccc-cccc-cccc-cccc-cccccccccccc', '11111111-1111-1111-1111-111111111111', 'День рождения Софии', '2026-08-22 18:00:00', 'Ресторан "Династия"', 150000.00, 'Детский праздник с аниматорами', 'draft');

-- Добавляем тестовые задачи
INSERT OR IGNORE INTO tasks (task_id, event_id, title, cost, is_done) VALUES
('t1t1t1t1-1111-1111-1111-t1t1t1t1t1t1', 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa', 'Забронировать ресторан', 100000.00, 1),
('t2t2t2t2-2222-2222-2222-t2t2t2t2t2t2', 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa', 'Заказать фотографа', 50000.00, 0),
('t3t3t3t3-3333-3333-3333-t3t3t3t3t3t3', 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa', 'Купить цветы', 30000.00, 0),
('t4t4t4t4-4444-4444-4444-t4t4t4t4t4t4', 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa', 'Заказать торт', 25000.00, 0),
('t5t5t5t5-5555-5555-5555-t5t5t5t5t5t5', 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb', 'Аренда зала', 200000.00, 0),
('t6t6t6t6-6666-6666-6666-t6t6t6t6t6t6', 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb', 'Кейтеринг', 150000.00, 0),
('t7t7t7t7-7777-7777-7777-t7t7t7t7t7t7', 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb', 'Печать бейджей', 5000.00, 1);

-- Добавляем тестовые отчеты
INSERT OR IGNORE INTO reports (report_id, event_id, total_cost, pdf_url) VALUES
('r1r1r1r1-1111-1111-1111-r1r1r1r1r1r1', 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa', 205000.00, '/reports/wedding_anna_2026.pdf'),
('r2r2r2r2-2222-2222-2222-r2r2r2r2r2r2', 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb', 355000.00, '/reports/it_conf_2026.pdf');

-- Добавляем тестовые фильтры
INSERT OR IGNORE INTO filters (filter_id, user_id, filter_name, filter_params) VALUES
('f1f1f1f1-1111-1111-1111-f1f1f1f1f1f1', '11111111-1111-1111-1111-111111111111', 'Предстоящие мероприятия', '{"status": ["planned", "in_progress"], "date_from": "now"}'),
('f2f2f2f2-2222-2222-2222-f2f2f2f2f2f2', '11111111-1111-1111-1111-111111111111', 'Высокобюджетные', '{"budget_min": 200000}');

-- =====================================================
-- ПОЛЕЗНЫЕ ЗАПРОСЫ ДЛЯ ТЕСТИРОВАНИЯ
-- =====================================================

-- 1. Получить все мероприятия пользователя с UUID
/*
SELECT * FROM events WHERE user_id = '11111111-1111-1111-1111-111111111111' ORDER BY event_date DESC;
*/

-- 2. Получить статистику по мероприятию
/*
SELECT * FROM event_statistics WHERE event_id = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';
*/

-- 3. Получить задачи мероприятия
/*
SELECT * FROM tasks WHERE event_id = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';
*/

-- 4. Получить последний отчет по мероприятию
/*
SELECT * FROM reports WHERE event_id = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa' ORDER BY generated_at DESC LIMIT 1;
*/

-- 5. Получить сохраненные фильтры пользователя
/*
SELECT * FROM filters WHERE user_id = '11111111-1111-1111-1111-111111111111';
*/

-- =====================================================
-- ОЧИСТКА БАЗЫ (использовать осторожно)
-- =====================================================

-- Удалить все данные
-- DELETE FROM tasks;
-- DELETE FROM reports;
-- DELETE FROM events;
-- DELETE FROM filters;
-- DELETE FROM users;

-- Сброс автоинкремента (для SQLite)
-- DELETE FROM sqlite_sequence;