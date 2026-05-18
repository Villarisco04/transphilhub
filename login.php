<?php
// Set timezone FIRST
date_default_timezone_set('Asia/Manila');

if(session_status() === PHP_SESSION_NONE){
    session_start();
}

require_once 'includes/csrf.php';
require_once 'includes/db.php';

// ── Messages ──
$error         = '';
$success       = '';
$logout_message = '';

if(isset($_GET['timeout']) && $_GET['timeout'] == 1)
    $error = "Your session has expired. Please login again.";

if(isset($_GET['logout']) && $_GET['logout'] == 'success')
    $logout_message = "You have been successfully logged out.";

if(isset($_GET['registered']) && $_GET['registered'] == 1)
    $success = "Registration successful! You can now login.";

if(isset($_GET['reset']) && $_GET['reset'] == 'success')
    $success = "Password reset successfully! You can now login.";

// ── Function to check if email is trusted ──
function is_email_trusted($email, $user_id, $pdo) {
    // Check database for valid trust token
    $stmt = $pdo->prepare("
        SELECT * FROM trusted_emails 
        WHERE user_id = ? AND email = ? AND expires_at > NOW()
    ");
    $stmt->execute([$user_id, $email]);
    
    if($stmt->fetch()){
        return true;
    }
    return false;
}

function create_trusted_email($email, $user_id, $pdo) {
    $trust_token = hash('sha256', $email . $user_id . 'transphil_secret_key' . time());
    $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
    
    // Delete old trust records for this email
    $pdo->prepare("DELETE FROM trusted_emails WHERE user_id = ? AND email = ?")->execute([$user_id, $email]);
    
    // Insert new trust record
    $stmt = $pdo->prepare("
        INSERT INTO trusted_emails (user_id, email, token, expires_at) 
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$user_id, $email, $trust_token, $expires]);
    
    // Set cookie for quick check
    setcookie('trusted_email_token', $trust_token, time() + (86400 * 30), '/', '', true, true);
}

// ── Handle login ──
if(isset($_POST['login'])){

    // CSRF check
    if(!isset($_POST['csrf_token']) || !CSRFToken::verify($_POST['csrf_token'])){
        $error = "Security validation failed. Please refresh and try again.";
    } else {

        $email    = trim($_POST['email']);
        $password = trim($_POST['password']);

        if(empty($email) || empty($password)){
            $error = "Please fill in all fields.";
        } else {

            // ── Rate limiting: check failed attempts ──
            $max_attempts = 5;
            $lockout_time = 900; // 15 minutes

            $attempts_stmt = $pdo->prepare("
                SELECT COUNT(*) FROM login_attempts
                WHERE email = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE) AND success = 0
            ");
            $attempts_stmt->execute([$email]);
            $attempts = $attempts_stmt->fetchColumn();

            if($attempts >= $max_attempts){
                $error = "Too many failed login attempts. Please try again in 15 minutes.";
            } else {

                $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND status = 'active'");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if($user && password_verify($password, $user['password'])){

                    // ── Log success ──
                    $pdo->prepare("INSERT INTO login_attempts (email, success, ip_address) VALUES (?, 1, ?)")
                        ->execute([$email, $_SERVER['REMOTE_ADDR'] ?? '']);

                    // ── Check if email is already trusted (skip MFA) ──
                    if(is_email_trusted($user['email'], $user['id'], $pdo)){
                        // Trusted email - login directly without OTP
                        $_SESSION['user_id']       = $user['id'];
                        $_SESSION['full_name']     = $user['full_name'];
                        $_SESSION['role']          = $user['role'];
                        $_SESSION['last_activity'] = time();
                        
                        // Redirect by role
                        if($user['role'] == 'admin')  { header("Location: admin/dashboard.php");  exit; }
                        if($user['role'] == 'agent')  { header("Location: agent/dashboard.php");  exit; }
                        if($user['role'] == 'client') { header("Location: client/dashboard.php"); exit; }
                    }

                    // ── Generate MFA OTP (for non-trusted emails) ──
                    $otp     = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                    $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));

                    // Delete old MFA codes for this user
                    $pdo->prepare("DELETE FROM mfa_codes WHERE user_id = ?")->execute([$user['id']]);

                    // Save new MFA code
                    $pdo->prepare("INSERT INTO mfa_codes (user_id, otp, expires_at, used) VALUES (?, ?, ?, 0)")
                        ->execute([$user['id'], $otp, $expires]);

                    // ── Try to send OTP via email ──
                    $mail_sent = false;
                    if(file_exists(__DIR__ . '/phpmailer/PHPMailer.php')){
                        require_once __DIR__ . '/phpmailer/Exception.php';
                        require_once __DIR__ . '/phpmailer/PHPMailer.php';
                        require_once __DIR__ . '/phpmailer/SMTP.php';

                        $smtp_user = 'iamfaye011@gmail.com';
                        $smtp_pass = 'zjtmslmoqorhhhxo';

                        if($smtp_user && $smtp_pass){
                            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                            try {
                                $mail->isSMTP();
                                $mail->Host       = 'smtp.gmail.com';
                                $mail->SMTPAuth   = true;
                                $mail->Username   = $smtp_user;
                                $mail->Password   = $smtp_pass;
                                $mail->SMTPSecure = 'tls';
                                $mail->Port       = 587;
                                $mail->setFrom($smtp_user, 'Trans-Phil House Hub');
                                $mail->addAddress($user['email'], $user['full_name']);
                                $mail->isHTML(true);
                                $mail->Subject = 'Your Login Verification Code — Trans-Phil House Hub';
                                $mail->Body    = "
                                <div style='font-family:DM Sans,Arial,sans-serif;max-width:480px;margin:auto;padding:30px;'>
                                    <div style='background:#1a3a6b;padding:20px;text-align:center;border-radius:10px 10px 0 0;'>
                                        <h2 style='color:#fff;margin:0;'>Trans-Phil House Hub</h2>
                                        <p style='color:#f07800;font-size:12px;letter-spacing:2px;margin:4px 0 0;text-transform:uppercase;'>Login Verification</p>
                                    </div>
                                    <div style='background:#fff;padding:28px;border:1px solid #e4e2ee;border-top:none;border-radius:0 0 10px 10px;'>
                                        <p>Hi <strong>{$user['full_name']}</strong>,</p>
                                        <p>Your login verification code is:</p>
                                        <div style='background:#f0f4ff;border-radius:10px;padding:20px;text-align:center;margin:16px 0;'>
                                            <div style='font-size:40px;font-weight:700;letter-spacing:12px;color:#1a3a6b;'>{$otp}</div>
                                            <p style='color:#6b7280;font-size:12px;margin:6px 0 0;'>Expires in <strong>10 minutes</strong></p>
                                        </div>
                                        <p style='color:#6b7280;font-size:12px;'>If you did not attempt to login, please change your password immediately.</p>
                                    </div>
                                </div>";
                                $mail->send();
                                $mail_sent = true;
                            } catch(Exception $e){
                                error_log("MFA mail error: " . $e->getMessage());
                            }
                        }
                    }

                    // ── Store temp session for MFA ──
                    $_SESSION['mfa_user_id']   = $user['id'];
                    $_SESSION['mfa_full_name'] = $user['full_name'];
                    $_SESSION['mfa_role']      = $user['role'];
                    $_SESSION['mfa_email']     = $user['email'];

                    // For local testing — show OTP if email not sent
                    if(!$mail_sent){
                        $_SESSION['mfa_dev_otp'] = $otp;
                    }

                    header("Location: mfa_verify.php");
                    exit;

                } else {
                    // ── Log failed attempt ──
                    $pdo->prepare("INSERT INTO login_attempts (email, success, ip_address) VALUES (?, 0, ?)")
                        ->execute([$email, $_SERVER['REMOTE_ADDR'] ?? '']);

                    $remaining = $max_attempts - ($attempts + 1);
                    if($remaining > 0){
                        $error = "Invalid email or password. {$remaining} attempt(s) remaining.";
                    } else {
                        $error = "Too many failed attempts. Please try again in 15 minutes.";
                    }
                }
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
    <title>Login — Trans-Phil House Hub</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box;}
        body{font-family:'DM Sans',sans-serif;background:#f5f4f0;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;}
        .login-container{width:100%;max-width:1200px;background:#fff;border-radius:24px;overflow:hidden;box-shadow:0 20px 40px rgba(0,0,0,0.08);display:flex;flex-wrap:wrap;}
        .brand-panel{flex:1;min-width:280px;background:linear-gradient(135deg,#1a3a6b 0%,#22508a 100%);padding:60px 40px;display:flex;flex-direction:column;justify-content:center;position:relative;overflow:hidden;}
        .brand-panel::before{content:'';position:absolute;top:-50px;right:-50px;width:200px;height:200px;background:rgba(45,177,43,0.08);border-radius:50%;}
        .brand-panel::after{content:'';position:absolute;bottom:-30px;left:-30px;width:150px;height:150px;background:rgba(240,120,0,0.08);border-radius:50%;}
        .logo-section{text-align:center;margin-bottom:20px;position:relative;z-index:2;}
        .logo-img{width:80px;height:80px;border-radius:50%;object-fit:cover;margin-bottom:20px;box-shadow:0 4px 8px rgba(0,0,0,0.15);}
        .brand-panel h1{font-size:28px;color:#fff;margin-bottom:10px;font-weight:700;}
        .brand-panel h1 span{color:#f07800;}
        .brand-panel p{color:rgba(255,255,255,0.75);font-size:14px;line-height:1.6;}
        .mfa-badge{display:inline-flex;align-items:center;gap:6px;background:rgba(45,177,43,0.15);border:1px solid rgba(45,177,43,0.3);color:#7eda7c;padding:6px 14px;border-radius:20px;font-size:12px;margin-top:20px;position:relative;z-index:2;}
        .form-panel{flex:1;min-width:380px;padding:60px 50px;background:#fff;}
        .form-header{text-align:center;margin-bottom:35px;}
        .form-header h2{font-size:32px;color:#1a3a6b;margin-bottom:10px;font-weight:700;}
        .form-header p{color:#6b7280;font-size:14px;}
        .alert-error{background:#fee2e2;border-left:4px solid #dc2626;color:#991b1b;padding:14px 16px;border-radius:10px;margin-bottom:25px;font-size:13px;display:flex;align-items:center;gap:10px;}
        .alert-success{background:#e6f7e6;border-left:4px solid #2db12b;color:#166534;padding:14px 16px;border-radius:10px;margin-bottom:25px;font-size:13px;display:flex;align-items:center;gap:10px;}
        .alert-logout{background:#e3f2fd;border-left:4px solid #1a3a6b;color:#1a3a6b;padding:14px 16px;border-radius:10px;margin-bottom:25px;font-size:13px;display:flex;align-items:center;gap:10px;}
        .form-group{margin-bottom:24px;}
        .form-group label{display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:8px;}
        .input-wrapper{position:relative;}
        .input-wrapper i.icon{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:#9ca3af;font-size:16px;pointer-events:none;}
        .form-group input{width:100%;padding:14px 44px 14px 42px;border:2px solid #e5e7eb;border-radius:12px;font-size:14px;font-family:'DM Sans',sans-serif;transition:.3s;outline:none;background:#faf9f6;}
        .form-group input:focus{border-color:#2db12b;background:#fff;}
        .toggle-pw{position:absolute;right:14px;top:50%;transform:translateY(-50%);color:#9ca3af;cursor:pointer;font-size:15px;border:none;background:none;padding:0;}
        .toggle-pw:hover{color:#1a3a6b;}
        .forgot-link{text-align:right;margin-bottom:25px;}
        .forgot-link a{color:#f07800;font-size:13px;text-decoration:none;font-weight:500;}
        .forgot-link a:hover{color:#d86d00;}
        .login-btn{width:100%;background:#f07800;color:#fff;border:none;padding:14px;border-radius:12px;font-size:15px;font-weight:600;cursor:pointer;transition:.3s;font-family:'DM Sans',sans-serif;margin-bottom:20px;display:flex;align-items:center;justify-content:center;gap:8px;}
        .login-btn:hover{background:#d86d00;transform:translateY(-2px);}
        .register-link{text-align:center;font-size:13px;color:#6b7280;}
        .register-link a{color:#1a3a6b;text-decoration:none;font-weight:600;}
        .register-link a:hover{color:#2db12b;}
        .security-strip{display:flex;align-items:center;justify-content:center;gap:16px;margin-top:20px;padding-top:20px;border-top:1px solid #f0ede8;flex-wrap:wrap;}
        .sec-item{display:flex;align-items:center;gap:5px;font-size:11px;color:#9ca3af;}
        .sec-item i{font-size:12px;color:#2db12b;}
        @media(max-width:768px){.brand-panel{padding:40px 30px;}.form-panel{padding:40px 30px;min-width:300px;}.form-header h2{font-size:26px;}}
    </style>
</head>
<body>

<div class="login-container">

    <!-- Brand Panel -->
    <div class="brand-panel">
        <div class="logo-section">
            <img src="assets/images/logo.jpg" alt="Trans-Phil Logo" class="logo-img">
            <h1>Trans-Phil <span>House Hub</span></h1>
            <p>Your trusted partner in finding the perfect property in Metro Manila.</p>
            <div class="mfa-badge">
                <i class="fas fa-shield-alt"></i> MFA Protected Login
            </div>
        </div>
    </div>

    <!-- Form Panel -->
    <div class="form-panel">
        <div class="form-header">
            <h2>Welcome Back</h2>
            <p>Login to access your Trans-Phil account</p>
        </div>

        <?php if($logout_message): ?>
        <div class="alert-logout"><i class="fas fa-check-circle"></i><?php echo $logout_message; ?></div>
        <?php endif; ?>

        <?php if($error): ?>
        <div class="alert-error"><i class="fas fa-exclamation-circle"></i><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if($success): ?>
        <div class="alert-success"><i class="fas fa-check-circle"></i><?php echo $success; ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

            <div class="form-group">
                <label>Email Address</label>
                <div class="input-wrapper">
                    <i class="fas fa-envelope icon"></i>
                    <input type="email" name="email" placeholder="Enter your email" required autocomplete="email"
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                </div>
            </div>

            <div class="form-group">
                <label>Password</label>
                <div class="input-wrapper">
                    <i class="fas fa-lock icon"></i>
                    <input type="password" name="password" id="password" placeholder="Enter your password" required autocomplete="current-password">
                    <button type="button" class="toggle-pw" onclick="togglePassword()">
                        <i class="fas fa-eye" id="eyeIcon"></i>
                    </button>
                </div>
            </div>

            <div class="forgot-link">
                <a href="forgot_password.php"><i class="fas fa-key"></i> Forgot Password?</a>
            </div>

            <button type="submit" name="login" class="login-btn">
                <i class="fas fa-arrow-right-to-bracket"></i> Sign In
            </button>

            <div class="register-link">
                Don't have an account? <a href="register.php">Create an account</a>
            </div>

            <!-- Security strip -->
            <div class="security-strip">
                <span class="sec-item"><i class="fas fa-shield-alt"></i> CSRF Protected</span>
                <span class="sec-item"><i class="fas fa-lock"></i> Encrypted</span>
                <span class="sec-item"><i class="fas fa-mobile-alt"></i> MFA Enabled</span>
                <span class="sec-item"><i class="fas fa-ban"></i> Rate Limited</span>
            </div>
        </form>
    </div>
</div>

<script>
function togglePassword(){
    const pw  = document.getElementById('password');
    const eye = document.getElementById('eyeIcon');
    pw.type   = pw.type === 'password' ? 'text' : 'password';
    eye.className = pw.type === 'password' ? 'fas fa-eye' : 'fas fa-eye-slash';
}
</script>

</body>
</html>