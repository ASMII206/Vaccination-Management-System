<?php

$nav = [
    'dashboard.php'  => ['Dashboard',          'fa-gauge'],
    'children.php'   => ['All Children',       'fa-child'],
    'schedule.php'   => ['Vaccination Dates',  'fa-calendar-check'],
    'reports.php'    => ['Reports',            'fa-file-medical'],
    'vaccines.php'   => ['Vaccines',           'fa-syringe'],
    'requests.php'   => ['Parent Requests',    'fa-clipboard-list'],
    'hospitals.php'  => ['Hospitals',          'fa-hospital'],
    'bookings.php'   => ['Booking Details',    'fa-book-medical'],
];
$result = mysqli_query($conn, "SELECT COUNT(*) FROM appointments WHERE status = 'Pending'");
$row = mysqli_fetch_row($result);
$pendingBadge = (int)$row[0];
$flash = flash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> | VaxCare Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../css/parent.css">
    <link rel="stylesheet" href="../css/admin-dashboard.css">
</head>
<body>
<div class="p-shell" id="pShell">

    <aside class="p-side">
        <a class="p-brand" href="../index.php"><img src="../images/final bg logo.png" alt="VaxCare"></a>
        <nav class="p-nav">
            <?php foreach ($nav as $file => $item): ?>
                <a href="<?= $file ?>" class="<?= $active === $file ? 'active' : '' ?>">
                    <i class="fa-solid <?= $item[1] ?>"></i><?= e($item[0]) ?>
                    <?php if ($file === 'requests.php' && $pendingBadge): ?><span class="nav-badge"><?= $pendingBadge ?></span><?php endif; ?>
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
            <div class="p-user">
                <span class="p-avatar">A</span>
                <div><strong>Administrator</strong><small>Admin</small></div>
            </div>
        </header>

        <main class="p-content">
            <?php if ($flash): ?>
                <div class="alert <?= e($flash[0]) ?>"><?= e($flash[1]) ?></div>
            <?php endif; ?>
