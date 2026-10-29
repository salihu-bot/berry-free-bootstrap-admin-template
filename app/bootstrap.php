<?php
declare(strict_types=1);

$config = [
    'host' => getenv('FCWM_DB_HOST') ?: '127.0.0.1',
    'port' => getenv('FCWM_DB_PORT') ?: '3306',
    'name' => getenv('FCWM_DB_NAME') ?: 'fcwm_complaints',
    'user' => getenv('FCWM_DB_USER') ?: 'root',
    'password' => getenv('FCWM_DB_PASSWORD') ?: '',
];

if (!preg_match('/^[a-zA-Z0-9_]+$/', $config['name'])) {
    http_response_code(500);
    exit('The database name may contain only letters, numbers, and underscores.');
}

session_name('fcwm_support_session');
session_set_cookie_params([
    'httponly' => true,
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'samesite' => 'Lax',
]);
session_start();

try {
    $server = new PDO(
        "mysql:host={$config['host']};port={$config['port']};charset=utf8mb4",
        $config['user'],
        $config['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    $server->exec("CREATE DATABASE IF NOT EXISTS `{$config['name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo = new PDO(
        "mysql:host={$config['host']};port={$config['port']};dbname={$config['name']};charset=utf8mb4",
        $config['user'],
        $config['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $exception) {
    http_response_code(500);
    $databaseError = htmlspecialchars($exception->getMessage(), ENT_QUOTES, 'UTF-8');
    echo '<!doctype html><html lang="en"><meta charset="utf-8"><title>Database setup</title>';
    echo '<body style="font:16px Arial;max-width:760px;margin:12vh auto;padding:24px;color:#24342d">';
    echo '<h1>Could not connect to the FCWM support database</h1>';
    echo '<p>Start Apache and MySQL in XAMPP, then check the database settings in <code>app/bootstrap.php</code>.</p>';
    echo '<pre style="white-space:pre-wrap;background:#f4f6f5;padding:16px;border-radius:8px">' . $databaseError . '</pre></body></html>';
    exit;
}

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS schools (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(150) NOT NULL UNIQUE,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB"
);
$pdo->exec(
    "CREATE TABLE IF NOT EXISTS departments (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(150) NOT NULL UNIQUE,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB"
);
$pdo->exec(
    "CREATE TABLE IF NOT EXISTS users (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        full_name VARCHAR(150) NOT NULL,
        email VARCHAR(190) NOT NULL UNIQUE,
        matric_number VARCHAR(50) NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        role VARCHAR(20) NOT NULL DEFAULT 'student',
        school_id INT UNSIGNED NULL,
        department_id INT UNSIGNED NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_users_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE SET NULL,
        CONSTRAINT fk_users_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
        INDEX idx_users_role (role)
    ) ENGINE=InnoDB"
);
$pdo->exec(
    "CREATE TABLE IF NOT EXISTS tickets (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        reference_no VARCHAR(40) NOT NULL UNIQUE,
        student_id INT UNSIGNED NOT NULL,
        department_id INT UNSIGNED NULL,
        topic VARCHAR(100) NOT NULL,
        subject VARCHAR(180) NOT NULL,
        description TEXT NOT NULL,
        status VARCHAR(30) NOT NULL DEFAULT 'Submitted',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT fk_tickets_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
        CONSTRAINT fk_tickets_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
        INDEX idx_tickets_status (status),
        INDEX idx_tickets_department (department_id),
        INDEX idx_tickets_student (student_id)
    ) ENGINE=InnoDB"
);
$pdo->exec(
    "CREATE TABLE IF NOT EXISTS ticket_updates (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        ticket_id BIGINT UNSIGNED NOT NULL,
        author_id INT UNSIGNED NULL,
        status VARCHAR(30) NOT NULL,
        message TEXT NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_updates_ticket FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
        CONSTRAINT fk_updates_author FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL,
        INDEX idx_updates_ticket (ticket_id, created_at)
    ) ENGINE=InnoDB"
);

foreach ([
    'School of Wildlife Management',
    'School of Forestry',
    'School of Fisheries and Aquaculture',
    'School of Environmental Management',
    'Other / Non-academic',
] as $schoolName) {
    $statement = $pdo->prepare('INSERT IGNORE INTO schools (name) VALUES (?)');
    $statement->execute([$schoolName]);
}
foreach ([
    'Academic Affairs',
    'Student Affairs',
    'Registry',
    'ICT Support',
    'Finance and Bursary',
    'Hostel and Welfare',
    'Library',
] as $departmentName) {
    $statement = $pdo->prepare('INSERT IGNORE INTO departments (name) VALUES (?)');
    $statement->execute([$departmentName]);
}
