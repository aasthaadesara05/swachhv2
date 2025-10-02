// Simple notifications badge updater
async function updateUnreadCount(apiPath, badgeId) {
    try {
        const res = await fetch(apiPath);
        if (!res.ok) return;
        const data = await res.json();
        const el = document.getElementById(badgeId);
        if (!el) return;
        const count = (data && typeof data.unread_count !== 'undefined') ? Number(data.unread_count) : 0;
        el.textContent = count;
        el.style.display = count > 0 ? 'inline-block' : 'none';
    } catch (_) {
        // ignore
    }
}

function initNotificationBell(apiPath, badgeId, intervalMs) {
    updateUnreadCount(apiPath, badgeId);
    const ms = intervalMs && intervalMs > 5000 ? intervalMs : 15000;
    setInterval(() => updateUnreadCount(apiPath, badgeId), ms);
}

// Expose globally
window.initNotificationBell = initNotificationBell;


