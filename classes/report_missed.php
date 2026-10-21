<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

class MissedPickup {
    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo =$pdo;
        $this->initTable();
    }

    private function initTable() {
        try {
            $sql = "CREATE TABLE IF NOT EXISTS `missed_pickups` (
              `report_id` INT(11) NOT NULL AUTO_INCREMENT,
              `user_id` INT(11) NOT NULL,
              `missed_date` DATE NOT NULL,
              `report_details` TEXT DEFAULT NULL,
              `status` VARCHAR(50) DEFAULT 'Pending',
              `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`report_id`),
              KEY `user_id` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
            $this->pdo->exec($sql);
        } catch (PDOException $e) {
            error_log("Error initializing table: " . $e->getMessage());
        }
    }

    // TRANSACTION 2: Add Report with Database Transaction & Activity Log
    public function addReportWithTransaction($user_id, $missed_date,$report_details) {
        try {
            $this->pdo->beginTransaction();

            $sql = "INSERT INTO missed_pickups (user_id, missed_date, report_details, status) 
                    VALUES (:user_id, :missed_date, :report_details, 'Pending')";
            $stmt =$this->pdo->prepare($sql);$stmt->execute([
                ':user_id' => $user_id,
                ':missed_date' => $missed_date,
                ':report_details' => $report_details
            ]);

            // Log activity inside transaction
            $log_stmt =$this->pdo->prepare("INSERT INTO activity_logs (user_id, action, details) VALUES (?, ?, ?)");
            $log_stmt->execute([$user_id, 'Report Missed Pickup', "Reported missed pickup for date: $missed_date"]);

            $this->pdo->commit();
            return true;
        } catch (PDOException $e) {$this->pdo->rollBack();
            error_log("Transaction 2 error: " . $e->getMessage());
            return false;
        }
    }

    public function getReportsByUserId($user_id) {
        try {
            $sql = "SELECT * FROM missed_pickups WHERE user_id = :user_id ORDER BY missed_date DESC";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':user_id' =>$user_id]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error fetching user reports: " . $e->getMessage());
            return [];
        }
    }

    public function deleteReport($report_id,$user_id) {
        try {
            $sql = "DELETE FROM missed_pickups WHERE report_id = :report_id AND user_id = :user_id";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([
                ':report_id' => $report_id,
                ':user_id' => $user_id
            ]);
        } catch (PDOException $e) {
            error_log("Error deleting report: " . $e->getMessage());
            return false;
        }
    }
}

$missedPickup = new MissedPickup($conn);

$user_id =$_SESSION['user_id'];
$full_name =$_SESSION['full_name'] ?? 'User';
$user_role =$_SESSION['user_role'] ?? 'Resident';
$initial = strtoupper(substr((string)$full_name,0,1));
$message = '';$error = '';
$csrf_token =$_SESSION['csrf_token'] ?? '';

function validCsrfToken($posted) {
    return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string)$posted);
}

// Handle DELETE
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $delete_id = intval($_GET['id']);
    if (!validCsrfToken($_GET['token'] ?? '')) {$error = "Invalid security token. Please reload the page and try again.";
    } elseif ($missedPickup->deleteReport($delete_id,$user_id)) {
        header("Location: report_missed.php?msg=deleted");
        exit();
    } else {
        $error = "Error deleting report.";
    }
}

// Handle CREATE (POST) using Transaction 2
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_report'])) {
    $missed_date =$_POST['missed_date'] ?? '';
    $address = trim($_POST['address'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    $combined_details = "Location: " . $address . ($notes ? " | Notes: " . $notes : "");

    if (!validCsrfToken($_POST['csrf_token'] ?? '')) {$error = "Invalid security token. Please reload the page and try again.";
    } elseif (!empty($missed_date) && !empty($address)) {
        if ($missedPickup->addReportWithTransaction($user_id,$missed_date, $combined_details)) {$message = "Missed pickup report submitted successfully!";
        } else {
            $error = "Error submitting report.";
        }
    } else {
        $error = "Please fill in all required fields.";
    }
}

if (isset($_GET['msg']) && $_GET['msg'] === 'deleted') {$message = "Report deleted successfully!";
}

$reports = $missedPickup->getReportsByUserId($user_id);

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
    <title>Report Missed Pickup - Waste Assistant</title>
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
                <a href="special_request.php"><i class="fa-solid fa-truck-fast"></i> Special Pickup</a>
                <a href="report_missed.php" class="active"><i class="fa-solid fa-triangle-exclamation"></i> Report Missed</a>
                <a href="logout.php" class="user-pill"><span class="avatar"><?= htmlspecialchars($initial) ?></span><span class="uname"><?= htmlspecialchars((string)$full_name) ?></span><i class="fa-solid fa-right-from-bracket"></i></a>
            </nav>
        </div>
    </header>

    <div class="container">
        <h1 class="page-title">Report Missed Pickup</h1>
        <p class="page-sub">Missed your collection? Let us know and we’ll investigate right away.</p>

        <?php if (!empty($message)): ?>
            <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="header-action">
                <h2><i class="fa-solid fa-triangle-exclamation" style="color:#e74c3c"></i> Submit Missed Report</h2>
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <div class="form-row">
                    <div class="form-group">
                        <label for="missed_date"><i class="fa-regular fa-calendar-xmark"></i> Missed Collection Date</label>
                        <input type="date" name="missed_date" id="missed_date" required max="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="form-group">
                        <label for="address"><i class="fa-solid fa-location-dot"></i> Pickup Location Address</label>
                        <input type="text" name="address" id="address" placeholder="e.g. 123 Mango St, Pusa" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="notes"><i class="fa-regular fa-note-sticky"></i> Additional Details / Notes</label>
                    <textarea name="notes" id="notes" placeholder="Specify bin placement, bin color, or any additional info..."></textarea>
                </div>

                <button type="submit" name="submit_report" class="btn btn-danger"><i class="fa-solid fa-triangle-exclamation"></i> Submit Report</button>
            </form>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fa-solid fa-clipboard-list"></i> Your Reported Missed Pickups (<?= count($reports) ?>)</h3>
            </div>
            <?php if (count($reports) > 0): ?>
                <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Date Missed</th>
                            <th>Details &amp; Location</th>
                            <th>Status</th>
                            <th style="text-align:right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reports as$row): ?>
                            <?php 
                                $status =$row['status'] ?? 'Pending';
                                $details =$row['report_details'] ?? '';
                            ?>
                            <tr>
                                <td style="white-space:nowrap"><?= htmlspecialchars((string)($row['missed_date'] ?? '')) ?></td>
                                <td style="max-width:320px"><?= htmlspecialchars((string)$details) ?></td>
                                <td><span class="badge <?= statusClass($status) ?>"><?= htmlspecialchars((string)$status) ?></span></td>
                                <td style="text-align:right;white-space:nowrap">
                                    <a href="report_missed.php?action=delete&id=<?= $row['report_id'] ?>&token=<?= urlencode($csrf_token) ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this report?');"><i class="fa-solid fa-trash"></i> Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php else: ?>
                <div class="empty">
                    <div class="empty-icon"><i class="fa-solid fa-circle-check"></i></div>
                    <strong>All clear!</strong>
                    <p>No missed pickup reports. Nice work.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

</body>
</html>