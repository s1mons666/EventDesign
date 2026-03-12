<?php
// public/test-api.php - тест API
?>
<!DOCTYPE html>
<html>
<head>
    <title>Test API</title>
</head>
<body>
    <h2>Test API Connection</h2>
    
    <div id="result"></div>
    
    <script>
        async function testAPI() {
            const result = document.getElementById('result');
            
            try {
                // Тест 1: Проверяем, существует ли api.php
                result.innerHTML += '<p>Testing api.php existence...</p>';
                const response1 = await fetch('/api.php');
                result.innerHTML += '<p>Status: ' + response1.status + '</p>';
                
                if (response1.status === 404) {
                    result.innerHTML += '<p style="color:red">❌ api.php not found!</p>';
                    return;
                }
                
                // Тест 2: Проверяем API с неправильным маршрутом
                result.innerHTML += '<p>Testing API with invalid route...</p>';
                const response2 = await fetch('/api.php?route=test');
                const data2 = await response2.json();
                result.innerHTML += '<p>Response: ' + JSON.stringify(data2) + '</p>';
                
                // Тест 3: Проверяем правильный маршрут
                result.innerHTML += '<p>Testing API with valid route...</p>';
                const token = localStorage.getItem('token');
                
                if (!token) {
                    result.innerHTML += '<p style="color:orange">⚠️ No token found. Please login first.</p>';
                    result.innerHTML += '<p><a href="login.php">Go to Login</a></p>';
                    return;
                }
                
                const response3 = await fetch('/api.php?route=events/index', {
                    headers: {
                        'Authorization': 'Bearer ' + token
                    }
                });
                const data3 = await response3.json();
                result.innerHTML += '<p>Response: ' + JSON.stringify(data3) + '</p>';
                
            } catch (error) {
                result.innerHTML += '<p style="color:red">Error: ' + error.message + '</p>';
            }
        }
        
        testAPI();
    </script>
</body>
</html>