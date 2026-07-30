<?php
if (!isset($user) || !$user) {
    return;
}

$nhBasePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$notificationPollPath = ($nhBasePath === '' ? '' : $nhBasePath) . '/notifications_poll.php';
?>
<div class="nhpush-root" id="nhPushRoot" hidden>
  <button class="nhpush-button" id="nhPushButton" type="button">
    Enable browser alerts
  </button>
</div>
<div class="nhpush-toast-stack" id="nhPushToastStack" aria-live="polite" aria-atomic="true"></div>

<style>
  .nhpush-root {
    position: fixed;
    right: 18px;
    bottom: 84px;
    z-index: 2147483646;
    font-family: 'Manrope', sans-serif;
  }

  .nhpush-button {
    border: 0;
    border-radius: 999px;
    padding: 11px 15px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: linear-gradient(135deg, #0f766e, #14b8a6);
    color: #fff;
    font-weight: 800;
    cursor: pointer;
    box-shadow: 0 16px 34px rgba(15, 118, 110, 0.26);
  }

  .nhpush-toast-stack {
    position: fixed;
    top: 18px;
    right: 18px;
    z-index: 2147483645;
    display: grid;
    gap: 10px;
    width: min(360px, calc(100vw - 24px));
    pointer-events: none;
  }

  .nhpush-toast {
    pointer-events: auto;
    background: rgba(255, 255, 255, 0.96);
    border: 1px solid rgba(20, 83, 45, 0.16);
    border-radius: 18px;
    box-shadow: 0 18px 38px rgba(15, 23, 42, 0.16);
    overflow: hidden;
  }

  .nhpush-toast a {
    display: block;
    padding: 14px 16px;
    color: #172033;
    text-decoration: none;
  }

  .nhpush-toast strong {
    display: block;
    margin-bottom: 4px;
    color: #14532d;
    font-size: 0.96rem;
  }

  .nhpush-toast span {
    display: block;
    color: #5b6472;
    font-size: 0.88rem;
    line-height: 1.5;
  }

  .nhpush-toast small {
    display: block;
    margin-top: 6px;
    color: #6b7280;
    font-size: 0.78rem;
  }
</style>

<script>
  (function () {
    const currentUserId = <?php echo (int) $user['id']; ?>;
    const pollUrl = <?php echo json_encode($notificationPollPath); ?>;
    const storageKey = 'nh:lastSeenNotification:' + currentUserId;
    const pushRoot = document.getElementById('nhPushRoot');
    const pushButton = document.getElementById('nhPushButton');
    const toastStack = document.getElementById('nhPushToastStack');
    const supportsBrowserAlerts = 'Notification' in window;

    function escapeHtml(text) {
      return String(text).replace(/[&<>"']/g, function (char) {
        return ({
          '&': '&amp;',
          '<': '&lt;',
          '>': '&gt;',
          '"': '&quot;',
          "'": '&#39;'
        })[char];
      });
    }

    function getLastSeenId() {
      const raw = window.localStorage.getItem(storageKey);
      const parsed = raw ? parseInt(raw, 10) : 0;
      return Number.isFinite(parsed) ? parsed : 0;
    }

    function setLastSeenId(id) {
      const safeId = Number.isFinite(id) ? id : 0;
      window.localStorage.setItem(storageKey, String(safeId));
    }

    function updatePermissionButton() {
      if (!supportsBrowserAlerts || !pushRoot || !pushButton) {
        return;
      }

      if (Notification.permission === 'default') {
        pushRoot.hidden = false;
        pushButton.disabled = false;
        pushButton.textContent = 'Enable browser alerts';
      } else if (Notification.permission === 'denied') {
        pushRoot.hidden = false;
        pushButton.disabled = true;
        pushButton.textContent = 'Browser alerts blocked';
      } else {
        pushRoot.hidden = true;
      }
    }

    function updateUnreadCount(count) {
      document.querySelectorAll('.notif-count').forEach(function (node) {
        node.textContent = String(count);
        node.style.display = count > 0 ? 'inline-flex' : 'none';
      });
    }

    function updateDropdownItems(items) {
      const menus = document.querySelectorAll('[data-notif-menu]');
      menus.forEach(function (menu) {
        const existingList = menu.querySelector('.notif-list');
        const existingEmpty = menu.querySelector('.notif-empty');

        if (!items.length) {
          if (existingList) {
            existingList.remove();
          }
          if (!existingEmpty) {
            const empty = document.createElement('div');
            empty.className = 'notif-empty';
            empty.textContent = 'No notifications yet.';
            menu.appendChild(empty);
          }
          return;
        }

        if (existingEmpty) {
          existingEmpty.remove();
        }

        const list = existingList || document.createElement('div');
        list.className = 'notif-list';
        list.innerHTML = items.map(function (item) {
          return '<a class="notif-item" href="' + escapeHtml(item.link) + '">' +
            '<strong>' + escapeHtml(item.message) + '</strong>' +
            '<span>' + escapeHtml(item.created_at) + '</span>' +
          '</a>';
        }).join('');

        if (!existingList) {
          menu.appendChild(list);
        }
      });
    }

    function showDesktopAlerts(items) {
      if (!supportsBrowserAlerts || Notification.permission !== 'granted') {
        return;
      }

      if (!items.length || (!document.hidden && document.hasFocus())) {
        return;
      }

      items.forEach(function (item) {
        const notification = new Notification('Neighborhood Help', {
          body: item.message,
          tag: 'nh-alert-' + item.id
        });

        notification.onclick = function () {
          window.focus();
          window.location.href = item.link;
          notification.close();
        };
      });
    }

    function showInAppToasts(items) {
      if (!toastStack || !items.length) {
        return;
      }

      items.forEach(function (item) {
        const toast = document.createElement('div');
        toast.className = 'nhpush-toast';
        toast.innerHTML =
          '<a href="' + escapeHtml(item.link) + '">' +
            '<strong>New notification</strong>' +
            '<span>' + escapeHtml(item.message) + '</span>' +
            '<small>' + escapeHtml(item.created_at) + '</small>' +
          '</a>';

        toastStack.prepend(toast);

        window.setTimeout(function () {
          toast.remove();
        }, 5000);
      });
    }

    async function pollNotifications(options) {
      const initializeOnly = options && options.initializeOnly;
      const afterId = getLastSeenId();
      const response = await fetch(pollUrl + '?after_id=' + afterId + '&recent_limit=6', {
        credentials: 'same-origin',
        headers: {
          'Accept': 'application/json'
        }
      });

      if (!response.ok) {
        throw new Error('Could not fetch notifications.');
      }

      const payload = await response.json();
      if (!payload.success) {
        throw new Error(payload.message || 'Notification polling failed.');
      }

      updateUnreadCount(payload.unread_count || 0);
      updateDropdownItems(Array.isArray(payload.recent) ? payload.recent : []);

      const latestId = Number(payload.latest_id || 0);
      if (initializeOnly && !window.localStorage.getItem(storageKey)) {
        setLastSeenId(latestId);
        return;
      }

      const items = Array.isArray(payload.items) ? payload.items : [];
      if (items.length) {
        showInAppToasts(items);
        showDesktopAlerts(items);
      }

      setLastSeenId(latestId);
    }

    if (pushButton) {
      pushButton.addEventListener('click', function () {
        if (!supportsBrowserAlerts || Notification.permission !== 'default') {
          updatePermissionButton();
          return;
        }

        Notification.requestPermission().finally(function () {
          updatePermissionButton();
        });
      });
    }

    updatePermissionButton();

    pollNotifications({ initializeOnly: true }).catch(function () {});
    window.setInterval(function () {
      pollNotifications({ initializeOnly: false }).catch(function () {});
    }, 20000);
  })();
</script>
