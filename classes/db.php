<?php
// Auto-detect: local (XAMPP) vs live (InfinityFree)
$is_live = isset($_SERVER['HTTP_HOST']) && (
    strpos($_SERVER['HTTP_HOST'], 'infinityfreeapp.com') !== false ||
    strpos($_SERVER['HTTP_HOST'], 'im-pusa') !== false
);

if ($is_live) {
    // === LIVE (InfinityFree) credentials ===
    // Read from classes/config.live.php (git-ignored) or from environment variables.
    $live = [];
    $live_config = __DIR__ . '/config.live.php';
    if (is_file($live_config)) {
        $live = (array) require $live_config;
    }
    $host     = getenv('DB_HOST')     ?: ($live['host']     ?? '');
    $user     = getenv('DB_USER')     ?: ($live['user']     ?? '');
    $password = getenv('DB_PASS')     ?: ($live['password'] ?? '');
    $database = getenv('DB_NAME')     ?: ($live['database'] ?? '');
    $port     = (int)(getenv('DB_PORT') ?: ($live['port'] ?? 3306));
} else {
    // === Local XAMPP ===
    $host = "localhost";
    $user = "root";
    $password = "";
    $database = "Pusa_IM";
    $port = 3306;
}

$dsn = "mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $conn = new PDO($dsn,$user, $password,$options);
} catch (PDOException $e) {
    error_log("Database Connection Failed: " . $e->getMessage());
    die("Database Connection Failed. Please try again later.");
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['csrf_token'])) {$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Auto-create all core tables to prevent fatal errors on fresh databases
try {
    $conn->exec("CREATE TABLE IF NOT EXISTS `users` (
      `user_id` INT(11) NOT NULL AUTO_INCREMENT,
      `full_name` VARCHAR(150) NOT NULL,
      `email` VARCHAR(190) NOT NULL,
      `password` VARCHAR(255) NOT NULL,
      `user_role` VARCHAR(50) DEFAULT 'Resident',
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`user_id`),
      UNIQUE KEY `email` (`email`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $conn->exec("CREATE TABLE IF NOT EXISTS `activity_logs` (
      `log_id` INT(11) NOT NULL AUTO_INCREMENT,
      `user_id` INT(11) DEFAULT NULL,
      `action` VARCHAR(150) NOT NULL,
      `details` TEXT DEFAULT NULL,
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`log_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $conn->exec("CREATE TABLE IF NOT EXISTS `schedules` (
      `schedule_id` INT(11) NOT NULL AUTO_INCREMENT,
      `zone_name` VARCHAR(100) NOT NULL,
      `waste_type` VARCHAR(100) NOT NULL,
      `collection_date` DATE NOT NULL,
      `time_window` VARCHAR(100) NOT NULL,
      `status` VARCHAR(50) DEFAULT 'Scheduled',
      PRIMARY KEY (`schedule_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $conn->exec("CREATE TABLE IF NOT EXISTS `missed_pickups` (
      `report_id` INT(11) NOT NULL AUTO_INCREMENT,
      `user_id` INT(11) NOT NULL,
      `missed_date` DATE NOT NULL,
      `report_details` TEXT DEFAULT NULL,
      `status` VARCHAR(50) DEFAULT 'Pending',
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`report_id`),
      KEY `user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $conn->exec("CREATE TABLE IF NOT EXISTS `special_requests` (
      `request_id` INT(11) NOT NULL AUTO_INCREMENT,
      `user_id` INT(11) NOT NULL,
      `request_date` DATE NOT NULL,
      `waste_type` VARCHAR(255) NOT NULL,
      `description` TEXT DEFAULT NULL,
      `status` VARCHAR(50) DEFAULT 'Pending',
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`request_id`),
      KEY `user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

} catch (PDOException $e) {
    error_log("Schema init failed: " . $e->getMessage());
}

function logActivity($conn,$user_id, $action,$details = '') {
    try {
        $stmt =$conn->prepare("INSERT INTO activity_logs (user_id, action, details) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $action,$details]);
    } catch (Throwable $e) {
        error_log("logActivity failed: " . $e->getMessage());
    }
}
?>