<?php
require_once __DIR__ . '/components/app.php';

header('Content-Type: application/json');

function order_status_format_datetime(?string $value): string
{
    $timestamp = strtotime((string) $value);

    return $timestamp ? date('M j, Y g:i A', $timestamp) : 'Just now';
}

if (!app_is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Please log in to view this order.']);
    exit();
}

$orderId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if ($orderId === false || $orderId === null || $orderId < 1) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid order id.']);
    exit();
}

try {
    $mysqli = app_db();
    $userId = (int) $_SESSION['user_id'];
    $statement = $mysqli->prepare(
        'SELECT id, status, total_amount, created_at, updated_at, cancel_reason
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
    $mysqli->close();

    if ($order === null) {
        http_response_code(404);
        echo json_encode(['error' => 'Order not found.']);
        exit();
    }

    $status = app_normalize_order_status((string) ($order['status'] ?? 'Pending'));
    $updatedAt = (string) (($order['updated_at'] ?? '') ?: ($order['created_at'] ?? ''));
    $statusMessage = match ($status) {
        'Preparing' => 'The cafe team is preparing your order now.',
        'Out for Delivery' => 'Your order has left the cafe and is on its way.',
        'Completed' => 'Your order has been completed. Thank you for ordering with JKC Cafe.',
        'Cancelled' => 'This order has been cancelled.',
        default => 'Your order is in the cafe queue and will move soon.',
    };

    echo json_encode([
        'success' => true,
        'order' => [
            'id' => (int) $order['id'],
            'status' => $status,
            'status_class' => app_order_status_class($status),
            'status_message' => $statusMessage,
            'total' => app_format_money((float) ($order['total_amount'] ?? 0)),
            'updated_at' => $updatedAt,
            'updated_label' => order_status_format_datetime($updatedAt),
            'can_cancel' => app_order_can_cancel($status),
            'is_terminal' => in_array($status, app_order_terminal_statuses(), true),
            'cancel_reason' => (string) ($order['cancel_reason'] ?? ''),
            'progress_steps' => app_order_progress_steps_for_status($status),
        ],
    ]);
} catch (mysqli_sql_exception $exception) {
    http_response_code(500);
    echo json_encode(['error' => 'Unable to load order status.']);
}
