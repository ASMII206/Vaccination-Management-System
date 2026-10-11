<?php
require_once __DIR__ . '/../includes/config.php';
require_parent();

$pageTitle = 'Edit Child';
$active = 'children.php';
$pid = parent_id();
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$groups = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];

$q = $pdo->prepare('SELECT * FROM children WHERE id = ? AND parent_id = ?');
$q->execute([$id, $pid]);
$child = $q->fetch();
if (!$child) {
    flash('error', 'Child record not found.');
    redirect('children.php');
}
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $new = [
        'name'        => trim($_POST['name'] ?? ''),
        'dob'         => $_POST['dob'] ?? '',
        'gender'      => $_POST['gender'] ?? '',
        'blood_group' => $_POST['blood_group'] ?? '',
        'notes'       => trim($_POST['notes'] ?? ''),
    ];

    if (mb_strlen($new['name']) < 2 || mb_strlen($new['name']) > 100) $errors[] = "Enter the child's name.";
    $dt = DateTime::createFromFormat('Y-m-d', $new['dob']);
    if (!$dt || $dt->format('Y-m-d') !== $new['dob'] || $new['dob'] > date('Y-m-d')) $errors[] = 'Enter a valid date of birth (not in the future).';
    if ($new['gender'] !== '' && !in_array($new['gender'], ['Male', 'Female', 'Other'], true)) $errors[] = 'Invalid gender.';
    if ($new['blood_group'] !== '' && !in_array($new['blood_group'], $groups, true)) $errors[] = 'Invalid blood group.';
    if (mb_strlen($new['notes']) > 255) $errors[] = 'Notes can be at most 255 characters.';

    if (!$errors) {
        $pdo->prepare('UPDATE children SET name = ?, dob = ?, gender = ?, blood_group = ?, notes = ? WHERE id = ? AND parent_id = ?')
            ->execute([$new['name'], $new['dob'], $new['gender'] ?: null, $new['blood_group'] ?: null, $new['notes'] ?: null, $id, $pid]);

        if ($new['dob'] !== $child['dob']) {
            // Date of birth changed: recalculate every due date
            $pdo->prepare('UPDATE child_vaccinations cv JOIN vaccines v ON v.id = cv.vaccine_id
                           SET cv.due_date = DATE_ADD(?, INTERVAL v.age_in_days DAY) WHERE cv.child_id = ?')
                ->execute([$new['dob'], $id]);
        }
        flash('success', 'Child details updated.');
        redirect('children.php');
    }
    $child = array_merge($child, $new);
}

include 'includes/layout_top.php';
?>

<?php if ($errors): ?>
<div class="alert error"><?php foreach ($errors as $er): ?><p><?= e($er) ?></p><?php endforeach; ?></div>
<?php endif; ?>

<div class="card narrow">
    <h2 class="card-title"><i class="fa-solid fa-pen-to-square"></i>Update Child Details</h2>
    <form method="post" action="child-edit.php?id=<?= $id ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $id ?>">

        <label class="lbl" for="name">Child's Name</label>
        <input class="field" id="name" name="name" value="<?= e($child['name']) ?>" required>

        <label class="lbl" for="dob">Date of Birth</label>
        <input class="field" type="date" id="dob" name="dob" value="<?= e($child['dob']) ?>" max="<?= date('Y-m-d') ?>" required>

        <label class="lbl" for="gender">Gender</label>
        <select class="field" id="gender" name="gender">
            <option value="">Not specified</option>
            <?php foreach (['Male', 'Female', 'Other'] as $g): ?>
                <option <?= $child['gender'] === $g ? 'selected' : '' ?>><?= $g ?></option>
            <?php endforeach; ?>
        </select>

        <label class="lbl" for="blood_group">Blood Group</label>
        <select class="field" id="blood_group" name="blood_group">
            <option value="">Unknown</option>
            <?php foreach ($groups as $g): ?>
                <option <?= $child['blood_group'] === $g ? 'selected' : '' ?>><?= $g ?></option>
            <?php endforeach; ?>
        </select>

        <label class="lbl" for="notes">Notes / Allergies</label>
        <input class="field" id="notes" name="notes" maxlength="255" value="<?= e($child['notes']) ?>">

        <div class="form-actions">
            <button class="pbtn">Save Changes</button>
            <a class="pbtn outline" href="children.php">Cancel</a>
        </div>
    </form>
</div>

<?php include 'includes/layout_bottom.php'; ?>
