<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'db.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    try {
        $stmt = $conn->prepare("SELECT user_id, full_name, password, user_role FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            $stored = $user['password'];
            $is_hashed = password_get_info($stored)['algoName'] !== 'unknown';

            $valid = $is_hashed
                ? password_verify($password, $stored)
                : hash_equals($stored, $password);

            if ($valid) {
                if (!$is_hashed) {
                    $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?")
                         ->execute([password_hash($password, PASSWORD_DEFAULT), $user['user_id']]);
                }

                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['user_role'] = $user['user_role'];

                if (function_exists('logActivity')) {
                    logActivity($conn, $user['user_id'], "User Login", "User logged in successfully.");
                }

                header("Location: index.php");
                exit();
            }
        }

        $message = "Invalid email or password. Please try again.";
    } catch (PDOException $e) {
        error_log("Login error: " . $e->getMessage());
        $message = "System is temporarily unavailable. Please try again later.";
    } catch (Throwable $e) {
        error_log("Login fatal: " . $e->getMessage());
        $message = "System is temporarily unavailable. Please try again later.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Waste Assistant</title>
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
                <div style="font-size:13px;color:#64748b">Clean community, made simple</div>
            </div>
        </div>

        <h2>Welcome back 👋</h2>
        <p class="sub">Log in to manage schedules, pickups, and reports.</p>
    
        <?php if (!empty($message)): ?>
            <div class="message"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="email"><i class="fa-regular fa-envelope"></i> Email Address</label>
                <input type="email" id="email" name="email" placeholder="you@example.com" required>
            </div>
            
            <div class="form-group">
                <label for="password"><i class="fa-solid fa-lock"></i> Password</label>
                <input type="password" id="password" name="password" placeholder="Enter your password" required>
            </div>

            <button type="submit" name="login" class="btn btn-block"><i class="fa-solid fa-right-to-bracket"></i> Login</button>
        </form>

        <div class="auth-footer">
            Don't have an account? <a href="register.php">Create one</a>
        </div>
    </div>
</div>

</body>
</html>
