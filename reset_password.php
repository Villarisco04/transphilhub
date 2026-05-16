<?php
require_once 'includes/csrf.php';
require_once 'includes/db.php';

// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Must have verified OTP in step 2
if(!isset($_SESSION['fp_email']) || !isset($_SESSION['fp_verified']) || $_SESSION['fp_verified'] !== true){
    header("Location: forgot_password.php");
    exit;
}

$email = $_SESSION['fp_email'];
$error = '';

// Generate CSRF token for the form
$csrf_token = CSRFToken::generate();

if(isset($_POST['reset_password'])){
    // 🔒 CSRF PROTECTION - Verify token FIRST
    if(!isset($_POST['csrf_token']) || !CSRFToken::verify($_POST['csrf_token'])){
        $error = "Security validation failed. Please refresh the page and try again.";
    } else {
        $password         = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $errors           = [];

        // Validation
        if(empty($password)){
            $errors[] = "Password is required.";
        } elseif(strlen($password) < 8){
            $errors[] = "Password must be at least 8 characters.";
        } elseif(!preg_match("/[A-Z]/", $password)){
            $errors[] = "Password must contain at least one uppercase letter.";
        } elseif(!preg_match("/[a-z]/", $password)){
            $errors[] = "Password must contain at least one lowercase letter.";
        } elseif(!preg_match("/[0-9]/", $password)){
            $errors[] = "Password must contain at least one number.";
        } elseif(!preg_match("/[^a-zA-Z0-9]/", $password)){
            $errors[] = "Password must contain at least one special character (!@#\$%^&*).";
        }

        if($password !== $confirm_password){
            $errors[] = "Passwords do not match.";
        }

        if(empty($errors)){
            // Update password
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt   = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
            $stmt->execute([$hashed, $email]);

            // Clean up session
            unset($_SESSION['fp_email'], $_SESSION['fp_step'], $_SESSION['fp_verified'],
                  $_SESSION['fp_name'], $_SESSION['fp_dev_otp']);

            // Clean up used OTPs
            $pdo->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);

            // Redirect to login with success
            header("Location: login.php?reset=success");
            exit;
        } else {
            $error = implode("<br>", $errors);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reset Password — Trans-Phil House Hub</title>
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
.page{flex:1;display:flex;align-items:center;justify-content:center;padding:40px 16px;}
.box{background:var(--white);border-radius:20px;padding:40px 36px;width:100%;max-width:440px;border:1px solid var(--border);box-shadow:0 4px 24px rgba(26,58,107,.08);}
.steps{display:flex;align-items:center;justify-content:center;gap:0;margin-bottom:28px;}
.step{display:flex;flex-direction:column;align-items:center;gap:4px;}
.step-circle{width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;border:2px solid var(--border);color:var(--muted);background:var(--bg);}
.step.active .step-circle{background:var(--green);color:var(--white);border-color:var(--green);}
.step.done .step-circle{background:var(--green);color:var(--white);border-color:var(--green);}
.step-label{font-size:10px;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;}
.step.active .step-label,.step.done .step-label{color:var(--green);font-weight:600;}
.step-line{width:48px;height:2px;background:var(--border);margin-top:-14px;}
.step-line.done{background:var(--green);}
.icon-wrap{width:72px;height:72px;border-radius:50%;background:#eef2f9;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;}
.icon-wrap i{font-size:32px;color:var(--navy);}
.box-title{font-size:22px;font-weight:700;color:var(--navy);text-align:center;margin-bottom:6px;}
.box-sub{font-size:13px;color:var(--muted);text-align:center;margin-bottom:28px;line-height:1.5;}

/* FORM */
.fg{margin-bottom:18px;}
.fg label{display:block;font-size:13px;font-weight:600;color:var(--navy);margin-bottom:7px;}
.input-wrap{position:relative;}
.input-wrap i.icon{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:15px;}
.input-wrap input{width:100%;padding:12px 44px 12px 40px;border:1.5px solid var(--border);border-radius:10px;font-family:inherit;font-size:14px;color:#333;background:#fafaf8;transition:.2s;}
.input-wrap input:focus{outline:none;border-color:var(--navy);background:var(--white);}
.toggle-pw{position:absolute;right:14px;top:50%;transform:translateY(-50%);color:var(--muted);cursor:pointer;font-size:15px;border:none;background:none;padding:0;}
.toggle-pw:hover{color:var(--navy);}

/* PASSWORD STRENGTH */
.strength-wrap{margin-top:8px;}
.strength-bars{display:flex;gap:4px;margin-bottom:4px;}
.strength-bar{flex:1;height:4px;border-radius:2px;background:var(--border);transition:.3s;}
.strength-label{font-size:11px;color:var(--muted);}

/* REQUIREMENTS */
.requirements{margin-top:10px;display:grid;grid-template-columns:1fr 1fr;gap:4px;}
.req{font-size:11px;color:var(--muted);display:flex;align-items:center;gap:5px;transition:.2s;}
.req.met{color:var(--green);}
.req i{font-size:10px;}

.btn{width:100%;padding:13px;background:var(--green);color:var(--white);border:none;border-radius:10px;font-family:inherit;font-size:15px;font-weight:700;cursor:pointer;transition:.2s;display:flex;align-items:center;justify-content:center;gap:8px;margin-top:8px;}
.btn:hover{background:#218f1f;}
.btn:active{transform:scale(.98);}

.alert{padding:12px 16px;border-radius:10px;font-size:13px;margin-bottom:18px;display:flex;align-items:flex-start;gap:10px;}
.alert-error{background:#fef0f0;color:#a32d2d;border:1px solid #f7c1c1;}
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
            <div class="step done">
                <div class="step-circle"><i class="fas fa-check" style="font-size:12px;"></i></div>
                <div class="step-label">OTP</div>
            </div>
            <div class="step-line done"></div>
            <div class="step active">
                <div class="step-circle">3</div>
                <div class="step-label">Reset</div>
            </div>
        </div>

        <div class="icon-wrap">
            <i class="fas fa-lock-open"></i>
        </div>
        <div class="box-title">Create New Password</div>
        <div class="box-sub">Your identity has been verified. Set a strong new password for your account.</div>

        <?php if($error): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle" style="flex-shrink:0;margin-top:1px;"></i><span><?php echo $error; ?></span></div>
        <?php endif; ?>

        <form method="POST" id="resetForm">
            <!-- CSRF Token Field -->
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            
            <div class="fg">
                <label>New Password</label>
                <div class="input-wrap">
                    <i class="fas fa-lock icon"></i>
                    <input type="password" name="password" id="password" placeholder="Min. 8 characters" required>
                    <button type="button" class="toggle-pw" onclick="togglePw('password','eyeIcon1')">
                        <i class="fas fa-eye" id="eyeIcon1"></i>
                    </button>
                </div>
                <!-- Strength meter -->
                <div class="strength-wrap">
                    <div class="strength-bars">
                        <div class="strength-bar" id="b1"></div>
                        <div class="strength-bar" id="b2"></div>
                        <div class="strength-bar" id="b3"></div>
                        <div class="strength-bar" id="b4"></div>
                    </div>
                    <div class="strength-label" id="strength-label"></div>
                </div>
                <!-- Requirements -->
                <div class="requirements">
                    <div class="req" id="req-len"><i class="fas fa-circle"></i> 8+ characters</div>
                    <div class="req" id="req-upper"><i class="fas fa-circle"></i> Uppercase letter</div>
                    <div class="req" id="req-lower"><i class="fas fa-circle"></i> Lowercase letter</div>
                    <div class="req" id="req-num"><i class="fas fa-circle"></i> Number</div>
                    <div class="req" id="req-special"><i class="fas fa-circle"></i> Special character</div>
                </div>
            </div>

            <div class="fg">
                <label>Confirm Password</label>
                <div class="input-wrap">
                    <i class="fas fa-lock-open icon"></i>
                    <input type="password" name="confirm_password" id="confirm_password" placeholder="Re-enter password" required>
                    <button type="button" class="toggle-pw" onclick="togglePw('confirm_password','eyeIcon2')">
                        <i class="fas fa-eye" id="eyeIcon2"></i>
                    </button>
                </div>
                <div id="match-msg" style="font-size:11px;margin-top:6px;"></div>
            </div>

            <button type="submit" name="reset_password" class="btn">
                <i class="fas fa-check-circle"></i> Reset Password
            </button>
        </form>
    </div>
</div>

<footer>© <?php echo date('Y'); ?> Trans-Phil House Corporation. All rights reserved.</footer>

<script>
function togglePw(id, iconId){
    const el = document.getElementById(id);
    const ic = document.getElementById(iconId);
    el.type = el.type === 'password' ? 'text' : 'password';
    ic.className = el.type === 'password' ? 'fas fa-eye' : 'fas fa-eye-slash';
}

// Strength meter
const pw     = document.getElementById('password');
const bars   = [1,2,3,4].map(i => document.getElementById('b'+i));
const label  = document.getElementById('strength-label');
const reqs   = {
    len:     document.getElementById('req-len'),
    upper:   document.getElementById('req-upper'),
    lower:   document.getElementById('req-lower'),
    num:     document.getElementById('req-num'),
    special: document.getElementById('req-special'),
};
const colors  = ['#e24b4a','#f07800','#EF9F27','#2db12b'];
const labels  = ['Weak','Fair','Good','Strong'];

pw.addEventListener('input', () => {
    const v = pw.value;
    let score = 0;
    const checks = {
        len:     v.length >= 8,
        upper:   /[A-Z]/.test(v),
        lower:   /[a-z]/.test(v),
        num:     /[0-9]/.test(v),
        special: /[^a-zA-Z0-9]/.test(v),
    };
    Object.entries(checks).forEach(([k, ok]) => {
        reqs[k].classList.toggle('met', ok);
        reqs[k].querySelector('i').className = ok ? 'fas fa-check-circle' : 'fas fa-circle';
        if(ok) score++;
    });
    const lvl = Math.max(0, score - 1); // 0-3
    bars.forEach((b, i) => {
        b.style.background = i <= Math.floor(score/2) && score > 0 ? colors[Math.min(score-1,3)] : 'var(--border)';
    });
    label.textContent = v.length ? labels[Math.min(score-1,3)] || '' : '';
    label.style.color = colors[Math.min(score-1,3)] || 'var(--muted)';
});

// Match check
const cp = document.getElementById('confirm_password');
const mm = document.getElementById('match-msg');
cp.addEventListener('input', () => {
    if(!cp.value) { mm.textContent = ''; return; }
    if(cp.value === pw.value){
        mm.innerHTML = '<span style="color:var(--green);"><i class="fas fa-check-circle"></i> Passwords match</span>';
    } else {
        mm.innerHTML = '<span style="color:#a32d2d;"><i class="fas fa-times-circle"></i> Passwords do not match</span>';
    }
});
</script>

</body>
</html>