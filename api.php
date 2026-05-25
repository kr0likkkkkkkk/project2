<?php
error_reporting(0);
session_start();

header("Content-Type: application/json; charset=utf-8");
header("X-XSS-Protection: 1; mode=block");

$db_host = 'localhost';
$db_name = 'u82192';
$db_user = 'u82192';
$db_pass = '2307509';

$method = $_SERVER['REQUEST_METHOD'];
$path = isset($_GET['path']) ? $_GET['path'] : '';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
    
    // POST запрос - создание новой записи
    if ($method === 'POST' && $path === '') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $input = $_POST;
        }
        
        $errors = validateApplicationData($input);
        
        if (!empty($errors)) {
            echo json_encode(['success' => false, 'errors' => $errors], JSON_UNESCAPED_UNICODE);
            exit();
        }
        
        $login = 'user_' . rand(1000, 9999);
        $password = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8);
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("
            INSERT INTO applications (full_name, phone, email, birth_date, gender, biography, agreed_to_contract, login, password_hash) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $input['full_name'],
            $input['phone'],
            $input['email'],
            $input['birth_date'],
            $input['gender'],
            $input['biography'],
            isset($input['agreed_to_contract']) ? 1 : 0,
            $login,
            $password_hash
        ]);
        
        $application_id = $pdo->lastInsertId();
        
        if (!empty($input['languages'])) {
            $lang_stmt = $pdo->prepare("INSERT INTO application_languages (application_id, language_id) VALUES (?, ?)");
            foreach ($input['languages'] as $lang_id) {
                $lang_stmt->execute([$application_id, $lang_id]);
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Данные успешно сохранены',
            'login' => $login,
            'password' => $password,
            'profile_url' => 'index.php?edit_id=' . $application_id
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    // PUT запрос - обновление существующей записи (требует авторизации)
    if ($method === 'PUT' && preg_match('/^(\d+)$/', $path, $matches)) {
        if (!isset($_SESSION['edit_id']) || $_SESSION['edit_id'] != $matches[1]) {
            echo json_encode(['success' => false, 'errors' => ['auth' => 'Необходима авторизация']], JSON_UNESCAPED_UNICODE);
            exit();
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $input = $_POST;
        }
        
        $errors = validateApplicationData($input);
        
        if (!empty($errors)) {
            echo json_encode(['success' => false, 'errors' => $errors], JSON_UNESCAPED_UNICODE);
            exit();
        }
        
        $edit_id = $matches[1];
        
        $stmt = $pdo->prepare("
            UPDATE applications 
            SET full_name = ?, phone = ?, email = ?, birth_date = ?, 
                gender = ?, biography = ?, agreed_to_contract = ?, is_edited = 1
            WHERE id = ?
        ");
        
        $stmt->execute([
            $input['full_name'],
            $input['phone'],
            $input['email'],
            $input['birth_date'],
            $input['gender'],
            $input['biography'],
            isset($input['agreed_to_contract']) ? 1 : 0,
            $edit_id
        ]);
        
        $pdo->prepare("DELETE FROM application_languages WHERE application_id = ?")->execute([$edit_id]);
        
        if (!empty($input['languages'])) {
            $lang_stmt = $pdo->prepare("INSERT INTO application_languages (application_id, language_id) VALUES (?, ?)");
            foreach ($input['languages'] as $lang_id) {
                $lang_stmt->execute([$edit_id, $lang_id]);
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Данные успешно обновлены'
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    // GET запрос - получение данных записи
    if ($method === 'GET' && preg_match('/^(\d+)$/', $path, $matches)) {
        $stmt = $pdo->prepare("
            SELECT a.*, GROUP_CONCAT(al.language_id) as language_ids
            FROM applications a
            LEFT JOIN application_languages al ON a.id = al.application_id
            WHERE a.id = ?
            GROUP BY a.id
        ");
        $stmt->execute([$matches[1]]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($data) {
            $data['language_ids'] = $data['language_ids'] ? explode(',', $data['language_ids']) : [];
            unset($data['password_hash']);
            echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode(['success' => false, 'errors' => ['not_found' => 'Запись не найдена']], JSON_UNESCAPED_UNICODE);
        }
        exit();
    }
    
    echo json_encode(['success' => false, 'errors' => ['method' => 'Метод не поддерживается']], JSON_UNESCAPED_UNICODE);
    
} catch(PDOException $e) {
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'errors' => ['database' => 'Ошибка базы данных']], JSON_UNESCAPED_UNICODE);
}

// Функция валидации данных
function validateApplicationData($data) {
    $errors = [];
    
    // Валидация ФИО
    $full_name = trim($data['full_name'] ?? '');
    if (empty($full_name)) {
        $errors['full_name'] = 'ФИО обязательно для заполнения';
    } elseif (strlen($full_name) > 150) {
        $errors['full_name'] = 'ФИО не должно превышать 150 символов. Допустимые символы: буквы, пробелы, дефисы.';
    } elseif (!preg_match('/^[a-zA-Zа-яА-ЯёЁ\s\-]+$/u', $full_name)) {
        $errors['full_name'] = 'ФИО может содержать только буквы, пробелы и дефисы. Недопустимые символы: цифры, знаки препинания, спецсимволы.';
    }
    
    // Валидация телефона
    $phone = trim($data['phone'] ?? '');
    if (empty($phone)) {
        $errors['phone'] = 'Телефон обязателен для заполнения';
    } else {
        $cleanPhone = preg_replace('/[^0-9+]/', '', $phone);
        if (strlen($cleanPhone) < 10) {
            $errors['phone'] = 'Телефон должен содержать минимум 10 цифр. Допустимые символы: цифры, +, -, пробелы, скобки.';
        } elseif (strlen($cleanPhone) > 20) {
            $errors['phone'] = 'Телефон не должен превышать 20 символов. Допустимые символы: цифры, +, -, пробелы, скобки.';
        } elseif (!preg_match('/^[\+\-\s\(\)0-9]{10,20}$/', $phone)) {
            $errors['phone'] = 'Неверный формат телефона. Допустимые символы: цифры, +, -, пробелы, скобки.';
        }
    }
    
    // Валидация email
    $email = trim($data['email'] ?? '');
    if (empty($email)) {
        $errors['email'] = 'Email обязателен для заполнения';
    } elseif (strlen($email) > 100) {
        $errors['email'] = 'Email не должен превышать 100 символов';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Неверный формат email. Пример правильного email: user@example.com';
    } elseif (!preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $email)) {
        $errors['email'] = 'Email может содержать только буквы, цифры, точки, дефисы и знак @. Недопустимые символы: пробелы, спецсимволы.';
    }
    
    // Валидация даты рождения (возраст от 18 до 100 лет)
    $birth_date = $data['birth_date'] ?? '';
    if (empty($birth_date)) {
        $errors['birth_date'] = 'Дата рождения обязательна для заполнения';
    } else {
        $birth_timestamp = strtotime($birth_date);
        if (!$birth_timestamp || $birth_timestamp > time()) {
            $errors['birth_date'] = 'Некорректная дата рождения. Формат: ГГГГ-ММ-ДД';
        } else {
            $age = date('Y') - date('Y', $birth_timestamp);
            if (date('md') < date('md', $birth_timestamp)) {
                $age--;
            }
            if ($age < 18) {
                $errors['birth_date'] = 'Возраст должен быть не менее 18 лет. Допустимый возраст: от 18 до 100 лет.';
            }
            if ($age > 100) {
                $errors['birth_date'] = 'Возраст не может превышать 100 лет. Допустимый возраст: от 18 до 100 лет.';
            }
        }
    }
    
    // Валидация пола
    $gender = $data['gender'] ?? '';
    if (empty($gender)) {
        $errors['gender'] = 'Выберите пол';
    } elseif (!in_array($gender, ['male', 'female', 'other'])) {
        $errors['gender'] = 'Выберите корректный пол из предложенных вариантов: Мужской, Женский, Другой';
    }
    
    // Валидация языков
    $languages = $data['languages'] ?? [];
    if (empty($languages)) {
        $errors['languages'] = 'Выберите хотя бы один язык программирования';
    } elseif (count($languages) > 12) {
        $errors['languages'] = 'Выбрано слишком много языков (максимум 12)';
    }
    
    // Валидация биографии
    if (strlen($data['biography'] ?? '') > 5000) {
        $errors['biography'] = 'Биография не должна превышать 5000 символов';
    }
    
    // Валидация чекбокса
    if (empty($data['agreed_to_contract'])) {
        $errors['agreed_to_contract'] = 'Вы должны подтвердить ознакомление с контрактом';
    }
    
    return $errors;
}
?>