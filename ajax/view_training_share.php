<?php
require_once 'config/database.php';
require_once 'includes/auth_check.php';

$share_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$share_id) {
    header("Location: notifications.php");
    exit();
}

// Get the share details
$stmt = $pdo->prepare("
    SELECT ts.*, 
           p.name as prospect_name, 
           p.id as prospect_id,
           t.title as training_title,
           t.description as training_description,
           t.content_type,
           t.file_path,
           t.content_url,
           u.name as shared_by_name
    FROM training_shares ts
    JOIN prospects p ON ts.prospect_id = p.id
    JOIN trainings t ON ts.training_id = t.id
    JOIN users u ON ts.shared_by = u.id
    WHERE ts.id = ? AND p.team_id = ?
");
$stmt->execute([$share_id, $current_user['team_id']]);
$share = $stmt->fetch();

if (!$share) {
    header("Location: notifications.php");
    exit();
}

// Check if user has permission to view this (must be the prospect owner or team leader)
if ($current_user['role'] != 'team_leader' && $share['user_id'] != $current_user['id']) {
    header("Location: notifications.php");
    exit();
}

// Mark as viewed if not already
if (!$share['viewed_at']) {
    $stmt = $pdo->prepare("UPDATE training_shares SET viewed_at = NOW() WHERE id = ?");
    $stmt->execute([$share_id]);
}

$page_title = "Shared Training - " . $share['training_title'];
?>

<?php include 'includes/header.php'; ?>

<div class="shared-training-page">
    <div class="training-container">
        <div class="training-header">
            <h1><?php echo htmlspecialchars($share['training_title']); ?></h1>
            <p class="shared-info">
                Shared by <?php echo htmlspecialchars($share['shared_by_name']); ?> 
                with prospect <?php echo htmlspecialchars($share['prospect_name']); ?>
                on <?php echo date('F j, Y g:i A', strtotime($share['created_at'])); ?>
            </p>
        </div>
        
        <?php if ($share['message']): ?>
            <div class="message-box">
                <h3>Message:</h3>
                <p><?php echo nl2br(htmlspecialchars($share['message'])); ?></p>
            </div>
        <?php endif; ?>
        
        <div class="training-content">
            <?php if ($share['content_type'] == 'file' && $share['file_path']): ?>
                <div class="file-preview">
                    <?php
                    $ext = pathinfo($share['file_path'], PATHINFO_EXTENSION);
                    $is_image = in_array($ext, ['jpg', 'jpeg', 'png', 'gif']);
                    $is_pdf = $ext == 'pdf';
                    ?>
                    
                    <?php if ($is_image): ?>
                        <div class="image-preview">
                            <img src="<?php echo htmlspecialchars($share['file_path']); ?>" alt="<?php echo htmlspecialchars($share['training_title']); ?>">
                        </div>
                    <?php elseif ($is_pdf): ?>
                        <div class="pdf-preview">
                            <embed src="<?php echo htmlspecialchars($share['file_path']); ?>" type="application/pdf" width="100%" height="600px" />
                        </div>
                    <?php else: ?>
                        <div class="file-info">
                            <p>File type: <?php echo strtoupper($ext); ?></p>
                            <p>Size: <?php 
                                $size = filesize($share['file_path']);
                                echo round($size / 1024 / 1024, 2) . ' MB';
                            ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <div class="download-section">
                        <a href="<?php echo htmlspecialchars($share['file_path']); ?>" download class="btn-download" onclick="trackDownload(<?php echo $share['id']; ?>)">
                            ⬇️ Download File
                        </a>
                    </div>
                </div>
            <?php elseif ($share['content_type'] == 'video_link'): ?>
                <div class="video-preview">
                    <iframe width="100%" height="400" src="<?php echo htmlspecialchars($share['content_url']); ?>" frameborder="0" allowfullscreen></iframe>
                </div>
            <?php elseif ($share['content_type'] == 'link'): ?>
                <div class="link-preview">
                    <a href="<?php echo htmlspecialchars($share['content_url']); ?>" target="_blank" class="btn-view">
                        🔗 Open Link in New Tab
                    </a>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="training-footer">
            <a href="notifications.php" class="btn btn-outline">← Back to Notifications</a>
            <?php if ($share['content_type'] == 'file'): ?>
                <a href="<?php echo htmlspecialchars($share['file_path']); ?>" download class="btn-download" onclick="trackDownload(<?php echo $share['id']; ?>)">
                    ⬇️ Download File
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.shared-training-page {
    padding: 32px;
    max-width: 900px;
    margin: 0 auto;
}

.training-container {
    background: white;
    border-radius: 24px;
    border: 1px solid #eaeaea;
    padding: 40px;
    box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05);
}

.training-header {
    margin-bottom: 32px;
    padding-bottom: 24px;
    border-bottom: 2px solid #eaeaea;
}

.training-header h1 {
    font-size: 28px;
    font-weight: 700;
    margin-bottom: 12px;
    color: #1a1a1a;
}

.shared-info {
    color: #666;
    font-size: 14px;
    line-height: 1.6;
}

.message-box {
    background: #fef3c7;
    border: 1px solid #fde68a;
    border-radius: 16px;
    padding: 24px;
    margin-bottom: 32px;
}

.message-box h3 {
    font-size: 16px;
    font-weight: 600;
    margin-bottom: 12px;
    color: #92400e;
}

.message-box p {
    color: #92400e;
    font-size: 15px;
    line-height: 1.6;
}

.training-content {
    margin-bottom: 32px;
}

.image-preview {
    text-align: center;
    background: #f5f5f5;
    padding: 20px;
    border-radius: 12px;
}

.image-preview img {
    max-width: 100%;
    max-height: 500px;
    border-radius: 8px;
}

.pdf-preview {
    background: #f5f5f5;
    padding: 20px;
    border-radius: 12px;
}

.video-preview {
    background: #f5f5f5;
    padding: 20px;
    border-radius: 12px;
}

.link-preview {
    text-align: center;
    padding: 40px;
    background: #f5f5f5;
    border-radius: 12px;
}

.file-info {
    text-align: center;
    padding: 40px;
    background: #f5f5f5;
    border-radius: 12px;
    color: #666;
}

.download-section {
    margin-top: 24px;
    text-align: center;
}

.btn-download {
    display: inline-block;
    padding: 14px 32px;
    background: #10b981;
    color: white;
    text-decoration: none;
    border-radius: 100px;
    font-weight: 600;
    transition: all 0.2s;
    border: none;
    cursor: pointer;
}

.btn-download:hover {
    background: #059669;
    transform: translateY(-2px);
    box-shadow: 0 10px 25px -5px rgba(16, 185, 129, 0.3);
}

.btn-view {
    display: inline-block;
    padding: 14px 32px;
    background: #1a1a1a;
    color: white;
    text-decoration: none;
    border-radius: 100px;
    font-weight: 600;
    transition: all 0.2s;
}

.btn-view:hover {
    background: #333;
    transform: translateY(-2px);
}

.training-footer {
    margin-top: 32px;
    padding-top: 24px;
    border-top: 2px solid #eaeaea;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.btn-outline {
    padding: 12px 24px;
    background: transparent;
    border: 1.5px solid #eaeaea;
    border-radius: 100px;
    color: #1a1a1a;
    text-decoration: none;
    font-weight: 500;
    transition: all 0.2s;
}

.btn-outline:hover {
    background: #f5f5f5;
}

@media (max-width: 768px) {
    .shared-training-page {
        padding: 16px;
    }
    
    .training-container {
        padding: 24px;
    }
    
    .training-header h1 {
        font-size: 24px;
    }
    
    .training-footer {
        flex-direction: column;
        gap: 12px;
    }
    
    .btn-download,
    .btn-view,
    .btn-outline {
        width: 100%;
        text-align: center;
    }
}
</style>

<script>
function trackDownload(shareId) {
    fetch('ajax/track_download.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'share_id=' + shareId
    });
}
</script>

<?php include 'includes/footer.php'; ?>