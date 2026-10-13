
<?php
require_once __DIR__ . '/../includes/config.php';
require_parent();

$pageTitle = 'Vaccination Dates';
$active = 'vaccinations.php';
$pid = parent_id();
$childFilter = (int)($_GET['child'] ?? 0);

$q = $pdo->prepare(
    'SELECT id, name FROM children WHERE parent_id = ? ORDER BY name'
);
$q->execute([$pid]);
$children = $q->fetchAll();


$sql = "
    SELECT
        cv.id,
        cv.child_id,
        c.name AS child_name,
        c.parent_id,
        c.dob,
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
    INNER JOIN children c ON c.id = cv.child_id
    INNER JOIN vaccines v ON v.id = cv.vaccine_id

    WHERE c.parent_id = ?
";

$params = [$pid];


if ($childFilter) {
    $sql .= ' AND cv.child_id = ?';
    $params[] = $childFilter;
}

$sql .= ' ORDER BY c.name, cv.due_date';

$q = $pdo->prepare($sql);
$q->execute($params);
$rows = $q->fetchAll();

include 'includes/layout_top.php';
?>

<div class="card">
    <h2 class="card-title">
        <i class="fa-solid fa-calendar-check"></i>
        Vaccination Schedule
    </h2>

    <?php if ($children): ?>
        <form method="get" action="vaccinations.php" class="filter">
            <select
                class="field"
                name="child"
                onchange="this.form.submit()"
                aria-label="Filter by child"
            >
                <option value="0">All children</option>

                <?php foreach ($children as $c): ?>
                    <option
                        value="<?= (int)$c['id'] ?>"
                        <?= $childFilter === (int)$c['id'] ? 'selected' : '' ?>
                    >
                        <?= e($c['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    <?php endif; ?>

    <?php if (!$rows): ?>
        <div class="empty">
            <i class="fa-regular fa-calendar"></i>
            No vaccination schedule yet.
            <a href="children.php">Add a child</a> to generate one.
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Child</th>
                        <th>Vaccine</th>
                        <th>Due Date</th>
                        <th>Availability</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($rows as $r): ?>
                        <?php $st = schedule_status($r); ?>

                        <tr>
                            <td>
                                <b><?= e($r['child_name']) ?></b>
                            </td>

                            <td><?= e($r['vaccine_name']) ?></td>

                            <td><?= fmt_date($r['due_date']) ?></td>

                            <td><?= badge($r['availability']) ?></td>

                            <td><?= badge($st) ?></td>

                            <td>
                                <?php if (
                                    $st === 'Upcoming' ||
                                    $st === 'Overdue'
                                ): ?>
                                    <a
                                        class="pbtn sm"
                                        href="book.php?cv=<?= (int)$r['id'] ?>"
                                    >
                                        Book hospital
                                    </a>
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
