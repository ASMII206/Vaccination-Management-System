<?php
require_once __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'List of Hospitals';
$active = 'hospitals.php';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int)($_POST['id'] ?? 0);
    $q = $pdo->prepare('SELECT name FROM hospitals WHERE id = ?');
    $q->execute([$id]);
    $name = $q->fetchColumn();
    if ($name === false) {
        flash('error', 'Hospital not found.');
    } else {
        $c = $pdo->prepare('SELECT COUNT(*) FROM appointments WHERE hospital_id = ?');
        $c->execute([$id]);
        if ((int)$c->fetchColumn() > 0) {
            flash('error', $name . ' cannot be deleted because it has booking records. You can edit its details instead.');
        } else {
            $pdo->prepare('DELETE FROM hospitals WHERE id = ?')->execute([$id]);
            flash('success', $name . ' was deleted.');
        }
    }
    redirect('hospitals.php');
}

$search = trim($_GET['q'] ?? '');
$like = '%' . $search . '%';
$hasLogin = has_column($pdo, 'hospitals', 'email');
$q = $pdo->prepare('SELECT h.*, (SELECT COUNT(*) FROM appointments a WHERE a.hospital_id = h.id) AS bookings
    FROM hospitals h WHERE h.name LIKE ? OR h.location LIKE ? OR h.address LIKE ? ORDER BY h.name');
$q->execute([$like, $like, $like]);
$rows = $q->fetchAll();

include __DIR__ . '/includes/layout_top.php';
?>

<div class="card">
    <h2 class="card-title"><i class="fa-solid fa-hospital"></i>Registered Hospitals<small><?= count($rows) ?> found</small></h2>

    <form method="get" action="hospitals.php" class="filter">
        <input class="field" type="search" name="q" value="<?= e($search) ?>" placeholder="Search by hospital name, city or address">
        <button class="pbtn">Search</button>
        <?php if ($search !== ''): ?><a class="pbtn outline" href="hospitals.php">Clear</a><?php endif; ?>
        <a class="pbtn" href="hospital-form.php" style="margin-left:auto"><i class="fa-solid fa-plus"></i>Add Hospital</a>
    </form>

    <?php if (!$rows): ?>
        <div class="empty"><i class="fa-solid fa-hospital"></i>No hospitals found.</div>
    <?php else: ?>
    <div class="table-wrap"><table>
        <thead><tr><th>Hospital</th><th>Address</th><th>Location</th><th>Phone</th><?php if ($hasLogin): ?><th>Login Email</th><?php endif; ?><th>Bookings</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $h): ?>
            <tr>
                <td><b><?= e($h['name']) ?></b></td>
                <td><?= e($h['address']) ?></td>
                <td><?= e($h['location']) ?></td>
                <td><?= e($h['phone'] ?: '-') ?></td>
                <?php if ($hasLogin): ?><td><?= e($h['email'] ?? '') ?: '-' ?></td><?php endif; ?>
                <td><?= (int)$h['bookings'] ?></td>
                <td class="actions">
                    <a class="pbtn sm outline" href="bookings.php?hospital=<?= (int)$h['id'] ?>">Bookings</a>
                    <a class="pbtn sm outline" href="hospital-form.php?id=<?= (int)$h['id'] ?>">Edit</a>
                    <form method="post" action="hospitals.php" class="inline" onsubmit="return confirm('Delete this hospital?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int)$h['id'] ?>">
                        <button class="pbtn sm danger">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/layout_bottom.php'; ?>
