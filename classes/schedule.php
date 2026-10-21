<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$zone_filter = $_GET['zone'] ?? '';
$type_filter = $_GET['type'] ?? '';
$full_name = $_SESSION['full_name'] ?? 'User';
$user_role = $_SESSION['user_role'] ?? 'Resident';
$initial = strtoupper(substr((string)$full_name, 0, 1));

$sql = "SELECT * FROM schedules WHERE collection_date >= CURDATE()";
$params = [];

if (!empty($zone_filter)) {
    $sql .= " AND zone_name LIKE ?";
    $params[] = "%" . $zone_filter . "%";
}

if (!empty($type_filter)) {
    $sql .= " AND waste_type = ?";
    $params[] = $type_filter;
}

$sql .= " ORDER BY collection_date ASC";

// Prepare and execute query with PDO
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$schedules = $stmt->fetchAll();

function schedBadge($s){
  $s=strtolower((string)$s);
  if($s==='pending') return 'badge-pending';
  if(in_array($s,['scheduled','approved'])) return 'badge-scheduled';
  if(in_array($s,['completed','resolved'])) return 'badge-completed';
  if(in_array($s,['cancelled','dismissed'])) return 'badge-cancelled';
  return 'badge-neutral';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule Tracking - Waste Assistant</title>
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
            <a href="schedule.php" class="active"><i class="fa-solid fa-calendar-days"></i> Schedules</a>
            <a href="special_request.php"><i class="fa-solid fa-truck-fast"></i> Special Pickup</a>
            <a href="report_missed.php"><i class="fa-solid fa-triangle-exclamation"></i> Report Missed</a>
            <a href="logout.php" class="user-pill"><span class="avatar"><?= htmlspecialchars($initial) ?></span><span class="uname"><?= htmlspecialchars((string)$full_name) ?></span><i class="fa-solid fa-right-from-bracket"></i></a>
        </nav>
    </div>
</header>

<div class="container">
    <h1 class="page-title">Schedule Tracking</h1>
    <p class="page-sub">Find upcoming collections by zone or waste type. <?= count($schedules) ?> result<?= count($schedules)===1?'':'s' ?> found.</p>

    <div class="card">
        <div class="header-action">
            <h2><i class="fa-solid fa-filter" style="color:#16a34a"></i> Filter schedules</h2>
            <a href="index.php" class="btn-back"><i class="fa-solid fa-arrow-left"></i> Back to Home</a>
        </div>

        <form method="GET" class="filter-box">
            <div style="position:relative;flex:1;min-width:220px">
                <i class="fa-solid fa-magnifying-glass" style="position:absolute;left:13px;top:50%;transform:translateY(-50%);color:#94a3b8"></i>
                <input type="text" name="zone" placeholder="Filter by Zone..." value="<?= htmlspecialchars((string)$zone_filter) ?>" style="padding-left:36px;width:100%">
            </div>
            <select name="type">
                <option value="">All Waste Types</option>
                <option value="General" <?= $type_filter === 'General' ? 'selected' : '' ?>>General</option>
                <option value="Recyclable" <?= $type_filter === 'Recyclable' ? 'selected' : '' ?>>Recyclable</option>
                <option value="Organic" <?= $type_filter === 'Organic' ? 'selected' : '' ?>>Organic</option>
                <option value="Hazardous" <?= $type_filter === 'Hazardous' ? 'selected' : '' ?>>Hazardous</option>
                <option value="Bulky" <?= $type_filter === 'Bulky' ? 'selected' : '' ?>>Bulky</option>
            </select>
            <button type="submit" class="btn"><i class="fa-solid fa-magnifying-glass"></i> Search</button>
            <?php if($zone_filter!==''||$type_filter!==''): ?>
              <a href="schedule.php" class="btn btn-ghost">Clear</a>
            <?php endif; ?>
        </form>

        <?php if (count($schedules) > 0): ?>
        <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th><i class="fa-solid fa-location-dot"></i> Zone</th>
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
                        <td style="white-space:nowrap"><i class="fa-regular fa-calendar" style="color:#94a3b8"></i> <?= htmlspecialchars((string)($row['collection_date'] ?? '')) ?></td>
                        <td><i class="fa-regular fa-clock" style="color:#94a3b8"></i> <?= htmlspecialchars((string)($row['time_window'] ?? '')) ?></td>
                        <td><span class="badge <?= schedBadge($row['status'] ?? '') ?>"><?= htmlspecialchars((string)($row['status'] ?? '')) ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php else: ?>
            <div class="empty">
                <div class="empty-icon"><i class="fa-solid fa-calendar-xmark"></i></div>
                <strong>No schedules match your criteria</strong>
                <p>Try a different zone or waste type, or clear filters.</p>
                <a href="schedule.php" class="btn btn-sm">Clear filters</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="site-footer">
    <span><i class="fa-solid fa-recycle"></i> Waste Assistant</span>
    <span><?= date('Y') ?></span>
</div>

</body>
</html>
