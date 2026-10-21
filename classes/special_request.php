<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

class SpecialRequest {
    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo =$pdo;
        $this->initTable();
    }

    private function initTable() {
        try {
            $sql = "CREATE TABLE IF NOT EXISTS `special_requests` (
              `request_id` INT(11) NOT NULL AUTO_INCREMENT,
              `user_id` INT(11) NOT NULL,
              `request_date` DATE NOT NULL,
              `waste_type` VARCHAR(255) NOT NULL,
              `description` TEXT DEFAULT NULL,
              `status` VARCHAR(50) DEFAULT 'Pending',
              `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`request_id`),
              KEY `user_id` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
            $this->pdo->exec($sql);
        } catch (PDOException $e) {
            error_log("Error creating special_requests table: " . $e->getMessage());
        }
    }

    // TRANSACTION 3: Add Special Request with Database Transaction & Activity Log
    public function addSpecialRequestWithTransaction($user_id, $request_date, $waste_type, $description) {
        try {
            $this->pdo->beginTransaction();

            $sql = "INSERT INTO special_requests (user_id, request_date, waste_type, description, status) 
                    VALUES (:user_id, :request_date, :waste_type, :description, 'Pending')";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':user_id' => $user_id,
                ':request_date' => $request_date,
                ':waste_type' => $waste_type,
                ':description' => $description
            ]);

            // Log activity inside transaction
            $log_stmt = $this->pdo->prepare("INSERT INTO activity_logs (user_id, action, details) VALUES (?, ?, ?)");
            $log_stmt->execute([$user_id, 'Create Special Request', "Submitted special request for $waste_type"]);

            $this->pdo->commit();
            return true;
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            error_log("Transaction 3 error: " . $e->getMessage());
            return false;
        }
    }

    public function getSpecialRequestsByUserId($user_id) {
        try {
            $sql = "SELECT * FROM special_requests WHERE user_id = :user_id ORDER BY request_date DESC";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':user_id' => $user_id]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error fetching special requests: " . $e->getMessage());
            return [];
        }
    }

    public function deleteSpecialRequest($request_id, $user_id) {
        try {
            $sql = "DELETE FROM special_requests WHERE request_id = :request_id AND user_id = :user_id";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([
                ':request_id' => $request_id,
                ':user_id' => $user_id
            ]);
        } catch (PDOException $e) {
            error_log("Error deleting special request: " . $e->getMessage());
            return false;
        }
    }
}

$specialRequestObj = new SpecialRequest($conn);
$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'] ?? 'User';
$user_role = $_SESSION['user_role'] ?? 'Resident';
$initial = strtoupper(substr((string)$full_name,0,1));
$message = '';
$error = '';
$csrf_token = $_SESSION['csrf_token'] ?? '';

function validCsrfToken($posted) {
    return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string)$posted);
}

// Handle Delete Request
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $delete_id = intval($_GET['id']);
    if (!validCsrfToken($_GET['token'] ?? '')) {
        $error = "Invalid security token. Please reload the page and try again.";
    } elseif ($specialRequestObj->deleteSpecialRequest($delete_id, $user_id)) {
        header("Location: special_request.php?msg=deleted");
        exit();
    } else {
        $error = "Error deleting request.";
    }
}

// Handle New Submission Request using Transaction 3
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_request'])) {
    $request_date = $_POST['request_date'] ?? '';
    $waste_type = trim($_POST['waste_type'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if (!validCsrfToken($_POST['csrf_token'] ?? '')) {$error = "Invalid security token. Please reload the page and try again.";
    } elseif (!empty($request_date) && !empty($waste_type)) {
        if ($specialRequestObj->addSpecialRequestWithTransaction($user_id, $request_date,$waste_type, $description)) {$message = "Special pickup request submitted successfully!";
        } else {
            $error = "Error submitting request.";
        }
    } else {
        $error = "Please fill in all required fields.";
    }
}

if (isset($_GET['msg']) && $_GET['msg'] === 'deleted') {$message = "Request deleted successfully!";
}

$requests = $specialRequestObj->getSpecialRequestsByUserId($user_id);

function statusClass($s) {
  $s = strtolower((string)$s);
  if (in_array($s, ['pending'])) return 'badge-pending';
  if (in_array($s, ['scheduled','approved','investigating'])) return 'badge-scheduled';
  if (in_array($s, ['resolved','completed'])) return 'badge-completed';
  if (in_array($s, ['cancelled','dismissed'])) return 'badge-cancelled';
  return 'badge-neutral';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Special Pickup Request - Waste Assistant</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <header class="site-header">
        <div class="site-header-inner">
            <a class="brand" href="index.php">
                <span class="brand-mark"><i class="fa-solid fa-recycle"></i></span>
                <span><h1>Waste Assistant</h1><small>Clean community, made simple</small></span>
            </a>
            <button class="nav-toggle" onclick="document.querySelector('.main-nav').classList.toggle('open')"><i class="fa-solid fa-bars"></i></button>
            <nav class="main-nav">
                <a href="index.php"><i class="fa-solid fa-house"></i> Home</a>
                <a href="schedule.php"><i class="fa-solid fa-calendar-days"></i> Schedules</a>
                <a href="special_request.php" class="active"><i class="fa-solid fa-truck-fast"></i> Special Pickup</a>
                <a href="report_missed.php"><i class="fa-solid fa-triangle-exclamation"></i> Report Missed</a>
                <a href="logout.php" class="user-pill"><span class="avatar"><?= htmlspecialchars($initial) ?></span><span class="uname"><?= htmlspecialchars((string)$full_name) ?></span><i class="fa-solid fa-right-from-bracket"></i></a>
            </nav>
        </div>
    </header>

    <div class="container">
        <h1 class="page-title">Special Pickup</h1>
        <p class="page-sub">Request bulky, e-waste, or yard waste collection. We’ll handle the heavy lifting.</p>

        <?php if (!empty($message)): ?>
            <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="header-action">
                <h2><i class="fa-solid fa-truck-fast" style="color:#16a34a"></i> Request Special Pickup</h2>
            </div>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <div class="form-row">
                    <div class="form-group">
                        <label for="request_date"><i class="fa-regular fa-calendar"></i> Preferred Pickup Date</label>
                        <input type="date" name="request_date" id="request_date" required min="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="form-group">
                        <label for="waste_type"><i class="fa-solid fa-boxes-stacked"></i> Waste Type / Category</label>
                        <select name="waste_type" id="waste_type" required>
                            <option value="">Select Waste Type</option>
                            <option value="Bulky Waste (Furniture, Appliances)">Bulky Waste (Furniture, Appliances)</option>
                            <option value="Electronic Waste (E-Waste)">Electronic Waste (E-Waste)</option>
                            <option value="Construction / Renovation Debris">Construction / Renovation Debris</option>
                            <option value="Hazardous Waste">Hazardous Waste</option>
                            <option value="Yard / Garden Waste">Yard / Garden Waste</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="description"><i class="fa-regular fa-note-sticky"></i> Description &amp; Location Notes</label>
                    <textarea name="description" id="description" placeholder="Describe the items (e.g. 1 sofa, 2 tires) and specify exact pickup instructions..."></textarea>
                </div>

                <button type="submit" name="submit_request" class="btn"><i class="fa-solid fa-paper-plane"></i> Submit Special Request</button>
            </form>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fa-solid fa-list"></i> Your Special Pickup Requests (<?= count($requests) ?>)</h3>
            </div>
            <?php if (count($requests) > 0): ?>
                <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Requested Date</th>
                            <th>Waste Type</th>
                            <th>Description</th>
                            <th>Status</th>
                            <th style="text-align:right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requests as$row): ?>
                            <?php 
                                $status =$row['status'] ?? 'Pending';
                            ?>
                            <tr>
                                <td style="white-space:nowrap"><?= htmlspecialchars((string)($row['request_date'] ?? '')) ?></td>
                                <td><?= htmlspecialchars((string)($row['waste_type'] ?? '')) ?></td>
                                <td style="max-width:280px"><?= htmlspecialchars((string)($row['description'] ?? '')) ?></td>
                                <td><span class="badge <?= statusClass($status) ?>"><?= htmlspecialchars((string)$status) ?></span></td>
                                <td style="text-align:right;white-space:nowrap">
                                    <a href="special_request.php?action=delete&id=<?= $row['request_id'] ?>&token=<?= urlencode($csrf_token) ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this request?');"><i class="fa-solid fa-trash"></i> Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php else: ?>
                <div class="empty">
                    <div class="empty-icon"><i class="fa-solid fa-box-open"></i></div>
                    <strong>No special requests yet</strong>
                    <p>Need a bulky or e-waste pickup? Create one in seconds.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

</body>
</html>