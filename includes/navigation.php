<nav class="side-nav" id="sideNav">
    <ul class="nav-menu">
        <li class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
            <a href="dashboard.php">
                <span class="nav-icon">📊</span>
                <span class="nav-text">Dashboard</span>
            </a>
        </li>
        <li class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'team.php' ? 'active' : ''; ?>">
            <a href="team.php">
                <span class="nav-icon">👥</span>
                <span class="nav-text">Team</span>
            </a>
        </li>
        <li class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'prospects.php' ? 'active' : ''; ?>">
            <a href="prospects.php">
                <span class="nav-icon">🎯</span>
                <span class="nav-text">Prospects</span>
            </a>
        </li>
        <li class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'tasks.php' ? 'active' : ''; ?>">
            <a href="tasks.php">
                <span class="nav-icon">✅</span>
                <span class="nav-text">Tasks</span>
            </a>
        </li>
        <li class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'targets.php' ? 'active' : ''; ?>">
            <a href="targets.php">
                <span class="nav-icon">🎯</span>
                <span class="nav-text">Targets</span>
            </a>
        </li>
        <li class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'training.php' ? 'active' : ''; ?>">
            <a href="training.php">
                <span class="nav-icon">📚</span>
                <span class="nav-text">Training</span>
            </a>
        </li>
        <li class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'events.php' ? 'active' : ''; ?>">
            <a href="events.php">
                <span class="nav-icon">📅</span>
                <span class="nav-text">Events</span>
            </a>
        </li>
        <li class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'leaderboard.php' ? 'active' : ''; ?>">
            <a href="leaderboard.php">
                <span class="nav-icon">🏆</span>
                <span class="nav-text">Leaderboard</span>
            </a>
        </li>
        <li class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'notifications.php' ? 'active' : ''; ?>">
            <a href="notifications.php">
                <span class="nav-icon">🔔</span>
                <span class="nav-text">Notifications</span>
                <span class="nav-badge" id="sideNotificationCount" style="display: none;"></span>
            </a>
        </li>
        <li class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">
            <a href="reports.php">
                <span class="nav-icon">📈</span>
                <span class="nav-text">Reports</span>
            </a>
        </li>
        <li class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>">
            <a href="profile.php">
                <span class="nav-icon">👤</span>
                <span class="nav-text">Profile</span>
            </a>
        </li>
        
        <!-- Admin Panel Link (only for super admin) -->
        <?php if (isset($current_user) && $current_user['role'] == 'super_admin'): ?>
            <li class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'admin_dashboard.php' ? 'active' : ''; ?>" style="margin-top: 20px; border-top: 1px solid #eaeaea; padding-top: 20px;">
                <a href="admin_dashboard.php">
                    <span class="nav-icon">⚡</span>
                    <span class="nav-text">Admin Panel</span>
                </a>
            </li>
        <?php endif; ?>
        
        <!-- Logout for all users (super admin has logout in admin sidebar too) -->
        <li class="nav-item" style="margin-top: 20px; border-top: 1px solid #eaeaea; padding-top: 20px;">
            <a href="logout.php" style="color: #ef4444;">
                <span class="nav-icon">🚪</span>
                <span class="nav-text">Logout</span>
            </a>
        </li>
    </ul>
</nav>

<style>
    .side-nav {
        background: white;
        border-right: 1px solid #eaeaea;
        width: 100%;
        position: fixed;
        top: 60px;
        left: -100%;
        height: calc(100vh - 60px);
        transition: left 0.3s ease;
        z-index: 99;
        overflow-y: auto;
    }

    .side-nav.open {
        left: 0;
    }

    @media (min-width: 768px) {
        .side-nav {
            width: 250px;
            left: 0;
            top: 60px;
        }
        
        .app-container {
            display: flex;
        }
        
        .main-content {
            margin-left: 250px;
            width: calc(100% - 250px);
        }
    }

    .nav-menu {
        list-style: none;
        padding: 1rem 0;
    }

    .nav-item {
        margin-bottom: 0.25rem;
        position: relative;
    }

    .nav-item a {
        display: flex;
        align-items: center;
        padding: 0.875rem 1.5rem;
        color: #666;
        text-decoration: none;
        transition: all 0.2s;
        gap: 0.75rem;
        font-size: 14px;
    }

    .nav-item:hover a {
        background: #f5f5f5;
        color: #1a1a1a;
    }

    .nav-item.active a {
        background: #1a1a1a;
        color: white;
    }

    .nav-icon {
        font-size: 1.25rem;
        min-width: 24px;
    }

    .nav-badge {
        background: #dc2626;
        color: white;
        font-size: 11px;
        padding: 2px 6px;
        border-radius: 50%;
        margin-left: auto;
        min-width: 20px;
        text-align: center;
        font-weight: 600;
    }
</style>