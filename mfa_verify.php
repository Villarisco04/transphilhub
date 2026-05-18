<?php
// Set timezone FIRST
date_default_timezone_set('Asia/Manila');

if(session_status() === PHP_SESSION_NONE){
    session_start();
}

require_once 'includes/db.php';

// Must have gone through login first
if(!isset($_SESSION['mfa_user_id'])){
    header("Location: login.php");
    exit;
}

$user_id   = $_SESSION['mfa_user_id'];
$full_name = $_SESSION['mfa_full_name'];
$role      = $_SESSION['mfa_role'];
$email     = $_SESSION['mfa_email'];
$error     = '';

// Mask email: ab***@gmail.com
$parts  = explode('@', $email);
$masked = substr($parts[0], 0, 2) . str_repeat('*', max(strlen($parts[0]) - 2, 3)) . '@' . $parts[1];

// ── Verify OTP ──
if(isset($_POST['verify_mfa'])){
    $entered = trim($_POST['otp']);

    if(empty($entered) || !ctype_digit($entered) || strlen($entered) !== 6){
        $error = "Please enter the 6-digit verification code.";
    } else {
        // Get current Manila time
        $now = date('Y-m-d H:i:s');
        
        // Check for valid, unused, not expired OTP
        $stmt = $pdo->prepare("
            SELECT * FROM mfa_codes 
            WHERE user_id = ? 
            AND otp = ? 
            AND used = 0 
            AND expires_at > ?
            ORDER BY id DESC 
            LIMIT 1
        ");
        $stmt->execute([$user_id, $entered, $now]);
        $code = $stmt->fetch();

        if(!$code){
            $error = "Invalid or expired verification code. Please try again.";
        } else {
            // Mark code as used
            $update = $pdo->prepare("UPDATE mfa_codes SET used = 1 WHERE id = ?");
            $update->execute([$code['id']]);

            // Check if user wants to trust this email for future logins
            if(isset($_POST['trust_email']) && $_POST['trust_email'] == 1){
                // Create trust token
                $trust_token = hash('sha256', $email . $user_id . 'transphil_secret_key' . time());
                $trust_expires = date('Y-m-d H:i:s', strtotime('+30 days'));
                
                // Delete old trust records
                $pdo->prepare("DELETE FROM trusted_emails WHERE user_id = ? AND email = ?")->execute([$user_id, $email]);
                
                // Insert new trust record
                $trust_stmt = $pdo->prepare("
                    INSERT INTO trusted_emails (user_id, email, token, expires_at) 
                    VALUES (?, ?, ?, ?)
                ");
                $trust_stmt->execute([$user_id, $email, $trust_token, $trust_expires]);
                
                // Set cookie
                setcookie('trusted_email_token', $trust_token, time() + (86400 * 30), '/', '', true, true);
            }

            // Create real session
            $_SESSION['user_id']       = $user_id;
            $_SESSION['full_name']     = $full_name;
            $_SESSION['role']          = $role;
            $_SESSION['last_activity'] = time();

            // Clean up temp MFA session
            unset($_SESSION['mfa_user_id'], $_SESSION['mfa_full_name'],
                  $_SESSION['mfa_role'], $_SESSION['mfa_email'],
                  $_SESSION['mfa_dev_otp']);

            // Redirect by role
            if($role === 'admin')  { header("Location: admin/dashboard.php");  exit; }
            if($role === 'agent')  { header("Location: agent/dashboard.php");  exit; }
            if($role === 'client') { header("Location: client/dashboard.php"); exit; }
        }
    }
}

// ── Resend OTP ──
if(isset($_POST['resend_mfa'])){
    // Delete old unused codes for this user
    $pdo->prepare("DELETE FROM mfa_codes WHERE user_id = ? AND used = 0")->execute([$user_id]);
    
    // Generate new OTP
    $otp     = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));

    // Insert new code
    $insert = $pdo->prepare("INSERT INTO mfa_codes (user_id, otp, expires_at, used) VALUES (?, ?, ?, 0)");
    $insert->execute([$user_id, $otp, $expires]);

    // Try to send email
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
                $mail->addAddress($email, $full_name);
                $mail->isHTML(true);
                $mail->Subject = 'Your Login Verification Code — Trans-Phil House Hub';
                $mail->Body    = "
                <div style='font-family:DM Sans,Arial,sans-serif;max-width:480px;margin:auto;padding:30px;'>
                    <div style='background:#1a3a6b;padding:20px;text-align:center;border-radius:10px 10px 0 0;'>
                        <h2 style='color:#fff;margin:0;'>Trans-Phil House Hub</h2>
                    </div>
                    <div style='background:#fff;padding:28px;border:1px solid #e4e2ee;border-top:none;border-radius:0 0 10px 10px;'>
                        <p>Hi <strong>{$full_name}</strong>, your verification code is:</p>
                        <div style='background:#f0f4ff;border-radius:10px;padding:20px;text-align:center;margin:16px 0;'>
                            <div style='font-size:40px;font-weight:700;letter-spacing:12px;color:#1a3a6b;'>{$otp}</div>
                            <p style='color:#6b7280;font-size:12px;margin:6px 0 0;'>Expires in <strong>10 minutes</strong></p>
                        </div>
                    </div>
                </div>";
                $mail->send();
                $mail_sent = true;
            } catch(Exception $e){
                error_log("Resend MFA error: " . $e->getMessage());
            }
        }
    }

    $_SESSION['mfa_dev_otp'] = $otp;
    $error = '';
    $resent = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Verify Login — Trans-Phil House Hub</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'DM Sans',sans-serif;background:#f5f4f0;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;}
.card{background:#fff;border-radius:24px;padding:48px 40px;width:100%;max-width:460px;box-shadow:0 20px 40px rgba(0,0,0,0.08);border:1px solid #e4e2ee;}
.shield-wrap{width:80px;height:80px;border-radius:50%;background:linear-gradient(135deg,#1a3a6b,#22508a);display:flex;align-items:center;justify-content:center;margin:0 auto 20px;}
.shield-wrap i{font-size:36px;color:#fff;}
h2{font-size:26px;font-weight:700;color:#1a3a6b;text-align:center;margin-bottom:6px;}
.sub{font-size:14px;color:#6b7280;text-align:center;margin-bottom:28px;line-height:1.5;}
.sub strong{color:#1a3a6b;}
.dev-notice{background:#fff3e6;border:1px dashed #f07800;border-radius:10px;padding:14px 16px;font-size:13px;color:#854f0b;margin-bottom:20px;text-align:center;}
.dev-notice strong{font-size:28px;letter-spacing:8px;display:block;margin-top:4px;color:#f07800;}
.alert-error{background:#fee2e2;border-left:4px solid #dc2626;color:#991b1b;padding:12px 16px;border-radius:10px;margin-bottom:18px;font-size:13px;display:flex;align-items:flex-start;gap:8px;}
.alert-error a{color:#991b1b;font-weight:700;}
.alert-success{background:#e6f7e6;border-left:4px solid #2db12b;color:#166534;padding:12px 16px;border-radius:10px;margin-bottom:18px;font-size:13px;display:flex;align-items:center;gap:8px;}
.otp-row{display:flex;gap:10px;justify-content:center;margin-bottom:20px;}
.otp-row input{width:50px;height:58px;text-align:center;font-size:24px;font-weight:700;border:2px solid #e5e7eb;border-radius:12px;font-family:'DM Sans',sans-serif;color:#1a3a6b;background:#faf9f6;transition:.2s;}
.otp-row input:focus{outline:none;border-color:#f07800;background:#fff;}
.otp-row input.filled{border-color:#1a3a6b;}
.timer{text-align:center;font-size:13px;color:#6b7280;margin-bottom:18px;}
.timer span{font-weight:700;color:#f07800;}
.trust-section{margin-bottom:16px;display:flex;align-items:center;gap:10px;}
.trust-section input{width:18px;height:18px;margin:0;cursor:pointer;}
.trust-section label{font-size:12px;color:#6b7280;cursor:pointer;}
.trust-section label i{color:#2db12b;margin-right:5px;}
.verify-btn{width:100%;background:#1a3a6b;color:#fff;border:none;padding:14px;border-radius:12px;font-size:15px;font-weight:600;cursor:pointer;font-family:'DM Sans',sans-serif;transition:.3s;display:flex;align-items:center;justify-content:center;gap:8px;margin-bottom:14px;}
.verify-btn:hover{background:#122d55;}
.verify-btn:disabled{background:#9ca3af;cursor:not-allowed;}
.resend-form{text-align:center;}
.resend-btn{background:none;border:none;font-family:'DM Sans',sans-serif;font-size:13px;color:#1a3a6b;font-weight:600;cursor:pointer;text-decoration:underline;padding:0;}
.resend-btn:hover{color:#f07800;}
.resend-btn:disabled{color:#9ca3af;text-decoration:none;cursor:not-allowed;}
.back-link{text-align:center;margin-top:16px;font-size:13px;color:#6b7280;}
.back-link a{color:#f07800;font-weight:600;text-decoration:none;}
.back-link a:hover{text-decoration:underline;}
</style>
</head>
<body>

<div class="card">

    <div class="shield-wrap">
        <i class="fas fa-shield-alt"></i>
    </div>

    <h2>Two-Factor Authentication</h2>
    <div class="sub">
        Hi <strong><?php echo htmlspecialchars($full_name); ?></strong>!<br>
        Enter the 6-digit code sent to <strong><?php echo htmlspecialchars($masked); ?></strong>
    </div>

    <?php if(isset($_SESSION['mfa_dev_otp'])): ?>
    <div class="dev-notice">
        <i class="fas fa-flask"></i> Local testing — your OTP:
        <strong><?php echo $_SESSION['mfa_dev_otp']; ?></strong>
        <small style="display:block;margin-top:4px;font-size:11px;">(Remove in production)</small>
    </div>
    <?php endif; ?>

    <?php if($error): ?>
    <div class="alert-error">
        <i class="fas fa-exclamation-circle"></i>
        <span><?php echo $error; ?></span>
    </div>
    <?php endif; ?>

    <?php if(isset($resent)): ?>
    <div class="alert-success">
        <i class="fas fa-check-circle"></i> A new code has been sent!
    </div>
    <?php endif; ?>

    <form method="POST" id="mfaForm">
        <div class="otp-row" id="otpBoxes">
            <input type="text" maxlength="1" class="otp-digit" inputmode="numeric" pattern="[0-9]">
            <input type="text" maxlength="1" class="otp-digit" inputmode="numeric" pattern="[0-9]">
            <input type="text" maxlength="1" class="otp-digit" inputmode="numeric" pattern="[0-9]">
            <input type="text" maxlength="1" class="otp-digit" inputmode="numeric" pattern="[0-9]">
            <input type="text" maxlength="1" class="otp-digit" inputmode="numeric" pattern="[0-9]">
            <input type="text" maxlength="1" class="otp-digit" inputmode="numeric" pattern="[0-9]">
        </div>
        <input type="hidden" name="otp" id="otp_real">
        
        <!-- Trust email checkbox - User decides AFTER successful OTP -->
        <div class="trust-section">
            <input type="checkbox" name="trust_email" id="trust_email" value="1">
            <label for="trust_email">
                <i class="fas fa-check-circle"></i> Trust this email for 30 days (skip OTP on future logins)
            </label>
        </div>

        <div class="timer" id="timerWrap">
            Code expires in <span id="countdown">10:00</span>
        </div>

        <button type="submit" name="verify_mfa" class="verify-btn" id="verifyBtn" disabled>
            <i class="fas fa-check-circle"></i> Verify & Login
        </button>
    </form>

    <form method="POST" class="resend-form">
        Didn't receive the code? &nbsp;
        <button type="submit" name="resend_mfa" class="resend-btn" id="resendBtn" disabled>
            Resend Code
        </button>
    </form>

    <div class="back-link">
        <a href="login.php"><i class="fas fa-arrow-left"></i> Back to Login</a>
    </div>

</div>

<script>
// OTP box logic
const digits    = document.querySelectorAll('.otp-digit');
const realInput = document.getElementById('otp_real');
const verifyBtn = document.getElementById('verifyBtn');

digits.forEach((box, i) => {
    box.addEventListener('input', () => {
        box.value = box.value.replace(/[^0-9]/g, '');
        if(box.value && i < digits.length - 1) digits[i+1].focus();
        syncOTP();
    });
    box.addEventListener('keydown', e => {
        if(e.key === 'Backspace' && !box.value && i > 0) digits[i-1].focus();
    });
    box.addEventListener('paste', e => {
        e.preventDefault();
        const pasted = (e.clipboardData || window.clipboardData).getData('text').replace(/[^0-9]/g,'');
        [...pasted].forEach((ch, j) => { if(digits[i+j]) digits[i+j].value = ch; });
        digits[Math.min(i + pasted.length, digits.length-1)].focus();
        syncOTP();
    });
    box.addEventListener('input', () => box.classList.toggle('filled', box.value !== ''));
});

function syncOTP(){
    const val = [...digits].map(d => d.value).join('');
    realInput.value = val;
    verifyBtn.disabled = val.length < 6;
}

digits[0].focus();

// Countdown timer — 10 minutes
const resendBtn = document.getElementById('resendBtn');
const countEl   = document.getElementById('countdown');
const timerWrap = document.getElementById('timerWrap');
let seconds     = 10 * 60;

const tick = setInterval(() => {
    seconds--;
    if(seconds <= 0){
        clearInterval(tick);
        countEl.textContent = '00:00';
        timerWrap.innerHTML = '<span style="color:#dc2626;">Code has expired.</span> Please resend.';
        resendBtn.disabled = false;
        return;
    }
    const m = String(Math.floor(seconds/60)).padStart(2,'0');
    const s = String(seconds%60).padStart(2,'0');
    countEl.textContent = m+':'+s;
    if(seconds === 60) resendBtn.disabled = false;
}, 1000);
</script>

</body>
</html>