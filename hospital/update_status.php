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


$q = $pdo->prepare("SELECT id, child_vaccination_id FROM appointments WHERE id = ? AND hospital_id = ? AND status = 'Approved'");
$q->execute([$id, $hid]);
$appt = $q->fetch();
if (!$appt) {
    flash('error', 'Appointment not found.');
    redirect($back);
}


if ($status === 'Vaccinated') {
    $q = $pdo->prepare("SELECT 1 FROM appointments WHERE child_vaccination_id = ? AND vaccination_status = 'Vaccinated' AND id <> ? LIMIT 1");
    $q->execute([$appt['child_vaccination_id'], $id]);
    if ($q->fetchColumn()) {
        flash('error', 'This dose is already recorded as vaccinated in another appointment.');
        redirect($back);
    }
}

if (has_column($pdo, 'appointments', 'remarks')) {
    $pdo->prepare('UPDATE appointments SET vaccination_status = ?, remarks = ? WHERE id = ? AND hospital_id = ?')
        ->execute([$status, $remarks !== '' ? $remarks : null, $id, $hid]);
} else {
    $pdo->prepare('UPDATE appointments SET vaccination_status = ? WHERE id = ? AND hospital_id = ?')
        ->execute([$status, $id, $hid]);
}

flash('success', appt_ref($id) . ' marked as ' . $status . '.');
redirect($back);
