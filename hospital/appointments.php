<?php

$qs = $_SERVER['QUERY_STRING'] ?? '';
header('Location: manage.php' . ($qs !== '' ? '?' . $qs : '') . '#appointments');
exit;
