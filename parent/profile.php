<?php
require_once __DIR__ . '/../includes/config.php';
require_parent();

$pageTitle = 'My Profile';
$active = 'profile.php';
$pid = parent_id();
$errors = [];

$q = $pdo->prepare('SELECT * FROM parents WHERE id = ?');
$q->execute([$pid]);
$me = $q->fetch();
if (!$me) {
    redirect('../authentication/logout.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    if (($_POST['action'] ?? '') === 'password') {
        $current = $_POST['current'] ?? '';
        $new = $_POST['new'] ?? '';
        if (!password_verify($current, $me['password_hash'])) $errors[] = 'Your current password is incorrect.';
        elseif (strlen($new) < 8) $errors[] = 'The new password must be at least 8 characters.';
        elseif ($new !== ($_POST['confirm'] ?? '')) $errors[] = 'The new passwords do not match.';
        else {
            $pdo->prepare('UPDATE parents SET password_hash = ? WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), $pid]);
            flash('success', 'Password changed.');
            redirect('profile.php');
        }
    } else {
        $name = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $addr = trim($_POST['address'] ?? '');
        if (mb_strlen($name) < 3 || mb_strlen($name) > 100) $errors[] = 'Name must be between 3 and 100 characters.';
        if (!preg_match('/^[0-9+\- ]{7,15}$/', $phone)) $errors[] = 'Enter a valid phone number (7-15 digits, + or -).';
        if ($addr === '' || mb_strlen($addr) > 255) $errors[] = 'Address is required.';
        if (!$errors) {
            $pdo->prepare('UPDATE parents SET full_name = ?, phone = ?, address = ? WHERE id = ?')->execute([$name, $phone, $addr, $pid]);
            $_SESSION['user_name'] = $name;
            flash('success', 'Profile updated.');
            redirect('profile.php');
        }
        $me['full_name'] = $name;
        $me['phone'] = $phone;
        $me['address'] = $addr;
    }
}

include 'includes/layout_top.php';
?>

<?php if ($errors): ?>
<div class="alert error"><?php foreach ($errors as $er): ?><p><?= e($er) ?></p><?php endforeach; ?></div>
<?php endif; ?>

<div class="grid-2">
    <div class="card">
        <h2 class="card-title"><i class="fa-solid fa-user"></i>Profile Details</h2>
        <form method="post" action="profile.php">
            <?= csrf_field() ?>
            <label class="lbl" for="full_name">Full Name</label>
            <input class="field" id="full_name" name="full_name" value="<?= e($me['full_name']) ?>" required>

            <label class="lbl" for="email">Email (cannot be changed)</label>
            <input class="field" id="email" value="<?= e($me['email']) ?>" disabled>

            <label class="lbl" for="phone">Phone</label>
            <input class="field" id="phone" name="phone" value="<?= e($me['phone']) ?>" required>

            <label class="lbl" for="address">Address</label>
            <input class="field" id="address" name="address" value="<?= e($me['address']) ?>" required>

            <p class="muted" style="margin-top:14px">Member since <?= fmt_date($me['created_at']) ?></p>
            <div class="form-actions"><button class="pbtn">Update Profile</button></div>
        </form>
    </div>

    <div class="card">
        <h2 class="card-title"><i class="fa-solid fa-lock"></i>Change Password</h2>
        <form method="post" action="profile.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="password">

            <label class="lbl" for="current">Current Password</label>
            <input class="field" type="password" id="current" name="current" required>

            <label class="lbl" for="new">New Password</label>
            <input class="field" type="password" id="new" name="new" minlength="8" required>

            <label class="lbl" for="confirm">Confirm New Password</label>
            <input class="field" type="password" id="confirm" name="confirm" minlength="8" required>

            <div class="form-actions"><button class="pbtn">Change Password</button></div>
        </form>
    </div>
</div>

<?php include 'includes/layout_bottom.php'; ?>
