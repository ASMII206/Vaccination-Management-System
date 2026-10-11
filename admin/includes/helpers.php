<?php
// Shared helpers for the admin pages (loaded after config.php)

function booking_ref($id) { return 'VMS-' . str_pad((string)(int)$id, 4, '0', STR_PAD_LEFT); }

// Outcome of an appointment as shown to the admin ("Pending" in the database = still to be vaccinated)
function outcome_text($status) { return $status === 'Pending' ? 'Scheduled' : $status; }
function outcome_badge($status) {
    $map = ['Pending' => 'blue', 'Vaccinated' => 'green', 'Not Vaccinated' => 'red'];
    return '<span class="badge ' . ($map[$status] ?? 'grey') . '">' . e(outcome_text($status)) . '</span>';
}

// Valid Y-m-d date or ''
function clean_date($d) {
    $dt = DateTime::createFromFormat('Y-m-d', (string)$d);
    return ($dt && $dt->format('Y-m-d') === $d) ? $d : '';
}

// Query string builder that skips empty values
function qs(array $params) {
    $params = array_filter($params, function ($v) { return $v !== '' && $v !== null && $v !== 0; });
    return $params ? '?' . http_build_query($params) : '';
}

// Stream rows as a CSV download and stop
function csv_download($filename, array $header, array $rows) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $header);
    foreach ($rows as $r) fputcsv($out, $r);
    fclose($out);
    exit;
}
