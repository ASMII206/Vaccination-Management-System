<?php

require_once __DIR__ . '/../../includes/config.php';
require_hospital();

$hid = hospital_id();
$result = mysqli_query($conn, "SELECT id, name, email, address, location, phone FROM hospitals WHERE id = $hid");
$hospital = mysqli_fetch_assoc($result);
if (!$hospital) {
    redirect('../authentication/logout.php');   
}


function outcome_label($status) { return $status === 'Pending' ? 'Scheduled' : $status; }
function outcome_chip($status) {
    $cls = ['Pending' => 's', 'Vaccinated' => 'v', 'Not Vaccinated' => 'n'][$status] ?? 's';
    return '<span class="chip ' . $cls . '">' . e(outcome_label($status)) . '</span>';
}
function appt_ref($id) { return 'VMS-' . str_pad((string)(int)$id, 4, '0', STR_PAD_LEFT); }
