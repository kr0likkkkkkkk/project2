<?php
// functions.php - общая валидация и работа с БД

function getDB() {
    $db_host = 'localhost';
    $db_name = 'u82192';
    $db_user = 'u82192';
    $db_pass = '2307509';
    
    try {
        $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch(PDOException $e) {
        error_log($e->getMessage());
        return null;
    }
}

// Создание таблиц, если их нет
function initDatabase($pdo) {
    // Таблица админов
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS admins (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            login VARCHAR(50) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL
        )
    ");
    
    // Проверяем, есть ли админ, если нет - создаём
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM admins WHERE login = 'admin'");
    $stmt->execute();
    if ($stmt->fetchColumn() == 0) {
        $hash = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO admins (login, password_hash) VALUES ('admin', ?)");
        $stmt->execute([$hash]);
    }
    
    // Таблица языков
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS programming_languages (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            language_name VARCHAR(50) NOT NULL UNIQUE
        )
    ");
    
    // Заполняем языки, если пусто
    $stmt = $pdo->query("SELECT COUNT(*) FROM programming_languages");
    if ($stmt->fetchColumn() == 0) {
        $languages = ['Pascal', 'C', 'C++', 'JavaScript', 'PHP', 'Python', 'Java', 'Haskell', 'Clojure', 'Prolog', 'Scala', 'Go'];
        $stmt = $pdo->prepare("INSERT INTO programming_languages (language_name) VALUES (?)");
        foreach ($languages as $lang) {
            $stmt->execute([$lang]);
        }
    }
    
    // Таблица заявок
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS applications (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            full_name VARCHAR(150) NOT NULL,
            phone VARCHAR(20) NOT NULL,
            email VARCHAR(100) NOT NULL,
            birth_date DATE NOT NULL,
            gender ENUM('male', 'female', 'other') NOT NULL,
            biography TEXT,
            agreed_to_contract TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            login VARCHAR(50) UNIQUE,
            password_hash VARCHAR(255),
            is_edited TINYINT(1) DEFAULT 0
        )
    ");
    
    // Таблица связей заявок и языков
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS application_languages (
            application_id INT UNSIGNED NOT NULL,
            language_id INT UNSIGNED NOT NULL,
            PRIMARY KEY (application_id, language_id),
            FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
            FOREIGN KEY (language_id) REFERENCES programming_languages(id) ON DELETE CASCADE
        )
    ");
}

// Проверка админа
function checkAdmin($login, $password, $pdo) {
    $stmt = $pdo->prepare("SELECT password_hash FROM admins WHERE login = ?");
    $stmt->execute([$login]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($admin && password_verify($password, $admin['password_hash'])) {
        return true;
    }
    return false;
}

// Валидация ФИО с указанием допустимых символов
function validateFullName($name) {
    if (empty($name)) return 'ФИО обязательно для заполнения';
    if (strlen($name) > 150) return 'ФИО не должно превышать 150 символов';
    if (!preg_match('/^[a-zA-Zа-яА-ЯёЁ\s\-]+$/u', $name)) {
        return 'ФИО может содержать только буквы (русские или английские), пробелы и дефисы';
    }
    return null;
}

// Валидация телефона
function validatePhone($phone) {
    if (empty($phone)) return 'Телефон обязателен для заполнения';
    $clean = preg_replace('/[^0-9+]/', '', $phone);
    if (strlen($clean) < 10) return 'Телефон должен содержать минимум 10 цифр';
    if (strlen($clean) > 20) return 'Телефон не должен превышать 20 символов';
    if (!preg_match('/^[\+\d\s\(\)-]+$/', $phone)) {
        return 'Телефон может содержать цифры, +, пробелы, скобки и дефисы';
    }
    return null;
}

// Валидация email
function validateEmail($email) {
    if (empty($email)) return 'Email обязателен для заполнения';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 'Email должен быть в формате user@example.com';
    }
    return null;
}

// Валидация даты рождения с указанием допустимого возраста
function validateBirthDate($date) {
    if (empty($date)) {
        return 'Дата рождения обязательна для заполнения';
    }
    
    $timestamp = strtotime($date);
    if (!$timestamp || $timestamp > time()) {
        return 'Некорректная дата рождения';
    }
    
    $age = date('Y') - date('Y', $timestamp);
    if (date('md') < date('md', $timestamp)) {
        $age--;
    }
    
    if ($age < 18) {
        return 'Возраст должен быть не менее 18 лет (вам ' . $age . ' лет). Допустимый возраст: от 18 до 100 лет.';
    }
    
    if ($age > 100) {
        return 'Возраст не может превышать 100 лет (вам ' . $age . ' лет). Допустимый возраст: от 18 до 100 лет.';
    }
    
    return null;
}

// Валидация пола
function validateGender($gender) {
    if (empty($gender)) return 'Выберите пол';
    if (!in_array($gender, ['male', 'female', 'other'])) return 'Выберите корректный пол (мужской, женский или другой)';
    return null;
}

// Валидация языков программирования
function validateLanguages($languages, $pdo) {
    if (empty($languages) || !is_array($languages)) return 'Выберите хотя бы один язык программирования';
    $placeholders = str_repeat('?,', count($languages) - 1) . '?';
    $stmt = $pdo->prepare("SELECT id FROM programming_languages WHERE id IN ($placeholders)");
    $stmt->execute($languages);
    $valid = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (count($valid) != count($languages)) return 'Один или несколько выбранных языков не существуют';
    return null;
}

// Валидация биографии
function validateBiography($bio) {
    if (strlen($bio) > 5000) return 'Биография не должна превышать 5000 символов';
    return null;
}

// Валидация согласия с контрактом
function validateContract($agreed) {
    if (empty($agreed) || $agreed != '1') return 'Вы должны подтвердить ознакомление с контрактом';
    return null;
}

// Полная валидация всех полей
function validateAll($data, $pdo) {
    $errors = [];
    $errors['full_name'] = validateFullName($data['full_name'] ?? '');
    $errors['phone'] = validatePhone($data['phone'] ?? '');
    $errors['email'] = validateEmail($data['email'] ?? '');
    $errors['birth_date'] = validateBirthDate($data['birth_date'] ?? '');
    $errors['gender'] = validateGender($data['gender'] ?? '');
    $errors['languages'] = validateLanguages($data['languages'] ?? [], $pdo);
    $errors['biography'] = validateBiography($data['biography'] ?? '');
    $errors['agreed_to_contract'] = validateContract($data['agreed_to_contract'] ?? '');
    return array_filter($errors);
}

// Создание нового пользователя
function createUser($data, $pdo) {
    $login = 'user_' . rand(10000, 99999);
    $password = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8);
    $hash = password_hash($password, PASSWORD_DEFAULT);
    
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("INSERT INTO applications (full_name, phone, email, birth_date, gender, biography, agreed_to_contract, login, password_hash) VALUES (?,?,?,?,?,?,?,?,?)");
    $stmt->execute([
        $data['full_name'], 
        $data['phone'], 
        $data['email'], 
        $data['birth_date'], 
        $data['gender'], 
        $data['biography'] ?? '', 
        1, 
        $login, 
        $hash
    ]);
    $id = $pdo->lastInsertId();
    
    $lang_stmt = $pdo->prepare("INSERT INTO application_languages (application_id, language_id) VALUES (?, ?)");
    foreach ($data['languages'] as $lang_id) {
        $lang_stmt->execute([$id, $lang_id]);
    }
    $pdo->commit();
    
    return ['login' => $login, 'password' => $password];
}

// Обновление данных пользователя
function updateUser($id, $data, $pdo) {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("UPDATE applications SET full_name=?, phone=?, email=?, birth_date=?, gender=?, biography=?, agreed_to_contract=1 WHERE id=?");
    $stmt->execute([
        $data['full_name'], 
        $data['phone'], 
        $data['email'], 
        $data['birth_date'], 
        $data['gender'], 
        $data['biography'] ?? '', 
        $id
    ]);
    
    $pdo->prepare("DELETE FROM application_languages WHERE application_id=?")->execute([$id]);
    $lang_stmt = $pdo->prepare("INSERT INTO application_languages (application_id, language_id) VALUES (?, ?)");
    foreach ($data['languages'] as $lang_id) {
        $lang_stmt->execute([$id, $lang_id]);
    }
    $pdo->commit();
    
    return true;
}

// Получение списка языков программирования
function getLanguages($pdo) {
    $stmt = $pdo->query("SELECT id, language_name FROM programming_languages ORDER BY language_name");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Получение данных пользователя по ID
function getUserById($id, $pdo) {
    $stmt = $pdo->prepare("SELECT * FROM applications WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        $stmt = $pdo->prepare("SELECT language_id FROM application_languages WHERE application_id = ?");
        $stmt->execute([$id]);
        $user['languages'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    return $user;
}

// Статистика по языкам
function getLanguageStats($pdo) {
    $stmt = $pdo->query("
        SELECT pl.id, pl.language_name, COUNT(al.application_id) as user_count
        FROM programming_languages pl
        LEFT JOIN application_languages al ON pl.id = al.language_id
        GROUP BY pl.id
        ORDER BY user_count DESC, pl.language_name
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Получение всех заявок
function getAllApplications($pdo) {
    $stmt = $pdo->query("
        SELECT a.*, GROUP_CONCAT(pl.language_name SEPARATOR ', ') as languages
        FROM applications a
        LEFT JOIN application_languages al ON a.id = al.application_id
        LEFT JOIN programming_languages pl ON al.language_id = pl.id
        GROUP BY a.id
        ORDER BY a.id DESC
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>