<?php
require_once 'includes/db.php';
session_start();

// Clear all session variables
$_SESSION = array();

// Destroy the session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-3600, '/');
}

// Destroy the session
session_destroy();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logout - Trans-Phil House Hub</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DM Sans', sans-serif;
            background: linear-gradient(135deg, #f0eff5 0%, #e8e4dc 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .logout-container {
            max-width: 500px;
            width: 100%;
            background: #ffffff;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
            text-align: center;
            animation: fadeIn 0.5s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .logout-header {
            background: linear-gradient(135deg, #1a3a6b 0%, #22508a 100%);
            padding: 40px 30px;
            color: white;
        }

        .logout-icon {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            border: 2px solid rgba(255, 255, 255, 0.3);
        }

        .logout-icon i {
            font-size: 40px;
            color: #f07800;
        }

        .logout-header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }

        .logout-header p {
            opacity: 0.8;
            font-size: 14px;
        }

        .logout-body {
            padding: 40px 30px;
        }

        .logout-message {
            background: #e3f2fd;
            border-left: 4px solid #1a3a6b;
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 30px;
            text-align: left;
            display: flex;
            align-items: center;
            gap: 12px;
            color: #1a3a6b;
            font-size: 14px;
        }

        .btn-group {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn-primary {
            background: linear-gradient(135deg, #f07800, #d86d00);
            color: white;
            padding: 12px 28px;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(240, 120, 0, 0.3);
        }

        .btn-secondary {
            background: transparent;
            color: #1a3a6b;
            padding: 12px 28px;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 600;
            border: 2px solid #e5e7eb;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-secondary:hover {
            border-color: #f07800;
            color: #f07800;
        }

        .redirect-timer {
            margin-top: 25px;
            font-size: 12px;
            color: #9ca3af;
        }

        .redirect-timer span {
            font-weight: 700;
            color: #f07800;
        }
    </style>
</head>
<body>

<div class="logout-container">
    <div class="logout-header">
        <div class="logout-icon">
            <i class="fas fa-sign-out-alt"></i>
        </div>
        <h1>Logged Out Successfully</h1>
        <p>You have been securely logged out of your account</p>
    </div>

    <div class="logout-body">
        <div class="logout-message">
            <i class="fas fa-check-circle" style="font-size: 18px;"></i>
            Thank you for using Trans-Phil House Hub. Come back soon!
        </div>

        <div class="btn-group">
            <a href="login.php" class="btn-primary">
                <i class="fas fa-arrow-right-to-bracket"></i> Login Again
            </a>
            <a href="index.php" class="btn-secondary">
                <i class="fas fa-home"></i> Return to Home
            </a>
        </div>

        <div class="redirect-timer">
            Redirecting to <a href="login.php" style="color: #f07800;">login page</a> in <span id="countdown">5</span> seconds...
        </div>
    </div>
</div>

<script>
    // Auto-redirect countdown
    let seconds = 5;
    const countdownEl = document.getElementById('countdown');
    
    const interval = setInterval(function() {
        seconds--;
        countdownEl.textContent = seconds;
        
        if (seconds <= 0) {
            clearInterval(interval);
            window.location.href = 'login.php';
        }
    }, 1000);
</script>

</body>
</html>