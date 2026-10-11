<?php
require_once __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'All Children';
$active = 'children.php';

$search = trim($_GET['q'] ?? '');
$gender = $_GET['gender'] ?? '';
if (!in_array($gender, ['Male', 'Female', 'Other'], true)) $gender = '';

$sql = 'SELECT c.*, p.full_name AS parent_name, p.phone AS parent_phone,
               (SELECT COUNT(*) FROM v_child_schedule s WHERE s.child_id = c.id) AS total,
               (SELECT COALESCE(SUM(s.is_vaccinated), 0) FROM v_child_schedule s WHERE s.child_id = c.id) AS done
        FROM children c JOIN parents p ON p.id = c.parent_id WHERE 1=1';
$params = [];
if ($search !== '') {
    $sql .= ' AND (c.name LIKE ? OR p.full_name LIKE ? OR p.email LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like);
}
if ($gender !== '') { $sql .= ' AND c.gender = ?'; $params[] = $gender; }
$sql .= ' ORDER BY c.name';
$q = $pdo->prepare($sql);
$q->execute($params);
$rows = $q->fetchAll();

include __DIR__ . '/includes/layout_top.php';
?>

<div class="card">
    <h2 class="card-title"><i class="fa-solid fa-child"></i>Registered Children<small><?= count($rows) ?> found</small></h2>

    <form method="get" action="children.php" class="filter">
        <input class="field" type="search" name="q" value="<?= e($search) ?>" placeholder="Search by child, parent name or email">
        <select class="field" name="gender" aria-label="Gender">
            <option value="">All genders</option>
            <?php foreach (['Male', 'Female', 'Other'] as $g): ?><option <?= $gender === $g ? 'selected' : '' ?>><?= $g ?></option><?php endforeach; ?>
        </select>
        <button class="pbtn">Search</button>
        <?php if ($search !== '' || $gender !== ''): ?><a class="pbtn outline" href="children.php">Clear</a><?php endif; ?>
    </form>

    <?php if (!$rows): ?>
        <div class="empty"><i class="fa-solid fa-child"></i>No children found.</div>
    <?php else: ?>
    <div class="table-wrap">
    <table>
        <thead><tr><th>Child</th><th>Date of Birth</th><th>Age</th><th>Gender</th><th>Blood Group</th><th>Parent</th><th>Progress</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $c): $pct = $c['total'] ? round($c['done'] / $c['total'] * 100) : 0; ?>
            <tr>
                <td><b><?= e($c['name']) ?></b></td>
                <td><?= fmt_date($c['dob']) ?></td>
                <td><?= e(age_text($c['dob'])) ?></td>
                <td><?= e($c['gender'] ?: '-') ?></td>
                <td><?= e($c['blood_group'] ?: '-') ?></td>
                <td><?= e($c['parent_name']) ?><span class="sub"><?= e($c['parent_phone'] ?: '') ?></span></td>
                <td><span class="bar"><span style="width:<?= $pct ?>%"></span></span><?= (int)$c['done'] ?> of <?= (int)$c['total'] ?></td>
                <td class="actions"><a class="pbtn sm outline" href="child-view.php?id=<?= (int)$c['id'] ?>">View profile</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/layout_bottom.php'; ?>
