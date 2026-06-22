<?php
require_once __DIR__ . '/../components/app.php';

header('Content-Type: application/json');

if (!app_is_admin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit();
}

$response = [
    'pending_orders' => 0,
    'recent_orders' => 0,
    'display_count' => 0,
    'unread_count' => 0,
    'unread_orders' => 0,
    'unread_contact_messages' => 0,
];

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $mysqli = app_db();
    app_ensure_contact_messages_table($mysqli);

    $columnResult = $mysqli->query("SHOW COLUMNS FROM `orders` LIKE 'is_read'");
    $columnExists = $columnResult->num_rows > 0;
    $columnResult->free();

    if (!$columnExists) {
        $mysqli->query('ALTER TABLE `orders` ADD COLUMN is_read TINYINT(1) DEFAULT 0');
    }

    $result = $mysqli->query(
        "SELECT
            COALESCE(SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END), 0) AS pending_orders,
            COALESCE(SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 ELSE 0 END), 0) AS recent_orders,
            COALESCE(SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END), 0) AS unread_count
         FROM `orders`"
    );
    $counts = $result->fetch_assoc() ?: [];
    $result->free();
    $contactResult = $mysqli->query('SELECT COUNT(*) FROM contact_messages WHERE status IS NULL OR status <> "read"');
    $unreadContactMessages = (int) ($contactResult->fetch_row()[0] ?? 0);
    $contactResult->free();
    $mysqli->close();

    $unreadOrders = (int) ($counts['unread_count'] ?? 0);
    $response['pending_orders'] = (int) ($counts['pending_orders'] ?? 0);
    $response['recent_orders'] = (int) ($counts['recent_orders'] ?? 0);
    $response['unread_orders'] = $unreadOrders;
    $response['unread_contact_messages'] = $unreadContactMessages;
    $response['unread_count'] = $unreadOrders + $unreadContactMessages;
    $response['display_count'] = $response['unread_count'];
} catch (mysqli_sql_exception $exception) {
    http_response_code(500);
    $response['error'] = 'Unable to load notification count.';
}

echo json_encode($response);
