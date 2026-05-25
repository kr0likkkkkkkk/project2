<?php
error_reporting(0);
session_start();

require_once 'functions.php';

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    die("Ошибка безопасности");
}

$pdo = getDB();
if (!$pdo) {
    die("Ошибка подключения к базе данных");
}

$data = $_POST;
$data['languages'] = $_POST['languages'] ?? [];

$errors = validateAll($data, $pdo);

// Если есть ошибки - сохраняем в Cookies и возвращаем на форму
if (!empty($errors)) {
    setcookie('errors', json_encode($errors, JSON_UNESCAPED_UNICODE), 0, '/');
    setcookie('form_data', json_encode($data, JSON_UNESCAPED_UNICODE), 0, '/');
    header('Location: index.php#anketa');
    exit();
}

$edit_id = $_POST['edit_id'] ?? null;

if ($edit_id) {
    // Редактирование существующего пользователя
    updateUser($edit_id, $data, $pdo);
    
    // Сохраняем данные в Cookies на 1 год
    setcookie('saved_form_data', json_encode($data, JSON_UNESCAPED_UNICODE), time() + 365 * 24 * 60 * 60, '/');
    
    $_SESSION['login_success'] = "Данные успешно обновлены!";
    header('Location: index.php#anketa');
    exit();
} else {
    // Новый пользователь
    $result = createUser($data, $pdo);
    
    // Сохраняем данные в Cookies на 1 год
    setcookie('saved_form_data', json_encode($data, JSON_UNESCAPED_UNICODE), time() + 365 * 24 * 60 * 60, '/');
    
    // Временные учётные данные для отображения
    setcookie('temp_credentials', json_encode($result, JSON_UNESCAPED_UNICODE), 0, '/');
    
    header('Location: index.php?success=1&credentials=1#anketa');
    exit();
}
?>