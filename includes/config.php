<?php
/* =========================================================
   VaxCare - shared configuration, database connection, helpers
   Local setup (XAMPP): MySQL user "root" with an empty password.
========================================================= */

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}
date_default_timezone_set('Asia/Karachi');

define('DB_HOST', 'localhost');
define('DB_NAME', 'vaccination_db');
define('DB_USER', 'root');
define('DB_PASS', '');

/* Admin account (checked on the server, never in the browser).
   Email:    admin@vaxcare.com
   Password: admin12345            */
define('ADMIN_EMAIL', 'admin@vaxcare.com');
define('ADMIN_PASSWORD_HASH', '$2b$10$8Vt/SUJDTAf6h/O1gUHfHeopu0G/2zMQf1Dxc/rtcyDCLYcmKPoaG');

try {
    $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $ex) {
    die('Database connection failed. Start MySQL and import database/vaccination_db.sql, then check includes/config.php.');
}

/* ---------- generic helpers ---------- */

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function redirect($url) { header('Location: ' . $url); exit; }

function flash($type = null, $msg = null) {
    if ($msg !== null) { $_SESSION['flash'] = [$type, $msg]; return null; }
    if (isset($_SESSION['flash'])) { $f = $_SESSION['flash']; unset($_SESSION['flash']); return $f; }
    return null;
}

function csrf_token() {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field() { return '<input type="hidden" name="csrf" value="' . csrf_token() . '">'; }
function csrf_valid() { return hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? ''); }
function csrf_check() {
    if (!csrf_valid()) { http_response_code(400); die('Invalid request. Please go back and try again.'); }
}

/* ---------- role / session helpers ---------- */

// Where each role lands after login (relative to the project root).
function portal_url($role) {
    $map = [
        'parent'   => 'parent/dashboard.php',
        'hospital' => 'hospital/dashboard.php',
        'admin'    => 'admin/dashboard.php',
    ];
    $rel = $map[$role] ?? null;
    return ($rel && is_file(dirname(__DIR__) . '/' . $rel)) ? $rel : null;
}

function require_parent() {
    if (($_SESSION['role'] ?? '') !== 'parent' || empty($_SESSION['user_id'])) redirect('../authentication/login.php');
}
function parent_id() { return (int)($_SESSION['user_id'] ?? 0); }

function require_admin() {
    if (($_SESSION['role'] ?? '') !== 'admin') redirect('../authentication/login.php');
}

function require_hospital() {
    if (($_SESSION['role'] ?? '') !== 'hospital' || empty($_SESSION['user_id'])) redirect('../authentication/login.php');
}
function hospital_id() { return (int)($_SESSION['user_id'] ?? 0); }

// Does a column exist? (lets pages keep working if an optional migration has not been run yet)
function has_column(PDO $pdo, $table, $column) {
    static $cache = [];
    $key = $table . '.' . $column;
    if (!isset($cache[$key])) {
        $q = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
        $q->execute([$table, $column]);
        $cache[$key] = (bool)$q->fetchColumn();
    }
    return $cache[$key];
}

/* ---------- vaccination helpers ---------- */

function fmt_date($d) { return $d ? date('d M Y', strtotime($d)) : '-'; }

function age_text($dob) {
    $diff = (new DateTime($dob))->diff(new DateTime('today'));
    if ($diff->y > 0) return $diff->y . ' yr ' . $diff->m . ' mo';
    if ($diff->m > 0) return $diff->m . ' mo ' . $diff->d . ' d';
    return $diff->d . ' days';
}

// Current state of one row from the v_child_schedule view
function schedule_status($row) {
    if ($row['is_vaccinated']) return 'Vaccinated';
    if ($row['booking_status']) return $row['booking_status'] === 'Approved' ? 'Booked (Approved)' : 'Booked (Pending)';
    return $row['due_date'] < date('Y-m-d') ? 'Overdue' : 'Upcoming';
}

function badge($text) {
    $map = ['Vaccinated' => 'green', 'Approved' => 'green', 'Booked (Approved)' => 'green', 'Available' => 'green',
            'Pending' => 'amber', 'Booked (Pending)' => 'amber', 'Upcoming' => 'blue',
            'Overdue' => 'red', 'Rejected' => 'red', 'Not Vaccinated' => 'red', 'Unavailable' => 'red', 'Cancelled' => 'grey'];
    return '<span class="badge ' . ($map[$text] ?? 'grey') . '">' . e($text) . '</span>';
}

// Create a child's vaccination schedule from their date of birth
function generate_schedule(PDO $pdo, $childId, $dob) {
    $vaccines = $pdo->query('SELECT id, age_in_days FROM vaccines')->fetchAll();
    $ins = $pdo->prepare('INSERT IGNORE INTO child_vaccinations (child_id, vaccine_id, due_date) VALUES (?,?,?)');
    foreach ($vaccines as $v) {
        $due = date('Y-m-d', strtotime($dob . ' +' . (int)$v['age_in_days'] . ' days'));
        $ins->execute([$childId, $v['id'], $due]);
    }
}
