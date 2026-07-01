<?php
require_once __DIR__ . '/components/app.php';

$wantsJson = str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')
    || strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';

function cancel_order_respond(array $payload, int $statusCode = 200, string $fallbackLocation = 'profile.php'): void
{
    global $wantsJson;

    if ($wantsJson) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($payload);
        exit();
    }

    header('Location: ' . $fallbackLocation);
    exit();
}

if (!app_is_logged_in()) {
    cancel_order_respond(['error' => 'Please log in to cancel this order.'], 401, 'login.php');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    cancel_order_respond(['error' => 'Invalid request method.'], 405);
}

app_require_csrf();

$orderId = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);

if ($orderId === false || $orderId === null || $orderId < 1) {
    cancel_order_respond(['error' => 'Invalid order id.'], 400);
}

try {
    $mysqli = app_db();
    $userId = (int) $_SESSION['user_id'];
    $statement = $mysqli->prepare(
        'SELECT id, status
         FROM orders
         WHERE id = ? AND user_id = ?
         LIMIT 1'
    );
    $statement->bind_param('ii', $orderId, $userId);
    $statement->execute();
    $result = $statement->get_result();
    $order = $result->fetch_assoc() ?: null;
    $result->free();
    $statement->close();

    if ($order === null) {
        $mysqli->close();
        cancel_order_respond(['error' => 'Order not found.'], 404);
    }

    $status = app_normalize_order_status((string) ($order['status'] ?? 'Pending'));

    if (!app_order_can_cancel($status)) {
        $mysqli->close();
        cancel_order_respond(['error' => 'This order can no longer be cancelled.'], 409, 'order-success.php?id=' . $orderId);
    }

    $newStatus = 'Cancelled';
    $cancelReason = 'Cancelled by customer.';
    $updateStatement = $mysqli->prepare(
        'UPDATE orders
         SET status = ?, cancel_reason = ?, updated_at = NOW()
         WHERE id = ? AND user_id = ? AND status IN ("Pending", "Preparing")'
    );
    $updateStatement->bind_param('ssii', $newStatus, $cancelReason, $orderId, $userId);
    $updateStatement->execute();
    $affectedRows = $updateStatement->affected_rows;
    $updateStatement->close();
    $mysqli->close();

    if ($affectedRows < 1) {
        cancel_order_respond(['error' => 'This order can no longer be cancelled.'], 409, 'order-success.php?id=' . $orderId);
    }

    cancel_order_respond([
        'success' => true,
        'message' => 'Your order has been cancelled.',
        'order' => [
            'id' => $orderId,
            'status' => $newStatus,
            'status_class' => app_order_status_class($newStatus),
            'status_message' => 'This order has been cancelled.',
            'updated_label' => date('M j, Y g:i A'),
            'can_cancel' => false,
            'is_terminal' => true,
            'cancel_reason' => $cancelReason,
            'progress_steps' => app_order_progress_steps_for_status($newStatus),
        ],
    ], 200, 'order-success.php?id=' . $orderId);
} catch (mysqli_sql_exception $exception) {
    cancel_order_respond(['error' => 'Unable to cancel this order right now.'], 500, 'order-success.php?id=' . (int) $orderId);
}
