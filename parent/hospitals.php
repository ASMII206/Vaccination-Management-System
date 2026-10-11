<?php
require_once __DIR__ . '/../includes/config.php';
require_parent();

$pageTitle = 'Hospitals';
$active = 'hospitals.php';
$search = trim($_GET['q'] ?? '');
$like = '%' . $search . '%';

$q = $pdo->prepare('SELECT id, name, address, location, phone FROM hospitals WHERE name LIKE ? OR location LIKE ? OR address LIKE ? ORDER BY name');
$q->execute([$like, $like, $like]);
$hospitals = $q->fetchAll();

include 'includes/layout_top.php';
?>

<div class="card">
    <h2 class="card-title"><i class="fa-solid fa-hospital"></i>Registered Hospitals<small><?= count($hospitals) ?> found</small></h2>

    <form method="get" action="hospitals.php" class="filter">
        <input class="field" type="search" name="q" value="<?= e($search) ?>" placeholder="Search by hospital name or city / area">
        <button class="pbtn">Search</button>
        <?php if ($search !== ''): ?><a class="pbtn outline" href="hospitals.php">Clear</a><?php endif; ?>
    </form>

    <?php if (!$hospitals): ?>
        <div class="empty"><i class="fa-solid fa-hospital"></i>No hospitals match your search.</div>
    <?php else: ?>
    <div class="grid-3">
        <?php foreach ($hospitals as $h): ?>
            <div class="hospital-card">
                <h3><?= e($h['name']) ?></h3>
                <p><i class="fa-solid fa-location-dot"></i><?= e($h['address']) ?></p>
                <p><i class="fa-solid fa-city"></i><?= e($h['location']) ?></p>
                <p><i class="fa-solid fa-phone"></i><?= e($h['phone'] ?: 'Not available') ?></p>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <p class="muted" style="margin-top:20px">To book a hospital, open <a href="vaccinations.php" style="color:var(--amber-dark);font-weight:bold">Vaccination Dates</a> and choose <b>Book hospital</b> next to a vaccine.</p>
</div>

<?php include 'includes/layout_bottom.php'; ?>
