<?php
require_once __DIR__ . '/../includes/config.php';
require_parent();

$pageTitle = 'Book Hospital';
$active = 'vaccinations.php';
$pid = parent_id();
$cvId = (int)($_GET['cv'] ?? $_POST['cv'] ?? 0);
$slots = ['09:00 AM - 11:00 AM', '11:00 AM - 01:00 PM', '02:00 PM - 04:00 PM', '04:00 PM - 06:00 PM'];

// The schedule entry must belong to the logged-in parent
$q = $pdo->prepare('SELECT * FROM v_child_schedule WHERE id = ? AND parent_id = ?');
$q->execute([$cvId, $pid]);
$item = $q->fetch();
if (!$item) {
    flash('error', 'Vaccination entry not found.');
    redirect('vaccinations.php');
}
$state = schedule_status($item);
if (!in_array($state, ['Upcoming', 'Overdue'], true)) {
    flash('error', 'This vaccination cannot be booked right now (status: ' . $state . ').');
    redirect('vaccinations.php');
}

$errors = [];
$picked = 0;
$date = '';
$slot = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $picked = (int)($_POST['hospital_id'] ?? 0);
    $date = $_POST['appointment_date'] ?? '';
    $slot = $_POST['time_slot'] ?? '';

    $h = $pdo->prepare('SELECT id FROM hospitals WHERE id = ?');
    $h->execute([$picked]);
    if (!$h->fetch()) $errors[] = 'Please select a hospital.';

    $dt = DateTime::createFromFormat('Y-m-d', $date);
    if (!$dt || $dt->format('Y-m-d') !== $date || $date < date('Y-m-d')) $errors[] = 'Choose today or a future date.';
    if (!in_array($slot, $slots, true)) $errors[] = 'Choose a time slot.';
    if ($item['availability'] === 'Unavailable') $errors[] = 'This vaccine is currently unavailable. Please try again later.';

    if (!$errors) {
        $pdo->prepare('INSERT INTO appointments (child_vaccination_id, parent_id, hospital_id, appointment_date, time_slot) VALUES (?,?,?,?,?)')
            ->execute([$cvId, $pid, $picked, $date, $slot]);
        flash('success', 'Booking request sent. It will be confirmed once the admin approves it.');
        redirect('requests.php');
    }
}

$search = trim($_GET['q'] ?? '');
$like = '%' . $search . '%';
$q = $pdo->prepare('SELECT * FROM hospitals WHERE name LIKE ? OR location LIKE ? ORDER BY name');
$q->execute([$like, $like]);
$hospitals = $q->fetchAll();

include 'includes/layout_top.php';
?>

<?php if ($errors): ?>
<div class="alert error"><?php foreach ($errors as $er): ?><p><?= e($er) ?></p><?php endforeach; ?></div>
<?php endif; ?>

<div class="card">
    <h2 class="card-title"><i class="fa-solid fa-syringe"></i>Vaccination Details</h2>
    <p class="summary-line">
        <b><?= e($item['child_name']) ?></b> &ndash; <b><?= e($item['vaccine_name']) ?></b>
        &middot; due <?= fmt_date($item['due_date']) ?> <?= badge($state) ?>
    </p>

    <form method="get" action="book.php" class="filter">
        <input type="hidden" name="cv" value="<?= $cvId ?>">
        <input class="field" type="search" name="q" value="<?= e($search) ?>" placeholder="Search hospital by name or city">
        <button class="pbtn outline">Search</button>
    </form>

    <form method="post" action="book.php?cv=<?= $cvId ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="cv" value="<?= $cvId ?>">

        <label class="lbl">Select Hospital</label>
        <?php if (!$hospitals): ?><p class="muted">No hospitals match your search.</p><?php endif; ?>
        <?php foreach ($hospitals as $h): ?>
            <label class="choice">
                <input type="radio" name="hospital_id" value="<?= (int)$h['id'] ?>" <?= $picked === (int)$h['id'] ? 'checked' : '' ?> required>
                <span><b><?= e($h['name']) ?></b><small><?= e($h['location']) ?> &middot; <?= e($h['address']) ?></small></span>
            </label>
        <?php endforeach; ?>

        <div class="grid-2" style="margin-top:8px">
            <div>
                <label class="lbl" for="appointment_date">Preferred Date</label>
                <input class="field" type="date" id="appointment_date" name="appointment_date" min="<?= date('Y-m-d') ?>" value="<?= e($date) ?>" required>
            </div>
            <div>
                <label class="lbl" for="time_slot">Time Slot</label>
                <select class="field" id="time_slot" name="time_slot" required>
                    <option value="">Select a slot</option>
                    <?php foreach ($slots as $s): ?>
                        <option <?= $slot === $s ? 'selected' : '' ?>><?= $s ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-actions">
            <button class="pbtn">Send Booking Request</button>
            <a class="pbtn outline" href="vaccinations.php">Cancel</a>
        </div>
    </form>
</div>

<?php include 'includes/layout_bottom.php'; ?>
