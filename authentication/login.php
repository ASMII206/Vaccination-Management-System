
<?php
require_once __DIR__ . '/../includes/config.php';

// Redirect already signed-in users to their respective portals.
if (!empty($_SESSION['role']) && ($url = portal_url($_SESSION['role']))) {
    redirect('../' . $url);
}

$error = '';
$email = '';
$notice = flash();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $account = null;

    if (!csrf_valid()) {
        $error = 'Your session expired. Please try again.';
    } elseif ($email === '' || $password === '') {
        $error = 'Please enter your email and password.';
    } else {
        // 1. Admin login
        if (
            hash_equals(ADMIN_EMAIL, $email) &&
            password_verify($password, ADMIN_PASSWORD_HASH)
        ) {
            $account = [
                'role' => 'admin',
                'id' => 0,
                'name' => 'Administrator'
            ];
        }

        // 2. Parent login
        if (!$account) {
            $q = $pdo->prepare(
                'SELECT id, full_name, password_hash
                 FROM parents WHERE email = ?'
            );
            $q->execute([$email]);
            $row = $q->fetch();

            if ($row && password_verify($password, $row['password_hash'])) {
                $account = [
                    'role' => 'parent',
                    'id' => (int) $row['id'],
                    'name' => $row['full_name']
                ];
            }
        }

        // 3. Hospital login
        if (!$account) {
            try {
                $q = $pdo->prepare(
                    'SELECT id, name, password_hash
                     FROM hospitals WHERE email = ?'
                );
                $q->execute([$email]);
                $row = $q->fetch();

                if (
                    $row &&
                    !empty($row['password_hash']) &&
                    password_verify($password, $row['password_hash'])
                ) {
                    $account = [
                        'role' => 'hospital',
                        'id' => (int) $row['id'],
                        'name' => $row['name']
                    ];
                }
            } catch (PDOException $ex) {
                // Hospital login columns may not be installed yet.
            }
        }

        if (!$account) {
            $error = 'Wrong email or password.';
        } elseif (!($url = portal_url($account['role']))) {
            $error = 'Your ' . $account['role'] .
                     ' account was verified, but that portal is not available yet.';
        } else {
            session_regenerate_id(true);

            $_SESSION['role'] = $account['role'];
            $_SESSION['user_id'] = $account['id'];
            $_SESSION['user_name'] = $account['name'];

            redirect('../' . $url);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>VaxCare | Login</title>

    <link rel="stylesheet" href="../css/login.css">
</head>

<body>
    <section class="login-section">
        <div class="box">

            <h2>Login</h2>

            <p class="mut">
                Sign in to access your VaxCare account.
            </p>

            <form id="loginForm" method="post" action="login.php">
                <?= csrf_field() ?>

                <label for="email">Email Address</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="Enter your email"
                    value="<?= e($email) ?>"
                    autocomplete="username"
                    required
                >

                <label for="password">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Enter your password"
                    autocomplete="current-password"
                    required
                >

                <?php if ($notice): ?>
                    <p class="success"><?= e($notice[1]) ?></p>
                <?php endif; ?>

                <p class="error" id="error"><?= e($error) ?></p>

                <button type="submit" class="btn">
                    Login
                </button>
            </form>

            <p class="hint">
                <a href="../index.php">&larr; Back to Home</a>
            </p>

        </div>
    </section>
</body>
</html>