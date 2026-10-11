<?php
require_once __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Child Profile';
$active = 'children.php';
$id = (int)($_GET['id'] ?? 0);

$q = $pdo->prepare('SELECT c.*, p.full_name AS parent_name, p.email AS parent_email, p.phone AS parent_phone, p.address AS parent_address
    FROM children c JOIN parents p ON p.id = c.parent_id WHERE c.id = ?');
$q->execute([$id]);
$child = $q->fetch();
if (!$child) {
    flash('error', 'Child not found.');
    redirect('children.php');
}

$q = $pdo->prepare('SELECT * FROM v_child_schedule WHERE child_id = ? ORDER BY due_date');
$q->execute([$id]);
$schedule = $q->fetchAll();
$done = 0;
foreach ($schedule as $s) if ($s['is_vaccinated']) $done++;
$pct = $schedule ? round($done / count($schedule) * 100) : 0;

$q = $pdo->prepare('SELECT a.id, a.appointment_date, a.time_slot, a.status, a.vaccination_status, h.name AS hospital, v.name AS vaccine_name
    FROM appointments a
    JOIN hospitals h ON h.id = a.hospital_id
    JOIN child_vaccinations cv ON cv.id = a.child_vaccination_id
    JOIN vaccines v ON v.id = cv.vaccine_id
    WHERE cv.child_id = ? ORDER BY a.appointment_date DESC, a.id DESC');
$q->execute([$id]);
$bookings = $q->fetchAll();

include __DIR__ . '/includes/layout_top.php';
?>

<p style="margin-bottom:18px"><a class="pbtn sm outline" href="children.php"><i class="fa-solid fa-arrow-left"></i>Back to all children</a></p>

<div class="card">
    <h2 class="card-title"><i class="fa-solid fa-child"></i><?= e($child['name']) ?><small><?= e(age_text($child['dob'])) ?></small></h2>
    <dl class="detail">
        <div><dt>Date of birth</dt><dd><?= fmt_date($child['dob']) ?></dd></div>
        <div><dt>Gender</dt><dd><?= e($child['gender'] ?: '-') ?></dd></div>
        <div><dt>Blood group</dt><dd><?= e($child['blood_group'] ?: '-') ?></dd></div>
        <div><dt>Notes / allergies</dt><dd><?= e($child['notes'] ?: '-') ?></dd></div>
        <div><dt>Parent</dt><dd><?= e($child['parent_name']) ?></dd></div>
        <div><dt>Parent email</dt><dd><?= e($child['parent_email']) ?></dd></div>
        <div><dt>Parent phone</dt><dd><?= e($child['parent_phone'] ?: '-') ?></dd></div>
        <div><dt>Address</dt><dd><?= e($child['parent_address'] ?: '-') ?></dd></div>
        <div><dt>Progress</dt><dd><span class="bar"><span style="width:<?= $pct ?>%"></span></span><?= $done ?> of <?= count($schedule) ?></dd></div>
    </dl>
</div>

<div class="card">
    <h2 class="card-title"><i class="fa-solid fa-calendar-check"></i>Vaccination Schedule</h2>
    <?php if (!$schedule): ?><p class="muted">No vaccination schedule has been generated for this child.</p><?php else: ?>
    <div class="table-wrap"><table>
        <thead><tr><th>Vaccine</th><th>Due Date</th><th>Availability</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($schedule as $s): ?>
            <tr><td><b><?= e($s['vaccine_name']) ?></b></td><td><?= fmt_date($s['due_date']) ?></td><td><?= badge($s['availability']) ?></td><td><?= badge(schedule_status($s)) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</div>

<div class="card">
    <h2 class="card-title"><i class="fa-solid fa-book-medical"></i>Hospital Bookings<small><?= count($bookings) ?> total</small></h2>
    <?php if (!$bookings): ?><p class="muted">No hospital bookings have been requested for this child.</p><?php else: ?>
    <div class="table-wrap"><table>
        <thead><tr><th>Reference</th><th>Vaccine</th><th>Hospital</th><th>Date &amp; Slot</th><th>Admin Decision</th><th>Vaccination</th></tr></thead>
        <tbody>
        <?php foreach ($bookings as $b): ?>
            <tr>
                <td><b><?= e(booking_ref($b['id'])) ?></b></td>
                <td><?= e($b['vaccine_name']) ?></td>
                <td><?= e($b['hospital']) ?></td>
                <td><?= fmt_date($b['appointment_date']) ?><span class="sub"><?= e($b['time_slot']) ?></span></td>
                <td><?= badge($b['status']) ?></td>
                <td><?= $b['status'] === 'Approved' ? outcome_badge($b['vaccination_status']) : '-' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/layout_bottom.php'; ?>
