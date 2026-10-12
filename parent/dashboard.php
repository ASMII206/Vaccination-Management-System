
<?php
require_once __DIR__ . '/../includes/config.php';
require_parent();

$pageTitle = 'Dashboard';
$active = 'dashboard.php';
$pid = parent_id();
$today = date('Y-m-d');
$soon = date('Y-m-d', strtotime('+30 days'));

// Children belonging to the logged-in parent
$q = $pdo->prepare(
    'SELECT * FROM children WHERE parent_id = ? ORDER BY name'
);
$q->execute([$pid]);
$children = $q->fetchAll();

$scheduleSql = "
    SELECT
        cv.id,
        cv.child_id,
        c.parent_id,
        c.name AS child_name,
        cv.vaccine_id,
        v.name AS vaccine_name,
        v.availability,
        cv.due_date,
        EXISTS (
            SELECT 1
            FROM appointments a
            WHERE a.child_vaccination_id = cv.id
              AND a.vaccination_status = 'Vaccinated'
        ) AS is_vaccinated,
        (
            SELECT a.status
            FROM appointments a
            WHERE a.child_vaccination_id = cv.id
              AND a.status IN ('Pending', 'Approved')
              AND a.vaccination_status = 'Pending'
            ORDER BY a.id DESC
            LIMIT 1
        ) AS booking_status
    FROM child_vaccinations cv
    JOIN children c ON c.id = cv.child_id
    JOIN vaccines v ON v.id = cv.vaccine_id
    WHERE c.parent_id = ?
      AND NOT EXISTS (
          SELECT 1
          FROM appointments a
          WHERE a.child_vaccination_id = cv.id
            AND a.vaccination_status = 'Vaccinated'
      )
    ORDER BY cv.due_date
";

$q = $pdo->prepare($scheduleSql);
$q->execute([$pid]);
$pending = $q->fetchAll();

// Overdue doses without a pending or approved booking
$overdue = array_filter($pending, function ($r) use ($today) {
    return $r['due_date'] < $today && !$r['booking_status'];
});

// Upcoming doses in the next 30 days
$upcoming = array_filter($pending, function ($r) use ($today, $soon) {
    return $r['due_date'] >= $today
        && $r['due_date'] <= $soon;
});

// Pending booking requests
$q = $pdo->prepare(
    "SELECT COUNT(*) FROM appointments
     WHERE parent_id = ? AND status = 'Pending'"
);
$q->execute([$pid]);
$pendingReq = (int) $q->fetchColumn();

// Completed vaccinations
$q = $pdo->prepare(
    "SELECT COUNT(*) FROM appointments
     WHERE parent_id = ? AND vaccination_status = 'Vaccinated'"
);
$q->execute([$pid]);
$doneCount = (int) $q->fetchColumn();

// Most recent booking requests
$q = $pdo->prepare(
    "SELECT a.*, h.name AS hospital,
            c.name AS child_name, v.name AS vaccine_name
     FROM appointments a
     JOIN hospitals h ON h.id = a.hospital_id
     JOIN child_vaccinations cv ON cv.id = a.child_vaccination_id
     JOIN children c ON c.id = cv.child_id
     JOIN vaccines v ON v.id = cv.vaccine_id
     WHERE a.parent_id = ?
     ORDER BY a.created_at DESC, a.id DESC
     LIMIT 5"
);
$q->execute([$pid]);
$recent = $q->fetchAll();

include 'includes/layout_top.php';
?>

<div class="stats">
    <div class="stat">
        <div class="stat-icon"><i class="fa-solid fa-child"></i></div>
        <div><b><?= count($children) ?></b><span>Children</span></div>
    </div>
    <div class="stat">
        <div class="stat-icon"><i class="fa-regular fa-calendar"></i></div>
        <div><b><?= count($upcoming) ?></b><span>Due in 30 days</span></div>
    </div>
    <div class="stat <?= count($overdue) ? 'alert-tile' : '' ?>">
        <div class="stat-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
        <div><b><?= count($overdue) ?></b><span>Overdue</span></div>
    </div>
    <div class="stat">
        <div class="stat-icon"><i class="fa-regular fa-clock"></i></div>
        <div><b><?= $pendingReq ?></b><span>Pending requests</span></div>
    </div>
    <div class="stat">
        <div class="stat-icon"><i class="fa-solid fa-syringe"></i></div>
        <div><b><?= $doneCount ?></b><span>Vaccinations done</span></div>
    </div>
</div>

<?php if (!$children): ?>
<div class="card">
    <div class="empty">
        <i class="fa-solid fa-child"></i>
        You have not added a child yet.<br><br>
        <a class="pbtn" href="children.php">Add your first child</a>
    </div>
</div>
<?php endif; ?>

<div class="grid-2">
    <div class="card">
        <h2 class="card-title">
            <i class="fa-regular fa-bell"></i>Vaccination Notifications
        </h2>

        <?php if (!$overdue && !$upcoming): ?>
            <p class="muted">
                Nothing is due in the next 30 days, and no vaccines are overdue.
            </p>
        <?php endif; ?>

        <ul class="notify">
            <?php foreach ($overdue as $r): ?>
            <li class="red">
                <span>
                    <b><?= e($r['child_name']) ?></b> missed
                    <b><?= e($r['vaccine_name']) ?></b><br>
                    <small class="muted">
                        Was due <?= fmt_date($r['due_date']) ?>
                    </small>
                </span>
                <a class="pbtn sm" href="book.php?cv=<?= (int)$r['id'] ?>">
                    Book now
                </a>
            </li>
            <?php endforeach; ?>

            <?php foreach ($upcoming as $r): ?>
            <li class="blue">
                <span>
                    <b><?= e($r['child_name']) ?></b> &ndash;
                    <b><?= e($r['vaccine_name']) ?></b><br>
                    <small class="muted">
                        Due <?= fmt_date($r['due_date']) ?>
                    </small>
                </span>

                <?php if ($r['booking_status']): ?>
                    <?= badge('Booked (' . $r['booking_status'] . ')') ?>
                <?php else: ?>
                    <a class="pbtn sm outline"
                       href="book.php?cv=<?= (int)$r['id'] ?>">Book</a>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <div class="card">
        <h2 class="card-title">
            <i class="fa-solid fa-clipboard-list"></i>Recent Requests
            <small><a href="requests.php">View all</a></small>
        </h2>

        <?php if (!$recent): ?>
            <p class="muted">
                You have not requested any hospital bookings yet.
            </p>
        <?php endif; ?>

        <?php foreach ($recent as $a): ?>
        <div class="row-item">
            <div>
                <b><?= e($a['child_name']) ?></b> &ndash;
                <?= e($a['vaccine_name']) ?>
                <div class="meta">
                    <?= e($a['hospital']) ?> &middot;
                    <?= fmt_date($a['appointment_date']) ?>
                </div>
            </div>
            <?= badge(
                $a['vaccination_status'] === 'Vaccinated'
                    ? 'Vaccinated'
                    : $a['status']
            ) ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php if ($children): ?>
<div class="card" style="margin-top:26px">
    <h2 class="card-title">
        <i class="fa-solid fa-child"></i>My Children
        <small><a href="children.php">Manage</a></small>
    </h2>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Age</th>
                    <th>Gender</th>
                    <th>Vaccination Progress</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($children as $c):
                $q = $pdo->prepare(
                    "SELECT
                        COUNT(*) AS total,
                        COALESCE(SUM(
                            EXISTS (
                                SELECT 1
                                FROM appointments a
                                WHERE a.child_vaccination_id = cv.id
                                  AND a.vaccination_status = 'Vaccinated'
                            )
                        ), 0) AS done
                     FROM child_vaccinations cv
                     WHERE cv.child_id = ?"
                );
                $q->execute([$c['id']]);
                $p = $q->fetch();

                $total = (int) $p['total'];
                $done = (int) $p['done'];
                $pct = $total ? round($done / $total * 100) : 0;
            ?>
                <tr>
                    <td><b><?= e($c['name']) ?></b></td>
                    <td><?= e(age_text($c['dob'])) ?></td>
                    <td><?= e($c['gender'] ?: '-') ?></td>
                    <td>
                        <span class="bar">
                            <span style="width:<?= $pct ?>%"></span>
                        </span>
                        <?= $done ?> of <?= $total ?>
                    </td>
                    <td>
                        <a class="pbtn sm outline"
                           href="vaccinations.php?child=<?= (int)$c['id'] ?>">
                            Schedule
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php include 'includes/layout_bottom.php'; ?>
