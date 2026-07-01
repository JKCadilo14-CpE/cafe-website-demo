<?php
require_once __DIR__ . '/../components/app.php';

app_require_admin();

$roleLabels = [
    0 => 'User',
    1 => 'Admin',
];

$message = '';
$messageType = '';
$users = [];

function admin_reset_users_auto_increment(mysqli $mysqli): void
{
    $result = $mysqli->query('SELECT COALESCE(MAX(id), 0) + 1 FROM users');
    $nextId = max(1, (int) ($result->fetch_row()[0] ?? 1));
    $result->free();

    $mysqli->query('ALTER TABLE users AUTO_INCREMENT = ' . $nextId);
}

function admin_locked_admin_count(mysqli $mysqli): int
{
    $adminResult = $mysqli->query('SELECT id FROM users WHERE role = 1 FOR UPDATE');
    $adminCount = $adminResult->num_rows;
    $adminResult->free();

    return $adminCount;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $mysqli = app_db();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        app_require_csrf();

        $action = (string) ($_POST['action'] ?? '');
        $userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);

        if ($userId === false || $userId === null || $userId < 1) {
            $message = 'Please choose a valid user before continuing.';
            $messageType = 'error';
        } elseif ($action === 'update_role') {
            $newRole = filter_input(INPUT_POST, 'role', FILTER_VALIDATE_INT);

            if ($newRole !== false && $newRole !== null && array_key_exists($newRole, $roleLabels)) {
                $currentAdminId = (int) ($_SESSION['user_id'] ?? 0);
                $transactionStarted = false;

                try {
                    $mysqli->begin_transaction();
                    $transactionStarted = true;

                    $userStatement = $mysqli->prepare('SELECT id, username, role FROM users WHERE id = ? LIMIT 1 FOR UPDATE');
                    $userStatement->bind_param('i', $userId);
                    $userStatement->execute();
                    $userResult = $userStatement->get_result();
                    $targetUser = $userResult->fetch_assoc();
                    $userResult->free();
                    $userStatement->close();

                    if ($targetUser === null) {
                        throw new RuntimeException('User account not found.');
                    }

                    $targetRole = (int) $targetUser['role'];

                    if ($userId === $currentAdminId && $newRole !== 1) {
                        throw new RuntimeException('You cannot demote the account you are currently using.');
                    }

                    if ($targetRole === 1 && $newRole !== 1 && admin_locked_admin_count($mysqli) <= 1) {
                        throw new RuntimeException('You cannot demote the last admin account.');
                    }

                    if ($targetRole !== $newRole) {
                        $statement = $mysqli->prepare('UPDATE users SET role = ? WHERE id = ?');
                        $statement->bind_param('ii', $newRole, $userId);
                        $statement->execute();
                        $statement->close();
                    }

                    $mysqli->commit();
                    $transactionStarted = false;

                    header('Location: admin-users-list.php?updated=1');
                    exit();
                } catch (Throwable $exception) {
                    if ($transactionStarted) {
                        $mysqli->rollback();
                    }

                    $message = $exception instanceof RuntimeException
                        ? $exception->getMessage()
                        : 'Unable to update this user role right now.';
                    $messageType = 'error';
                }
            } else {
                $message = 'Please choose a valid role before updating.';
                $messageType = 'error';
            }
        } elseif ($action === 'delete_user') {
            $currentAdminId = (int) ($_SESSION['user_id'] ?? 0);

            if ($userId === $currentAdminId) {
                $message = 'You cannot delete the account you are currently using.';
                $messageType = 'error';
            } else {
                $profileImage = '';

                $mysqli->begin_transaction();

                try {
                    $userStatement = $mysqli->prepare('SELECT id, username, role, profile_image FROM users WHERE id = ? LIMIT 1 FOR UPDATE');
                    $userStatement->bind_param('i', $userId);
                    $userStatement->execute();
                    $userResult = $userStatement->get_result();
                    $targetUser = $userResult->fetch_assoc();
                    $userResult->free();
                    $userStatement->close();

                    if ($targetUser === null) {
                        throw new RuntimeException('User account not found.');
                    }

                    if ((int) $targetUser['role'] === 1) {
                        $adminCount = admin_locked_admin_count($mysqli);

                        if ($adminCount <= 1) {
                            throw new RuntimeException('You cannot delete the last admin account.');
                        }
                    }

                    $profileImage = (string) ($targetUser['profile_image'] ?? '');

                    $deleteItemsStatement = $mysqli->prepare(
                        "DELETE oi
                         FROM order_items oi
                         INNER JOIN `orders` o ON o.id = oi.order_id
                         WHERE o.user_id = ?
                           AND (o.status IS NULL OR o.status NOT IN ('Pending', 'Preparing', 'Out for Delivery'))"
                    );
                    $deleteItemsStatement->bind_param('i', $userId);
                    $deleteItemsStatement->execute();
                    $deleteItemsStatement->close();

                    $deleteOrdersStatement = $mysqli->prepare(
                        "DELETE FROM `orders`
                         WHERE user_id = ?
                           AND (status IS NULL OR status NOT IN ('Pending', 'Preparing', 'Out for Delivery'))"
                    );
                    $deleteOrdersStatement->bind_param('i', $userId);
                    $deleteOrdersStatement->execute();
                    $deleteOrdersStatement->close();

                    $cancelOrdersStatement = $mysqli->prepare(
                        "UPDATE `orders`
                         SET status = 'Cancelled',
                             cancel_reason = 'User account deleted by admin.',
                             updated_at = NOW(),
                             user_id = NULL
                         WHERE user_id = ?
                           AND status IN ('Pending', 'Preparing', 'Out for Delivery')"
                    );
                    $cancelOrdersStatement->bind_param('i', $userId);
                    $cancelOrdersStatement->execute();
                    $cancelOrdersStatement->close();

                    $deleteUserStatement = $mysqli->prepare('DELETE FROM users WHERE id = ?');
                    $deleteUserStatement->bind_param('i', $userId);
                    $deleteUserStatement->execute();
                    $deleteUserStatement->close();

                    $mysqli->commit();
                    app_delete_profile_image($profileImage);
                    admin_reset_users_auto_increment($mysqli);

                    header('Location: admin-users-list.php?deleted=1');
                    exit();
                } catch (Throwable $exception) {
                    $mysqli->rollback();
                    $message = $exception instanceof RuntimeException
                        ? $exception->getMessage()
                        : 'Unable to delete this user account right now.';
                    $messageType = 'error';
                }
            }
        } else {
            $message = 'Please choose a valid user action.';
            $messageType = 'error';
        }
    }


    if (isset($_GET['updated'])) {
        $message = 'User role updated successfully.';
        $messageType = 'success';
    } elseif (isset($_GET['deleted'])) {
        $message = 'User account deleted successfully.';
        $messageType = 'success';
    }

    $result = $mysqli->query('SELECT id, username, email, role, profile_image FROM users ORDER BY id ASC');
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    $result->free();
    $mysqli->close();
} catch (mysqli_sql_exception $exception) {
    $message = 'Unable to load users right now. Please check the database connection.';
    $messageType = 'error';
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin Users | JKC Cafe Admin</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
        <link rel="stylesheet" href="css/admin-shell.css">
        <link rel="stylesheet" href="css/admin-style.css">
        <link rel="stylesheet" href="css/admin-users.css">
    </head>
    <body>
        <div class="admin-layout">
            <?php require __DIR__ . '/components/admin-sidebar.php'; ?>

            <main class="admin-main">
                <?php require __DIR__ . '/components/admin-topbar.php'; ?>

                <section class="users-dashboard" aria-label="Admin Users List">
                    <div class="users-panel">
                        <div class="users-panel-header">
                            <h1 class="users-panel-title">
                                <i class="fa-solid fa-users-gear" aria-hidden="true"></i>
                                Users
                            </h1>
                            <span class="users-count"><?php echo count($users); ?> total users</span>
                        </div>

                        <?php if ($message !== ''): ?>
                            <div class="admin-message <?php echo htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                        <?php endif; ?>

                        <div class="users-table-wrap">
                            <table class="users-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Username</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($users)): ?>
                                        <tr>
                                            <td colspan="5" class="empty-state">No users found.</td>
                                        </tr>
                                    <?php endif; ?>

                                    <?php foreach ($users as $user): ?>
                                        <?php
                                            $userId = (int) $user['id'];
                                            $userRole = (int) $user['role'];
                                            $roleName = $roleLabels[$userRole] ?? 'Unknown';
                                            $roleClass = strtolower($roleName);
                                        ?>
                                        <tr>
                                            <td><?php echo $userId; ?></td>
                                            <td><?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td>
                                                <span class="role-badge <?php echo htmlspecialchars($roleClass, ENT_QUOTES, 'UTF-8'); ?>">
                                                    <?php echo htmlspecialchars($roleName, ENT_QUOTES, 'UTF-8'); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="user-action-group">
                                                    <form class="role-form" action="admin-users-list.php" method="POST">
                                                        <?php echo app_csrf_field(); ?>
                                                        <input type="hidden" name="action" value="update_role">
                                                        <input type="hidden" name="user_id" value="<?php echo $userId; ?>">
                                                        <select class="role-select" name="role" aria-label="Change role for <?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?>">
                                                            <?php foreach ($roleLabels as $roleValue => $label): ?>
                                                                <option value="<?php echo $roleValue; ?>" <?php echo $userRole === $roleValue ? 'selected' : ''; ?>>
                                                                    <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                        <button type="submit" class="update-role-button">
                                                            <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
                                                            Update
                                                        </button>
                                                    </form>

                                                    <form class="delete-user-form" action="admin-users-list.php" method="POST" onsubmit="return confirm('Permanently delete <?php echo htmlspecialchars(addslashes((string) $user['username']), ENT_QUOTES, 'UTF-8'); ?>? Active orders will be cancelled and the account cannot be restored.');">
                                                        <?php echo app_csrf_field(); ?>
                                                        <input type="hidden" name="action" value="delete_user">
                                                        <input type="hidden" name="user_id" value="<?php echo $userId; ?>">
                                                        <button type="submit" class="delete-user-button" <?php echo $userId === (int) ($_SESSION['user_id'] ?? 0) ? 'disabled aria-disabled="true"' : ''; ?>>
                                                            <i class="fa-solid fa-trash-can" aria-hidden="true"></i>
                                                            Delete
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
            </main>
        </div>

        <script src="js/admin_script.js"></script>
    </body>
</html>
