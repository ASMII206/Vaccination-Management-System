<?php
require_once __DIR__ . '/../includes/config.php';

if (!empty($_SESSION['role']) && ($url = portal_url($_SESSION['role']))) {
    redirect('../' . $url);
}

$fields = [
    'role', 'name', 'email', 'phone', 'address', 'location',
    'child_name', 'child_dob', 'hospital', 'vaccine_date'
];

$old = array_fill_keys($fields, '');

if (isset($_GET['role']) && in_array($_GET['role'], ['parent', 'hospital'], true)) {
    $old['role'] = $_GET['role'];
}

$errors = [];

$notice = flash();
$loginError = '';
$loginEmail = '';
$startView = isset($_GET['role']) ? 'register' : 'login';

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['form_type'] ?? '') === 'login'
) {
    $loginEmail = strtolower(trim($_POST['email'] ?? ''));
    $loginPassword = $_POST['password'] ?? '';
    $account = null;

    if (!csrf_valid()) {
        $loginError = 'Your session expired. Please try again.';
    } elseif ($loginEmail === '' || $loginPassword === '') {
        $loginError = 'Please enter your email and password.';
    } else {
        $loginEmailSafe = esc($loginEmail);

        $result = mysqli_query($conn, "SELECT id, full_name, password_hash FROM parents WHERE email = '$loginEmailSafe'");
        $row = mysqli_fetch_assoc($result);

        if ($row && password_verify($loginPassword, $row['password_hash'])) {
            $account = [
                'role' => 'parent',
                'id' => (int) $row['id'],
                'name' => $row['full_name']
            ];
        }

        if (!$account) {
            try {
                $result = mysqli_query($conn, "SELECT id, name, password_hash FROM hospitals WHERE email = '$loginEmailSafe'");
                $row = mysqli_fetch_assoc($result);

                if (
                    $row &&
                    !empty($row['password_hash']) &&
                    password_verify($loginPassword, $row['password_hash'])
                ) {
                    $account = [
                        'role' => 'hospital',
                        'id' => (int) $row['id'],
                        'name' => $row['name']
                    ];
                }
            } catch (mysqli_sql_exception $ex) {
            }
        }

        if (!$account) {
            try {
                $result = mysqli_query($conn, "SELECT id, full_name, password_hash FROM admins WHERE email = '$loginEmailSafe'");
                $row = mysqli_fetch_assoc($result);

                if (
                    $row &&
                    !empty($row['password_hash']) &&
                    password_verify($loginPassword, $row['password_hash'])
                ) {
                    $account = [
                        'role' => 'admin',
                        'id' => (int) $row['id'],
                        'name' => $row['full_name']
                    ];
                }
            } catch (mysqli_sql_exception $ex) {
                $loginError = 'Admin login error: ' . $ex->getMessage();
            }
        }

        if (!$account) {
            if ($loginError === '') {
                $loginError = 'Wrong email or password.';
            }
        } elseif (!($url = portal_url($account['role']))) {
            $loginError = 'Your ' . $account['role'] .
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

$hospitalList = [];
$hospitalResult = mysqli_query($conn, 'SELECT name FROM hospitals ORDER BY name');
while ($hospitalRow = mysqli_fetch_row($hospitalResult)) {
    $hospitalList[] = $hospitalRow[0];
}

function valid_date($d) {
    $dt = DateTime::createFromFormat('Y-m-d', $d);
    return $dt && $dt->format('Y-m-d') === $d;
}

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['form_type'] ?? '') === 'register'
) {
    $startView = 'register';

    foreach ($fields as $f) {
        $old[$f] = trim($_POST[$f] ?? '');
    }

    $old['email'] = strtolower($old['email']);

    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    $role = $old['role'];
    $today = date('Y-m-d');
    $hasChild = false;
    $hospitalId = null;

    if (!csrf_valid()) {
        $errors[] = 'Your session expired. Please try again.';
    }

    if (!in_array($role, ['parent', 'hospital'], true)) {
        $errors[] = 'Please select Parent or Hospital.';
    }

    if (mb_strlen($old['name']) < 3 || mb_strlen($old['name']) > 100) {
        $errors[] = 'Name must be between 3 and 100 characters.';
    }

    $emailOk = filter_var($old['email'], FILTER_VALIDATE_EMAIL)
        && strlen($old['email']) <= 120;

    if (!$emailOk) {
        $errors[] = 'Enter a valid email address.';
    }

    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }

    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }

    if (!preg_match('/^[0-9+\- ]{7,15}$/', $old['phone'])) {
        $errors[] = 'Enter a valid phone number.';
    }

    if ($old['address'] === '' || mb_strlen($old['address']) > 255) {
        $errors[] = 'Address is required.';
    }

    if (empty($_POST['terms'])) {
        $errors[] = 'Please accept the terms of service and privacy policy.';
    }

    if ($emailOk) {

        $emailSafe = esc($old['email']);

        $result = mysqli_query($conn, "SELECT 1 FROM parents WHERE email = '$emailSafe' LIMIT 1");
        $taken = mysqli_num_rows($result) > 0;

        if (!$taken) {
            $result = mysqli_query($conn, "SELECT 1 FROM hospitals WHERE email = '$emailSafe' LIMIT 1");
            $taken = mysqli_num_rows($result) > 0;
        }

        if ($taken) {
            $errors[] = 'This email address is already registered.';
        }
    }

    if ($role === 'parent') {

        $hasChild = (
            $old['child_name'] !== '' ||
            $old['child_dob'] !== ''
        );

        if ($hasChild) {

            if (
                mb_strlen($old['child_name']) < 2 ||
                mb_strlen($old['child_name']) > 100
            ) {
                $errors[] = "Enter your child's name.";
            }

            if (
                !valid_date($old['child_dob']) ||
                $old['child_dob'] > $today
            ) {
                $errors[] = "Enter a valid date of birth for your child.";
            }
        }

        if ($old['hospital'] !== '' || $old['vaccine_date'] !== '') {

            if (!$hasChild) {
                $errors[] = "Add your child's name and date of birth to request a booking.";

            } elseif (
                $old['hospital'] === '' ||
                $old['vaccine_date'] === ''
            ) {
                $errors[] = 'Enter both the hospital and vaccination date, or leave both empty.';

            } else {

                if (
                    !valid_date($old['vaccine_date']) ||
                    $old['vaccine_date'] < $today
                ) {
                    $errors[] = 'Vaccination date must be today or a future date.';
                }

                $hospitalSafe = esc($old['hospital']);
                $found = [];

                $result = mysqli_query($conn, "SELECT id FROM hospitals WHERE LOWER(name) = LOWER('$hospitalSafe')");
                while ($row = mysqli_fetch_assoc($result)) {
                    $found[] = $row['id'];
                }

                if (!$found) {
                    $result = mysqli_query($conn, "SELECT id FROM hospitals WHERE name LIKE '%$hospitalSafe%'");
                    while ($row = mysqli_fetch_assoc($result)) {
                        $found[] = $row['id'];
                    }
                }

                if (count($found) === 1) {
                    $hospitalId = (int) $found[0];
                } else {
                    $errors[] = 'Please choose a hospital from the suggestions list.';
                }
            }
        }

    } elseif ($role === 'hospital') {

        if (
            $old['location'] === '' ||
            mb_strlen($old['location']) > 100
        ) {
            $errors[] = 'Enter the hospital location (city / area).';
        }

        try {
            mysqli_query($conn, "SELECT email, password_hash FROM hospitals LIMIT 1");
        } catch (mysqli_sql_exception $ex) {
            $errors[] = 'Hospital registration requires email and password_hash columns in the hospitals table.';
        }
    }

    if (!$errors) {

        try {
            mysqli_begin_transaction($conn);

            $hash = password_hash($password, PASSWORD_DEFAULT);

            $nameSafe = esc($old['name']);
            $emailSafe = esc($old['email']);
            $phoneSafe = esc($old['phone']);
            $addressSafe = esc($old['address']);

            if ($role === 'parent') {

                mysqli_query($conn, "INSERT INTO parents (full_name, email, phone, address, password_hash)
                    VALUES ('$nameSafe', '$emailSafe', '$phoneSafe', '$addressSafe', '$hash')");

                $parentId = (int) mysqli_insert_id($conn);

                if ($hasChild) {

                    $childNameSafe = esc($old['child_name']);
                    $childDobSafe = esc($old['child_dob']);

                    mysqli_query($conn, "INSERT INTO children (parent_id, name, dob)
                        VALUES ($parentId, '$childNameSafe', '$childDobSafe')");

                    $childId = (int) mysqli_insert_id($conn);

                    generate_schedule($childId, $old['child_dob']);

                    if ($hospitalId) {

                        $result = mysqli_query($conn, "
                            SELECT cv.id
                            FROM child_vaccinations cv
                            JOIN vaccines v ON v.id = cv.vaccine_id
                            WHERE cv.child_id = $childId
                              AND v.availability = 'Available'
                              AND NOT EXISTS (
                                  SELECT 1
                                  FROM appointments a
                                  WHERE a.child_vaccination_id = cv.id
                                    AND a.vaccination_status = 'Vaccinated'
                              )
                            ORDER BY cv.due_date, cv.id
                            LIMIT 1
                        ");

                        $row = mysqli_fetch_assoc($result);

                        if ($row) {
                            $cvId = (int) $row['id'];
                            $vaccineDateSafe = esc($old['vaccine_date']);

                            mysqli_query($conn, "INSERT INTO appointments
                                (child_vaccination_id, parent_id, hospital_id, appointment_date, time_slot)
                                VALUES ($cvId, $parentId, $hospitalId, '$vaccineDateSafe', 'To be confirmed')");
                        }
                    }
                }

            } else {

                $locationSafe = esc($old['location']);

                mysqli_query($conn, "INSERT INTO hospitals
                    (name, address, location, phone, email, password_hash)
                    VALUES ('$nameSafe', '$addressSafe', '$locationSafe', '$phoneSafe', '$emailSafe', '$hash')");
            }

            mysqli_commit($conn);

            flash('success', 'Registration successful. Please log in.');
            redirect('register.php');

        } catch (mysqli_sql_exception $e) {
            try {
                mysqli_rollback($conn);
            } catch (mysqli_sql_exception $rollbackError) {
            }

            $errors[] = 'Registration failed. Please check your database connection and try again.';
        }
    }
}

$today = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VaxCare | Register</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="../css/register.css">
</head>

<body data-view="<?= e($startView) ?>">
    <main class="page">

        <aside class="info">
            <div class="info-content">

                <a href="../index.php" class="brand">
                    <img src="../images/final bg logo.png" alt="VaxCare logo">
                </a>

                <h1>
                    <span class="white">Join VaxCare today,</span>
                    <span class="orange">protect your child's health</span>
                </h1>

                <p class="lead">
                    Create a free account as a parent or a hospital and
                    manage every vaccination in one place.
                </p>

                <ul class="features">
                    <li><span class="tick">&#10003;</span>Vaccination schedule made automatically</li>
                    <li><span class="tick">&#10003;</span>Book hospital appointments online</li>
                    <li><span class="tick">&#10003;</span>Reminders so no dose is missed</li>
                    <li><span class="tick">&#10003;</span>Hospitals manage bookings easily</li>
                </ul>

            </div>
        </aside>

        <section class="register-section">
            <div class="box" id="box">

                <div class="box-head">
                    <h2 id="headTitle">Welcome Back to VaxCare</h2>
                    <p id="headText">Sign in to manage your child's vaccinations.</p>
                </div>

                <div class="box-body">

                    <div class="view" id="loginView">

                        <form id="loginForm" class="login-form" method="post" action="register.php">
                            <?= csrf_field() ?>
                            <input type="hidden" name="form_type" value="login">

                            <input
                                type="email"
                                id="loginEmail"
                                name="email"
                                placeholder="Email"
                                value="<?= e($loginEmail) ?>"
                                autocomplete="username"
                                aria-label="Email"
                                required
                            >

                            <div class="pass-wrap">
                                <input
                                    type="password"
                                    id="loginPassword"
                                    name="password"
                                    placeholder="Password"
                                    autocomplete="current-password"
                                    aria-label="Password"
                                    required
                                >
                                <button type="button" class="eye" id="toggle" aria-label="Show password">
                                    <i class="fa fa-eye" id="eyeIcon" aria-hidden="true"></i>
                                </button>
                            </div>

                            <?php if ($notice): ?>
                                <p class="login-success"><?= e($notice[1]) ?></p>
                            <?php endif; ?>

                            <p class="login-error"><?= e($loginError) ?></p>

                            <button type="submit" class="btn">Sign In</button>
                        </form>

                        <p class="create">Create a new account</p>

                        <div class="roles">
                            <button type="button" class="role" data-open="parent">Parent</button>
                            <button type="button" class="role" data-open="hospital">Hospital</button>
                        </div>

                        <p class="hint">
                            <a href="../index.php">&larr; Back to Home</a>
                        </p>

                    </div>

                    <div class="view" id="registerView" hidden>

                    <button type="button" class="login-link" id="toLogin">Already registered? Sign in</button>

                    <p class="pick">Register as</p>

                    <div class="roles">
                        <button type="button" class="role" data-role="parent">Parent</button>
                        <button type="button" class="role" data-role="hospital">Hospital</button>
                    </div>

                    <?php if ($errors): ?>
                        <div class="form-msg error">
                            <?php foreach ($errors as $er): ?>
                                <p><?= e($er) ?></p>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <form id="registerForm" method="post" action="register.php" novalidate>
                        <?= csrf_field() ?>
                        <input type="hidden" name="form_type" value="register">
                        <input type="hidden" name="role" id="role" value="<?= e($old['role']) ?>">

                        <ol class="steps">
                            <li class="step-item" data-dot="1"><span class="dot">1</span><span class="step-name">Account</span></li>
                            <li class="step-item" data-dot="2"><span class="dot">2</span><span class="step-name">Contact</span></li>
                            <li class="step-item" data-dot="3"><span class="dot">3</span><span class="step-name" id="thirdName">Details</span></li>
                        </ol>

                        <div class="step" data-step="1">
                            <div class="fields">
                                <div class="field">
                                    <label for="name" id="nameLabel">Name</label>
                                    <input type="text" name="name" id="name" value="<?= e($old['name']) ?>" required>
                                </div>

                                <div class="field">
                                    <label for="email">Email Address</label>
                                    <input type="email" name="email" id="email" value="<?= e($old['email']) ?>" required>
                                </div>

                                <div class="field">
                                    <label for="password">Password</label>
                                    <input type="password" name="password" id="password" minlength="8" required>
                                </div>

                                <div class="field">
                                    <label for="confirm_password">Confirm Password</label>
                                    <input type="password" name="confirm_password" id="confirm_password" required>
                                </div>
                            </div>
                        </div>

                        <div class="step" data-step="2">
                            <div class="fields">
                                <div class="field">
                                    <label for="phone">Phone</label>
                                    <input
                                        type="tel"
                                        name="phone"
                                        id="phone"
                                        value="<?= e($old['phone']) ?>"
                                        pattern="[0-9+\- ]{7,15}"
                                        title="Enter a valid phone number (digits, + or -)"
                                        required
                                    >
                                </div>

                                <div class="field">
                                    <label for="address">Address</label>
                                    <input type="text" name="address" id="address" value="<?= e($old['address']) ?>" required>
                                </div>
                            </div>
                        </div>

                        <div class="step" data-step="3">
                            <div class="fields">

                                <div class="field full hospital-only" hidden>
                                    <label for="location">Location (city / area)</label>
                                    <input type="text" name="location" id="location" value="<?= e($old['location']) ?>" disabled required>
                                </div>

                                <p class="group-title full parent-only" hidden>Child details (optional)</p>

                                <div class="field parent-only" hidden>
                                    <label for="child_name">Child Name</label>
                                    <input type="text" name="child_name" id="child_name" value="<?= e($old['child_name']) ?>" disabled>
                                </div>

                                <div class="field parent-only" hidden>
                                    <label for="child_dob">Child Date of Birth</label>
                                    <input type="date" name="child_dob" id="child_dob" value="<?= e($old['child_dob']) ?>" max="<?= $today ?>" disabled>
                                </div>

                                <div class="field parent-only" hidden>
                                    <label for="hospital">Hospital Name</label>
                                    <input
                                        type="text"
                                        name="hospital"
                                        id="hospital"
                                        value="<?= e($old['hospital']) ?>"
                                        list="hospitalList"
                                        placeholder="Search hospital"
                                        autocomplete="off"
                                        disabled
                                    >
                                </div>

                                <div class="field parent-only" hidden>
                                    <label for="vaccine_date">Vaccination Date</label>
                                    <input type="date" name="vaccine_date" id="vaccine_date" value="<?= e($old['vaccine_date']) ?>" min="<?= $today ?>" disabled>
                                </div>

                            </div>

                            <div class="check">
                                <input type="checkbox" name="terms" id="terms" required>
                                <label for="terms">I agree to the terms of service and privacy policy</label>
                            </div>
                        </div>

                        <div class="actions">
                            <button type="button" class="btn ghost" id="backBtn">Back</button>
                            <button type="button" class="btn" id="nextBtn">Next</button>
                            <button type="submit" class="btn" id="submitBtn">Sign up</button>
                        </div>
                    </form>

                    <p class="hint">
                        <a href="../index.php">&larr; Back to home</a>
                    </p>

                    </div>

                </div>
            </div>
        </section>

    </main>

    <datalist id="hospitalList">
        <?php foreach ($hospitalList as $hn): ?>
            <option value="<?= e($hn) ?>">
        <?php endforeach; ?>
    </datalist>

    <script src="../js/register.js"></script>
</body>
</html>