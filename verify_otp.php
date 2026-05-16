<?php
// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/csrf.php';
require_once 'includes/db.php';

date_default_timezone_set('Asia/Manila');

// Must have gone through step 1
if(!isset($_SESSION['fp_email']) || !isset($_SESSION['fp_step']) || $_SESSION['fp_step'] < 2){
    header("Location: forgot_password.php");
    exit;
}

$email   = $_SESSION['fp_email'];
$name    = $_SESSION['fp_name'] ?? 'User';
$error   = '';
$success = '';

// Generate CSRF token for the form
$csrf_token = CSRFToken::generate();

// ── Handle OTP verification ──
if(isset($_POST['verify_otp'])){
    // 🔒 CSRF PROTECTION - Verify token FIRST
    if(!isset($_POST['csrf_token']) || !CSRFToken::verify($_POST['csrf_token'])){
        $error = "Security validation failed. Please refresh the page and try again.";
    } else {
        $entered = trim($_POST['otp']);

        if(empty($entered) || !ctype_digit($entered) || strlen($entered) !== 6){
            $error = "Please enter the 6-digit OTP code.";
        } else {
            // Get current time in Manila timezone
            $now = date('Y-m-d H:i:s');
            
            // Check OTP in DB
            $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE email = ? AND otp = ? AND expires_at > ? AND used = 0");
            $stmt->execute([$email, $entered, $now]);
            $reset = $stmt->fetch();

            if(!$reset){
                // Check if OTP exists but expired
                $chk = $pdo->prepare("SELECT * FROM password_resets WHERE email = ? AND otp = ? AND used = 0");
                $chk->execute([$email, $entered]);
                if($chk->fetch()){
                    $error = "This OTP has expired. Please <a href='forgot_password.php'>request a new one</a>.";
                } else {
                    $error = "Incorrect OTP. Please try again.";
                }
            } else {
                // OTP is valid — mark it used and go to step 3
                $update = $pdo->prepare("UPDATE password_resets SET used = 1 WHERE id = ?");
                $update->execute([$reset['id']]);
                $_SESSION['fp_step']     = 3;
                $_SESSION['fp_verified'] = true;
                header("Location: reset_password.php");
                exit;
            }
        }
    }
}

// ── Handle resend OTP ──
if(isset($_POST['resend_otp'])){
    // Delete old
    $pdo->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);

    // Generate new OTP
    $otp     = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));
    $pdo->prepare("INSERT INTO password_resets (email, otp, expires_at, used) VALUES (?, ?, ?, 0)")->execute([$email, $otp, $expires]);

    // Update dev OTP in session
    $_SESSION['fp_dev_otp'] = $otp;

    $success = "A new OTP has been sent to your email.";
}

// Mask email for display: e***@gmail.com
$parts   = explode('@', $email);
$masked  = substr($parts[0], 0, 2) . str_repeat('*', max(strlen($parts[0]) - 2, 3)) . '@' . $parts[1];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Enter OTP — Trans-Phil House Hub</title>
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
.steps{display:flex;align-items:center;justify-content:center;gap:0;margin-bottom:28px;}
.step{display:flex;flex-direction:column;align-items:center;gap:4px;}
.step-circle{width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;border:2px solid var(--border);color:var(--muted);background:var(--bg);}
.step.active .step-circle{background:var(--orange);color:var(--white);border-color:var(--orange);}
.step.done .step-circle{background:var(--green);color:var(--white);border-color:var(--green);}
.step-label{font-size:10px;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;}
.step.active .step-label,.step.done .step-label{font-weight:600;}
.step.active .step-label{color:var(--orange);}
.step.done .step-label{color:var(--green);}
.step-line{width:48px;height:2px;background:var(--border);margin-top:-14px;}
.step-line.done{background:var(--green);}
.icon-wrap{width:72px;height:72px;border-radius:50%;background:#e6f7e6;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;}
.icon-wrap i{font-size:32px;color:var(--green);}
.box-title{font-size:22px;font-weight:700;color:var(--navy);text-align:center;margin-bottom:6px;}
.box-sub{font-size:13px;color:var(--muted);text-align:center;margin-bottom:28px;line-height:1.5;}
.box-sub strong{color:var(--navy);}

/* OTP INPUT BOXES */
.otp-row{display:flex;gap:10px;justify-content:center;margin-bottom:20px;}
.otp-row input{
    width:48px;height:56px;text-align:center;font-size:22px;font-weight:700;
    border:2px solid var(--border);border-radius:10px;font-family:inherit;
    color:var(--navy);background:#fafaf8;transition:.2s;
}
.otp-row input:focus{outline:none;border-color:var(--orange);background:var(--white);}
.otp-row input.filled{border-color:var(--navy);}

/* Hidden real input */
#otp_real{display:none;}

.timer{text-align:center;font-size:13px;color:var(--muted);margin-bottom:16px;}
.timer span{font-weight:700;color:var(--orange);}
.timer.expired span{color:#a32d2d;}

.btn{width:100%;padding:13px;background:var(--navy);color:var(--white);border:none;border-radius:10px;font-family:inherit;font-size:15px;font-weight:700;cursor:pointer;transition:.2s;display:flex;align-items:center;justify-content:center;gap:8px;}
.btn:hover{background:#122d55;}
.btn:active{transform:scale(.98);}
.btn:disabled{background:#9ca3af;cursor:not-allowed;}

.resend-form{text-align:center;margin-top:16px;}
.resend-btn{background:none;border:none;font-family:inherit;font-size:13px;color:var(--navy);font-weight:600;cursor:pointer;text-decoration:underline;padding:0;}
.resend-btn:hover{color:var(--orange);}
.resend-btn:disabled{color:var(--muted);text-decoration:none;cursor:not-allowed;}

.alert{padding:12px 16px;border-radius:10px;font-size:13px;margin-bottom:18px;display:flex;align-items:flex-start;gap:10px;}
.alert-error{background:#fef0f0;color:#a32d2d;border:1px solid #f7c1c1;}
.alert-success{background:#e6f7e6;color:#218f1f;border:1px solid #b6e0b5;}
.alert a{color:inherit;font-weight:700;}

/* DEV OTP notice */
.dev-notice{background:#fff3e6;border:1px dashed var(--orange);border-radius:10px;padding:12px 16px;font-size:13px;color:#854f0b;margin-bottom:18px;text-align:center;}
.dev-notice strong{font-size:22px;letter-spacing:6px;display:block;margin-top:4px;color:var(--orange);}

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
    <a href="forgot_password.php"><i class="fas fa-arrow-left"></i> Back</a>
</nav>

<div class="page">
    <div class="box">

        <!-- Steps -->
        <div class="steps">
            <div class="step done">
                <div class="step-circle"><i class="fas fa-check" style="font-size:12px;"></i></div>
                <div class="step-label">Email</div>
            </div>
            <div class="step-line done"></div>
            <div class="step active">
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
            <i class="fas fa-shield-alt"></i>
        </div>
        <div class="box-title">Check Your Email</div>
        <div class="box-sub">
            Hi <strong><?php echo htmlspecialchars($name); ?></strong>! We sent a 6-digit OTP to<br>
            <strong><?php echo htmlspecialchars($masked); ?></strong><br>
            It expires in <strong>15 minutes</strong>.
        </div>

        <?php if($error): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle" style="flex-shrink:0;margin-top:1px;"></i><span><?php echo $error; ?></span></div>
        <?php endif; ?>

        <?php if($success): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i><?php echo $success; ?></div>
        <?php endif; ?>

        <?php if(isset($_SESSION['fp_dev_otp'])): ?>
        <div class="dev-notice">
            <i class="fas fa-flask"></i> Local testing — your OTP is:
            <strong><?php echo $_SESSION['fp_dev_otp']; ?></strong>
            <small style="display:block;margin-top:4px;font-size:11px;">(Remove this notice in production)</small>
        </div>
        <?php endif; ?>

        <form method="POST" id="otpForm">
            <!-- CSRF Token -->
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            
            <!-- Visual OTP boxes (JS fills the hidden input) -->
            <div class="otp-row" id="otpBoxes">
                <input type="text" maxlength="1" class="otp-digit" inputmode="numeric" pattern="[0-9]">
                <input type="text" maxlength="1" class="otp-digit" inputmode="numeric" pattern="[0-9]">
                <input type="text" maxlength="1" class="otp-digit" inputmode="numeric" pattern="[0-9]">
                <input type="text" maxlength="1" class="otp-digit" inputmode="numeric" pattern="[0-9]">
                <input type="text" maxlength="1" class="otp-digit" inputmode="numeric" pattern="[0-9]">
                <input type="text" maxlength="1" class="otp-digit" inputmode="numeric" pattern="[0-9]">
            </div>

            <!-- Hidden input submitted -->
            <input type="hidden" name="otp" id="otp_real">

            <!-- Countdown timer -->
            <div class="timer" id="timer">OTP expires in <span id="countdown">15:00</span></div>

            <button type="submit" name="verify_otp" class="btn" id="verifyBtn">
                <i class="fas fa-check-circle"></i> Verify OTP
            </button>
        </form>

        <form method="POST" class="resend-form">
            Didn't receive it? &nbsp;
            <button type="submit" name="resend_otp" class="resend-btn" id="resendBtn" disabled>
                Resend OTP
            </button>
        </form>

        <div class="back-link">
            Wrong email? <a href="forgot_password.php">Start over</a>
        </div>
    </div>
</div>

<footer>© <?php echo date('Y'); ?> Trans-Phil House Corporation. All rights reserved.</footer>

<script>
// ── OTP BOX LOGIC ──
const digits  = document.querySelectorAll('.otp-digit');
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
        const next = Math.min(i + pasted.length, digits.length - 1);
        digits[next].focus();
        syncOTP();
    });
    box.addEventListener('change', () => box.classList.toggle('filled', box.value !== ''));
});

function syncOTP(){
    const val = [...digits].map(d => d.value).join('');
    realInput.value = val;
    verifyBtn.disabled = val.length < 6;
}
verifyBtn.disabled = true;
digits[0].focus();

// ── COUNTDOWN TIMER ──
const resendBtn = document.getElementById('resendBtn');
const countEl   = document.getElementById('countdown');
const timerEl   = document.getElementById('timer');
let seconds     = 15 * 60;

const tick = setInterval(() => {
    seconds--;
    if(seconds <= 0){
        clearInterval(tick);
        countEl.textContent = '00:00';
        timerEl.classList.add('expired');
        timerEl.innerHTML = 'OTP has <span>expired</span>. Please resend.';
        resendBtn.disabled = false;
        return;
    }
    const m = String(Math.floor(seconds/60)).padStart(2,'0');
    const s = String(seconds % 60).padStart(2,'0');
    countEl.textContent = m + ':' + s;
    if(seconds === 60) resendBtn.disabled = false; // allow resend in last 1 min
}, 1000);
</script>

</body>
</html>