<?php
require_once 'config/database.php';
require_once 'includes/auth_check.php';

$page_title = "Training";
$message = '';
$error = '';

// Handle training upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'upload_training') {
        $title = $_POST['title'] ?? '';
        $description = $_POST['description'] ?? '';
        $category = $_POST['category'] ?? '';
        $content_type = $_POST['content_type'] ?? '';
        
        // Initialize variables
        $content_url = null;
        $file_path = null;
        
        if ($content_type == 'file') {
            // Handle file upload
            if (isset($_FILES['training_file']) && $_FILES['training_file']['error'] == 0) {
                $upload_dir = 'uploads/training/';
                
                // Create directory if it doesn't exist
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                // Generate unique filename
                $file_extension = pathinfo($_FILES['training_file']['name'], PATHINFO_EXTENSION);
                $file_name = time() . '_' . uniqid() . '.' . $file_extension;
                $target_file = $upload_dir . $file_name;
                
                // Move uploaded file
                if (move_uploaded_file($_FILES['training_file']['tmp_name'], $target_file)) {
                    $file_path = $target_file;
                    // Log success for debugging
                    error_log("File uploaded successfully: " . $target_file);
                } else {
                    $error = 'Failed to upload file. Please check directory permissions.';
                    error_log("Upload failed: " . print_r($_FILES['training_file']['error'], true));
                }
            } else {
                $upload_error = $_FILES['training_file']['error'] ?? 'No file selected';
                $error = 'Please select a file to upload. Error code: ' . $upload_error;
                error_log("Upload error: " . $upload_error);
            }
        } else {
            // Handle URL-based content
            $content_url = $_POST['content_url'] ?? '';
            if (empty($content_url)) {
                $error = 'URL is required for this content type.';
            }
        }
        
        if (empty($error) && !empty($title) && !empty($category) && !empty($content_type)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO trainings (team_id, uploaded_by, title, description, category, content_type, content_url, file_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$current_user['team_id'], $current_user['id'], $title, $description, $category, $content_type, $content_url, $file_path]);
                
                // Log activity
                $stmt = $pdo->prepare("INSERT INTO activity_logs (team_id, user_id, action, description, points_earned) VALUES (?, ?, 'upload_training', ?, 15)");
                $stmt->execute([$current_user['team_id'], $current_user['id'], "Uploaded training: $title"]);
                
                $_SESSION['success_message'] = "Training material uploaded successfully!";
                header("Location: training.php");
                exit();
            } catch (Exception $e) {
                $error = 'Failed to upload training: ' . $e->getMessage();
                error_log("Database error: " . $e->getMessage());
            }
        } else if (empty($error)) {
            $error = 'Please fill in all required fields';
        }
        
    } elseif ($_POST['action'] == 'edit_training') {
        $training_id = $_POST['training_id'] ?? 0;
        $title = $_POST['title'] ?? '';
        $description = $_POST['description'] ?? '';
        $category = $_POST['category'] ?? '';
        
        if (!$training_id || !$title || !$category) {
            $error = 'Please fill in all required fields';
        } else {
            try {
                // Check if user has permission to edit
                $stmt = $pdo->prepare("SELECT uploaded_by, file_path, content_type, content_url FROM trainings WHERE id = ? AND team_id = ?");
                $stmt->execute([$training_id, $current_user['team_id']]);
                $training = $stmt->fetch();
                
                if (!$training) {
                    throw new Exception('Training not found');
                }
                
                if ($current_user['role'] != 'team_leader' && $training['uploaded_by'] != $current_user['id']) {
                    throw new Exception('You do not have permission to edit this training');
                }
                
                $file_path = $training['file_path'];
                $content_url = $training['content_url'];
                
                // Handle file upload if new file is provided for file type
                if ($training['content_type'] == 'file' && isset($_FILES['training_file']) && $_FILES['training_file']['error'] == 0) {
                    $upload_dir = 'uploads/training/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    
                    // Delete old file if exists
                    if ($file_path && file_exists($file_path)) {
                        unlink($file_path);
                    }
                    
                    $file_extension = pathinfo($_FILES['training_file']['name'], PATHINFO_EXTENSION);
                    $file_name = time() . '_' . uniqid() . '.' . $file_extension;
                    $target_file = $upload_dir . $file_name;
                    
                    if (move_uploaded_file($_FILES['training_file']['tmp_name'], $target_file)) {
                        $file_path = $target_file;
                    } else {
                        throw new Exception('Failed to upload file');
                    }
                }
                
                // Handle URL update for link types
                if (($training['content_type'] == 'video_link' || $training['content_type'] == 'link') && isset($_POST['content_url'])) {
                    $content_url = $_POST['content_url'];
                }
                
                // Update training
                $stmt = $pdo->prepare("UPDATE trainings SET title = ?, description = ?, category = ?, file_path = ?, content_url = ?, updated_by = ? WHERE id = ?");
                $stmt->execute([$title, $description, $category, $file_path, $content_url, $current_user['id'], $training_id]);
                
                $_SESSION['success_message'] = "Training updated successfully!";
                header("Location: view_training.php?id=" . $training_id);
                exit();
                
            } catch (Exception $e) {
                $error = 'Failed to update training: ' . $e->getMessage();
            }
        }
        
    } elseif ($_POST['action'] == 'delete_training') {
        $training_id = $_POST['training_id'] ?? 0;
        
        // Get file path to delete the file
        $stmt = $pdo->prepare("SELECT file_path FROM trainings WHERE id = ? AND team_id = ?");
        $stmt->execute([$training_id, $current_user['team_id']]);
        $training = $stmt->fetch();
        
        if ($training && $training['file_path'] && file_exists($training['file_path'])) {
            unlink($training['file_path']); // Delete the file
        }
        
        $stmt = $pdo->prepare("DELETE FROM trainings WHERE id = ? AND team_id = ?");
        $stmt->execute([$training_id, $current_user['team_id']]);
        
        $_SESSION['success_message'] = "Training deleted successfully!";
        header("Location: training.php");
        exit();
    }
}

// Get session messages
if (isset($_SESSION['success_message'])) {
    $message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Get trainings
$stmt = $pdo->prepare("SELECT t.*, u.name as uploader_name 
                       FROM trainings t 
                       JOIN users u ON t.uploaded_by = u.id 
                       WHERE t.team_id = ? 
                       ORDER BY t.created_at DESC");
$stmt->execute([$current_user['team_id']]);
$trainings = $stmt->fetchAll();

// Group trainings by category
$categories = [
    'getting_started' => ['label' => 'Getting Started', 'icon' => '🚀', 'color' => '#3b82f6'],
    'product' => ['label' => 'Product Training', 'icon' => '📦', 'color' => '#8b5cf6'],
    'recruitment' => ['label' => 'Recruitment', 'icon' => '🤝', 'color' => '#ec4899'],
    'leadership' => ['label' => 'Leadership', 'icon' => '👑', 'color' => '#f59e0b']
];

$grouped_trainings = [];
foreach ($categories as $key => $info) {
    $grouped_trainings[$key] = array_filter($trainings, function($t) use ($key) {
        return $t['category'] == $key;
    });
}

// Statistics
$total_trainings = count($trainings);
$video_count = count(array_filter($trainings, function($t) { return $t['content_type'] == 'video_link'; }));
$file_count = count(array_filter($trainings, function($t) { return $t['content_type'] == 'file'; }));
$link_count = count(array_filter($trainings, function($t) { return $t['content_type'] == 'link'; }));
$this_month = count(array_filter($trainings, function($t) { 
    return date('Y-m', strtotime($t['created_at'])) == date('Y-m'); 
}));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Training - LeaderDesk</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f5f5f5;
            color: #1a1a1a;
            line-height: 1.5;
        }

        .app {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 280px;
            background: white;
            border-right: 1px solid #eaeaea;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            padding: 32px 24px;
        }

        .sidebar-logo {
            font-size: 24px;
            font-weight: 700;
            letter-spacing: -0.5px;
            margin-bottom: 40px;
            padding-bottom: 24px;
            border-bottom: 1px solid #eaeaea;
        }

        .sidebar-logo span {
            background: #f0f0f0;
            padding: 4px 12px;
            border-radius: 40px;
            margin-left: 8px;
            font-size: 14px;
        }

        .nav-menu {
            list-style: none;
        }

        .nav-item {
            margin-bottom: 8px;
            position: relative;
        }

        .nav-item a {
            display: flex;
            align-items: center;
            padding: 12px 16px;
            color: #4a4a4a;
            text-decoration: none;
            border-radius: 12px;
            transition: all 0.2s;
            gap: 12px;
            font-weight: 500;
        }

        .nav-item a:hover {
            background: #f5f5f5;
            color: #1a1a1a;
        }

        .nav-item.active a {
            background: #1a1a1a;
            color: white;
        }

        .nav-icon {
            font-size: 20px;
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

        .main {
            flex: 1;
            margin-left: 280px;
            padding: 32px;
        }

        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 32px;
            padding-bottom: 24px;
            border-bottom: 1px solid #eaeaea;
        }

        .page-title h1 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .page-title p {
            color: #666;
            font-size: 15px;
        }

        .header-actions {
            display: flex;
            gap: 12px;
        }

        .btn {
            padding: 12px 24px;
            border-radius: 100px;
            font-weight: 600;
            font-size: 14px;
            text-decoration: none;
            transition: all 0.2s;
            cursor: pointer;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: #1a1a1a;
            color: white;
        }

        .btn-primary:hover {
            background: #333;
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1);
        }

        .btn-outline {
            background: transparent;
            border: 1.5px solid #eaeaea;
            color: #1a1a1a;
        }

        .btn-outline:hover {
            background: #f5f5f5;
        }

        .btn-danger {
            background: #ef4444;
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        .alert {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 24px;
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            animation: slideDown 0.3s ease-out;
        }

        .alert-success {
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .alert-error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .alert-close {
            background: none;
            border: none;
            font-size: 18px;
            cursor: pointer;
            color: inherit;
            opacity: 0.5;
        }

        .alert-close:hover {
            opacity: 1;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: white;
            padding: 24px;
            border-radius: 16px;
            border: 1px solid #eaeaea;
        }

        .stat-label {
            color: #666;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 4px;
        }

        .stat-sub {
            font-size: 13px;
            color: #888;
        }

        .category-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 32px;
            flex-wrap: wrap;
        }

        .category-tab {
            padding: 10px 20px;
            border-radius: 100px;
            border: 1px solid #eaeaea;
            background: white;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .category-tab:hover {
            background: #f5f5f5;
        }

        .category-tab.active {
            background: #1a1a1a;
            color: white;
            border-color: #1a1a1a;
        }

        .training-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }

        .training-card {
            background: white;
            border-radius: 20px;
            border: 1px solid #eaeaea;
            overflow: hidden;
            transition: all 0.2s;
            animation: fadeIn 0.3s ease-out;
            cursor: pointer;
        }

        .training-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.05);
        }

        .training-header {
            padding: 20px;
            border-bottom: 1px solid #eaeaea;
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .training-icon {
            width: 50px;
            height: 50px;
            background: #f5f5f5;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        .training-info {
            flex: 1;
        }

        .training-category {
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #666;
            margin-bottom: 4px;
        }

        .training-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .training-meta {
            font-size: 12px;
            color: #888;
        }

        .training-body {
            padding: 20px;
        }

        .training-description {
            color: #666;
            font-size: 14px;
            margin-bottom: 16px;
            line-height: 1.6;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .training-type {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 12px;
            background: #f5f5f5;
            border-radius: 100px;
            font-size: 12px;
            margin-bottom: 16px;
        }

        .training-actions {
            display: flex;
            gap: 12px;
            margin-top: 16px;
        }

        .btn-view {
            flex: 1;
            padding: 12px;
            background: #1a1a1a;
            color: white;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            font-size: 14px;
            transition: all 0.2s;
            display: inline-block;
        }

        .btn-view:hover {
            background: #333;
        }

        .btn-delete {
            padding: 12px 16px;
            background: #fef2f2;
            border: none;
            border-radius: 12px;
            color: #991b1b;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.2s;
        }

        .btn-delete:hover {
            background: #fee2e2;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 20px;
            border: 1px solid #eaeaea;
        }

        .empty-state span {
            font-size: 48px;
            display: block;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        .empty-state h3 {
            font-size: 20px;
            margin-bottom: 8px;
        }

        .empty-state p {
            color: #888;
            margin-bottom: 24px;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 24px;
            padding: 32px;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            animation: modalSlideIn 0.3s ease-out;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .modal-header h2 {
            font-size: 24px;
            font-weight: 700;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #888;
        }

        .modal-close:hover {
            color: #1a1a1a;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 8px;
            color: #1a1a1a;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-input,
        .form-select,
        .form-textarea {
            width: 100%;
            padding: 12px 16px;
            border: 1.5px solid #eaeaea;
            border-radius: 12px;
            font-size: 15px;
            transition: all 0.2s;
            font-family: 'Inter', sans-serif;
        }

        .form-input:focus,
        .form-select:focus,
        .form-textarea:focus {
            outline: none;
            border-color: #1a1a1a;
        }

        .form-textarea {
            min-height: 100px;
            resize: vertical;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid #eaeaea;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 1024px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                z-index: 1000;
                transition: transform 0.3s;
            }

            .sidebar.open {
                transform: translateX(0);
            }

            .main {
                margin-left: 0;
                padding: 20px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .training-grid {
                grid-template-columns: 1fr;
            }

            .category-tabs {
                flex-wrap: nowrap;
                overflow-x: auto;
                padding-bottom: 8px;
            }

            .category-tab {
                white-space: nowrap;
            }

            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="app">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-logo">
                LeaderDesk<span>.co</span>
            </div>
            
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="dashboard.php">
                        <span class="nav-icon">📊</span>
                        Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a href="team.php">
                        <span class="nav-icon">👥</span>
                        Team
                    </a>
                </li>
                <li class="nav-item">
                    <a href="prospects.php">
                        <span class="nav-icon">🎯</span>
                        Prospects
                    </a>
                </li>
                <li class="nav-item">
                    <a href="tasks.php">
                        <span class="nav-icon">✅</span>
                        Tasks
                    </a>
                </li>
                <li class="nav-item">
                    <a href="targets.php">
                        <span class="nav-icon">🎯</span>
                        Targets
                    </a>
                </li>
                <li class="nav-item active">
                    <a href="training.php">
                        <span class="nav-icon">📚</span>
                        Training
                    </a>
                </li>
                <li class="nav-item">
                    <a href="events.php">
                        <span class="nav-icon">📅</span>
                        Events
                    </a>
                </li>
                <li class="nav-item">
                    <a href="leaderboard.php">
                        <span class="nav-icon">🏆</span>
                        Leaderboard
                    </a>
                </li>
                <li class="nav-item">
                    <a href="notifications.php">
                        <span class="nav-icon">🔔</span>
                        Notifications
                        <span class="nav-badge" id="sideNotificationCount" style="display: none;"></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="profile.php">
                        <span class="nav-icon">👤</span>
                        Profile
                    </a>
                </li>
                
                <?php if (isset($current_user) && $current_user['role'] == 'super_admin'): ?>
                    <li class="nav-item" style="margin-top: 20px; border-top: 1px solid #eaeaea; padding-top: 20px;">
                        <a href="admin_dashboard.php">
                            <span class="nav-icon">⚡</span>
                            Admin Panel
                        </a>
                    </li>
                <?php endif; ?>
                
                <li class="nav-item" style="margin-top: 20px; border-top: 1px solid #eaeaea; padding-top: 20px;">
                    <a href="logout.php" style="color: #ef4444;">
                        <span class="nav-icon">🚪</span>
                        Logout
                    </a>
                </li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main">
            <!-- Top Bar -->
            <div class="top-bar">
                <div class="page-title">
                    <h1>Training Center</h1>
                    <p>Access training materials and resources</p>
                </div>
                
                <?php if ($current_user['role'] == 'team_leader'): ?>
                    <div class="header-actions">
                        <button class="btn btn-primary" onclick="openModal('uploadTrainingModal')">
                            <span>+</span> Upload Training
                        </button>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Alert Messages -->
            <?php if ($message): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($message); ?>
                    <button class="alert-close" onclick="this.parentElement.remove()">×</button>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <?php echo htmlspecialchars($error); ?>
                    <button class="alert-close" onclick="this.parentElement.remove()">×</button>
                </div>
            <?php endif; ?>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">Total Materials</div>
                    <div class="stat-value"><?php echo $total_trainings; ?></div>
                    <div class="stat-sub">Training items</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-label">Videos</div>
                    <div class="stat-value"><?php echo $video_count; ?></div>
                    <div class="stat-sub">Video training</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-label">Documents</div>
                    <div class="stat-value"><?php echo $file_count; ?></div>
                    <div class="stat-sub">Files to download</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-label">Links</div>
                    <div class="stat-value"><?php echo $link_count; ?></div>
                    <div class="stat-sub">Web resources</div>
                </div>
            </div>

            <!-- Category Tabs -->
            <div class="category-tabs">
                <button class="category-tab active" onclick="filterCategory('all')">📚 All Materials</button>
                <?php foreach ($categories as $key => $info): ?>
                    <button class="category-tab" onclick="filterCategory('<?php echo $key; ?>')">
                        <?php echo $info['icon']; ?> <?php echo $info['label']; ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <!-- Training Grid -->
            <?php if (empty($trainings)): ?>
                <div class="empty-state">
                    <span>📚</span>
                    <h3>No training materials yet</h3>
                    <p>Upload your first training material to get started</p>
                    <?php if ($current_user['role'] == 'team_leader'): ?>
                        <button class="btn btn-primary" onclick="openModal('uploadTrainingModal')">Upload Training</button>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="training-grid" id="trainingGrid">
                    <?php foreach ($trainings as $training): 
                        $icon = '📄';
                        if ($training['content_type'] == 'video_link') $icon = '🎥';
                        elseif ($training['content_type'] == 'file') {
                            $ext = pathinfo($training['file_path'], PATHINFO_EXTENSION);
                            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) $icon = '🖼️';
                            elseif ($ext == 'pdf') $icon = '📕';
                            elseif (in_array($ext, ['doc', 'docx'])) $icon = '📘';
                            elseif (in_array($ext, ['ppt', 'pptx'])) $icon = '📊';
                            else $icon = '📁';
                        }
                        elseif ($training['content_type'] == 'link') $icon = '🔗';
                        
                        $category_info = $categories[$training['category']] ?? ['label' => 'Other', 'icon' => '📁'];
                    ?>
                        <div class="training-card" data-category="<?php echo $training['category']; ?>" onclick="window.location.href='view_training.php?id=<?php echo $training['id']; ?>'">
                            <div class="training-header">
                                <div class="training-icon"><?php echo $icon; ?></div>
                                <div class="training-info">
                                    <div class="training-category"><?php echo $category_info['label']; ?></div>
                                    <div class="training-title"><?php echo htmlspecialchars($training['title']); ?></div>
                                    <div class="training-meta">Uploaded by <?php echo htmlspecialchars($training['uploader_name']); ?></div>
                                </div>
                            </div>
                            
                            <div class="training-body">
                                <?php if ($training['description']): ?>
                                    <p class="training-description"><?php echo htmlspecialchars($training['description']); ?></p>
                                <?php endif; ?>
                                
                                <div class="training-type">
                                    <?php 
                                    if ($training['content_type'] == 'video_link') echo '🎥 Video Link';
                                    elseif ($training['content_type'] == 'file') {
                                        $ext = pathinfo($training['file_path'], PATHINFO_EXTENSION);
                                        echo '📁 ' . strtoupper($ext) . ' File';
                                    }
                                    elseif ($training['content_type'] == 'link') echo '🔗 Web Link';
                                    ?>
                                </div>
                                
                                <div class="training-actions" onclick="event.stopPropagation();">
                                    <span class="btn-view" onclick="window.location.href='view_training.php?id=<?php echo $training['id']; ?>'">
                                        View Details →
                                    </span>
                                    
                                    <?php if ($current_user['role'] == 'team_leader' || $training['uploaded_by'] == $current_user['id']): ?>
                                        <button class="btn-delete" onclick="deleteTraining(<?php echo $training['id']; ?>)">🗑️</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Upload Training Modal -->
    <div id="uploadTrainingModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Upload Training Material</h2>
                <button class="modal-close" onclick="closeModal('uploadTrainingModal')">&times;</button>
            </div>
            
            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload_training">
                
                <div class="form-group">
                    <label class="form-label">Title *</label>
                    <input type="text" name="title" class="form-input" required placeholder="e.g., Product Training Video">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-textarea" placeholder="Describe what this training covers..."></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Category *</label>
                    <select name="category" class="form-select" required>
                        <option value="">Select category</option>
                        <?php foreach ($categories as $key => $info): ?>
                            <option value="<?php echo $key; ?>"><?php echo $info['label']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Content Type *</label>
                    <select name="content_type" class="form-select" required id="contentType" onchange="toggleContentFields()">
                        <option value="">Select type</option>
                        <option value="video_link">Video Link (YouTube, Vimeo)</option>
                        <option value="link">Web Link</option>
                        <option value="file">File Upload (PDF, Image, Document)</option>
                    </select>
                </div>
                
                <!-- URL Field (shown for video_link and link) -->
                <div class="form-group" id="urlField" style="display: none;">
                    <label class="form-label" id="urlLabel">URL</label>
                    <input type="url" name="content_url" class="form-input" placeholder="https://...">
                </div>
                
                <!-- File Upload Field (shown for file type) -->
                <div class="form-group" id="fileField" style="display: none;">
                    <label class="form-label">Upload File</label>
                    <input type="file" name="training_file" class="form-input" accept=".pdf,.doc,.docx,.ppt,.pptx,.jpg,.jpeg,.png,.gif,.mp4">
                    <small style="color: #666;">Supported formats: PDF, Word, PowerPoint, Images, MP4</small>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal('uploadTrainingModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Upload Training</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteTrainingModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Delete Training</h2>
                <button class="modal-close" onclick="closeModal('deleteTrainingModal')">&times;</button>
            </div>
            
            <p style="margin: 20px 0;">Are you sure you want to delete this training material? This action cannot be undone.</p>
            
            <form method="POST" action="" id="deleteTrainingForm">
                <input type="hidden" name="action" value="delete_training">
                <input type="hidden" name="training_id" id="delete_training_id">
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal('deleteTrainingModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="background: #dc2626;">Delete Training</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('show');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
        }

        function toggleContentFields() {
            const type = document.getElementById('contentType').value;
            const urlField = document.getElementById('urlField');
            const fileField = document.getElementById('fileField');
            
            urlField.style.display = 'none';
            fileField.style.display = 'none';
            
            if (type === 'video_link' || type === 'link') {
                urlField.style.display = 'block';
                document.getElementById('urlLabel').textContent = type === 'video_link' ? 'Video URL' : 'Link URL';
            } else if (type === 'file') {
                fileField.style.display = 'block';
            }
        }

        function filterCategory(category) {
            document.querySelectorAll('.category-tab').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');
            
            const cards = document.querySelectorAll('.training-card');
            cards.forEach(card => {
                if (category === 'all' || card.dataset.category === category) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        function deleteTraining(trainingId) {
            if (confirm('Are you sure you want to delete this training material? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_training">
                    <input type="hidden" name="training_id" value="${trainingId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function updateNotificationBadges() {
            fetch('ajax/get_notifications.php')
                .then(response => response.json())
                .then(data => {
                    const sideBadge = document.getElementById('sideNotificationCount');
                    if (sideBadge) {
                        if (data.unread_count > 0) {
                            sideBadge.textContent = data.unread_count;
                            sideBadge.style.display = 'inline';
                        } else {
                            sideBadge.textContent = '';
                            sideBadge.style.display = 'none';
                        }
                    }
                })
                .catch(error => console.error('Error updating badges:', error));
        }

        document.addEventListener('DOMContentLoaded', function() {
            updateNotificationBadges();
            setInterval(updateNotificationBadges, 30000);
        });

        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('show');
            }
        }

        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(alert => {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 300);
            });
        }, 5000);
    </script>
</body>
</html>