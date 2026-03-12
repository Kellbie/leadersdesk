<?php
$page_title = "Page Not Found";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Page Not Found | LeaderDesk</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #1a1a1a;
            line-height: 1.5;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .error-container {
            max-width: 600px;
            width: 100%;
            background: white;
            border-radius: 32px;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
            overflow: hidden;
            text-align: center;
            animation: slideUp 0.5s ease-out;
        }

        .error-header {
            background: #1a1a1a;
            color: white;
            padding: 60px 40px 40px;
            position: relative;
        }

        .error-header::before {
            content: '404';
            position: absolute;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 120px;
            font-weight: 800;
            opacity: 0.1;
            color: white;
            pointer-events: none;
        }

        .error-icon {
            font-size: 80px;
            margin-bottom: 20px;
            position: relative;
            z-index: 1;
        }

        .error-header h1 {
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 10px;
            position: relative;
            z-index: 1;
        }

        .error-header p {
            opacity: 0.8;
            font-size: 16px;
            position: relative;
            z-index: 1;
        }

        .error-content {
            padding: 40px;
        }

        .error-message {
            font-size: 18px;
            color: #4a4a4a;
            margin-bottom: 30px;
        }

        .suggestions {
            background: #f8f9fa;
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            text-align: left;
        }

        .suggestions h3 {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
            color: #1a1a1a;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .suggestions ul {
            list-style: none;
        }

        .suggestions li {
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 12px;
            color: #666;
        }

        .suggestions li span {
            font-size: 20px;
        }

        .suggestions li a {
            color: #1a1a1a;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s;
        }

        .suggestions li a:hover {
            color: #667eea;
        }

        .action-buttons {
            display: flex;
            gap: 16px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            padding: 14px 32px;
            border-radius: 100px;
            font-weight: 600;
            font-size: 15px;
            text-decoration: none;
            transition: all 0.2s;
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

        .btn-secondary {
            background: transparent;
            border: 1.5px solid #eaeaea;
            color: #1a1a1a;
        }

        .btn-secondary:hover {
            background: #f5f5f5;
        }

        .search-box {
            margin-top: 30px;
            padding-top: 30px;
            border-top: 1px solid #eaeaea;
        }

        .search-form {
            display: flex;
            gap: 10px;
            max-width: 400px;
            margin: 15px auto 0;
        }

        .search-input {
            flex: 1;
            padding: 12px 16px;
            border: 1.5px solid #eaeaea;
            border-radius: 100px;
            font-size: 14px;
            font-family: 'Inter', sans-serif;
        }

        .search-input:focus {
            outline: none;
            border-color: #1a1a1a;
        }

        .search-btn {
            padding: 12px 24px;
            background: #1a1a1a;
            color: white;
            border: none;
            border-radius: 100px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .search-btn:hover {
            background: #333;
        }

        .error-footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eaeaea;
            color: #888;
            font-size: 13px;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 640px) {
            .error-header {
                padding: 40px 20px 30px;
            }

            .error-header::before {
                font-size: 80px;
            }

            .error-content {
                padding: 30px 20px;
            }

            .action-buttons {
                flex-direction: column;
            }

            .search-form {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-header">
            <div class="error-icon">🔍</div>
            <h1>Page Not Found</h1>
            <p>The page you're looking for doesn't exist or has been moved</p>
        </div>
        
        <div class="error-content">
            <div class="error-message">
                Sorry, we couldn't find the page you requested. This might be because:
            </div>
            
            <div class="suggestions">
                <h3>
                    <span>📍</span>
                    You might want to try:
                </h3>
                <ul>
                    <li>
                        <span>📊</span>
                        <a href="dashboard.php">Go to your Dashboard</a> - Your main command center
                    </li>
                    <li>
                        <span>👥</span>
                        <a href="team.php">Manage your Team</a> - View and manage team members
                    </li>
                    <li>
                        <span>🎯</span>
                        <a href="prospects.php">Check your Prospects</a> - Track your pipeline
                    </li>
                    <li>
                        <span>✅</span>
                        <a href="tasks.php">View your Tasks</a> - See pending tasks
                    </li>
                </ul>
            </div>
            
            <div class="action-buttons">
                <a href="dashboard.php" class="btn btn-primary">
                    <span>🏠</span>
                    Go to Dashboard
                </a>
                <a href="javascript:history.back()" class="btn btn-secondary">
                    <span>←</span>
                    Go Back
                </a>
            </div>
            
            <div class="search-box">
                <p style="color: #666; margin-bottom: 10px;">Looking for something specific?</p>
                <form class="search-form" action="search.php" method="GET">
                    <input type="text" name="q" class="search-input" placeholder="Search..." required>
                    <button type="submit" class="search-btn">Search</button>
                </form>
            </div>
            
            <div class="error-footer">
                <p>If you believe this is a mistake, please contact support</p>
                <p style="margin-top: 5px;">
                    <a href="mailto:support@leaderdesk.com" style="color: #1a1a1a;">support@leaderdesk.com</a>
                </p>
            </div>
        </div>
    </div>
</body>
</html>