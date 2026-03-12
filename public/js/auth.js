// public/js/auth.js
document.addEventListener('DOMContentLoaded', () => {
    const user = AuthAPI.getCurrentUser();
    
    if (user && window.location.pathname.includes('login')) {
        window.location.href = 'dashboard.php';
    }

    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            try {
                const email = document.getElementById('email').value;
                const password = document.getElementById('password').value;
                const rememberMe = document.getElementById('rememberMe')?.checked || false;

                await AuthAPI.login(email, password, rememberMe);
                showSuccess('Вход выполнен!');
                setTimeout(() => window.location.href = 'dashboard.php', 1000);
            } catch (error) {
                showError(error.message || 'Ошибка входа');
            }
        });
    }

    const registerForm = document.getElementById('registerForm');
    if (registerForm) {
        registerForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const password = document.getElementById('password').value;
            const confirm = document.getElementById('confirm_password').value;
            
            if (password !== confirm) {
                showError('Пароли не совпадают');
                return;
            }

            if (password.length < 6) {
                showError('Пароль должен быть минимум 6 символов');
                return;
            }

            try {
                const userData = {
                    first_name: document.getElementById('firstname').value,
                    last_name: document.getElementById('lastname').value,
                    email: document.getElementById('email').value,
                    phone: document.getElementById('phone')?.value || '',
                    password: password,
                    remember_me: document.getElementById('rememberMe')?.checked || false
                };

                await AuthAPI.register(userData);
                showSuccess('Регистрация успешна!');
                setTimeout(() => window.location.href = 'dashboard.php', 1000);
            } catch (error) {
                if (error.data?.errors) {
                    Object.keys(error.data.errors).forEach(key => {
                        const input = document.getElementById(key);
                        if (input) {
                            input.style.borderColor = '#eacaca';
                        }
                    });
                }
                showError(error.message || 'Ошибка регистрации');
            }
        });
    }
});

function showError(message) {
    const errorDiv = document.getElementById('errorMessage');
    if (errorDiv) {
        errorDiv.textContent = message;
        errorDiv.style.display = 'block';
        setTimeout(() => errorDiv.style.display = 'none', 3000);
    } else {
        alert(message);
    }
}

function showSuccess(message) {
    const successDiv = document.getElementById('successMessage');
    if (successDiv) {
        successDiv.textContent = message;
        successDiv.style.display = 'block';
        setTimeout(() => successDiv.style.display = 'none', 2000);
    }
}