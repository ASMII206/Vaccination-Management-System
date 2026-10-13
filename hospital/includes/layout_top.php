<?php

$q = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE hospital_id = ? AND status = 'Approved' AND vaccination_status = 'Pending'");
$q->execute([$hid]);
$scheduledCount = (int)$q->fetchColumn();
$flash = flash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($pageTitle) ?> - Hospital Portal</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../css/hospitaldashboard.css">
</head>
<body>
<div class="app">
  <aside class="side">
  <div class="brand">
    <img src="../images/final bg logo.png" alt="Vax Care Logo" class="sidebar-logo mk">
    <span>Hospital<br>Portal</span>
</div>
    <nav class="nav-w">
        <a class="nav <?= $active === 'dashboard.php' ? 'on' : '' ?>" href="dashboard.php">Dashboard</a>
        <a class="nav <?= $active === 'appointments.php' ? 'on' : '' ?>" href="appointments.php">Appointments<?php if ($scheduledCount): ?><span class="bdg"><?= $scheduledCount ?></span><?php endif; ?></a>
        <a class="nav <?= $active === 'profile.php' ? 'on' : '' ?>" href="profile.php">Hospital profile</a>
    </nav>
    <div class="foot">
      <div class="who"><b><?= e($hospital['name']) ?></b><small><?= e($hospital['email'] ?: $hospital['location']) ?></small></div>
      <a class="out" href="../authentication/logout.php">Sign out</a>
    </div>
  </aside>
  <main class="main">
    <header class="top">
      <div><h1><?= e($pageTitle) ?></h1><p class="mut"><?= e($pageSub) ?></p></div>
      <div class="dt"><?= date('l, d F Y') ?></div>
    </header>
    <?php if ($flash): ?>
      <div class="alert <?= e($flash[0]) ?>"><?= e($flash[1]) ?></div>
    <?php endif; ?>
