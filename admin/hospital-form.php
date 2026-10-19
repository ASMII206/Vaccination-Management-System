<?php
require_once __DIR__ . '/includes/bootstrap.php';

$active = 'hospitals.php';
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$editing = $id > 0;
$pageTitle = $editing ? 'Update Hospital' : 'Add Hospital';

$hasLogin = has_column('hospitals', 'email') && has_column('hospitals', 'password_hash');
$form = ['name' => '', 'address' => '', 'location' => '', 'phone' => '', 'email' => ''];
$current = null;
if ($editing) {
    $result = mysqli_query($conn, "SELECT * FROM hospitals WHERE id = $id");
    $current = mysqli_fetch_assoc($result);
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
        $nameSafe = esc($form['name']);
        $result = mysqli_query($conn, "SELECT 1 FROM hospitals WHERE LOWER(name) = LOWER('$nameSafe') AND id <> $id");
        if (mysqli_num_rows($result) > 0) $errors[] = 'A hospital with this name already exists.';
    }
    if (!$errors && $form['email'] !== '') {
        $emailSafe = esc($form['email']);
        $result = mysqli_query($conn, "SELECT 1 FROM hospitals WHERE email = '$emailSafe' AND id <> $id");
        if (mysqli_num_rows($result) > 0) $errors[] = 'This email is already used by another hospital.';


        $result = mysqli_query($conn, "SELECT 1 FROM parents WHERE email = '$emailSafe'");
        $usedByParent = mysqli_num_rows($result) > 0;
        $result = mysqli_query($conn, "SELECT 1 FROM admins WHERE email = '$emailSafe'");
        $usedByAdmin = mysqli_num_rows($result) > 0;
        if ($usedByParent || $usedByAdmin) $errors[] = 'This email is already registered to another account.';
    }

    if (!$errors) {
        $email = $form['email'] !== '' ? $form['email'] : null;
        $phone = $form['phone'] !== '' ? $form['phone'] : null;
        $nameSafe = esc($form['name']);
        $addressSafe = esc($form['address']);
        $locationSafe = esc($form['location']);
        $phoneSql = nullable($phone);
        $emailSql = nullable($email);

        if ($editing) {
            mysqli_query($conn, "UPDATE hospitals SET name = '$nameSafe', address = '$addressSafe', location = '$locationSafe', phone = $phoneSql WHERE id = $id");
            if ($hasLogin) mysqli_query($conn, "UPDATE hospitals SET email = $emailSql WHERE id = $id");
            if ($password !== '') {
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                mysqli_query($conn, "UPDATE hospitals SET password_hash = '$newHash' WHERE id = $id");
            }
            flash('success', $form['name'] . ' was updated.');
        } else {
            mysqli_query($conn, "INSERT INTO hospitals (name, address, location, phone) VALUES ('$nameSafe', '$addressSafe', '$locationSafe', $phoneSql)");
            if ($hasLogin && $email !== null) {
                $newId = mysqli_insert_id($conn);
                $hashSql = $password !== '' ? "'" . password_hash($password, PASSWORD_DEFAULT) . "'" : 'NULL';
                mysqli_query($conn, "UPDATE hospitals SET email = $emailSql, password_hash = $hashSql WHERE id = $newId");
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
