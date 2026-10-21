<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'db.php';

$message = '';
$is_success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $raw_password = $_POST['password'] ?? '';
    $role = 'Resident';

    if ($name === '' || $email === '') {
        $message = "Please fill in all required fields.";
    } elseif (strlen($raw_password) < 6) {
        $message = "Password must be at least 6 characters.";
    } else {
        $password = password_hash($raw_password, PASSWORD_DEFAULT);

        // TRANSACTION 1: User Registration + Activity Logging
        try {
            $conn->beginTransaction();

            $stmt = $conn->prepare("INSERT INTO users (full_name, email, password, user_role) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $email, $password, $role]);
            $new_user_id = $conn->lastInsertId();

            // Log activity inside the same transaction
            $log_stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, details) VALUES (?, ?, ?)");
            $log_stmt->execute([$new_user_id, "User Registration", "New account created successfully."]);

            $conn->commit();
            $message = "Registration successful! You can now log in.";
            $is_success = true;
        } catch (PDOException $e) {
            $conn->rollBack();
            if (($e->getCode() == 23000 || $e->getCode() == 1062)) {
                $message = "That email is already registered. Try logging in.";
            } else {
                error_log("Registration transaction error: " . $e->getMessage());
                $message = "Registration failed. Please try again later.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Waste Assistant</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="auth-wrap">
    <div class="auth-card">
        <div class="auth-brand">
            <span class="brand-mark"><i class="fa-solid fa-recycle"></i></span>
            <div>
                <div style="font-weight:800;font-size:18px;letter-spacing:-.02em">Waste Assistant</div>
                <div style="font-size:13px;color:#64748b">Join your clean community</div>
            </div>
        </div>

        <h2>Create account</h2>
        <p class="sub">Get pickup reminders, request special collections, and report misses.</p>

        <?php if (!empty($message)): ?>
            <div class="message <?= $is_success ? 'success' : '' ?>">
                <i class="fa-solid <?= $is_success ? 'fa-circle-check' : 'fa-circle-exclamation' ?>"></i>
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="full_name"><i class="fa-regular fa-user"></i> Full Name</label>
                <input type="text" id="full_name" name="full_name" placeholder="Juan Dela Cruz" required>
            </div>
            <div class="form-group">
                <label for="email"><i class="fa-regular fa-envelope"></i> Email Address</label>
                <input type="email" id="email" name="email" placeholder="you@example.com" required>
            </div>
            <div class="form-group">
                <label for="password"><i class="fa-solid fa-lock"></i> Password</label>
                <input type="password" id="password" name="password" placeholder="Min. 6 characters" required minlength="6">
            </div>
            <button type="submit" name="register" class="btn btn-block"><i class="fa-solid fa-user-plus"></i> Register</button>
        </form>

        <div class="auth-footer">
            Already have an account? <a href="login.php">Log in</a>
        </div>
    </div>
</div>

</body>
</html>