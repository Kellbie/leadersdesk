// Global notification badge updater
function updateNotificationBadges() {
    fetch('ajax/get_notifications.php')
        .then(response => response.json())
        .then(data => {
            // Update header badge
            const headerBadge = document.getElementById('notificationCount');
            if (headerBadge) {
                if (data.unread_count > 0) {
                    headerBadge.textContent = data.unread_count;
                    headerBadge.style.display = 'block';
                } else {
                    headerBadge.style.display = 'none';
                }
            }
            
            // Update sidebar badge
            const sideBadge = document.getElementById('sideNotificationCount');
            if (sideBadge) {
                if (data.unread_count > 0) {
                    sideBadge.textContent = data.unread_count;
                    sideBadge.style.display = 'inline';
                } else {
                    sideBadge.style.display = 'none';
                }
            }
        })
        .catch(error => console.error('Error updating badges:', error));
}

// Update on page load
document.addEventListener('DOMContentLoaded', function() {
    updateNotificationBadges();
    // Update every 30 seconds
    setInterval(updateNotificationBadges, 30000);
});