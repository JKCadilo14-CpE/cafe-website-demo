<?php
require_once __DIR__ . '/../components/app.php';

app_require_admin();

$currentPage = 'contact-messages';
$messages = [];
$selectedMessage = null;
$message = '';
$messageType = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    app_require_csrf();

    $action = (string) ($_POST['action'] ?? '');
    $messageId = filter_input(INPUT_POST, 'message_id', FILTER_VALIDATE_INT);

    if ($action === 'mark_read' && $messageId !== false && $messageId !== null && $messageId > 0) {
        try {
            app_mark_contact_message_read((int) $messageId);
            header('Location: admin-contact-messages.php?id=' . (int) $messageId . '&updated=read');
            exit;
        } catch (mysqli_sql_exception $exception) {
            $message = 'Unable to update this message right now.';
            $messageType = 'error';
        }
    } else {
        $message = 'Invalid message action.';
        $messageType = 'error';
    }
}

try {
    $messages = app_contact_messages();
    $requestedId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

    if ($requestedId !== false && $requestedId !== null && $requestedId > 0) {
        $selectedMessage = app_contact_message((int) $requestedId);
    }

    if ($selectedMessage === null && $messages !== []) {
        $selectedMessage = $messages[0];
    }

    if (isset($_GET['updated']) && $_GET['updated'] === 'read') {
        $message = 'Message marked as read.';
        $messageType = 'success';
    }
} catch (mysqli_sql_exception $exception) {
    $message = 'Unable to load contact messages right now.';
    $messageType = 'error';
}

$unreadCount = 0;

foreach ($messages as $contactMessage) {
    if ((string) ($contactMessage['status'] ?? 'unread') !== 'read') {
        $unreadCount++;
    }
}

$totalMessages = count($messages);
$readCount = max(0, $totalMessages - $unreadCount);
$latestReceived = $messages !== [] ? (string) ($messages[0]['created_at'] ?? 'Not available') : 'No messages yet';
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Contact Messages | JKC Cafe Admin</title>
        <link rel="icon" href="../images/logo.svg" type="image/svg+xml">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
        <link rel="stylesheet" href="css/admin-shell.css">
        <link rel="stylesheet" href="css/admin-style.css">
        <link rel="stylesheet" href="css/admin-contact.css">
    </head>
    <body class="admin-contact-messages-page">
        <div class="admin-layout">
            <?php require __DIR__ . '/components/admin-sidebar.php'; ?>

            <main class="admin-main">
                <?php require __DIR__ . '/components/admin-topbar.php'; ?>

                <section class="contact-messages-dashboard" aria-labelledby="contact-messages-title">
                    <div class="contact-messages-panel">
                        <div class="contact-messages-header">
                            <div>
                                <p class="contact-messages-eyebrow">Cafe inbox</p>
                                <h1 class="contact-messages-title" id="contact-messages-title">
                                    <i class="fa-regular fa-envelope" aria-hidden="true"></i>
                                    Contact Messages
                                </h1>
                                <p class="contact-messages-subtitle">Review guest questions, catering notes, visit feedback, and order concerns from one calm workspace.</p>
                            </div>
                            <a class="contact-messages-visit-link" href="../contact.php" target="_blank" rel="noopener">
                                <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
                                View contact page
                            </a>
                        </div>

                        <div class="contact-messages-summary" aria-label="Message summary">
                            <article>
                                <span>Total messages</span>
                                <strong><?php echo $totalMessages; ?></strong>
                                <small>All guest submissions</small>
                            </article>
                            <article class="is-highlighted">
                                <span>Needs review</span>
                                <strong><?php echo $unreadCount; ?></strong>
                                <small>Unread messages</small>
                            </article>
                            <article>
                                <span>Completed</span>
                                <strong><?php echo $readCount; ?></strong>
                                <small>Marked as read</small>
                            </article>
                            <article>
                                <span>Latest received</span>
                                <strong><?php echo e($latestReceived); ?></strong>
                                <small>Newest cafe note</small>
                            </article>
                        </div>

                        <?php if ($message !== ''): ?>
                            <div class="admin-message <?php echo e($messageType); ?>" role="<?php echo $messageType === 'error' ? 'alert' : 'status'; ?>">
                                <?php echo e($message); ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($messages === []): ?>
                            <div class="contact-messages-empty">
                                <span class="contact-messages-empty-icon" aria-hidden="true">
                                    <i class="fa-regular fa-envelope-open"></i>
                                </span>
                                <h2>No guest messages yet</h2>
                                <p>When someone writes from the contact page, their note will appear here with topic, timestamp, and reply-ready details.</p>
                                <a class="contact-messages-visit-link" href="../contact.php" target="_blank" rel="noopener">
                                    Check the contact page
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="contact-messages-layout">
                                <div class="contact-messages-list" aria-label="Message list">
                                    <?php foreach ($messages as $contactMessage): ?>
                                        <?php
                                            $contactMessageId = (int) ($contactMessage['id'] ?? 0);
                                            $isUnread = (string) ($contactMessage['status'] ?? 'unread') !== 'read';
                                            $isSelected = $selectedMessage !== null && (int) ($selectedMessage['id'] ?? 0) === $contactMessageId;
                                            $messagePreview = trim((string) ($contactMessage['message'] ?? ''));
                                            if (strlen($messagePreview) > 112) {
                                                $messagePreview = substr($messagePreview, 0, 112) . '...';
                                            }
                                        ?>
                                        <a class="contact-message-card<?php echo $isUnread ? ' is-unread' : ''; ?><?php echo $isSelected ? ' is-selected' : ''; ?>" href="admin-contact-messages.php?id=<?php echo $contactMessageId; ?>"<?php echo $isSelected ? ' aria-current="true"' : ''; ?>>
                                            <span class="contact-message-card-top">
                                                <span class="contact-message-topic"><?php echo e(app_contact_topic_label((string) ($contactMessage['topic'] ?? 'support'))); ?></span>
                                                <span class="contact-message-status"><?php echo $isUnread ? 'Unread' : 'Read'; ?></span>
                                            </span>
                                            <strong><?php echo e((string) ($contactMessage['name'] ?? 'Guest')); ?></strong>
                                            <span class="contact-message-preview"><?php echo e($messagePreview !== '' ? $messagePreview : 'No message preview available.'); ?></span>
                                            <time datetime="<?php echo e((string) ($contactMessage['created_at'] ?? '')); ?>">
                                                <?php echo e((string) ($contactMessage['created_at'] ?? 'Not available')); ?>
                                            </time>
                                        </a>
                                    <?php endforeach; ?>
                                </div>

                                <?php $selectedIsUnread = $selectedMessage !== null && (string) ($selectedMessage['status'] ?? 'unread') !== 'read'; ?>
                                <article class="contact-message-detail<?php echo $selectedIsUnread ? ' is-unread' : ''; ?>" aria-labelledby="contact-message-detail-title">
                                    <?php if ($selectedMessage !== null): ?>
                                        <div class="contact-message-detail-head">
                                            <div>
                                                <span class="contact-message-status"><?php echo $selectedIsUnread ? 'Unread' : 'Read'; ?></span>
                                                <p class="contact-message-detail-kicker">Selected message</p>
                                                <h2 id="contact-message-detail-title"><?php echo e((string) ($selectedMessage['name'] ?? 'Guest')); ?></h2>
                                            </div>
                                            <a class="contact-message-email-link" href="mailto:<?php echo e((string) ($selectedMessage['email'] ?? '')); ?>">
                                                <i class="fa-regular fa-paper-plane" aria-hidden="true"></i>
                                                <?php echo e((string) ($selectedMessage['email'] ?? '')); ?>
                                            </a>
                                        </div>
                                        <dl class="contact-message-meta">
                                            <div>
                                                <dt>Topic</dt>
                                                <dd><?php echo e(app_contact_topic_label((string) ($selectedMessage['topic'] ?? 'support'))); ?></dd>
                                            </div>
                                            <div>
                                                <dt>Received</dt>
                                                <dd><?php echo e((string) ($selectedMessage['created_at'] ?? 'Not available')); ?></dd>
                                            </div>
                                        </dl>
                                        <div class="contact-message-body-label">
                                            <i class="fa-regular fa-message" aria-hidden="true"></i>
                                            Guest note
                                        </div>
                                        <div class="contact-message-body">
                                            <?php echo nl2br(e((string) ($selectedMessage['message'] ?? ''))); ?>
                                        </div>
                                        <?php if ($selectedIsUnread): ?>
                                            <form class="contact-message-actions" action="admin-contact-messages.php" method="post">
                                                <?php echo app_csrf_field(); ?>
                                                <input type="hidden" name="action" value="mark_read">
                                                <input type="hidden" name="message_id" value="<?php echo (int) ($selectedMessage['id'] ?? 0); ?>">
                                                <button type="submit" class="contact-message-read-button">
                                                    <i class="fa-solid fa-check" aria-hidden="true"></i>
                                                    Mark as read
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <p class="contact-message-read-note">
                                                <i class="fa-solid fa-check" aria-hidden="true"></i>
                                                This message is already marked as read.
                                            </p>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </article>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            </main>
        </div>

        <script src="js/admin_script.js"></script>
    </body>
</html>
