<?php
require_once __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'List of Vaccines';
$active = 'vaccines.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int)($_POST['id'] ?? 0);
    $to = $_POST['availability'] ?? '';
    if (in_array($to, ['Available', 'Unavailable'], true)) {
        $st = $pdo->prepare('UPDATE vaccines SET availability = ? WHERE id = ?');
        $st->execute([$to, $id]);
        $n = $pdo->prepare('SELECT name FROM vaccines WHERE id = ?');
        $n->execute([$id]);
        if ($name = $n->fetchColumn()) flash('success', $name . ' is now marked ' . strtolower($to) . '.');
        else flash('error', 'Vaccine not found.');
    }
    redirect('vaccines.php' . qs(['filter' => $_POST['filter'] ?? '']));
}

$filter = $_GET['filter'] ?? '';
if (!in_array($filter, ['Available', 'Unavailable'], true)) $filter = '';

$counts = $pdo->query("SELECT availability, COUNT(*) FROM vaccines GROUP BY availability")->fetchAll(PDO::FETCH_KEY_PAIR);
$all = array_sum($counts);

$sql = 'SELECT v.*, (SELECT COUNT(*) FROM child_vaccinations cv WHERE cv.vaccine_id = v.id) AS scheduled
        FROM vaccines v' . ($filter !== '' ? ' WHERE v.availability = ?' : '') . ' ORDER BY v.age_in_days, v.name';
$q = $pdo->prepare($sql);
$q->execute($filter !== '' ? [$filter] : []);
$rows = $q->fetchAll();

function recommended_age($days) {
    $days = (int)$days;
    if ($days === 0) return 'At birth';
    if ($days < 60) return $days . ' days';
    if ($days < 730) return round($days / 30.4) . ' months';
    return round($days / 365, 1) . ' years';
}

include __DIR__ . '/includes/layout_top.php';
?>

<div class="seg">
    <a href="vaccines.php" class="<?= $filter === '' ? 'on' : '' ?>">All<small><?= $all ?></small></a>
    <a href="vaccines.php?filter=Available" class="<?= $filter === 'Available' ? 'on' : '' ?>">Available<small><?= (int)($counts['Available'] ?? 0) ?></small></a>
    <a href="vaccines.php?filter=Unavailable" class="<?= $filter === 'Unavailable' ? 'on' : '' ?>">Unavailable<small><?= (int)($counts['Unavailable'] ?? 0) ?></small></a>
</div>

<div class="card">
    <h2 class="card-title"><i class="fa-solid fa-syringe"></i>Vaccine Availability<small><?= count($rows) ?> shown</small></h2>
    <p class="muted" style="margin-bottom:16px">Parents cannot book a vaccine that is marked unavailable.</p>

    <?php if (!$rows): ?>
        <div class="empty"><i class="fa-solid fa-syringe"></i>No vaccines found.</div>
    <?php else: ?>
    <div class="table-wrap"><table>
        <thead><tr><th>Vaccine</th><th>Recommended Age</th><th>Children Scheduled</th><th>Availability</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $v): $next = $v['availability'] === 'Available' ? 'Unavailable' : 'Available'; ?>
            <tr>
                <td><b><?= e($v['name']) ?></b><?php if (!empty($v['description'])): ?><span class="sub"><?= e($v['description']) ?></span><?php endif; ?></td>
                <td><?= e(recommended_age($v['age_in_days'])) ?></td>
                <td><?= (int)$v['scheduled'] ?></td>
                <td><?= badge($v['availability']) ?></td>
                <td class="actions">
                    <form method="post" action="vaccines.php" class="inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int)$v['id'] ?>">
                        <input type="hidden" name="availability" value="<?= $next ?>">
                        <input type="hidden" name="filter" value="<?= e($filter) ?>">
                        <button class="pbtn sm <?= $next === 'Unavailable' ? 'danger' : 'outline' ?>">Mark <?= strtolower($next) ?></button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/layout_bottom.php'; ?>
