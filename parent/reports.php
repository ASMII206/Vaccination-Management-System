<?php
require_once __DIR__ . '/../includes/config.php';
require_parent();

$pageTitle = 'Vaccination Reports';
$active = 'reports.php';
$pid = parent_id();

$q = $pdo->prepare('SELECT * FROM children WHERE parent_id = ? ORDER BY name');
$q->execute([$pid]);
$children = $q->fetchAll();

// Completed vaccinations, grouped by child
$q = $pdo->prepare("SELECT a.appointment_date, h.name AS hospital, cv.child_id, v.name AS vaccine_name
    FROM appointments a
    JOIN hospitals h ON h.id = a.hospital_id
    JOIN child_vaccinations cv ON cv.id = a.child_vaccination_id
    JOIN vaccines v ON v.id = cv.vaccine_id
    WHERE a.parent_id = ? AND a.vaccination_status = 'Vaccinated'
    ORDER BY a.appointment_date DESC");
$q->execute([$pid]);
$byChild = [];
foreach ($q->fetchAll() as $r) $byChild[$r['child_id']][] = $r;

include 'includes/layout_top.php';
?>

<?php if (!$children): ?>
<div class="card"><div class="empty"><i class="fa-solid fa-file-medical"></i>Add a child to see vaccination reports. <a href="children.php">Add a child</a></div></div>
<?php endif; ?>

<?php foreach ($children as $c):
    $s = $pdo->prepare("
    SELECT
        COUNT(*) AS total,
        COALESCE(SUM(
            CASE
                WHEN EXISTS (
                    SELECT 1
                    FROM appointments a
                    WHERE a.child_vaccination_id = cv.id
                      AND a.vaccination_status = 'Vaccinated'
                )
                THEN 1
                ELSE 0
            END
        ), 0) AS done
    FROM child_vaccinations cv
    WHERE cv.child_id = ?
");

$s->execute([$c['id']]);
$p = $s->fetch();
    $records = $byChild[$c['id']] ?? []; ?>
<div class="card">
    <h2 class="card-title"><i class="fa-solid fa-file-medical"></i><?= e($c['name']) ?>
        <small><?= e(age_text($c['dob'])) ?> &middot; <?= (int)$p['done'] ?> of <?= (int)$p['total'] ?> vaccines completed</small></h2>
    <?php if (!$records): ?>
        <p class="muted">No completed vaccinations have been recorded yet.</p>
    <?php else: ?>
    <div class="table-wrap">
    <table>
        <thead><tr><th>Vaccine</th><th>Date</th><th>Hospital</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($records as $r): ?>
            <tr>
                <td><b><?= e($r['vaccine_name']) ?></b></td>
                <td><?= fmt_date($r['appointment_date']) ?></td>
                <td><?= e($r['hospital']) ?></td>
                <td><?= badge('Vaccinated') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>
<?php endforeach; ?>

<?php if ($children): ?>
<button type="button" class="pbtn outline no-print" onclick="window.print()"><i class="fa-solid fa-print"></i>Print report</button>
<?php endif; ?>

<?php include 'includes/layout_bottom.php'; ?>
