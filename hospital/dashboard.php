<?php
require_once __DIR__ . '/includes/bootstrap.php';
$pageTitle = 'Dashboard';
$active = 'dashboard.php';
$today = date('Y-m-d');
$result = mysqli_query($conn, "SELECT COUNT(*) AS total,
        COALESCE(SUM(vaccination_status = 'Pending'), 0) AS scheduled,
        COALESCE(SUM(vaccination_status = 'Vaccinated'), 0) AS vaccinated,
        COALESCE(SUM(vaccination_status = 'Not Vaccinated'), 0) AS not_vaccinated
    FROM appointments WHERE hospital_id = $hid AND status = 'Approved'");
$s = mysqli_fetch_assoc($result);
$total = (int)$s['total'];
$scheduled = (int)$s['scheduled'];
$vaccinated = (int)$s['vaccinated'];
$notVaccinated = (int)$s['not_vaccinated'];

$result = mysqli_query($conn, "SELECT COUNT(*) FROM appointments WHERE hospital_id = $hid AND status = 'Approved' AND vaccination_status = 'Pending' AND appointment_date < '$today'");
$row = mysqli_fetch_row($result);
$needOutcome = (int)$row[0];

$result = mysqli_query($conn, "SELECT a.id, a.appointment_date, a.time_slot, c.name AS child_name, v.name AS vaccine_name
    FROM appointments a
    JOIN child_vaccinations cv ON cv.id = a.child_vaccination_id
    JOIN children c ON c.id = cv.child_id
    JOIN vaccines v ON v.id = cv.vaccine_id
    WHERE a.hospital_id = $hid AND a.status = 'Approved' AND a.vaccination_status = 'Pending' AND a.appointment_date >= '$today'
    ORDER BY a.appointment_date, a.id LIMIT 5");
$next = mysqli_fetch_all($result, MYSQLI_ASSOC);

$decided = $vaccinated + $notVaccinated;
$rate = $decided ? round($vaccinated / $decided * 100) : null;

include __DIR__ . '/includes/layout_top.php';
?>

<?php
/* Chart geometry (display only): circle with circumference 100 */
$chartSum = $vaccinated + $scheduled + $notVaccinated;
$segments = [
    ['Vaccinated',     $vaccinated,    '#1f9d62', 'c-green'],
    ['Scheduled',      $scheduled,     '#f9a602', 'c-amber'],
    ['Not vaccinated', $notVaccinated, '#d9483f', 'c-red'],
];
$offset = 0;
?>

<div class="stats">
    <div class="stat">
        <div class="stat-icon"><i class="fa-solid fa-clipboard-list"></i></div>
        <div><b><?= $total ?></b><span>Total appointments</span></div>
    </div>
    <div class="stat">
        <div class="stat-icon"><i class="fa-regular fa-clock"></i></div>
        <div><b><?= $scheduled ?></b><span>Scheduled</span></div>
    </div>
    <div class="stat">
        <div class="stat-icon"><i class="fa-solid fa-syringe"></i></div>
        <div><b><?= $vaccinated ?></b><span>Vaccinated</span></div>
    </div>
    <div class="stat <?= $notVaccinated ? 'alert-tile' : '' ?>">
        <div class="stat-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
        <div><b><?= $notVaccinated ?></b><span>Not vaccinated</span></div>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <h2 class="card-title">
            <i class="fa-solid fa-chart-pie"></i>Outcome Summary
        </h2>

        <div class="chart-wrap">
            <div class="donut">
                <svg viewBox="0 0 42 42" role="img" aria-label="Appointment outcomes chart">
                    <circle class="ring base" cx="21" cy="21" r="15.9155"></circle>
                    <?php if ($chartSum > 0): foreach ($segments as $seg):
                        $len = $seg[1] / $chartSum * 100;
                        if ($len <= 0) continue; ?>
                        <circle class="ring" cx="21" cy="21" r="15.9155"
                                stroke="<?= $seg[2] ?>"
                                stroke-dasharray="<?= round($len, 3) ?> <?= round(100 - $len, 3) ?>"
                                stroke-dashoffset="<?= round(-$offset, 3) ?>"></circle>
                    <?php $offset += $len; endforeach; endif; ?>
                </svg>
                <div class="donut-center">
                    <b><?= $total ?></b>
                    <span>Appointments</span>
                </div>
            </div>
            <div class="legend">
                <?php foreach ($segments as $seg): ?>
                <div class="legend-row">
                    <i class="<?= $seg[3] ?>"></i>
                    <span><?= e($seg[0]) ?></span>
                    <b><?= (int)$seg[1] ?></b>
                    <small><?= $chartSum ? round($seg[1] / $chartSum * 100) : 0 ?>%</small>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <p class="muted" style="margin-top:16px">
            <?php if ($rate === null): ?>
                Summary will appear here once outcomes are recorded.
            <?php else: ?>
                <b style="color:var(--navy)"><?= $rate ?>%</b> of recorded outcomes were vaccinated.
            <?php endif; ?>
        </p>
    </div>
    <div class="card">
        <h2 class="card-title">
            <i class="fa-regular fa-bell"></i>Next Appointments
            <small><a href="manage.php#appointments">View all</a></small>
        </h2>

        <?php if ($needOutcome): ?>
            <ul class="notify">
                <li class="red">
                    <span><b><?= $needOutcome ?></b> past appointment<?= $needOutcome === 1 ? '' : 's' ?> still need<?= $needOutcome === 1 ? 's' : '' ?> an outcome.</span>
                    <a class="pbtn sm" href="manage.php?status=Scheduled#appointments">Review</a>
                </li>
            </ul>
        <?php endif; ?>
        <?php if (!$next): ?>
            <p class="muted">No upcoming appointments.</p>
        <?php endif; ?>
        <?php foreach ($next as $r): ?>
        <div class="row-item">
            <div>
                <b><?= e($r['child_name']) ?></b> &ndash; <?= e($r['vaccine_name']) ?>
                <div class="meta"><?= fmt_date($r['appointment_date']) ?> &middot; <?= e($r['time_slot']) ?></div>
            </div>
            <?= badge('Pending') ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="card">
    <h2 class="card-title">
        <i class="fa-solid fa-hospital"></i>Hospital Details
        <small><a href="manage.php#profile">Edit profile</a></small>
    </h2>
    <div class="table-wrap">
        <table class="detail-table">
            <tbody>
                <tr><th>Hospital name</th><td><?= e($hospital['name']) ?></td></tr>
                <tr><th>Address</th><td><?= e($hospital['address']) ?></td></tr>
                <tr><th>Location</th><td><?= e($hospital['location']) ?></td></tr>
                <tr><th>Phone</th><td><?= e($hospital['phone'] ?: 'Not added yet') ?></td></tr>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/includes/layout_bottom.php'; ?>
