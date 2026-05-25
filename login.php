<?php
error_reporting(0);
session_start();

require_once 'functions.php';

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    die("Ошибка безопасности");
}

$pdo = getDB();
if (!$pdo) {
    $_SESSION['errors'] = ['database' => 'Ошибка подключения к базе данных'];
    header('Location: index.php#anketa');
    exit();
}

$login = trim($_POST['login'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($login) || empty($password)) {
    $_SESSION['errors'] = ['login' => 'Введите логин и пароль'];
    header('Location: index.php#anketa');
    exit();
}

$stmt = $pdo->prepare("SELECT id, password_hash FROM applications WHERE login = ?");
$stmt->execute([$login]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user && password_verify($password, $user['password_hash'])) {
    $_SESSION['edit_id'] = $user['id'];
    $_SESSION['login_success'] = "Вы успешно вошли! Теперь вы можете редактировать свои данные.";
} else {
    $_SESSION['errors'] = ['login' => 'Неверный логин или пароль'];
}

header('Location: index.php#anketa');
exit();
?>