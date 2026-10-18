<?php

$nav = [
    'dashboard.php'     => ['Dashboard',         'fa-gauge'],
    'children.php'      => ['My Children',       'fa-child'],
    'vaccinations.php'  => ['Vaccination Dates', 'fa-calendar-check'],
    'hospitals.php'     => ['Hospitals',         'fa-hospital'],
    'requests.php'      => ['My Requests',       'fa-clipboard-list'],
    'reports.php'       => ['Reports',           'fa-file-medical'],
    'profile.php'       => ['My Profile',        'fa-user'],
];
$flash = flash();
$initial = mb_strtoupper(mb_substr($_SESSION['user_name'] ?? 'P', 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> | VaxCare Parent Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../css/parent.css">
</head>
<body>
<div class="p-shell" id="pShell">

    <aside class="p-side">
        <a class="p-brand" href="../index.php"><img src="../images/final bg logo.png" alt="VaxCare"></a>
        <nav class="p-nav">
            
<?php foreach ($nav as $file => $navItem): ?>
    <a href="<?= $file ?>" class="<?= $active === $file ? 'active' : '' ?>">
        <i class="fa-solid <?= $navItem[1] ?>"></i><?= e($navItem[0]) ?>
    </a>
<?php endforeach; ?>

        </nav>
    </aside>
    <div class="p-overlay" id="pOverlay"></div>

    <div class="p-main">
        <header class="p-top">
            <button type="button" class="p-burger" id="pToggle" aria-label="Open menu"><i class="fa-solid fa-bars"></i></button>
            <h1><?= e($pageTitle) ?></h1>
            <div class="p-user" id="pUser">
                <button type="button" class="p-user-btn" id="pUserBtn" aria-haspopup="true" aria-expanded="false">
                    <span class="p-avatar"><?= e($initial) ?></span>
                    <div><strong><?= e($_SESSION['user_name'] ?? '') ?></strong><small>Parent</small></div>
                    <i class="fa-solid fa-chevron-down p-caret"></i>
                </button>
                <nav class="p-menu" id="pMenu">
                    <a href="../authentication/logout.php"><i class="fa-solid fa-right-from-bracket"></i>Logout</a>
                </nav>
            </div>
        </header>

        <main class="p-content">
            <?php if ($flash): ?>
                <div class="alert <?= e($flash[0]) ?>"><?= e($flash[1]) ?></div>
            <?php endif; ?>
