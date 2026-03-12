        </main>
    </div>
    
    <!-- Toast notifications container -->
    <div class="toast-container"></div>
    
    <!-- JavaScript -->
    <script src="assets/js/main.js"></script>
    
    <!-- Global Notification Badge Updater -->
    <script>
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
    </script>

    <script>
        // Mobile menu toggle functionality
        document.addEventListener('DOMContentLoaded', function() {
            const menuToggle = document.getElementById('menuToggle');
            const sideNav = document.getElementById('sideNav');
            
            if (menuToggle && sideNav) {
                menuToggle.addEventListener('click', function() {
                    sideNav.classList.toggle('open');
                    menuToggle.textContent = sideNav.classList.contains('open') ? '✕' : '☰';
                });
            }
            
            // Close mobile menu when clicking outside
            if (window.innerWidth < 768) {
                document.addEventListener('click', function(e) {
                    if (sideNav && menuToggle && 
                        !sideNav.contains(e.target) && 
                        !menuToggle.contains(e.target) && 
                        sideNav.classList.contains('open')) {
                        sideNav.classList.remove('open');
                        menuToggle.textContent = '☰';
                    }
                });
            }
        });
    </script>
</body>
</html>