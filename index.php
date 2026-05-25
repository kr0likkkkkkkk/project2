<?php
error_reporting(0);
session_start();

require_once 'functions.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$pdo = getDB();
$languages = [];
if ($pdo) {
    initDatabase($pdo);
    $languages = getLanguages($pdo);
}

$edit_id = $_SESSION['edit_id'] ?? null;
$user_data = null;
$user_languages = [];

if ($edit_id && $pdo) {
    $user_data = getUserById($edit_id, $pdo);
    if ($user_data) {
        $user_languages = $user_data['languages'] ?? [];
    }
}

// Чтение сохранённых Cookies с данными формы (на 1 год) - если нет данных из БД
$saved_form_data = [];
if (isset($_COOKIE['saved_form_data'])) {
    $saved_form_data = json_decode($_COOKIE['saved_form_data'], true) ?: [];
}

if (!$edit_id && empty($user_data) && !empty($saved_form_data)) {
    $user_data = $saved_form_data;
    $user_languages = $saved_form_data['languages'] ?? [];
}

// Чтение ошибок из Cookies
$errors = [];
if (isset($_COOKIE['errors'])) {
    $errors = json_decode($_COOKIE['errors'], true) ?: [];
    setcookie('errors', '', time() - 3600, '/');
}

// Чтение временных данных формы (при ошибках)
$temp_form_data = [];
if (isset($_COOKIE['form_data'])) {
    $temp_form_data = json_decode($_COOKIE['form_data'], true) ?: [];
    setcookie('form_data', '', time() - 3600, '/');
}

// Если есть временные данные - используем их
if (!empty($temp_form_data) && !$edit_id) {
    $user_data = $temp_form_data;
    $user_languages = $temp_form_data['languages'] ?? [];
}

// Сообщения об успехе
$success_message = '';
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $success_message = 'Данные успешно сохранены!';
}
if (isset($_SESSION['login_success'])) {
    $success_message = $_SESSION['login_success'];
    unset($_SESSION['login_success']);
}

// Временные учётные данные
$temp_credentials = [];
if (isset($_COOKIE['temp_credentials'])) {
    $temp_credentials = json_decode($_COOKIE['temp_credentials'], true) ?: [];
    setcookie('temp_credentials', '', time() - 3600, '/');
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WebSphere Studio - Создаем цифровые площадки для вашего бизнеса</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Roboto:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    
    <style>
        /* Дополнительные стили для формы */
        .form-section {
            background: linear-gradient(135deg, #f5f7fa 0%, #e9ecef 100%);
        }
        .anketa-form, .login-form-box {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .anketa-form .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        .anketa-form .half-width {
            flex: 1;
        }
        .anketa-form .form-group {
            margin-bottom: 20px;
        }
        .anketa-form label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
        }
        .anketa-form .required:after {
            content: " *";
            color: #e74c3c;
        }
        .anketa-form input, .anketa-form select, .anketa-form textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
        }
        .anketa-form input.error-field, .anketa-form select.error-field, .anketa-form textarea.error-field {
            border-color: #e74c3c;
            background-color: #fff8f8;
        }
        .anketa-form input:focus, .anketa-form select:focus, .anketa-form textarea:focus {
            border-color: #3498db;
            outline: none;
        }
        .anketa-form select[multiple] {
            height: 150px;
        }
        .anketa-form .radio-group {
            display: flex;
            gap: 30px;
            flex-wrap: wrap;
        }
        .anketa-form .radio-group label {
            font-weight: normal;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .anketa-form .checkbox-group {
            margin: 20px 0;
        }
        .anketa-form .submit-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
        }
        .anketa-form .submit-btn:hover {
            transform: translateY(-2px);
        }
        .login-btn {
            background: #4caf50 !important;
        }
        .logout-btn {
            background: #f44336 !important;
        }
        .admin-link {
            text-align: center;
            margin-top: 20px;
        }
        .admin-btn {
            display: inline-block;
            background: #2c3e50;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
        }
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #28a745;
        }
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #dc3545;
        }
        .error-message ul {
            margin-top: 10px;
            margin-left: 20px;
        }
        .info-message {
            background: #e3f2fd;
            color: #1565c0;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #2196f3;
        }
        .credentials-message {
            background: #fff3e0;
            border-left: 4px solid #ff9800;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .field-error-message {
            color: #e74c3c;
            font-size: 12px;
            margin-top: 5px;
            display: block;
        }
        .hint {
            display: block;
            margin-top: 8px;
            font-size: 12px;
            color: #7f8c8d;
            background: #f8f9fa;
            padding: 5px 10px;
            border-radius: 5px;
        }
        .hint strong {
            color: #3498db;
        }
        @media (max-width: 768px) {
            .anketa-form .form-row { flex-direction: column; gap: 0; }
            .anketa-form .radio-group { flex-direction: column; gap: 10px; }
        }
    </style>
</head>
<body>
    <header class="header">
        <video autoplay muted loop playsinline id="header-video">
            <source src="video.mp4" type="video/mp4">
        </video>
        <div class="video-overlay"></div>
        
        <nav class="navbar">
            <div class="container">
                <a href="#" class="logo">WebSphere<span>Studio</span></a>
                <ul class="nav-menu">
                    <li><a href="#services">Услуги</a></li>
                    <li class="dropdown">
                        <a href="#solutions">Решения <i class="fas fa-chevron-down"></i></a>
                        <ul class="dropdown-menu">
                            <li><a href="#ecommerce">E-commerce</a></li>
                            <li><a href="#portals">Корпоративные порталы</a></li>
                            <li><a href="#crm">Интеграция CRM</a></li>
                            <li><a href="#migration">Миграция</a></li>
                        </ul>
                    </li>
                    <li><a href="#about">О нас</a></li>
                    <li><a href="#anketa">Анкета</a></li>
                    <li><a href="#projects">Проекты</a></li>
                    <li><a href="#contact">Контакты</a></li>
                </ul>
                <div class="mobile-menu-btn">
                    <i class="fas fa-bars"></i>
                </div>
            </div>
        </nav>
        
        <div class="mobile-menu">
            <div class="mobile-menu-header">
                <a href="#" class="logo">WebSphere<span>Studio</span></a>
                <div class="mobile-menu-close"><i class="fas fa-times"></i></div>
            </div>
            <ul class="mobile-nav">
                <li><a href="#services">Услуги</a></li>
                <li class="mobile-dropdown">
                    <a href="#solutions">Решения <i class="fas fa-chevron-down"></i></a>
                    <ul class="mobile-submenu">
                        <li><a href="#ecommerce">E-commerce</a></li>
                        <li><a href="#portals">Корпоративные порталы</a></li>
                        <li><a href="#crm">Интеграция CRM</a></li>
                        <li><a href="#migration">Миграция</a></li>
                    </ul>
                </li>
                <li><a href="#about">О нас</a></li>
                <li><a href="#anketa">Анкета</a></li>
                <li><a href="#projects">Проекты</a></li>
                <li><a href="#contact">Контакты</a></li>
            </ul>
        </div>
        
        <div class="hero-content">
            <div class="container">
                <h1>Создаем цифровые площадки для вашего бизнеса</h1>
                <p>Веб-разработка, e-commerce, корпоративные порталы, цифровая трансформация бизнеса</p>
                <a href="#anketa" class="btn">Стать разработчиком</a>
            </div>
        </div>
    </header>

    <main>
        <section id="services" class="section services">
            <div class="container">
                <h2 class="section-title">Наши услуги</h2>
                <p class="section-subtitle">Полный цикл разработки цифровых решений</p>
                <div class="services-grid">
                    <div class="service-card">
                        <div class="service-icon"><i class="fas fa-code"></i></div>
                        <h3>Веб-разработка</h3>
                        <p>Создание сайтов и веб-приложений</p>
                    </div>
                    <div class="service-card">
                        <div class="service-icon"><i class="fas fa-shopping-cart"></i></div>
                        <h3>E-commerce</h3>
                        <p>Интернет-магазины и платформы</p>
                    </div>
                    <div class="service-card">
                        <div class="service-icon"><i class="fas fa-building"></i></div>
                        <h3>Корпоративные порталы</h3>
                        <p>Внутренние системы управления</p>
                    </div>
                    <div class="service-card">
                        <div class="service-icon"><i class="fas fa-sync-alt"></i></div>
                        <h3>Цифровая трансформация</h3>
                        <p>Модернизация IT-инфраструктуры</p>
                    </div>
                </div>
            </div>
        </section>

        <section id="about" class="section about">
            <div class="container">
                <h2 class="section-title">О WebSphere Studio</h2>
                <p class="section-subtitle">Современная студия с акцентом на качество, скорость и результат</p>
                <div class="about-content">
                    <div class="about-text">
                        <p>Мы создаем цифровые решения для бизнеса любого масштаба. Наша специализация — создание комплексных веб-платформ, которые приносят реальную прибыль.</p>
                        <p>За 7 лет работы мы реализовали более 150 проектов, помогая компаниям увеличивать продажи и оптимизировать процессы.</p>
                    </div>
                    <div class="about-stats">
                        <div class="stat"><div class="stat-number">150+</div><div class="stat-text">Проектов</div></div>
                        <div class="stat"><div class="stat-number">7 лет</div><div class="stat-text">Опыта</div></div>
                        <div class="stat"><div class="stat-number">95%</div><div class="stat-text">Рекомендуют</div></div>
                    </div>
                </div>
            </div>
        </section>

        <section id="anketa" class="section form-section">
            <div class="container">
                <h2 class="section-title">Анкета разработчика</h2>
                <p class="section-subtitle">Присоединяйтесь к нашей программе</p>
                
                <?php if ($success_message): ?>
                    <div class="success-message">✅ <?= htmlspecialchars($success_message) ?></div>
                <?php endif; ?>
                
                <?php if (!empty($temp_credentials)): ?>
                    <div class="credentials-message">
                        <strong>⚠️ Сохраните ваши данные для входа!</strong><br>
                        Логин: <strong><?= htmlspecialchars($temp_credentials['login']) ?></strong><br>
                        Пароль: <strong><?= htmlspecialchars($temp_credentials['password']) ?></strong><br>
                        <small>Для редактирования данных используйте эти данные в форме входа ниже.</small>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($errors)): ?>
                    <div class="error-message">
                        <strong>Пожалуйста, исправьте следующие ошибки:</strong>
                        <ul>
                            <?php foreach ($errors as $field => $error): ?>
                                <li><?= htmlspecialchars($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <?php if ($edit_id && $user_data): ?>
                    <div class="info-message">Вы авторизованы. Редактируйте свои данные.</div>
                <?php endif; ?>
                
                <form class="anketa-form" action="save.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="edit_id" id="edit_id" value="<?= $edit_id ?>">
                    
                    <div class="form-row">
                        <div class="form-group half-width">
                            <label class="required">ФИО</label>
                            <input type="text" name="full_name" id="full_name" 
                                   class="<?= isset($errors['full_name']) ? 'error-field' : '' ?>"
                                   value="<?= htmlspecialchars($user_data['full_name'] ?? '') ?>" 
                                   placeholder="Иванов Иван Иванович">
                            <?php if (isset($errors['full_name'])): ?>
                                <span class="field-error-message"><?= htmlspecialchars($errors['full_name']) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="form-group half-width">
                            <label class="required">Телефон</label>
                            <input type="tel" name="phone" id="phone"
                                   class="<?= isset($errors['phone']) ? 'error-field' : '' ?>"
                                   value="<?= htmlspecialchars($user_data['phone'] ?? '') ?>" 
                                   placeholder="+7 (123) 456-78-90">
                            <?php if (isset($errors['phone'])): ?>
                                <span class="field-error-message"><?= htmlspecialchars($errors['phone']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group half-width">
                            <label class="required">Email</label>
                            <input type="email" name="email" id="email"
                                   class="<?= isset($errors['email']) ? 'error-field' : '' ?>"
                                   value="<?= htmlspecialchars($user_data['email'] ?? '') ?>" 
                                   placeholder="example@mail.com">
                            <?php if (isset($errors['email'])): ?>
                                <span class="field-error-message"><?= htmlspecialchars($errors['email']) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="form-group half-width">
                            <label class="required">Дата рождения</label>
                            <input type="date" name="birth_date" id="birth_date"
                                   class="<?= isset($errors['birth_date']) ? 'error-field' : '' ?>"
                                   value="<?= htmlspecialchars($user_data['birth_date'] ?? '') ?>">
                            <?php if (isset($errors['birth_date'])): ?>
                                <span class="field-error-message"><?= htmlspecialchars($errors['birth_date']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="required">Пол</label>
                        <div class="radio-group">
                            <label><input type="radio" name="gender" value="male" <?= (($user_data['gender'] ?? '') == 'male') ? 'checked' : '' ?>> Мужской</label>
                            <label><input type="radio" name="gender" value="female" <?= (($user_data['gender'] ?? '') == 'female') ? 'checked' : '' ?>> Женский</label>
                            <label><input type="radio" name="gender" value="other" <?= (($user_data['gender'] ?? '') == 'other') ? 'checked' : '' ?>> Другой</label>
                        </div>
                        <?php if (isset($errors['gender'])): ?>
                            <span class="field-error-message"><?= htmlspecialchars($errors['gender']) ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label class="required">Любимые языки программирования</label>
                        <select name="languages[]" id="languages" multiple
                                class="<?= isset($errors['languages']) ? 'error-field' : '' ?>">
                            <?php foreach ($languages as $lang): ?>
                                <option value="<?= $lang['id'] ?>" <?= in_array($lang['id'], $user_languages) ? 'selected' : '' ?>><?= htmlspecialchars($lang['language_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span class="hint">Удерживайте клавишу <strong>Ctrl</strong> (Windows) или <strong>Cmd</strong> (Mac) для выбора нескольких языков</span>
                        <?php if (isset($errors['languages'])): ?>
                            <span class="field-error-message"><?= htmlspecialchars($errors['languages']) ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label>Биография</label>
                        <textarea name="biography" id="biography" rows="4"
                                  class="<?= isset($errors['biography']) ? 'error-field' : '' ?>"
                                  placeholder="Расскажите немного о себе..."><?= htmlspecialchars($user_data['biography'] ?? '') ?></textarea>
                        <?php if (isset($errors['biography'])): ?>
                            <span class="field-error-message"><?= htmlspecialchars($errors['biography']) ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="checkbox-group">
                        <label>
                            <input type="checkbox" name="agreed_to_contract" value="1" id="agree" <?= (($user_data['agreed_to_contract'] ?? 0) == 1) ? 'checked' : '' ?>>
                            Я ознакомлен(а) с условиями контракта
                        </label>
                        <?php if (isset($errors['agreed_to_contract'])): ?>
                            <span class="field-error-message"><?= htmlspecialchars($errors['agreed_to_contract']) ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <button type="submit" class="submit-btn"><?= $edit_id ? 'Обновить данные' : 'Отправить анкету' ?></button>
                </form>
                
                <div class="login-form-box">
                    <h3>Вход для редактирования</h3>
                    <form action="login.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <div class="form-row">
                            <div class="form-group half-width">
                                <input type="text" name="login" placeholder="Логин">
                            </div>
                            <div class="form-group half-width">
                                <input type="password" name="password" placeholder="Пароль">
                            </div>
                        </div>
                        <button type="submit" class="submit-btn login-btn">Войти</button>
                    </form>
                    
                    <?php if ($edit_id): ?>
                        <form action="logout.php" method="POST" style="margin-top: 15px;">
                            <button type="submit" class="submit-btn logout-btn">Выйти</button>
                        </form>
                    <?php endif; ?>
                </div>
                
                <div class="admin-link">
                    <a href="admin.php" class="admin-btn">Админ-панель</a>
                </div>
            </div>
        </section>

        <section id="projects" class="section projects">
            <div class="container">
                <h2 class="section-title">Наши проекты</h2>
                <p class="section-subtitle">Примеры реализованных решений</p>
                
                <div class="slider">
                    <div class="slider-track">
                        <div class="slide"><div class="slide-content"><div class="slide-icon"><i class="fas fa-shopping-cart"></i></div><h3>Интернет-магазин "ModaStyle"</h3><p>Платформа для онлайн-продаж одежды с интеграцией платежных систем и CRM. Увеличение продаж на 40% за первые 3 месяца.</p><div class="slide-tags"><span class="tag">E-commerce</span><span class="tag">Интеграция</span></div></div></div>
                        <div class="slide"><div class="slide-content"><div class="slide-icon"><i class="fas fa-building"></i></div><h3>Корпоративный портал "BuildCorp"</h3><p>Система управления проектами для строительной компании. Автоматизация документооборота для 500+ сотрудников.</p><div class="slide-tags"><span class="tag">Портал</span><span class="tag">Автоматизация</span></div></div></div>
                        <div class="slide"><div class="slide-content"><div class="slide-icon"><i class="fas fa-sync-alt"></i></div><h3>Цифровизация "FoodService"</h3><p>Модернизация IT-инфраструктуры сети ресторанов. Внедрение системы управления заказами и аналитики.</p><div class="slide-tags"><span class="tag">Трансформация</span><span class="tag">Аналитика</span></div></div></div>
                        <div class="slide"><div class="slide-content"><div class="slide-icon"><i class="fas fa-chart-line"></i></div><h3>Аналитическая платформа "RetailPro"</h3><p>Система анализа продаж для розничной сети. Визуализация данных и прогнозирование спроса.</p><div class="slide-tags"><span class="tag">Аналитика</span><span class="tag">BI</span></div></div></div>
                    </div>
                    <button class="slider-btn prev-btn"><i class="fas fa-chevron-left"></i></button>
                    <button class="slider-btn next-btn"><i class="fas fa-chevron-right"></i></button>
                    <div class="slider-dots"><span class="dot active" data-slide="0"></span><span class="dot" data-slide="1"></span><span class="dot" data-slide="2"></span><span class="dot" data-slide="3"></span></div>
                </div>
            </div>
        </section>

        <section id="contact" class="section contact">
            <div class="container">
                <h2 class="section-title">Свяжитесь с нами</h2>
                <p class="section-subtitle">Обсудим ваш проект и предложим решение</p>
                <div class="contact-container">
                    <div class="contact-info">
                        <h3>Контакты</h3>
                        <div class="contact-item"><i class="fas fa-map-marker-alt"></i><div><h4>Адрес</h4><p>г. Краснодар, ул. Технологическая, 42</p></div></div>
                        <div class="contact-item"><i class="fas fa-phone"></i><div><h4>Телефон</h4><p>+7 (861) 123-45-67</p></div></div>
                        <div class="contact-item"><i class="fas fa-envelope"></i><div><h4>Email</h4><p>info@websphere-studio.ru</p></div></div>
                    </div>
                    <div class="contact-form">
                        <h3>Оставить заявку</h3>
                        <form id="contactForm">
                            <div class="form-group"><input type="text" placeholder="Ваше имя" required></div>
                            <div class="form-group"><input type="email" placeholder="Email" required></div>
                            <div class="form-group"><input type="tel" placeholder="Телефон"></div>
                            <div class="form-group"><textarea placeholder="Опишите ваш проект" rows="4"></textarea></div>
                            <button type="submit" class="btn">Отправить заявку</button>
                        </form>
                        <div id="contactFormMessage"></div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-logo"><a href="#" class="logo">WebSphere<span>Studio</span></a><p>Создаем цифровые площадки для вашего бизнеса</p></div>
                <div class="footer-info"><p>WebSphere Studio © 2023</p><p>г. Краснодар, ул. Технологическая, 42</p><p>info@websphere-studio.ru</p></div>
            </div>
        </div>
    </footer>

    <script src="script.js"></script>
    <script>
        // Прокрутка к форме после перезагрузки
        if (window.location.hash === '#anketa' || window.location.search.includes('success') || window.location.search.includes('credentials')) {
            var formSection = document.getElementById('anketa');
            if (formSection) {
                setTimeout(function() {
                    formSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }, 100);
            }
        }
    </script>
</body>
</html>