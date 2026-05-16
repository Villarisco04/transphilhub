<?php
// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/csrf.php';
require_once 'includes/db.php';

// Check for session timeout message
if(isset($_GET['timeout']) && $_GET['timeout'] == 1){
    $error = "Your session has expired. Please login again.";
}

// Logout success message
$logout_message = '';
if(isset($_GET['logout']) && $_GET['logout'] == 'success'){
    $logout_message = "You have been successfully logged out.";
}

$error = '';
$success = '';

// Handle registration success
if(isset($_GET['registered']) && $_GET['registered'] == 1){
    $success = "Registration successful! You can now login.";
}

// Handle login
if(isset($_POST['login'])){
    if(!isset($_POST['csrf_token']) || !CSRFToken::verify($_POST['csrf_token'])){
        $error = "Security validation failed. Please refresh the page and try again.";
    } else {
        $email = trim($_POST['email']);
        $password = trim($_POST['password']);

        if(empty($email) || empty($password)){
            $error = "Please fill in all fields.";
        } else {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if($user){
                if(password_verify($password, $user['password'])){
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['last_activity'] = time();  // ✅ ADD THIS LINE - Session timeout

                    if($user['role'] == 'admin'){
                        header("Location: admin/dashboard.php");
                        exit;
                    }
                    if($user['role'] == 'agent'){
                        header("Location: agent/dashboard.php");
                        exit;
                    }
                    if($user['role'] == 'client'){
                        header("Location: client/dashboard.php");
                        exit;
                    }
                } else {
                    $error = "Invalid password.";
                }
            } else {
                $error = "User not found.";
            }
        }
    }
}

$csrf_token = CSRFToken::generate();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Trans-Phil House Hub</title>
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
            background: #f5f4f0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-container {
            width: 100%;
            max-width: 1200px;
            background: #ffffff;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.08);
            display: flex;
            flex-wrap: wrap;
        }

        .brand-panel {
            flex: 1;
            min-width: 280px;
            background: linear-gradient(135deg, #1a3a6b 0%, #22508a 100%);
            padding: 60px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        .brand-panel::before {
            content: '';
            position: absolute;
            top: -50px;
            right: -50px;
            width: 200px;
            height: 200px;
            background: rgba(45, 177, 43, 0.08);
            border-radius: 50%;
        }

        .brand-panel::after {
            content: '';
            position: absolute;
            bottom: -30px;
            left: -30px;
            width: 150px;
            height: 150px;
            background: rgba(240, 120, 0, 0.08);
            border-radius: 50%;
        }

        .logo-section {
            text-align: center;
            margin-bottom: 20px;
            position: relative;
            z-index: 2;
        }

        .logo-img {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 20px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }

        .brand-panel h1 {
            font-size: 28px;
            color: #ffffff;
            margin-bottom: 10px;
            font-weight: 700;
        }

        .brand-panel h1 span {
            color: #f07800;
        }

        .brand-panel p {
            color: rgba(255, 255, 255, 0.75);
            font-size: 14px;
            line-height: 1.6;
        }

        .form-panel {
            flex: 1;
            min-width: 380px;
            padding: 60px 50px;
            background: #ffffff;
        }

        .form-header {
            text-align: center;
            margin-bottom: 35px;
        }

        .form-header h2 {
            font-size: 32px;
            color: #1a3a6b;
            margin-bottom: 10px;
            font-weight: 700;
        }

        .form-header p {
            color: #6b7280;
            font-size: 14px;
        }

        .alert-error {
            background: #fee2e2;
            border-left: 4px solid #dc2626;
            color: #991b1b;
            padding: 14px 16px;
            border-radius: 10px;
            margin-bottom: 25px;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background: #e6f7e6;
            border-left: 4px solid #2db12b;
            color: #166534;
            padding: 14px 16px;
            border-radius: 10px;
            margin-bottom: 25px;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-logout {
            background: #e3f2fd;
            border-left: 4px solid #1a3a6b;
            color: #1a3a6b;
            padding: 14px 16px;
            border-radius: 10px;
            margin-bottom: 25px;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-group {
            margin-bottom: 24px;
        }

        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 8px;
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            font-size: 16px;
        }

        .form-group input {
            width: 100%;
            padding: 14px 14px 14px 42px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 14px;
            font-family: 'DM Sans', sans-serif;
            transition: all 0.3s ease;
            outline: none;
            background: #faf9f6;
        }

        .form-group input:focus {
            border-color: #2db12b;
            background: #ffffff;
        }

        .forgot-link {
            text-align: right;
            margin-bottom: 25px;
        }

        .forgot-link a {
            color: #f07800;
            font-size: 13px;
            text-decoration: none;
            font-weight: 500;
        }

        .forgot-link a:hover {
            color: #d86d00;
        }

        .login-btn {
            width: 100%;
            background: #f07800;
            color: #ffffff;
            border: none;
            padding: 14px;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: 'DM Sans', sans-serif;
            margin-bottom: 20px;
        }

        .login-btn:hover {
            background: #d86d00;
            transform: translateY(-2px);
        }

        .register-link {
            text-align: center;
            font-size: 13px;
            color: #6b7280;
        }

        .register-link a {
            color: #1a3a6b;
            text-decoration: none;
            font-weight: 600;
        }

        .register-link a:hover {
            color: #2db12b;
        }

        @media (max-width: 768px) {
            .brand-panel {
                padding: 40px 30px;
            }
            .form-panel {
                padding: 40px 30px;
                min-width: 300px;
            }
            .form-header h2 {
                font-size: 26px;
            }
        }
    </style>
</head>
<body>

<div class="login-container">
    <div class="brand-panel">
        <div class="logo-section">
            <img src="assets/images/logo.jpg" alt="Trans-Phil Logo" class="logo-img">
            <h1>Trans-Phil <span>House Hub</span></h1>
            <p>Your trusted partner in finding the perfect property</p>
        </div>
    </div>

    <div class="form-panel">
        <div class="form-header">
            <h2>Welcome Back</h2>
            <p>Login to access your Trans-Phil account</p>
        </div>

        <?php if($logout_message): ?>
            <div class="alert-logout">
                <i class="fas fa-check-circle"></i>
                <?php echo $logout_message; ?>
            </div>
        <?php endif; ?>

        <?php if($error): ?>
            <div class="alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if($success): ?>
            <div class="alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            
            <div class="form-group">
                <label>Email Address</label>
                <div class="input-wrapper">
                    <i class="fas fa-envelope"></i>
                    <input type="email" name="email" placeholder="Enter your email" required autocomplete="email">
                </div>
            </div>

            <div class="form-group">
                <label>Password</label>
                <div class="input-wrapper">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="password" placeholder="Enter your password" required autocomplete="current-password">
                </div>
            </div>

            <div class="forgot-link">
                <a href="forgot_password.php">Forgot Password?</a>
            </div>

            <button type="submit" name="login" class="login-btn">
                <i class="fas fa-arrow-right-to-bracket"></i> Sign In
            </button>

            <div class="register-link">
                Don't have an account? <a href="register.php">Create an account</a>
            </div>
        </form>
    </div>
</div>

</body>
</html>