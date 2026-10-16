<?php

$nav = [
    'dashboard.php' => ['Dashboard',               'fa-gauge'],
    'manage.php'    => ['Appointments & Profile',  'fa-calendar-check'],
];
$q = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE hospital_id = ? AND status = 'Approved' AND vaccination_status = 'Pending'");
$q->execute([$hid]);
$scheduledCount = (int)$q->fetchColumn();
$flash = flash();
$initial = mb_strtoupper(mb_substr($hospital['name'] ?? 'H', 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> | VaxCare Hospital Portal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../css/hospital.css">
</head>
<body>
<div class="p-shell" id="pShell">

    <aside class="p-side">
        <a class="p-brand" href="../index.php">
            <img src="../images/final bg logo.png" alt="VaxCare">
            <span class="tagline">Hospital Portal</span>
        </a>
        <nav class="p-nav">
            <?php foreach ($nav as $file => $item): ?>
                <a href="<?= $file ?>" class="<?= $active === $file ? 'active' : '' ?>">
                    <i class="fa-solid <?= $item[1] ?>"></i><?= e($item[0]) ?>
                    <?php if ($file === 'manage.php' && $scheduledCount): ?><span class="nav-count"><?= $scheduledCount ?></span><?php endif; ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="p-logout">
            <a href="../authentication/logout.php"><i class="fa-solid fa-right-from-bracket"></i>Logout</a>
        </div>
    </aside>
    <div class="p-overlay" id="pOverlay"></div>

    <div class="p-main">
        <header class="p-top">
            <button type="button" class="p-burger" id="pToggle" aria-label="Open menu"><i class="fa-solid fa-bars"></i></button>
            <h1><?= e($pageTitle) ?></h1>
            <div class="p-date"><i class="fa-regular fa-calendar"></i><?= date('D, d M Y') ?></div>
            <div class="p-user">
                <span class="p-avatar"><?= e($initial) ?></span>
                <div><strong><?= e($hospital['name']) ?></strong><small>Hospital</small></div>
            </div>
        </header>

        <main class="p-content">
            <?php if ($flash): ?>
                <div class="alert <?= e($flash[0]) ?>"><?= e($flash[1]) ?></div>
            <?php endif; ?>
