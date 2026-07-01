<?php
require_once __DIR__ . '/components/app.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: index.php');
    exit();
}

app_require_csrf();
app_destroy_session();

header('Location: index.php');
exit();
