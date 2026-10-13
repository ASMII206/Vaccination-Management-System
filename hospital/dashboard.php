<?php
require_once __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Dashboard';
$pageSub = 'Overview of appointments assigned to your hospital';
$active = 'dashboard.php';
$today = date('Y-m-d');


$q = $pdo->prepare("SELECT COUNT(*) AS total,
        COALESCE(SUM(vaccination_status = 'Pending'), 0) AS scheduled,
        COALESCE(SUM(vaccination_status = 'Vaccinated'), 0) AS vaccinated,
        COALESCE(SUM(vaccination_status = 'Not Vaccinated'), 0) AS not_vaccinated
    FROM appointments WHERE hospital_id = ? AND status = 'Approved'");
$q->execute([$hid]);
$s = $q->fetch();
$total = (int)$s['total'];
$scheduled = (int)$s['scheduled'];
$vaccinated = (int)$s['vaccinated'];
$notVaccinated = (int)$s['not_vaccinated'];

$q = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE hospital_id = ? AND status = 'Approved' AND vaccination_status = 'Pending' AND appointment_date < ?");
$q->execute([$hid, $today]);
$needOutcome = (int)$q->fetchColumn();


$q = $pdo->prepare("SELECT a.id, a.appointment_date, a.time_slot, c.name AS child_name, v.name AS vaccine_name
    FROM appointments a
    JOIN child_vaccinations cv ON cv.id = a.child_vaccination_id
    JOIN children c ON c.id = cv.child_id
    JOIN vaccines v ON v.id = cv.vaccine_id
    WHERE a.hospital_id = ? AND a.status = 'Approved' AND a.vaccination_status = 'Pending' AND a.appointment_date >= ?
    ORDER BY a.appointment_date, a.id LIMIT 5");
$q->execute([$hid, $today]);
$next = $q->fetchAll();

$pct = function ($n) use ($total) { return $total ? round($n / $total * 100, 1) : 0; };
$decided = $vaccinated + $notVaccinated;
$rate = $decided ? round($vaccinated / $decided * 100) : null;

include __DIR__ . '/includes/layout_top.php';
?>

<section class="pan" style="margin-bottom:20px;">
    <h2>Hospital Details</h2>
    <p class="mut">Your registered hospital information.</p>
    <p><strong>Hospital Name:</strong> <?= e($hospital['name']) ?></p>
    <p><strong>Address:</strong> <?= e($hospital['address']) ?></p>
    <p><strong>Location:</strong> <?= e($hospital['location']) ?></p>
    <p><strong>Phone:</strong> <?= e($hospital['phone'] ?: 'Not added yet') ?></p>
    <a class="btn ghost sm" style="margin-top:8px" href="profile.php">Edit profile</a>
</section>

<div class="strip">
  <div class="st">
    <p><i class="dn"></i>Total appointments</p>
    <strong><?= $total ?></strong>
    <small>Assigned to this hospital</small>
  </div>
  <div class="st">
    <p><i class="ds"></i>Awaiting outcome</p>
    <strong><?= $scheduled ?></strong>
    <small>Scheduled appointments</small>
  </div>
  <div class="st">
    <p><i class="dv"></i>Vaccinated</p>
    <strong><?= $vaccinated ?></strong>
    <small>Status confirmed</small>
  </div>
  <div class="st">
    <p><i class="dn"></i>Not vaccinated</p>
    <strong><?= $notVaccinated ?></strong>
    <small>Status confirmed</small>
  </div>
</div>

<div class="cols">
  <section class="pan">
    <h2>Next appointments</h2>
    <p class="mut">Upcoming appointments for your hospital.</p>

    <?php if ($needOutcome): ?>
      <p class="note"><b><?= $needOutcome ?></b> past appointment<?= $needOutcome === 1 ? '' : 's' ?> still need<?= $needOutcome === 1 ? 's' : '' ?> an outcome.
        <a href="appointments.php?status=Scheduled">Review</a></p>
    <?php endif; ?>

    <?php if (!$next): ?>
      <p>No upcoming appointments.</p>
    <?php endif; ?>
    <?php foreach ($next as $r): ?>
      <div class="up">
        <div class="dd"><b><?= date('d', strtotime($r['appointment_date'])) ?></b><small><?= date('M', strtotime($r['appointment_date'])) ?></small></div>
        <div><b><?= e($r['child_name']) ?></b><small><?= e($r['vaccine_name']) ?> &middot; <?= e($r['time_slot']) ?></small></div>
        <?= outcome_chip('Pending') ?>
      </div>
    <?php endforeach; ?>
    <a class="btn ghost sm" style="margin-top:8px" href="appointments.php">View all appointments</a>
  </section>

  <section class="pan">
    <h2>Outcome summary</h2>
    <p class="mut">Summary of vaccination appointments by status.</p>
    <div class="bar">
      <span class="dv" style="width:<?= $pct($vaccinated) ?>%"></span>
      <span class="ds" style="width:<?= $pct($scheduled) ?>%"></span>
      <span class="dm" style="width:<?= $pct($notVaccinated) ?>%"></span>
    </div>
    <div class="lg"><span><i class="dv"></i>Vaccinated</span><b><?= $vaccinated ?></b></div>
    <div class="lg"><span><i class="ds"></i>Scheduled</span><b><?= $scheduled ?></b></div>
    <div class="lg"><span><i class="dm"></i>Not vaccinated</span><b><?= $notVaccinated ?></b></div>
    <p class="rate">
      <?php if ($rate === null): ?>
        Vaccination summary will appear here once outcomes are recorded.
      <?php else: ?>
        <b><?= $rate ?>%</b> of recorded outcomes were vaccinated.
      <?php endif; ?>
    </p>
  </section>
</div>

<?php include __DIR__ . '/includes/layout_bottom.php'; ?>
