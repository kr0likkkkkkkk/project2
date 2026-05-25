<?php
session_start();
require_once 'functions.php';

$pdo = getDB();
if (!$pdo) {
    die("Ошибка подключения к базе данных");
}

initDatabase($pdo);

// HTTP Basic авторизация для админа
$auth_ok = false;

if (!empty($_SERVER['PHP_AUTH_USER']) && !empty($_SERVER['PHP_AUTH_PW'])) {
    if (checkAdmin($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'], $pdo)) {
        $auth_ok = true;
    }
}

if (!$auth_ok) {
    header('HTTP/1.1 401 Unauthorized');
    header('WWW-Authenticate: Basic realm="Admin Panel"');
    echo '<h1>401 Требуется авторизация</h1>';
    echo '<p>Введите логин и пароль администратора</p>';
    echo '<p><strong>Логин:</strong> admin<br><strong>Пароль:</strong> admin123</p>';
    exit();
}

$message = '';
$error = '';

// Удаление заявки
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    try {
        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM application_languages WHERE application_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM applications WHERE id = ?")->execute([$id]);
        $pdo->commit();
        $message = "Заявка #$id удалена";
    } catch (Exception $e) {
        $error = "Ошибка при удалении";
    }
}

// Получаем все заявки (с логином и паролем)
$applications = $pdo->query("
    SELECT a.*, GROUP_CONCAT(pl.language_name SEPARATOR ', ') as languages
    FROM applications a
    LEFT JOIN application_languages al ON a.id = al.application_id
    LEFT JOIN programming_languages pl ON al.language_id = pl.id
    GROUP BY a.id
    ORDER BY a.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

$total_users = count($applications);

// Статистика по языкам
$language_stats = $pdo->query("
    SELECT pl.id, pl.language_name, COUNT(al.application_id) as user_count
    FROM programming_languages pl
    LEFT JOIN application_languages al ON pl.id = al.language_id
    GROUP BY pl.id
    ORDER BY user_count DESC, pl.language_name
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админ-панель</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        body {
            background: linear-gradient(135deg, #f5e1f5 0%, #d4b8d4 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        .header {
            background: linear-gradient(135deg, #b83eb8 0%, #8a2be2 100%);
            padding: 20px 30px;
            border-radius: 15px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            color: white;
        }
        .header h1 {
            font-size: 24px;
        }
        .back-btn {
            background: white;
            color: #b83eb8;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
            transition: transform 0.3s;
        }
        .back-btn:hover {
            transform: translateY(-2px);
        }
        /* Блок статистики */
        .stats-section {
            background: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .stats-section h2 {
            color: #6a1b6a;
            font-size: 18px;
            margin-bottom: 15px;
            text-align: center;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 15px;
        }
        .stat-card {
            background: linear-gradient(135deg, #f5e1f5 0%, #e8c8e8 100%);
            padding: 15px;
            border-radius: 12px;
            text-align: center;
            transition: transform 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-3px);
        }
        .stat-card h3 {
            font-size: 14px;
            color: #6a1b6a;
            margin-bottom: 8px;
        }
        .stat-card .count {
            font-size: 28px;
            font-weight: bold;
            color: #b83eb8;
        }
        .total-card {
            background: linear-gradient(135deg, #b83eb8 0%, #8a2be2 100%);
        }
        .total-card h3, .total-card .count {
            color: white;
        }
        .message {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #28a745;
        }
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #dc3545;
        }
        table {
            width: 100%;
            background: white;
            border-collapse: collapse;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        th, td {
            padding: 12px 10px;
            text-align: left;
            border-bottom: 1px solid #f0e0f0;
            font-size: 13px;
        }
        th {
            background: linear-gradient(135deg, #b83eb8 0%, #8a2be2 100%);
            color: white;
            font-weight: 600;
        }
        tr:hover {
            background: #f9f0f9;
        }
        .delete-btn {
            background: #e74c3c;
            color: white;
            padding: 6px 12px;
            text-decoration: none;
            border-radius: 5px;
            font-size: 12px;
            transition: background 0.3s;
        }
        .delete-btn:hover {
            background: #c0392b;
        }
        .login-pass {
            font-family: monospace;
            font-size: 12px;
            background: #f5e1f5;
            padding: 4px 8px;
            border-radius: 5px;
            display: inline-block;
        }
        @media (max-width: 768px) {
            th, td {
                font-size: 10px;
                padding: 6px;
            }
            .header {
                flex-direction: column;
                gap: 10px;
                text-align: center;
            }
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Админ-панель</h1>
            <a href="index.php" class="back-btn">← На главную</a>
        </div>
        
        <!-- СТАТИСТИКА -->
        <div class="stats-section">
            <h2>Статистика по языкам программирования</h2>
            <div class="stats-grid">
                <div class="stat-card total-card">
                    <h3>Всего пользователей</h3>
                    <div class="count"><?= $total_users ?></div>
                </div>
                <?php foreach ($language_stats as $stat): ?>
                    <div class="stat-card">
                        <h3><?= htmlspecialchars($stat['language_name']) ?></h3>
                        <div class="count"><?= $stat['user_count'] ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <?php if ($message): ?>
            <div class="message">✅ <?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error-message">❌ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <h3 style="color: #6a1b6a; margin-bottom: 15px;">Список пользователей</h3>
        
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>ФИО</th>
                    <th>Телефон</th>
                    <th>Email</th>
                    <th>Логин</th>
                    <th>Пароль (хеш)</th>
                    <th>Языки</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($applications as $app): ?>
                    <tr>
                        <td><?= $app['id'] ?></td>
                        <td><strong><?= htmlspecialchars($app['full_name']) ?></strong></td>
                        <td><?= htmlspecialchars($app['phone']) ?></td>
                        <td><?= htmlspecialchars($app['email']) ?></td>
                        <td><span class="login-pass"><?= htmlspecialchars($app['login'] ?? '-') ?></span></td>
                        <td><span class="login-pass" style="font-size: 10px;"><?= htmlspecialchars(substr($app['password_hash'] ?? '-', 0, 20)) ?>...</span></td>
                        <td><small><?= htmlspecialchars($app['languages'] ?? '-') ?></small></td>
                        <td>
                            <a href="?delete=<?= $app['id'] ?>" class="delete-btn" onclick="return confirm('Удалить заявку #<?= $app['id'] ?>?')">🗑 Удалить</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>