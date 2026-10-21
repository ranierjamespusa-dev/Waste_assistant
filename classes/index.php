<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'db.php';

// Redirect to login if user is not authenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'] ?? 'User';
$user_role = $_SESSION['user_role'] ?? 'Resident';
$initial = strtoupper(substr((string)$full_name, 0, 1));

// Auto-create schedules table if it doesn't exist
$conn->exec("CREATE TABLE IF NOT EXISTS `schedules` (
  `schedule_id` INT(11) NOT NULL AUTO_INCREMENT,
  `zone_name` VARCHAR(100) NOT NULL,
  `waste_type` VARCHAR(100) NOT NULL,
  `collection_date` DATE NOT NULL,
  `time_window` VARCHAR(100) NOT NULL,
  `status` VARCHAR(50) DEFAULT 'Scheduled',
  PRIMARY KEY (`schedule_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Fetch upcoming schedules using PDO
$sched_stmt = $conn->prepare("SELECT * FROM schedules WHERE collection_date >= CURDATE() ORDER BY collection_date ASC LIMIT 5");
$sched_stmt->execute();
$schedules = $sched_stmt->fetchAll();

// Fetch recent missed pickup reports for the user
$missed_stmt = $conn->prepare("SELECT * FROM missed_pickups WHERE user_id = ? ORDER BY missed_date DESC LIMIT 3");
$missed_stmt->execute([$user_id]);
$missed_reports = $missed_stmt->fetchAll();

// Fetch recent special pickup requests for the user
$special_stmt = $conn->prepare("SELECT * FROM special_requests WHERE user_id = ? ORDER BY request_date DESC LIMIT 3");
$special_stmt->execute([$user_id]);
$special_requests = $special_stmt->fetchAll();

// Stats for hero cards
try {
  $c1 = $conn->prepare("SELECT COUNT(*) FROM schedules WHERE collection_date >= CURDATE()");
  $c1->execute(); $total_upcoming = (int)$c1->fetchColumn();
} catch (Exception $e) { $total_upcoming = count($schedules); }
try {
  $c2 = $conn->prepare("SELECT COUNT(*) FROM missed_pickups WHERE user_id = ?");
  $c2->execute([$user_id]); $total_missed = (int)$c2->fetchColumn();
} catch (Exception $e) { $total_missed = count($missed_reports); }
try {
  $c3 = $conn->prepare("SELECT COUNT(*) FROM special_requests WHERE user_id = ?");
  $c3->execute([$user_id]); $total_special = (int)$c3->fetchColumn();
} catch (Exception $e) { $total_special = count($special_requests); }

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
    <title>Dashboard - Waste Assistant</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <header class="site-header">
        <div class="site-header-inner">
            <a class="brand" href="index.php">
                <span class="brand-mark"><i class="fa-solid fa-recycle"></i></span>
                <span>
                    <h1>Waste Assistant</h1>
                    <small>Clean community, made simple</small>
                </span>
            </a>
            <button class="nav-toggle" onclick="document.querySelector('.main-nav').classList.toggle('open')" aria-label="Menu">
                <i class="fa-solid fa-bars"></i>
            </button>
            <nav class="main-nav">
                <a href="index.php" class="active"><i class="fa-solid fa-house"></i> Home</a>
                <a href="schedule.php"><i class="fa-solid fa-calendar-days"></i> Schedules</a>
                <a href="special_request.php"><i class="fa-solid fa-truck-fast"></i> Special Pickup</a>
                <a href="report_missed.php"><i class="fa-solid fa-triangle-exclamation"></i> Report Missed</a>
                <a href="logout.php" class="user-pill" title="Logout">
                    <span class="avatar"><?= htmlspecialchars($initial) ?></span>
                    <span class="uname"><?= htmlspecialchars((string)$full_name) ?></span>
                    <i class="fa-solid fa-right-from-bracket"></i>
                </a>
            </nav>
        </div>
    </header>

    <div class="container">
        <div class="hero">
            <span class="hero-badge"><i class="fa-solid fa-leaf"></i> <?= htmlspecialchars(date('l, M j')) ?> &bull; <?= htmlspecialchars($user_role) ?> Dashboard</span>
            <h2>Welcome back, <?= htmlspecialchars((string)$full_name) ?>!</h2>
            <p>Manage your waste collection requests, view schedules, and report missed pickups easily — all in one clean place.</p>
            <div class="quick-actions">
                <a href="report_missed.php" class="action-btn btn-red"><i class="fa-solid fa-triangle-exclamation"></i> Report Missed Pickup</a>
                <a href="special_request.php" class="action-btn btn-green"><i class="fa-solid fa-truck-fast"></i> Request Special Pickup</a>
                <a href="schedule.php" class="action-btn btn-blue"><i class="fa-solid fa-calendar-days"></i> View All Schedules</a>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon green"><i class="fa-solid fa-calendar-check"></i></div>
                <div><strong><?= $total_upcoming ?></strong><span>Upcoming collections</span></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon amber"><i class="fa-solid fa-triangle-exclamation"></i></div>
                <div><strong><?= $total_missed ?></strong><span>Missed pickup reports</span></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fa-solid fa-truck"></i></div>
                <div><strong><?= $total_special ?></strong><span>Special requests</span></div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fa-solid fa-calendar-days"></i> Upcoming Collection Schedules</h3>
                <a class="card-link" href="schedule.php">View all &rarr;</a>
            </div>
            <?php if (count($schedules) > 0): ?>
                <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Zone</th>
                            <th>Waste Type</th>
                            <th>Collection Date</th>
                            <th>Time Window</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($schedules as $row): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars((string)($row['zone_name'] ?? '')) ?></strong></td>
                                <td><?= htmlspecialchars((string)($row['waste_type'] ?? '')) ?></td>
                                <td><?= htmlspecialchars((string)($row['collection_date'] ?? '')) ?></td>
                                <td><?= htmlspecialchars((string)($row['time_window'] ?? '')) ?></td>
                                <td><span class="badge <?= statusClass($row['status'] ?? 'Scheduled') ?>"><?= htmlspecialchars((string)($row['status'] ?? 'Scheduled')) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php else: ?>
                <div class="empty">
                    <div class="empty-icon"><i class="fa-solid fa-calendar-xmark"></i></div>
                    <strong>No upcoming collections</strong>
                    <p>There are no schedules in the database yet. Check back soon.</p>
                    <a href="schedule.php" class="btn btn-sm"><i class="fa-solid fa-calendar-days"></i> Browse schedules</a>
                </div>
            <?php endif; ?>
        </div>

        <div class="grid-2">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fa-solid fa-triangle-exclamation"></i> Recent Missed Pickups</h3>
                    <a class="card-link" href="report_missed.php">Manage &rarr;</a>
                </div>
                <?php if (count($missed_reports) > 0): ?>
                    <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Details</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($missed_reports as $row): ?>
                                <tr>
                                    <td style="white-space:nowrap"><?= htmlspecialchars((string)($row['missed_date'] ?? '')) ?></td>
                                    <td><?= htmlspecialchars(mb_strimwidth((string)($row['report_details'] ?? ''),0,60,"...")) ?></td>
                                    <td><span class="badge <?= statusClass($row['status'] ?? 'Pending') ?>"><?= htmlspecialchars((string)($row['status'] ?? 'Pending')) ?></span></td>
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

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fa-solid fa-truck-fast"></i> Recent Special Requests</h3>
                    <a class="card-link" href="special_request.php">Manage &rarr;</a>
                </div>
                <?php if (count($special_requests) > 0): ?>
                    <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($special_requests as $row): ?>
                                <tr>
                                    <td style="white-space:nowrap"><?= htmlspecialchars((string)($row['request_date'] ?? '')) ?></td>
                                    <td><?= htmlspecialchars(mb_strimwidth((string)($row['waste_type'] ?? ''),0,40,"...")) ?></td>
                                    <td><span class="badge <?= statusClass($row['status'] ?? 'Pending') ?>"><?= htmlspecialchars((string)($row['status'] ?? 'Pending')) ?></span></td>
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
                        <a href="special_request.php" class="btn btn-sm"><i class="fa-solid fa-plus"></i> New request</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="site-footer">
        <span><i class="fa-solid fa-recycle"></i> Waste Assistant &bull; Keeping your community clean</span>
        <span><?= date('Y') ?> &bull; v2.0</span>
    </div>

</body>
</html>
