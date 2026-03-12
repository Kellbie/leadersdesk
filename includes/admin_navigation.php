<?php
// This file should be included in all admin pages
// Get current page for active highlighting
$current_page = basename($_SERVER['PHP_SELF']);
?>

<nav class="admin-sidebar">
    <div class="admin-logo">
        LeaderDesk<span>ADMIN</span>
    </div>
    
    <ul class="admin-nav">
        <li>
            <a href="admin_dashboard.php" class="<?php echo $current_page == 'admin_dashboard.php' ? 'active' : ''; ?>">
                <span class="nav-icon">📊</span>
                Dashboard
            </a>
        </li>
        <li>
            <a href="admin_teams.php" class="<?php echo $current_page == 'admin_teams.php' ? 'active' : ''; ?>">
                <span class="nav-icon">👥</span>
                Teams
            </a>
        </li>
        <li>
            <a href="admin_users.php" class="<?php echo $current_page == 'admin_users.php' ? 'active' : ''; ?>">
                <span class="nav-icon">👤</span>
                Users
            </a>
        </li>
        <li>
            <a href="admin_subscriptions.php" class="<?php echo $current_page == 'admin_subscriptions.php' ? 'active' : ''; ?>">
                <span class="nav-icon">💳</span>
                Subscriptions
            </a>
        </li>
        <li>
            <a href="admin_payment_settings.php" class="<?php echo $current_page == 'admin_payment_settings.php' ? 'active' : ''; ?>">
                <span class="nav-icon">💰</span>
                Payment Settings
            </a>
        </li>
        <li>
            <a href="admin_announcements.php" class="<?php echo $current_page == 'admin_announcements.php' ? 'active' : ''; ?>">
                <span class="nav-icon">📢</span>
                Announcements
            </a>
        </li>
        <li>
            <a href="admin_settings.php" class="<?php echo $current_page == 'admin_settings.php' ? 'active' : ''; ?>">
                <span class="nav-icon">⚙️</span>
                Settings
            </a>
        </li>
    </ul>
    
    <div class="back-to-app">
        <a href="logout.php">
            <span class="nav-icon">🚪</span>
            Logout
        </a>
    </div>
</nav>

<style>
.admin-sidebar {
    width: 260px;
    background: #1a1a1a;
    color: white;
    padding: 24px 20px;
    position: fixed;
    height: 100vh;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    z-index: 100;
}

.admin-logo {
    font-size: 22px;
    font-weight: 700;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid #333;
}

.admin-logo span {
    color: #ef4444;
    font-size: 11px;
    margin-left: 6px;
    background: #333;
    padding: 3px 8px;
    border-radius: 100px;
}

.admin-nav {
    list-style: none;
    flex: 1;
    margin: 0;
    padding: 0;
}

.admin-nav li {
    margin-bottom: 6px;
}

.admin-nav a {
    display: flex;
    align-items: center;
    padding: 12px 16px;
    color: #999;
    text-decoration: none;
    border-radius: 8px;
    transition: all 0.2s;
    gap: 12px;
    font-size: 14px;
}

.admin-nav a:hover {
    background: #333;
    color: white;
}

.admin-nav a.active {
    background: #333;
    color: white;
    border-left: 3px solid #ef4444;
}

.nav-icon {
    font-size: 18px;
    min-width: 24px;
}

.back-to-app {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #333;
}

.back-to-app a {
    display: flex;
    align-items: center;
    gap: 12px;
    color: #ef4444;
    text-decoration: none;
    padding: 12px 16px;
    border-radius: 8px;
    font-size: 14px;
    transition: all 0.2s;
}

.back-to-app a:hover {
    background: #333;
}

/* Mobile responsive */
@media (max-width: 768px) {
    .admin-sidebar {
        transform: translateX(-100%);
        transition: transform 0.3s ease;
    }

    .admin-sidebar.open {
        transform: translateX(0);
    }

    .admin-main {
        margin-left: 0 !important;
    }
}

/* Scrollbar styling */
.admin-sidebar::-webkit-scrollbar {
    width: 6px;
}

.admin-sidebar::-webkit-scrollbar-track {
    background: #333;
}

.admin-sidebar::-webkit-scrollbar-thumb {
    background: #666;
    border-radius: 3px;
}

.admin-sidebar::-webkit-scrollbar-thumb:hover {
    background: #888;
}
</style>

<script>
// Mobile menu toggle for admin sidebar
document.addEventListener('DOMContentLoaded', function() {
    const menuToggle = document.getElementById('adminMenuToggle');
    const sidebar = document.querySelector('.admin-sidebar');
    
    if (menuToggle) {
        menuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('open');
        });
    }

    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', function(event) {
        if (window.innerWidth <= 768) {
            if (sidebar && !sidebar.contains(event.target) && !menuToggle?.contains(event.target)) {
                sidebar.classList.remove('open');
            }
        }
    });
});
</script>