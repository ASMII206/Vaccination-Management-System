
<?php

$host = "localhost";
$username = "root";
$password = "";
$database = "vaccination_db";

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    session_start();
}

date_default_timezone_set('Asia/Karachi');

/* Database connection */
mysqli_report(MYSQLI_REPORT_OFF);

$conn = mysqli_connect(
    $host,
    $username,
    $password,
    $database
);

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

mysqli_set_charset($conn, "utf8mb4");

/* Ab se database errors exception ban jayenge (try/catch me kaam aaye) */
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/* Text ko SQL query ke liye safe banao */
function esc($value) {
    global $conn;
    return mysqli_real_escape_string($conn, (string)$value);
}

/* Khali value ho to NULL, warna 'value' (quotes ke saath) */
function nullable($value) {
    if ($value === '' || $value === null) {
        return 'NULL';
    }
    return "'" . esc($value) . "'";
}

/* Escape output */
function e($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/* Redirect */
function redirect($url) {
    header('Location: ' . $url);
    exit;
}

/* Flash messages */
function flash($type = null, $msg = null) {
    if ($msg !== null) {
        $_SESSION['flash'] = [$type, $msg];
        return null;
    }

    if (isset($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }

    return null;
}

/* CSRF protection */
function csrf_token() {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf'];
}

function csrf_field() {
    return '<input type="hidden" name="csrf" value="' .
        csrf_token() . '">';
}

function csrf_valid() {
    return hash_equals(
        $_SESSION['csrf'] ?? '',
        $_POST['csrf'] ?? ''
    );
}

function csrf_check() {
    if (!csrf_valid()) {
        http_response_code(400);
        die('Invalid request. Please go back and try again.');
    }
}

/* Portal URLs */
function portal_url($role) {
    $map = [
        'parent' => 'parent/dashboard.php',
        'hospital' => 'hospital/dashboard.php',
        'admin' => 'admin/dashboard.php',
    ];

    $rel = $map[$role] ?? null;

    return ($rel && is_file(dirname(__DIR__) . '/' . $rel))
        ? $rel
        : null;
}

/* Parent access */
function require_parent() {
    if (
        ($_SESSION['role'] ?? '') !== 'parent' ||
        empty($_SESSION['user_id'])
    ) {
        redirect('../authentication/login.php');
    }
}

function parent_id() {
    return (int)($_SESSION['user_id'] ?? 0);
}

/* Admin access */
function require_admin() {
    if (($_SESSION['role'] ?? '') !== 'admin') {
        redirect('../authentication/login.php');
    }
}

/* Hospital access */
function require_hospital() {
    if (
        ($_SESSION['role'] ?? '') !== 'hospital' ||
        empty($_SESSION['user_id'])
    ) {
        redirect('../authentication/login.php');
    }
}

function hospital_id() {
    return (int)($_SESSION['user_id'] ?? 0);
}

/* Check database column */
function has_column($table, $column) {
    global $conn;

    $table = esc($table);
    $column = esc($column);

    $sql = "SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = '$table'
            AND COLUMN_NAME = '$column'";

    $result = mysqli_query($conn, $sql);
    $row = mysqli_fetch_row($result);

    return (bool)$row[0];
}

/* Format date */
function fmt_date($d) {
    return $d ? date('d M Y', strtotime($d)) : '-';
}

/* Calculate age */
function age_text($dob) {
    $diff = (new DateTime($dob))->diff(new DateTime('today'));

    if ($diff->y > 0) {
        return $diff->y . ' yr ' . $diff->m . ' mo';
    }

    if ($diff->m > 0) {
        return $diff->m . ' mo ' . $diff->d . ' d';
    }

    return $diff->d . ' days';
}

/* Vaccination status */
function schedule_status($row) {
    if ($row['is_vaccinated']) {
        return 'Vaccinated';
    }

    if ($row['booking_status']) {
        return $row['booking_status'] === 'Approved'
            ? 'Booked (Approved)'
            : 'Booked (Pending)';
    }

    return $row['due_date'] < date('Y-m-d')
        ? 'Overdue'
        : 'Upcoming';
}

/* Status badges */
function badge($text) {
    $map = [
        'Vaccinated' => 'green',
        'Approved' => 'green',
        'Booked (Approved)' => 'green',
        'Available' => 'green',
        'Pending' => 'amber',
        'Booked (Pending)' => 'amber',
        'Upcoming' => 'blue',
        'Overdue' => 'red',
        'Rejected' => 'red',
        'Not Vaccinated' => 'red',
        'Unavailable' => 'red',
        'Cancelled' => 'grey'
    ];

    return '<span class="badge ' .
        ($map[$text] ?? 'grey') . '">' .
        e($text) . '</span>';
}

/* Generate child vaccination schedule */
function generate_schedule($childId, $dob) {
    global $conn;

    $result = mysqli_query($conn, "SELECT id, age_in_days FROM vaccines");

    while ($v = mysqli_fetch_assoc($result)) {
        $due = date(
            'Y-m-d',
            strtotime($dob . ' +' . (int)$v['age_in_days'] . ' days')
        );

        $childId = (int)$childId;
        $vaccineId = (int)$v['id'];

        mysqli_query($conn, "INSERT IGNORE INTO child_vaccinations
            (child_id, vaccine_id, due_date)
            VALUES ($childId, $vaccineId, '$due')");
    }
}
