<?php
require_once __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Appointments & Profile';
$active = 'manage.php';
$today = date('Y-m-d');
$errors = [];
$errorsPw = [];
$form = $hospital;

/* ---------- Profile / password POST ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    if (($_POST['action'] ?? '') === 'password') {
        $q = $pdo->prepare('SELECT password_hash FROM hospitals WHERE id = ?');
        $q->execute([$hid]);
        $hash = $q->fetchColumn();
        $new = $_POST['new'] ?? '';

        if (!$hash || !password_verify($_POST['current'] ?? '', $hash)) $errorsPw[] = 'Your current password is incorrect.';
        elseif (strlen($new) < 8) $errorsPw[] = 'The new password must be at least 8 characters.';
        elseif ($new !== ($_POST['confirm'] ?? '')) $errorsPw[] = 'The new passwords do not match.';
        else {
            $pdo->prepare('UPDATE hospitals SET password_hash = ? WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), $hid]);
            flash('success', 'Password changed.');
            redirect('manage.php#profile');
        }
    } else {
        $name = trim($_POST['name'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if (mb_strlen($name) < 3 || mb_strlen($name) > 120) $errors[] = 'Hospital name must be between 3 and 120 characters.';
        if ($address === '' || mb_strlen($address) > 255) $errors[] = 'Address is required.';
        if ($location === '' || mb_strlen($location) > 100) $errors[] = 'Location (city / area) is required.';
        if (!preg_match('/^[0-9+\- ]{7,15}$/', $phone)) $errors[] = 'Enter a valid phone number (7-15 digits, + or -).';

        if (!$errors) {
            $pdo->prepare('UPDATE hospitals SET name = ?, address = ?, location = ?, phone = ? WHERE id = ?')
                ->execute([$name, $address, $location, $phone, $hid]);
            $_SESSION['user_name'] = $name;
            flash('success', 'Hospital profile updated.');
            redirect('manage.php#profile');
        }
        $form['name'] = $name;
        $form['address'] = $address;
        $form['location'] = $location;
        $form['phone'] = $phone;
    }
}

/* ---------- Appointments ---------- */
$search = trim($_GET['q'] ?? '');
$filter = $_GET['status'] ?? '';
if (!in_array($filter, ['Scheduled', 'Vaccinated', 'Not Vaccinated'], true)) $filter = '';

$hasRemarks = has_column($pdo, 'appointments', 'remarks');

$sql = 'SELECT a.id, a.appointment_date, a.time_slot, a.vaccination_status, a.status AS req_status,
               ' . ($hasRemarks ? 'a.remarks' : 'NULL') . ' AS remarks,
               c.name AS child_name, c.dob, p.full_name AS parent_name, v.name AS vaccine_name
        FROM appointments a
        JOIN child_vaccinations cv ON cv.id = a.child_vaccination_id
        JOIN children c ON c.id = cv.child_id
        JOIN parents p ON p.id = a.parent_id
        JOIN vaccines v ON v.id = cv.vaccine_id
        WHERE a.hospital_id = ? AND a.status IN (\'Pending\', \'Approved\')';
$params = [$hid];

if ($filter !== '') {
    $sql .= ' AND a.vaccination_status = ?';
    $params[] = $filter === 'Scheduled' ? 'Pending' : $filter;
    if ($filter === 'Scheduled') $sql .= ' AND a.status = \'Approved\'';
}
if ($search !== '') {
    $refId = preg_match('/^(?:vms-?)?0*(\d+)$/i', $search, $m) ? (int)$m[1] : 0;
    $sql .= ' AND (c.name LIKE ? OR p.full_name LIKE ? OR v.name LIKE ? OR a.id = ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like, $refId);
}
$sql .= ' ORDER BY (a.vaccination_status = \'Pending\') DESC, a.appointment_date, a.id';

$q = $pdo->prepare($sql);
$q->execute($params);
$rows = $q->fetchAll();

function tab_link($status, $search) {
    $qs = array_filter(['q' => $search, 'status' => $status], function ($v) { return $v !== ''; });
    return 'manage.php' . ($qs ? '?' . http_build_query($qs) : '') . '#appointments';
}

include __DIR__ . '/includes/layout_top.php';
?>

<div class="card sec-anchor" id="appointments">
    <h2 class="card-title"><i class="fa-solid fa-calendar-check"></i>Appointments<small>Parent requests and bookings approved by the administrator</small></h2>

    <div class="toolbar">
        <form method="get" action="manage.php#appointments">
            <?php if ($filter !== ''): ?><input type="hidden" name="status" value="<?= e($filter) ?>"><?php endif; ?>
            <input class="field" type="search" name="q" value="<?= e($search) ?>" placeholder="Search by child, parent, vaccine or reference" aria-label="Search appointments">
        </form>
        <div class="seg">
            <a href="<?= e(tab_link('', $search)) ?>" class="<?= $filter === '' ? 'on' : '' ?>">All</a>
            <a href="<?= e(tab_link('Scheduled', $search)) ?>" class="<?= $filter === 'Scheduled' ? 'on' : '' ?>">Scheduled</a>
            <a href="<?= e(tab_link('Vaccinated', $search)) ?>" class="<?= $filter === 'Vaccinated' ? 'on' : '' ?>">Vaccinated</a>
            <a href="<?= e(tab_link('Not Vaccinated', $search)) ?>" class="<?= $filter === 'Not Vaccinated' ? 'on' : '' ?>">Not Vaccinated</a>
        </div>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Reference</th><th>Child</th><th>Parent</th><th>Vaccine</th><th>Date and time</th><th>Status</th><th></th></tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="7" class="empty">
                    <?= ($search !== '' || $filter !== '') ? 'No appointments match your search.' : 'No appointments yet. Parent requests will appear here.' ?>
                </td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $r):
                $awaiting = $r['req_status'] === 'Pending';
                $isScheduled = !$awaiting && $r['vaccination_status'] === 'Pending';
                $label = outcome_label($r['vaccination_status']); ?>
                <tr>
                    <td><b><?= e(appt_ref($r['id'])) ?></b></td>
                    <td><?= e($r['child_name']) ?><small><?= e(age_text($r['dob'])) ?></small></td>
                    <td><?= e($r['parent_name']) ?></td>
                    <td><?= e($r['vaccine_name']) ?></td>
                    <td>
                        <?= fmt_date($r['appointment_date']) ?>
                        <?php if ($isScheduled && $r['appointment_date'] < $today): ?><span class="tag od">Overdue</span>
                        <?php elseif ($isScheduled && $r['appointment_date'] === $today): ?><span class="tag">Today</span><?php endif; ?>
                        <small><?= e($r['time_slot']) ?></small>
                    </td>
                    <td>
                        <?= $awaiting ? '<span class="badge amber">Awaiting approval</span>' : ($isScheduled ? '<span class="badge amber">Scheduled</span>' : badge($r['vaccination_status'])) ?>
                        <?php if ($r['remarks']): ?><small title="<?= e($r['remarks']) ?>">Has remarks</small><?php endif; ?>
                    </td>
                    <td>
                        <?php if (!$awaiting): ?>
                        <button type="button" class="pbtn sm <?= $isScheduled ? '' : 'outline' ?>" data-status-open
                                data-id="<?= (int)$r['id'] ?>"
                                data-child="<?= e($r['child_name'] . ' (' . age_text($r['dob']) . ')') ?>"
                                data-details="<?= e($r['vaccine_name'] . ', ' . fmt_date($r['appointment_date']) . ' - ' . $r['time_slot']) ?>"
                                data-status="<?= e($label) ?>"
                                data-remarks="<?= e($r['remarks']) ?>"><?= $isScheduled ? 'Update status' : 'Change' ?></button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="grid-2 sec-anchor" id="profile">
    <div class="card">
        <h2 class="card-title"><i class="fa-solid fa-hospital"></i>Hospital Profile<small>Shown to parents when they book</small></h2>

        <?php if ($errors): ?>
            <div class="alert error"><?php foreach ($errors as $er): ?><p><?= e($er) ?></p><?php endforeach; ?></div>
        <?php endif; ?>

        <form method="post" action="manage.php#profile">
            <?= csrf_field() ?>
            <label class="lbl" for="name">Hospital name</label>
            <input class="field" id="name" name="name" value="<?= e($form['name']) ?>" maxlength="120" required>

            <label class="lbl" for="email">Login email (cannot be changed)</label>
            <input class="field" id="email" value="<?= e($hospital['email']) ?>" disabled>

            <label class="lbl" for="address">Address</label>
            <input class="field" id="address" name="address" value="<?= e($form['address']) ?>" maxlength="255" required>

            <div class="frow">
                <div>
                    <label class="lbl" for="location">Location (city / area)</label>
                    <input class="field" id="location" name="location" value="<?= e($form['location']) ?>" maxlength="100" required>
                </div>
                <div>
                    <label class="lbl" for="phone">Phone</label>
                    <input class="field" id="phone" name="phone" value="<?= e($form['phone']) ?>" pattern="[0-9+\- ]{7,15}" title="Enter a valid phone number (digits, + or -)" required>
                </div>
            </div>

            <div class="form-actions"><button class="pbtn">Save changes</button></div>
        </form>
    </div>

    <div class="card">
        <h2 class="card-title"><i class="fa-solid fa-lock"></i>Change Password<small>At least 8 characters</small></h2>

        <?php if ($errorsPw): ?>
            <div class="alert error"><?php foreach ($errorsPw as $er): ?><p><?= e($er) ?></p><?php endforeach; ?></div>
        <?php endif; ?>

        <form method="post" action="manage.php#profile">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="password">

            <label class="lbl" for="current">Current password</label>
            <input class="field" type="password" id="current" name="current" required>

            <label class="lbl" for="new">New password</label>
            <input class="field" type="password" id="new" name="new" minlength="8" required>

            <label class="lbl" for="confirm">Confirm new password</label>
            <input class="field" type="password" id="confirm" name="confirm" minlength="8" required>

            <div class="form-actions"><button class="pbtn">Change password</button></div>
        </form>
    </div>
</div>

<?php ob_start(); ?>
<div class="modal-ov" id="statusModal">
    <form class="modal" method="post" action="update_status.php" role="dialog" aria-modal="true" aria-label="Update vaccination status">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="">
        <input type="hidden" name="ret_q" value="<?= e($search) ?>">
        <input type="hidden" name="ret_status" value="<?= e($filter) ?>">
        <h2>Update vaccination status</h2>
        <p class="muted" style="margin-bottom:16px"><span id="m-child"></span><br><span id="m-details"></span></p>
        <label class="choice"><input type="radio" name="status" value="Vaccinated" required><span><b>Vaccinated</b><small>The dose was administered.</small></span></label>
        <label class="choice"><input type="radio" name="status" value="Not Vaccinated"><span><b>Not vaccinated</b><small>The child did not receive the dose.</small></span></label>
        <label class="lbl" for="remarks">Remarks (optional)</label>
        <textarea class="field" id="remarks" name="remarks" rows="3" maxlength="255"></textarea>
        <div class="form-actions">
            <button type="button" class="pbtn outline" data-close>Cancel</button>
            <button class="pbtn">Save status</button>
        </div>
    </form>
</div>
<?php $extraBottom = ob_get_clean(); ?>

<?php include __DIR__ . '/includes/layout_bottom.php'; ?>