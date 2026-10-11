<?php
require_once __DIR__ . '/../includes/config.php';
require_parent();

$pageTitle = 'Vaccination Dates';
$active = 'vaccinations.php';
$pid = parent_id();
$childFilter = (int)($_GET['child'] ?? 0);

$q = $pdo->prepare('SELECT id, name FROM children WHERE parent_id = ? ORDER BY name');
$q->execute([$pid]);
$children = $q->fetchAll();

$sql = 'SELECT * FROM v_child_schedule WHERE parent_id = ?';
$params = [$pid];
if ($childFilter) {
    $sql .= ' AND child_id = ?';
    $params[] = $childFilter;
}
$sql .= ' ORDER BY child_name, due_date';
$q = $pdo->prepare($sql);
$q->execute($params);
$rows = $q->fetchAll();

include 'includes/layout_top.php';
?>

<div class="card">
    <h2 class="card-title"><i class="fa-solid fa-calendar-check"></i>Vaccination Schedule</h2>

    <?php if ($children): ?>
    <form method="get" action="vaccinations.php" class="filter">
        <select class="field" name="child" onchange="this.form.submit()" aria-label="Filter by child">
            <option value="0">All children</option>
            <?php foreach ($children as $c): ?>
                <option value="<?= (int)$c['id'] ?>" <?= $childFilter === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </form>
    <?php endif; ?>

    <?php if (!$rows): ?>
        <div class="empty"><i class="fa-regular fa-calendar"></i>No vaccination schedule yet. <a href="children.php">Add a child</a> to generate one.</div>
    <?php else: ?>
    <div class="table-wrap">
    <table>
        <thead><tr><th>Child</th><th>Vaccine</th><th>Due Date</th><th>Availability</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): $st = schedule_status($r); ?>
            <tr>
                <td><b><?= e($r['child_name']) ?></b></td>
                <td><?= e($r['vaccine_name']) ?></td>
                <td><?= fmt_date($r['due_date']) ?></td>
                <td><?= badge($r['availability']) ?></td>
                <td><?= badge($st) ?></td>
                <td>
                    <?php if ($st === 'Upcoming' || $st === 'Overdue'): ?>
                        <a class="pbtn sm" href="book.php?cv=<?= (int)$r['id'] ?>">Book hospital</a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>

<?php include 'includes/layout_bottom.php'; ?>
