<?php
// public/login.php
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
    <title>EventDesign - Вход</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/auth.css">
    <style>
        /* Дополнительные стили для мобильной адаптации */
        @media (max-width: 480px) {
            .auth-card {
                padding: 30px 20px !important;
                margin: 0 15px !important;
            }
            
            .auth-card h2 {
                font-size: 26px !important;
            }
            
            .auth-subtitle {
                font-size: 14px !important;
                margin-bottom: 25px !important;
            }
            
            .form-group {
                margin-bottom: 15px !important;
            }
            
            .form-group input {
                padding: 14px !important;
                font-size: 16px !important;
                -webkit-appearance: none !important;
                border-radius: 10px !important;
            }
            
            .form-options {
                flex-direction: column !important;
                align-items: flex-start !important;
                gap: 12px !important;
                margin: 10px 0 20px !important;
            }
            
            .checkbox {
                font-size: 14px !important;
            }
            
            .forgot-link {
                font-size: 14px !important;
                margin-left: 0 !important;
            }
            
            .auth-btn {
                padding: 16px !important;
                font-size: 16px !important;
                width: 100% !important;
                margin-top: 5px !important;
            }
            
            .auth-footer {
                font-size: 14px !important;
                margin-top: 20px !important;
            }
            
            #message {
                font-size: 14px !important;
                padding: 12px !important;
                margin-top: 20px !important;
            }
            
            .header .logo {
                font-size: 22px !important;
            }
        }
        
        @media (max-width: 360px) {
            .auth-card {
                padding: 25px 15px !important;
            }
            
            .auth-card h2 {
                font-size: 24px !important;
            }
            
            .form-group input {
                padding: 12px !important;
            }
            
            .btn {
                padding: 14px !important;
            }
        }
        
        @media (max-width: 320px) {
            .auth-card {
                padding: 20px 12px !important;
            }
            
            .auth-card h2 {
                font-size: 22px !important;
            }
            
            .form-group label {
                font-size: 13px !important;
            }
            
            .form-group input {
                padding: 10px !important;
                font-size: 14px !important;
            }
        }
        
        /* Для сенсорных экранов - убираем hover эффекты */
        @media (hover: none) and (pointer: coarse) {
            .btn:hover {
                transform: none !important;
                box-shadow: none !important;
            }
            
            .btn:active {
                opacity: 0.8 !important;
                transform: scale(0.98) !important;
            }
        }
    </style>
</head>
<body class="auth-page">
    <header class="header">
        <div class="container nav">
            <div class="logo">Event<span>Design</span></div>
        </div>
    </header>

    <section class="auth-section">
        <div class="auth-container">
            <div class="auth-card">
                <h2>Вход в аккаунт</h2>
                <p class="auth-subtitle">Добро пожаловать обратно!</p>

                <form class="auth-form" id="loginForm" method="POST" onsubmit="return false;">
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" value="test@test.com" required>
                    </div>

                    <div class="form-group">
                        <label for="password">Пароль</label>
                        <input type="password" id="password" name="password" value="123456" required>
                    </div>

                    <div class="form-options">
                        <label class="checkbox">
                            <input type="checkbox" id="rememberMe" name="rememberMe" checked> Запомнить меня
                        </label>
                    </div>

                    <button type="submit" class="btn pink auth-btn" id="loginBtn">Войти</button>

                    <p class="auth-footer">
                        Нет аккаунта? 
                        <a href="register.php">Зарегистрироваться</a>
                    </p>
                </form>

                <div id="message" style="display:none; padding:10px; border-radius:8px; margin-top:20px; text-align:center;"></div>
            </div>
        </div>
    </section>

    <script>
    document.getElementById('loginForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const btn = document.getElementById('loginBtn');
        const messageDiv = document.getElementById('message');
        
        btn.textContent = 'Вход...';
        btn.disabled = true;
        messageDiv.style.display = 'none';
        
        const formData = new FormData();
        formData.append('email', document.getElementById('email').value);
        formData.append('password', document.getElementById('password').value);
        formData.append('rememberMe', document.getElementById('rememberMe').checked ? 'true' : 'false');
        
        try {
            const response = await fetch('login-handler.php', {
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
                messageDiv.textContent = 'Вход выполнен! Перенаправление...';
                messageDiv.style.display = 'block';
                
                setTimeout(() => {
                    window.location.href = 'dashboard.php';
                }, 1000);
            } else {
                messageDiv.style.backgroundColor = '#eacaca';
                messageDiv.style.color = '#6f6177';
                messageDiv.textContent = data.message || 'Ошибка входа';
                messageDiv.style.display = 'block';
                btn.textContent = 'Войти';
                btn.disabled = false;
            }
        } catch (error) {
            console.error('Error:', error);
            messageDiv.style.backgroundColor = '#eacaca';
            messageDiv.style.color = '#6f6177';
            messageDiv.textContent = 'Ошибка соединения с сервером';
            messageDiv.style.display = 'block';
            btn.textContent = 'Войти';
            btn.disabled = false;
        }
    });
    </script>
</body>
</html>