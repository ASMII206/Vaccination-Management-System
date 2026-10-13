<?php
require_once __DIR__ . '/../includes/config.php';
require_parent();

$pageTitle = 'My Requests';
$active = 'requests.php';
$pid = parent_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    
    $st = $pdo->prepare("UPDATE appointments SET status = 'Cancelled' WHERE id = ? AND parent_id = ? AND status = 'Pending'");
    $st->execute([(int)($_POST['id'] ?? 0), $pid]);
    if ($st->rowCount()) flash('success', 'Request cancelled.');
    else flash('error', 'Only pending requests can be cancelled.');
    redirect('requests.php');
}

$q = $pdo->prepare('SELECT a.*, h.name AS hospital, c.name AS child_name, v.name AS vaccine_name
    FROM appointments a
    JOIN hospitals h ON h.id = a.hospital_id
    JOIN child_vaccinations cv ON cv.id = a.child_vaccination_id
    JOIN children c ON c.id = cv.child_id
    JOIN vaccines v ON v.id = cv.vaccine_id
    WHERE a.parent_id = ? ORDER BY a.created_at DESC, a.id DESC');
$q->execute([$pid]);
$rows = $q->fetchAll();

include 'includes/layout_top.php';
?>

<div class="card">
    <h2 class="card-title"><i class="fa-solid fa-clipboard-list"></i>Hospital Booking Requests<small><?= count($rows) ?> total</small></h2>

    <?php if (!$rows): ?>
        <div class="empty"><i class="fa-solid fa-clipboard-list"></i>You have not requested any bookings yet. <a href="vaccinations.php">Go to Vaccination Dates</a></div>
    <?php else: ?>
    <div class="table-wrap">
    <table>
        <thead><tr><th>#</th><th>Child</th><th>Vaccine</th><th>Hospital</th><th>Date &amp; Slot</th><th>Admin Decision</th><th>Vaccination</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><?= (int)$r['id'] ?></td>
                <td><b><?= e($r['child_name']) ?></b></td>
                <td><?= e($r['vaccine_name']) ?></td>
                <td><?= e($r['hospital']) ?></td>
                <td><?= fmt_date($r['appointment_date']) ?><br><small><?= e($r['time_slot']) ?></small></td>
                <td><?= badge($r['status']) ?></td>
                <td><?= badge($r['vaccination_status']) ?></td>
                <td>
                    <?php if ($r['status'] === 'Pending'): ?>
                    <form method="post" action="requests.php" class="inline" onsubmit="return confirm('Cancel this booking request?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                        <button class="pbtn sm danger">Cancel</button>
                    </form>
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
