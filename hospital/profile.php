<?php
require_once __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Hospital profile';
$pageSub = 'Keep your hospital details up to date';
$active = 'profile.php';
$errors = [];
$errorsPw = [];
$form = $hospital;   

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
            redirect('profile.php');
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
            redirect('profile.php');
        }
        
        $form['name'] = $name;
        $form['address'] = $address;
        $form['location'] = $location;
        $form['phone'] = $phone;
    }
}

include __DIR__ . '/includes/layout_top.php';
?>

<section class="pan prof" style="margin-bottom:20px;">
  <div class="pf-h" style="margin-top:0">
    <div><h2>Hospital details</h2><p class="mut" style="margin:0">These details are shown to parents when they book.</p></div>
  </div>

  <?php if ($errors): ?>
    <div class="alert error" style="margin-top:16px"><?php foreach ($errors as $er): ?><p><?= e($er) ?></p><?php endforeach; ?></div>
  <?php endif; ?>

  <form method="post" action="profile.php">
    <?= csrf_field() ?>
    <label for="name">Hospital name</label>
    <input id="name" name="name" value="<?= e($form['name']) ?>" maxlength="120" required>

    <label for="email">Login email (cannot be changed)</label>
    <input id="email" value="<?= e($hospital['email']) ?>" disabled>

    <label for="address">Address</label>
    <input id="address" name="address" value="<?= e($form['address']) ?>" maxlength="255" required>

    <div class="frow">
      <div>
        <label for="location">Location (city / area)</label>
        <input id="location" name="location" value="<?= e($form['location']) ?>" maxlength="100" required>
      </div>
      <div>
        <label for="phone">Phone</label>
        <input id="phone" name="phone" value="<?= e($form['phone']) ?>" pattern="[0-9+\- ]{7,15}" title="Enter a valid phone number (digits, + or -)" required>
      </div>
    </div>

    <div class="acts" style="justify-content:flex-start"><button class="btn">Save changes</button></div>
  </form>
</section>

<section class="pan prof">
  <h2>Change password</h2>
  <p class="mut">Use at least 8 characters.</p>

  <?php if ($errorsPw): ?>
    <div class="alert error"><?php foreach ($errorsPw as $er): ?><p><?= e($er) ?></p><?php endforeach; ?></div>
  <?php endif; ?>

  <form method="post" action="profile.php">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="password">

    <label for="current">Current password</label>
    <input type="password" id="current" name="current" required>

    <div class="frow">
      <div>
        <label for="new">New password</label>
        <input type="password" id="new" name="new" minlength="8" required>
      </div>
      <div>
        <label for="confirm">Confirm new password</label>
        <input type="password" id="confirm" name="confirm" minlength="8" required>
      </div>
    </div>

    <div class="acts" style="justify-content:flex-start"><button class="btn">Change password</button></div>
  </form>
</section>

<?php include __DIR__ . '/includes/layout_bottom.php'; ?>
