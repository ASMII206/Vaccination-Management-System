<?php
require_once __DIR__ . '/../includes/config.php';
require_parent();

$pageTitle = 'My Children';
$active = 'children.php';
$pid = parent_id();
$errors = [];
$groups = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
$form = ['name' => '', 'dob' => '', 'gender' => '', 'blood_group' => '', 'notes' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    if (($_POST['action'] ?? '') === 'delete') {
        $pdo->prepare('DELETE FROM children WHERE id = ? AND parent_id = ?')->execute([(int)($_POST['id'] ?? 0), $pid]);
        flash('success', 'Child record deleted.');
        redirect('children.php');
    }

    foreach ($form as $k => $_) $form[$k] = trim($_POST[$k] ?? '');

    if (mb_strlen($form['name']) < 2 || mb_strlen($form['name']) > 100) $errors[] = "Enter the child's name.";
    $dt = DateTime::createFromFormat('Y-m-d', $form['dob']);
    if (!$dt || $dt->format('Y-m-d') !== $form['dob'] || $form['dob'] > date('Y-m-d')) $errors[] = 'Enter a valid date of birth (not in the future).';
    if (!in_array($form['gender'], ['Male', 'Female', 'Other'], true)) $errors[] = 'Select a gender.';
    if ($form['blood_group'] !== '' && !in_array($form['blood_group'], $groups, true)) $errors[] = 'Invalid blood group.';
    if (mb_strlen($form['notes']) > 255) $errors[] = 'Notes can be at most 255 characters.';

    if (!$errors) {
        $pdo->prepare('INSERT INTO children (parent_id, name, dob, gender, blood_group, notes) VALUES (?,?,?,?,?,?)')
            ->execute([$pid, $form['name'], $form['dob'], $form['gender'], $form['blood_group'] ?: null, $form['notes'] ?: null]);
        generate_schedule($pdo, (int)$pdo->lastInsertId(), $form['dob']);
        flash('success', 'Child added. The vaccination schedule has been created.');
        redirect('children.php');
    }
}

$q = $pdo->prepare('SELECT * FROM children WHERE parent_id = ? ORDER BY created_at DESC, id DESC');
$q->execute([$pid]);
$children = $q->fetchAll();

include 'includes/layout_top.php';
?>

<?php if ($errors): ?>
<div class="alert error"><?php foreach ($errors as $er): ?><p><?= e($er) ?></p><?php endforeach; ?></div>
<?php endif; ?>

<div class="grid-2">
    <div class="card">
        <h2 class="card-title"><i class="fa-solid fa-user-plus"></i>Add a Child</h2>
        <form method="post" action="children.php">
            <?= csrf_field() ?>
            <label class="lbl" for="name">Child's Name</label>
            <input class="field" id="name" name="name" value="<?= e($form['name']) ?>" required>

            <label class="lbl" for="dob">Date of Birth</label>
            <input class="field" type="date" id="dob" name="dob" value="<?= e($form['dob']) ?>" max="<?= date('Y-m-d') ?>" required>

            <label class="lbl" for="gender">Gender</label>
            <select class="field" id="gender" name="gender" required>
                <option value="">Select</option>
                <?php foreach (['Male', 'Female', 'Other'] as $g): ?>
                    <option <?= $form['gender'] === $g ? 'selected' : '' ?>><?= $g ?></option>
                <?php endforeach; ?>
            </select>

            <label class="lbl" for="blood_group">Blood Group (optional)</label>
            <select class="field" id="blood_group" name="blood_group">
                <option value="">Unknown</option>
                <?php foreach ($groups as $g): ?>
                    <option <?= $form['blood_group'] === $g ? 'selected' : '' ?>><?= $g ?></option>
                <?php endforeach; ?>
            </select>

            <label class="lbl" for="notes">Notes / Allergies (optional)</label>
            <input class="field" id="notes" name="notes" maxlength="255" value="<?= e($form['notes']) ?>">

            <div class="form-actions"><button class="pbtn">Add Child</button></div>
        </form>
    </div>

    <div class="card">
        <h2 class="card-title"><i class="fa-solid fa-child"></i>Registered Children<small><?= count($children) ?></small></h2>
        <?php if (!$children): ?>
            <p class="muted">No children added yet. Add your child to generate the vaccination schedule.</p>
        <?php endif; ?>
        <?php foreach ($children as $c): ?>
            <div class="row-item">
                <div>
                    <b><?= e($c['name']) ?></b>
                    <div class="meta"><?= e($c['gender'] ?: 'Gender not set') ?> &middot; <?= e(age_text($c['dob'])) ?> &middot; Born <?= fmt_date($c['dob']) ?></div>
                    <?php if ($c['blood_group'] || $c['notes']): ?>
                        <div class="meta"><?= $c['blood_group'] ? 'Blood group ' . e($c['blood_group']) : '' ?><?= ($c['blood_group'] && $c['notes']) ? ' &middot; ' : '' ?><?= e($c['notes']) ?></div>
                    <?php endif; ?>
                </div>
                <div class="actions">
                    <a class="pbtn sm outline" href="vaccinations.php?child=<?= (int)$c['id'] ?>">Schedule</a>
                    <a class="pbtn sm outline" href="child-edit.php?id=<?= (int)$c['id'] ?>">Edit</a>
                    <form method="post" action="children.php" class="inline" onsubmit="return confirm('Delete this child and all related vaccination records?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                        <button class="pbtn sm danger">Delete</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php include 'includes/layout_bottom.php'; ?>
