<?php
// public/logout.php
session_start();

// Очищаем сессию
$_SESSION = array();

// Удаляем cookie сессии
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

// Удаляем куки авторизации
setcookie('auth_token', '', time() - 3600, '/');

// Очищаем localStorage (через JavaScript)
echo '<script>
    localStorage.removeItem("token");
    localStorage.removeItem("user");
    window.location.href = "index.php";
</script>';
exit;
?>