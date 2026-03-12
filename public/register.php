<?php
// public/register.php
session_start();

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>EventDesign - Регистрация</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/auth.css">
</head>
<body>
    <header class="header">
        <div class="container nav">
            <div class="logo">Event<span>Design</span></div>
        </div>
    </header>

    <section class="auth-section">
        <div class="auth-container">
            <div class="auth-card">
                <h2>Создать аккаунт</h2>
                <p class="auth-subtitle">Присоединяйтесь к EventDesign!</p>

                <form class="auth-form" id="registerForm" method="POST" onsubmit="return false;">
                    <div class="form-row">
                        <div class="form-group half">
                            <label for="first_name">Имя</label>
                            <input type="text" id="first_name" name="first_name" required>
                        </div>
                        <div class="form-group half">
                            <label for="last_name">Фамилия</label>
                            <input type="text" id="last_name" name="last_name" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" required>
                    </div>

                    <div class="form-group">
                        <label for="password">Пароль</label>
                        <input type="password" id="password" name="password" required>
                        <span class="hint">Минимум 6 символов</span>
                    </div>

                    <button type="submit" class="btn pink auth-btn" id="registerBtn">Зарегистрироваться</button>

                    <p class="auth-footer">
                        Уже есть аккаунт? 
                        <a href="login.php">Войти</a>
                    </p>
                </form>

                <div id="message" style="display:none; padding:10px; border-radius:8px; margin-top:20px; text-align:center;"></div>
            </div>
        </div>
    </section>

    <script>
    document.getElementById('registerForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const btn = document.getElementById('registerBtn');
        const messageDiv = document.getElementById('message');
        
        const password = document.getElementById('password').value;
        
        if (password.length < 6) {
            messageDiv.style.backgroundColor = '#eacaca';
            messageDiv.style.color = '#6f6177';
            messageDiv.textContent = 'Пароль должен быть минимум 6 символов';
            messageDiv.style.display = 'block';
            return;
        }
        
        btn.textContent = 'Регистрация...';
        btn.disabled = true;
        messageDiv.style.display = 'none';
        
        // Создаем FormData
        const formData = new FormData();
        formData.append('first_name', document.getElementById('first_name').value);
        formData.append('last_name', document.getElementById('last_name').value);
        formData.append('email', document.getElementById('email').value);
        formData.append('password', password);
        
        try {
            const response = await fetch('register-handler.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                if (data.data?.token) {
                    localStorage.setItem('token', data.data.token);
                    localStorage.setItem('user', JSON.stringify(data.data.user));
                }
                
                messageDiv.style.backgroundColor = '#cfe3d8';
                messageDiv.style.color = '#6f6177';
                messageDiv.textContent = 'Регистрация успешна! Перенаправление...';
                messageDiv.style.display = 'block';
                
                setTimeout(() => {
                    window.location.href = 'dashboard.php';
                }, 1000);
            } else {
                messageDiv.style.backgroundColor = '#eacaca';
                messageDiv.style.color = '#6f6177';
                messageDiv.textContent = data.message || 'Ошибка регистрации';
                messageDiv.style.display = 'block';
                btn.textContent = 'Зарегистрироваться';
                btn.disabled = false;
            }
        } catch (error) {
            console.error('Error:', error);
            messageDiv.style.backgroundColor = '#eacaca';
            messageDiv.style.color = '#6f6177';
            messageDiv.textContent = 'Ошибка соединения с сервером';
            messageDiv.style.display = 'block';
            btn.textContent = 'Зарегистрироваться';
            btn.disabled = false;
        }
    });
    </script>
</body>
</html>