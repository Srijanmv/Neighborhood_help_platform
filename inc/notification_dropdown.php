<?php
if (!isset($user) || !$user) {
    return;
}

$notificationPreviewItems = get_notifications_for_user($user['id'], 6);
$notificationUnreadCount = get_unread_notification_count($user['id']);
?>
<div class="notif-menu">
  <button class="notif-trigger" type="button" aria-expanded="false" aria-haspopup="true" data-notif-toggle>
    <span>Notifications</span>
    <span class="notif-count"><?php echo e((string) $notificationUnreadCount); ?></span>
  </button>
  <div class="notif-dropdown" data-notif-menu>
    <div class="notif-dropdown-head">
      <strong>Recent notifications</strong>
      <a href="notifications.php">View all</a>
    </div>
    <?php if (empty($notificationPreviewItems)): ?>
      <div class="notif-empty">No notifications yet.</div>
    <?php else: ?>
      <div class="notif-list">
        <?php foreach ($notificationPreviewItems as $notificationItem): ?>
          <?php
            $notificationLink = 'notifications.php';
            if (($notificationItem['type'] ?? '') === 'new_message') {
                $notificationLink = 'messages.php';
            } elseif (!empty($notificationItem['post_id'])) {
                $notificationLink = 'view_post.php?id=' . (int) $notificationItem['post_id'];
            }
          ?>
          <a class="notif-item" href="<?php echo e($notificationLink); ?>">
            <strong><?php echo e($notificationItem['message']); ?></strong>
            <span><?php echo e($notificationItem['created_at']); ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
