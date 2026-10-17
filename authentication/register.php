
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

$hospitalList = [];
$result = mysqli_query($conn, "SELECT name FROM hospitals ORDER BY name");
while ($row = mysqli_fetch_assoc($result)) {
    $hospitalList[] = $row['name'];
}

function valid_date($d) {
    $dt = DateTime::createFromFormat('Y-m-d', $d);
    return $dt && $dt->format('Y-m-d') === $d;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

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

    // Validate request and form
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

    // Check whether email is already registered
    // No hardcoded ADMIN_EMAIL or dummy account is used.
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

    // Parent registration validation
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

        // Optional vaccination booking
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

    // Hospital registration validation
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

    // Create account if validation succeeds
    if (!$errors) {

        try {
            mysqli_begin_transaction($conn);

            // Save the user's own password securely
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

                    // Create a booking if a hospital was selected
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

                // Hospital account
                $locationSafe = esc($old['location']);

                mysqli_query($conn, "INSERT INTO hospitals (name, address, location, phone, email, password_hash)
                    VALUES ('$nameSafe', '$addressSafe', '$locationSafe', '$phoneSafe', '$emailSafe', '$hash')");
            }

            mysqli_commit($conn);

            flash('success', 'Registration successful. Please log in.');
            redirect('login.php');

        } catch (mysqli_sql_exception $e) {
            // Kuch galat ho to sab changes wapis le lo
            mysqli_rollback($conn);

            $errors[] = 'Registration failed. Please check your database connection and try again.';
        }

    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Document</title>
  
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../css/register.css">
</head>
<body>
  
    <section class="col-12 col-lg-7 col-xl-7 panel d-flex align-items-center justify-content-center p-3 p-sm-4 p-md-5">

      <div class="panel-inner w-100">

        <form
          method="post"
          action="register.php"
          id="registerForm"
        >

          <?= csrf_field() ?>


          



          <div class="form-header mb-4">
            <h2 class="form-title">REGISTER</h2>
          </div>

          <?php if ($errors): ?>
          <div class="form-msg error">
            <?php foreach ($errors as $er): ?>
            <p><?= e($er) ?></p>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>


          



      <div class="mb-3">

        <label
          for="role"
          class="form-label"
        >
          Register as
        </label>

        <select
          name="role"
          id="role"
          class="form-select"
          required
        >

          <option
            value=""
            disabled
            <?= $old['role'] === '' ? 'selected' : '' ?>
          >
            Select your role
          </option>

          <option value="parent"<?= $old['role'] === 'parent' ? ' selected' : '' ?>>
            Parent
          </option>

          <option value="hospital"<?= $old['role'] === 'hospital' ? ' selected' : '' ?>>
            Hospital
          </option>

        </select>

      </div>


      



      <div class="row g-3">


        

        <div class="col-12 col-md-6">

          <label
            for="name"
            class="form-label"
            id="nameLabel"
          >
            Name
          </label>

          <input
            type="text"
            name="name"
            id="name"
            value="<?= e($old['name']) ?>"
            class="form-control"
            required
          >

        </div>


        

        <div class="col-12 col-md-6">

          <label
            for="email"
            class="form-label"
          >
            Email Address
          </label>

          <input
            type="email"
            name="email"
            id="email"
            value="<?= e($old['email']) ?>"
            class="form-control"
            required
          >

        </div>


        

        <div class="col-12 col-md-6">

          <label
            for="password"
            class="form-label"
          >
            Password
          </label>

          <input
            type="password"
            name="password"
            id="password"
            class="form-control"
            minlength="8"
            required
          >

        </div>


        

        <div class="col-12 col-md-6">

          <label
            for="confirm_password"
            class="form-label"
          >
            Confirm Password
          </label>

          <input
            type="password"
            name="confirm_password"
            id="confirm_password"
            class="form-control"
            required
          >

        </div>


        

        <div class="col-12 col-md-6">

          <label
            for="phone"
            class="form-label"
          >
            Phone
          </label>

          <input
            type="tel"
            name="phone"
            id="phone"
            value="<?= e($old['phone']) ?>"
            class="form-control"
            pattern="[0-9+\- ]{7,15}"
            title="Enter a valid phone number (digits, + or -)"
            required
          >

        </div>


        

        <div class="col-12 col-md-6" id="addressGroup">

          <label
            for="address"
            class="form-label"
          >
            Address
          </label>

          <input
            type="text"
            name="address"
            id="address"
            value="<?= e($old['address']) ?>"
            class="form-control"
            required
          >

        </div>


        

        <div
          class="col-12 col-md-6 d-none"
          id="locationGroup"
        >

          <label
            for="location"
            class="form-label"
          >
            Location (city / area)
          </label>

          <input
            type="text"
            name="location"
            id="location"
            value="<?= e($old['location']) ?>"
            class="form-control"
            disabled
          >

        </div>



        

        <div class="col-12 col-md-6 d-none parent-only">
          <label for="child_name" class="form-label">Child Name</label>
          <input type="text" name="child_name" id="child_name" value="<?= e($old['child_name']) ?>" class="form-control" disabled>
        </div>

        <div class="col-12 col-md-6 d-none parent-only">
          <label for="child_dob" class="form-label">Child Date of Birth</label>
          <input type="text" name="child_dob" id="child_dob" value="<?= e($old['child_dob']) ?>" class="form-control" placeholder="Select date"
                 onfocus="this.type='date'" onblur="if(!this.value)this.type='text'" disabled>
        </div>

        <div class="col-12 col-md-6 d-none parent-only">
          <label for="hospital" class="form-label">Hospital Name</label>
          <input type="text" name="hospital" id="hospital" value="<?= e($old['hospital']) ?>" list="hospitalList" autocomplete="off" class="form-control" placeholder="Search hospital" disabled>
        </div>

        <div class="col-12 col-md-6 d-none parent-only">
          <label for="vaccine_date" class="form-label">Vaccination Date</label>
          <input type="text" name="vaccine_date" id="vaccine_date" value="<?= e($old['vaccine_date']) ?>" class="form-control" placeholder="Select date"
                 onfocus="this.type='date'" onblur="if(!this.value)this.type='text'" disabled>
        </div>


      </div>


      



      <div class="form-check mt-4 mb-4">

        <input
          class="form-check-input"
          type="checkbox"
          name="terms"
          id="terms"
          required
        >

        <label
          class="form-check-label"
          for="terms"
        >
          I agree to the terms of service and privacy policy
        </label>

      </div>


      



      <button
        type="submit"
        class="submit"
      >
        Sign up
      </button>


      



      <p class="login">

        <a href="../index.php">&larr; Back to home</a>

      </p>


    </form>

      </div>

    </section>
  <datalist id="hospitalList">
    <?php foreach ($hospitalList as $hn): ?>
    <option value="<?= e($hn) ?>">
    <?php endforeach; ?>
  </datalist>

  <script src="../js/register.js"></script>
</body>
</html>