<?php
// Merged into manage.php (kept so old links still work).
$qs = $_SERVER['QUERY_STRING'] ?? '';
header('Location: manage.php' . ($qs !== '' ? '?' . $qs : '') . '#appointments');
exit;
