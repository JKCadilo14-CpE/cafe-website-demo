<?php
require_once __DIR__ . '/../components/app.php';

header('Content-Type: application/json');

if (!app_is_admin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit();
}

function ensure_is_read_column(mysqli $mysqli): void
{
    $columnResult = $mysqli->query("SHOW COLUMNS FROM `orders` LIKE 'is_read'");
    $columnExists = $columnResult->num_rows > 0;
    $columnResult->free();

    if (!$columnExists) {
        $mysqli->query('ALTER TABLE `orders` ADD COLUMN is_read TINYINT(1) DEFAULT 0');
    }
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $mysqli = app_db();
    ensure_is_read_column($mysqli);
    app_ensure_contact_messages_table($mysqli);

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        app_require_csrf();

        $action = (string) ($_POST['action'] ?? '');
        $type = (string) ($_POST['type'] ?? 'order');

        if ($action !== 'mark_read') {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid notification request.']);
            exit();
        }

        if ($type === 'contact_message' || $type === 'contact') {
            $messageId = filter_input(INPUT_POST, 'message_id', FILTER_VALIDATE_INT);

            if ($messageId === false || $messageId === null || $messageId < 1) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid notification request.']);
                exit();
            }

            $statement = $mysqli->prepare('UPDATE contact_messages SET status = "read" WHERE id = ?');
            $statement->bind_param('i', $messageId);
            $statement->execute();
            $statement->close();
        } else {
            $orderId = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);

            if ($orderId === false || $orderId === null || $orderId < 1) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid notification request.']);
                exit();
            }

            $statement = $mysqli->prepare('UPDATE `orders` SET is_read = 1 WHERE id = ?');
            $statement->bind_param('i', $orderId);
            $statement->execute();
            $statement->close();
        }

        $mysqli->close();

        echo json_encode(['success' => true]);
        exit();
    }

    $unreadOrderResult = $mysqli->query('SELECT COUNT(*) FROM `orders` WHERE is_read = 0');
    $unreadOrderCount = (int) ($unreadOrderResult->fetch_row()[0] ?? 0);
    $unreadOrderResult->free();

    $unreadContactResult = $mysqli->query('SELECT COUNT(*) FROM contact_messages WHERE status IS NULL OR status <> "read"');
    $unreadContactCount = (int) ($unreadContactResult->fetch_row()[0] ?? 0);
    $unreadContactResult->free();

    $notifications = [];
    $limit = 10;
    $orderLimit = $limit;
    $orderStatement = $mysqli->prepare(
        'SELECT id, status, created_at, is_read
         FROM `orders`
         ORDER BY created_at DESC
         LIMIT ?'
    );
    $orderStatement->bind_param('i', $orderLimit);
    $orderStatement->execute();
    $orderResult = $orderStatement->get_result();

    while ($row = $orderResult->fetch_assoc()) {
        $orderId = (int) ($row['id'] ?? 0);
        $status = (string) ($row['status'] ?? 'Pending');
        $notifications[] = [
            'type' => 'order',
            'id' => $orderId,
            'title' => 'Order #' . $orderId,
            'subtitle' => 'Status: ' . $status,
            'status' => $status,
            'created_at' => (string) ($row['created_at'] ?? ''),
            'is_read' => (int) ($row['is_read'] ?? 0),
            'href' => 'admin-order-details.php?id=' . $orderId,
        ];
    }

    $orderResult->free();
    $orderStatement->close();

    $contactLimit = $limit;
    $contactStatement = $mysqli->prepare(
        'SELECT id, name, topic, status, created_at
         FROM contact_messages
         ORDER BY created_at DESC, id DESC
         LIMIT ?'
    );
    $contactStatement->bind_param('i', $contactLimit);
    $contactStatement->execute();
    $contactResult = $contactStatement->get_result();

    while ($row = $contactResult->fetch_assoc()) {
        $messageId = (int) ($row['id'] ?? 0);
        $name = trim((string) ($row['name'] ?? 'Guest'));
        $topicLabel = app_contact_topic_label((string) ($row['topic'] ?? 'support'));
        $isRead = (string) ($row['status'] ?? 'unread') === 'read' ? 1 : 0;
        $notifications[] = [
            'type' => 'contact_message',
            'id' => $messageId,
            'title' => 'New message from ' . ($name !== '' ? $name : 'Guest'),
            'subtitle' => 'Contact message: ' . $topicLabel,
            'name' => $name !== '' ? $name : 'Guest',
            'topic' => $topicLabel,
            'created_at' => (string) ($row['created_at'] ?? ''),
            'is_read' => $isRead,
            'href' => 'admin-contact-messages.php?id=' . $messageId,
        ];
    }

    $contactResult->free();
    $contactStatement->close();

    usort($notifications, static function (array $a, array $b): int {
        $left = strtotime((string) ($a['created_at'] ?? '')) ?: 0;
        $right = strtotime((string) ($b['created_at'] ?? '')) ?: 0;

        return $right <=> $left;
    });

    $notifications = array_slice($notifications, 0, $limit);
    $mysqli->close();

    echo json_encode([
        'unread_count' => $unreadOrderCount + $unreadContactCount,
        'notifications' => $notifications,
    ]);
} catch (mysqli_sql_exception $exception) {
    http_response_code(500);
    echo json_encode(['error' => 'Unable to load notifications.']);
}
