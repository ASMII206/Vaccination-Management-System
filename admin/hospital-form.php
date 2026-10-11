<?php
require_once __DIR__ . '/includes/bootstrap.php';

$active = 'hospitals.php';
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$editing = $id > 0;
$pageTitle = $editing ? 'Update Hospital' : 'Add Hospital';

$hasLogin = has_column($pdo, 'hospitals', 'email') && has_column($pdo, 'hospitals', 'password_hash');
$form = ['name' => '', 'address' => '', 'location' => '', 'phone' => '', 'email' => ''];
$current = null;
if ($editing) {
    $q = $pdo->prepare('SELECT * FROM hospitals WHERE id = ?');
    $q->execute([$id]);
    $current = $q->fetch();
    if (!$current) {
        flash('error', 'Hospital not found.');
        redirect('hospitals.php');
    }
    foreach ($form as $k => $_) $form[$k] = (string)($current[$k] ?? '');
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach ($form as $k => $_) $form[$k] = trim($_POST[$k] ?? '');
    if (!$hasLogin) $form['email'] = '';
    $form['email'] = strtolower($form['email']);
    $password = $hasLogin ? ($_POST['password'] ?? '') : '';

    if (mb_strlen($form['name']) < 3 || mb_strlen($form['name']) > 120) $errors[] = 'Enter the hospital name (3 to 120 characters).';
    if ($form['address'] === '' || mb_strlen($form['address']) > 255) $errors[] = 'Enter the hospital address.';
    if ($form['location'] === '' || mb_strlen($form['location']) > 100) $errors[] = 'Enter the city / location.';
    if ($form['phone'] !== '' && !preg_match('/^[0-9+\-\s()]{7,20}$/', $form['phone'])) $errors[] = 'Enter a valid phone number.';
    if ($form['email'] !== '' && !filter_var($form['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
    if ($password !== '' && strlen($password) < 8) $errors[] = 'The password must be at least 8 characters.';
    if ($password !== '' && $form['email'] === '') $errors[] = 'Add an email address so the hospital can log in with this password.';

    if (!$errors) {
        $d = $pdo->prepare('SELECT 1 FROM hospitals WHERE LOWER(name) = LOWER(?) AND id <> ?');
        $d->execute([$form['name'], $id]);
        if ($d->fetchColumn()) $errors[] = 'A hospital with this name already exists.';
    }
    if (!$errors && $form['email'] !== '') {
        $d = $pdo->prepare('SELECT 1 FROM hospitals WHERE email = ? AND id <> ?');
        $d->execute([$form['email'], $id]);
        if ($d->fetchColumn()) $errors[] = 'This email is already used by another hospital.';
        $d = $pdo->prepare('SELECT 1 FROM parents WHERE email = ?');
        $d->execute([$form['email']]);
        if ($d->fetchColumn() || hash_equals(ADMIN_EMAIL, $form['email'])) $errors[] = 'This email is already registered to another account.';
    }

    if (!$errors) {
        $email = $form['email'] !== '' ? $form['email'] : null;
        $phone = $form['phone'] !== '' ? $form['phone'] : null;
        if ($editing) {
            $pdo->prepare('UPDATE hospitals SET name = ?, address = ?, location = ?, phone = ? WHERE id = ?')
                ->execute([$form['name'], $form['address'], $form['location'], $phone, $id]);
            if ($hasLogin) $pdo->prepare('UPDATE hospitals SET email = ? WHERE id = ?')->execute([$email, $id]);
            if ($password !== '') {
                $pdo->prepare('UPDATE hospitals SET password_hash = ? WHERE id = ?')->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
            }
            flash('success', $form['name'] . ' was updated.');
        } else {
            $pdo->prepare('INSERT INTO hospitals (name, address, location, phone) VALUES (?,?,?,?)')
                ->execute([$form['name'], $form['address'], $form['location'], $phone]);
            if ($hasLogin && $email !== null) {
                $hash = $password !== '' ? password_hash($password, PASSWORD_DEFAULT) : null;
                $pdo->prepare('UPDATE hospitals SET email = ?, password_hash = ? WHERE id = ?')->execute([$email, $hash, (int)$pdo->lastInsertId()]);
            }
            flash('success', $form['name'] . ' was added.');
        }
        redirect('hospitals.php');
    }
}

include __DIR__ . '/includes/layout_top.php';
?>

<?php if ($errors): ?>
<div class="alert error"><?php foreach ($errors as $er): ?><p><?= e($er) ?></p><?php endforeach; ?></div>
<?php endif; ?>

<div class="card narrow" style="max-width:760px">
    <h2 class="card-title"><i class="fa-solid fa-hospital"></i><?= $editing ? 'Update Hospital Details' : 'Add a Hospital' ?></h2>
    <form method="post" action="hospital-form.php<?= $editing ? '?id=' . $id : '' ?>">
        <?= csrf_field() ?>
        <?php if ($editing): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>

        <div class="form-grid">
            <div class="full">
                <label class="lbl" for="name">Hospital Name</label>
                <input class="field" id="name" name="name" maxlength="120" value="<?= e($form['name']) ?>" required>
            </div>
            <div class="full">
                <label class="lbl" for="address">Address</label>
                <input class="field" id="address" name="address" maxlength="255" value="<?= e($form['address']) ?>" required>
            </div>
            <div>
                <label class="lbl" for="location">City / Location</label>
                <input class="field" id="location" name="location" maxlength="100" value="<?= e($form['location']) ?>" required>
            </div>
            <div>
                <label class="lbl" for="phone">Phone</label>
                <input class="field" id="phone" name="phone" maxlength="20" value="<?= e($form['phone']) ?>">
            </div>
            <?php if ($hasLogin): ?>
            <div>
                <label class="lbl" for="email">Login Email (optional)</label>
                <input class="field" type="email" id="email" name="email" maxlength="120" value="<?= e($form['email']) ?>">
            </div>
            <div>
                <label class="lbl" for="password"><?= $editing ? 'New Password (optional)' : 'Password (optional)' ?></label>
                <input class="field" type="password" id="password" name="password" autocomplete="new-password">
            </div>
            <div class="full"><span class="help">Add an email and password only if this hospital should log in to the Hospital Portal to update vaccination status.<?= $editing ? ' Leave the password empty to keep the current one.' : '' ?></span></div>
            <?php endif; ?>
        </div>

        <div class="form-actions">
            <button class="pbtn"><?= $editing ? 'Save Changes' : 'Add Hospital' ?></button>
            <a class="pbtn outline" href="hospitals.php">Cancel</a>
        </div>
    </form>
</div>

<?php include __DIR__ . '/includes/layout_bottom.php'; ?>
