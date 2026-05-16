<?php
require_once 'includes/csrf.php';
require_once 'includes/db.php';

// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// If already logged in, redirect
if(isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit;
}

date_default_timezone_set('Asia/Manila');

$error   = '';
$success = '';

// Generate CSRF token for the form
$csrf_token = CSRFToken::generate();

// ── STEP 1: Submit email, generate & send OTP ──
if(isset($_POST['send_otp'])){
    // 🔒 CSRF PROTECTION - Verify token FIRST
    if(!isset($_POST['csrf_token']) || !CSRFToken::verify($_POST['csrf_token'])){
        $error = "Security validation failed. Please refresh the page and try again.";
    } else {
        $email = trim(strtolower($_POST['email']));

        if(empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)){
            $error = "Please enter a valid email address.";
        } else {
            // Check if email exists
            $stmt = $pdo->prepare("SELECT id, full_name FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if(!$user){
                // Generic message for security — don't reveal if email exists
                $error = "No account found with that email address.";
            } else {
                // Delete any old unused OTPs for this email
                $pdo->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);

                // Generate 6-digit OTP
                $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                $expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));

                // Save OTP to DB
                $stmt = $pdo->prepare("INSERT INTO password_resets (email, otp, expires_at, used) VALUES (?, ?, ?, 0)");
                $stmt->execute([$email, $otp, $expires]);

                // ── Send OTP via PHPMailer ──
                $mail_sent = false;

                if(file_exists(__DIR__ . '/phpmailer/PHPMailer.php')){
                    require_once __DIR__ . '/phpmailer/Exception.php';
                    require_once __DIR__ . '/phpmailer/PHPMailer.php';
                    require_once __DIR__ . '/phpmailer/SMTP.php';

                    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                    try {
                        $mail->isSMTP();
                        $mail->Host       = 'smtp.gmail.com';
                        $mail->SMTPAuth   = true;
                        $mail->Username   = 'iamfaye011@gmail.com';
                        $mail->Password   = 'zjtmslmoqorhhhxo';
                        $mail->SMTPSecure = 'tls';
                        $mail->Port       = 587;

                        $mail->setFrom('iamfaye011@gmail.com', 'Trans-Phil House Hub');
                        $mail->addAddress($email, $user['full_name']);
                        $mail->isHTML(true);
                        $mail->Subject = 'Your Password Reset OTP — Trans-Phil House Hub';
                        $mail->Body    = "
                        <div style='font-family:DM Sans,sans-serif;max-width:480px;margin:auto;padding:30px;'>
                            <div style='text-align:center;margin-bottom:24px;'>
                                <h2 style='color:#1a3a6b;margin:0;'>Trans-Phil House Hub</h2>
                                <p style='color:#f07800;font-size:12px;letter-spacing:2px;text-transform:uppercase;'>Password Reset</p>
                            </div>
                            <p style='color:#333;'>Hi <strong>{$user['full_name']}</strong>,</p>
                            <p style='color:#333;'>Use the OTP below to reset your password. It expires in <strong>15 minutes</strong>.</p>
                            <div style='background:#f0f4ff;border-radius:12px;padding:24px;text-align:center;margin:24px 0;'>
                                <div style='font-size:42px;font-weight:700;letter-spacing:12px;color:#1a3a6b;'>{$otp}</div>
                                <p style='color:#6b7280;font-size:12px;margin-top:8px;'>One-Time Password</p>
                            </div>
                            <p style='color:#6b7280;font-size:12px;'>If you did not request this, please ignore this email.</p>
                            <hr style='border:none;border-top:1px solid #e5e7eb;margin:20px 0;'>
                            <p style='color:#9ca3af;font-size:11px;text-align:center;'>Trans-Phil House Corporation · 1177 Bagtikan St, Makati City</p>
                        </div>";
                        $mail->send();
                        $mail_sent = true;
                    } catch (Exception $e) {
                        error_log("Mail error: " . $e->getMessage());
                    }
                }

                if($mail_sent){
                    // Store in session for verification
                    $_SESSION['fp_email'] = $email;
                    $_SESSION['fp_name'] = $user['full_name'];
                    $_SESSION['fp_step'] = 2;
                    
                    header("Location: verify_otp.php");
                    exit;
                } else {
                    $error = "Unable to send OTP email. Please try again later.";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Forgot Password — Trans-Phil House Hub</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
:root{--navy:#1a3a6b;--orange:#f07800;--green:#2db12b;--bg:#f0eff5;--white:#fff;--border:#e4e2ee;--muted:#6b6880;}
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'DM Sans',sans-serif;background:var(--bg);min-height:100vh;display:flex;flex-direction:column;}

.nav{background:var(--white);border-bottom:2px solid var(--border);padding:0 32px;height:72px;display:flex;align-items:center;justify-content:space-between;box-shadow:0 2px 10px rgba(0,0,0,0.04);}
.nav-logo{display:flex;align-items:center;gap:12px;text-decoration:none;}
.logo-icon{width:42px;height:42px;object-fit:contain;}
.lt1{font-size:18px;font-weight:700;color:var(--navy);}
.lt2{font-size:10px;font-weight:600;color:var(--orange);letter-spacing:2px;text-transform:uppercase;}
.nav a{font-size:13px;color:var(--muted);text-decoration:none;}
.nav a:hover{color:var(--navy);}

.page{flex:1;display:flex;align-items:center;justify-content:center;padding:40px 16px;}
.box{background:var(--white);border-radius:20px;padding:40px 36px;width:100%;max-width:440px;border:1px solid var(--border);box-shadow:0 4px 24px rgba(26,58,107,.08);}

.icon-wrap{width:72px;height:72px;border-radius:50%;background:#fff3e6;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;}
.icon-wrap i{font-size:32px;color:var(--orange);}

.box-title{font-size:22px;font-weight:700;color:var(--navy);text-align:center;margin-bottom:6px;}
.box-sub{font-size:13px;color:var(--muted);text-align:center;margin-bottom:28px;line-height:1.5;}

.steps{display:flex;align-items:center;justify-content:center;gap:0;margin-bottom:28px;}
.step{display:flex;flex-direction:column;align-items:center;gap:4px;}
.step-circle{width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;border:2px solid var(--border);color:var(--muted);background:var(--bg);}
.step.active .step-circle{background:var(--navy);color:var(--white);border-color:var(--navy);}
.step.done .step-circle{background:var(--green);color:var(--white);border-color:var(--green);}
.step-label{font-size:10px;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;}
.step.active .step-label{color:var(--navy);font-weight:600;}
.step-line{width:48px;height:2px;background:var(--border);margin-top:-14px;}
.step-line.done{background:var(--green);}

.fg{margin-bottom:18px;}
.fg label{display:block;font-size:13px;font-weight:600;color:var(--navy);margin-bottom:7px;}
.input-wrap{position:relative;}
.input-wrap i{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:15px;}
.input-wrap input{width:100%;padding:12px 14px 12px 40px;border:1.5px solid var(--border);border-radius:10px;font-family:inherit;font-size:14px;color:#333;background:#fafaf8;transition:.2s;}
.input-wrap input:focus{outline:none;border-color:var(--orange);background:var(--white);}

.btn{width:100%;padding:13px;background:var(--orange);color:var(--white);border:none;border-radius:10px;font-family:inherit;font-size:15px;font-weight:700;cursor:pointer;transition:.2s;display:flex;align-items:center;justify-content:center;gap:8px;}
.btn:hover{background:#d86d00;}
.btn:active{transform:scale(.98);}

.alert{padding:12px 16px;border-radius:10px;font-size:13px;margin-bottom:18px;display:flex;align-items:center;gap:10px;}
.alert-error{background:#fef0f0;color:#a32d2d;border:1px solid #f7c1c1;}
.alert-success{background:#e6f7e6;color:#218f1f;border:1px solid #b6e0b5;}

.back-link{text-align:center;margin-top:20px;font-size:13px;color:var(--muted);}
.back-link a{color:var(--navy);font-weight:600;text-decoration:none;}
.back-link a:hover{color:var(--orange);}
footer{text-align:center;padding:20px;font-size:12px;color:var(--muted);}
</style>
</head>
<body>

<nav class="nav">
    <a href="index.php" class="nav-logo">
        <img src="assets/images/logo.jpg" class="logo-icon" alt="Logo">
        <div>
            <div class="lt1">Trans-Phil</div>
            <div class="lt2">House Hub</div>
        </div>
    </a>
    <a href="login.php"><i class="fas fa-arrow-left"></i> Back to Login</a>
</nav>

<div class="page">
    <div class="box">

        <div class="steps">
            <div class="step active">
                <div class="step-circle">1</div>
                <div class="step-label">Email</div>
            </div>
            <div class="step-line"></div>
            <div class="step">
                <div class="step-circle">2</div>
                <div class="step-label">OTP</div>
            </div>
            <div class="step-line"></div>
            <div class="step">
                <div class="step-circle">3</div>
                <div class="step-label">Reset</div>
            </div>
        </div>

        <div class="icon-wrap">
            <i class="fas fa-envelope-open-text"></i>
        </div>
        <div class="box-title">Forgot Password?</div>
        <div class="box-sub">Enter your registered email address and we'll send you a 6-digit OTP to reset your password.</div>

        <?php if($error): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if($success): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i><?php echo $success; ?></div>
        <?php endif; ?>

        <form method="POST">
            <!-- CSRF Token Field -->
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            
            <div class="fg">
                <label>Email Address</label>
                <div class="input-wrap">
                    <i class="fas fa-envelope"></i>
                    <input type="email" name="email" placeholder="your@email.com" required
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                </div>
            </div>
            <button type="submit" name="send_otp" class="btn">
                <i class="fas fa-paper-plane"></i> Send OTP Code
            </button>
        </form>

        <div class="back-link">
            Remembered your password? <a href="login.php">Sign In</a>
        </div>
    </div>
</div>

<footer>© <?php echo date('Y'); ?> Trans-Phil House Corporation. All rights reserved.</footer>

</body>
</html>