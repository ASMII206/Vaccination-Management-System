<?php
require_once __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('manage.php#appointments');
csrf_check();


$ret = array_filter([
    'q'      => trim($_POST['ret_q'] ?? ''),
    'status' => $_POST['ret_status'] ?? '',
], function ($v) { return $v !== ''; });
$back = 'manage.php' . ($ret ? '?' . http_build_query($ret) : '') . '#appointments';

$id = (int)($_POST['id'] ?? 0);
$status = $_POST['status'] ?? '';
$remarks = trim($_POST['remarks'] ?? '');

if (!in_array($status, ['Vaccinated', 'Not Vaccinated'], true)) {
    flash('error', 'Please choose Vaccinated or Not vaccinated.');
    redirect($back);
}
if (mb_strlen($remarks) > 255) {
    flash('error', 'Remarks can be at most 255 characters.');
    redirect($back);
}


$result = mysqli_query($conn, "SELECT id, child_vaccination_id FROM appointments WHERE id = $id AND hospital_id = $hid AND status = 'Approved'");
$appt = mysqli_fetch_assoc($result);
if (!$appt) {
    flash('error', 'Appointment not found.');
    redirect($back);
}


if ($status === 'Vaccinated') {
    $cvId = (int)$appt['child_vaccination_id'];
    $result = mysqli_query($conn, "SELECT 1 FROM appointments WHERE child_vaccination_id = $cvId AND vaccination_status = 'Vaccinated' AND id <> $id LIMIT 1");
    if (mysqli_num_rows($result) > 0) {
        flash('error', 'This dose is already recorded as vaccinated in another appointment.');
        redirect($back);
    }
}

$statusSafe = esc($status);

if (has_column('appointments', 'remarks')) {
    $remarksSql = nullable($remarks);
    mysqli_query($conn, "UPDATE appointments SET vaccination_status = '$statusSafe', remarks = $remarksSql WHERE id = $id AND hospital_id = $hid");
} else {
    mysqli_query($conn, "UPDATE appointments SET vaccination_status = '$statusSafe' WHERE id = $id AND hospital_id = $hid");
}

flash('success', appt_ref($id) . ' marked as ' . $status . '.');
redirect($back);
