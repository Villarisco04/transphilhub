<?php
require_once 'includes/csrf.php';
require_once 'includes/db.php';
require_once 'includes/notifications.php'; // Add this line at the top

// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Asia/Manila');

$error = '';
$success = '';
$user_id = null; // Initialize variable

// Generate CSRF token for the form
$csrf_token = CSRFToken::generate();

// Handle registration
if(isset($_POST['register'])){
    // 🔒 CSRF PROTECTION - Verify token FIRST
    if(!isset($_POST['csrf_token']) || !CSRFToken::verify($_POST['csrf_token'])){
        $error = "Security validation failed. Please refresh the page and try again.";
    } else {
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $role = $_POST['role'];
        $phone = trim($_POST['phone']);
        $address = trim($_POST['address']);
        $terms = isset($_POST['terms']) ? true : false;
        
        // Validation
        $errors = [];
        
        // Full name validation
        if(empty($full_name)){
            $errors[] = "Full name is required.";
        } elseif(strlen($full_name) < 3){
            $errors[] = "Full name must be at least 3 characters.";
        } elseif(!preg_match("/^[a-zA-Z\s\.\-]+$/", $full_name)){
            $errors[] = "Full name can only contain letters, spaces, dots, and hyphens.";
        }
        
        // Email validation
        if(empty($email)){
            $errors[] = "Email address is required.";
        } elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)){
            $errors[] = "Invalid email format.";
        }
        
        // Phone validation
        if(empty($phone)){
            $errors[] = "Contact number is required.";
        } elseif(!preg_match("/^[0-9]{11}$/", $phone)){
            $errors[] = "Contact number must be 11 digits (e.g., 09123456789).";
        }
        
        // Password validation
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
        }
        
        // Confirm password
        if($password !== $confirm_password){
            $errors[] = "Passwords do not match.";
        }
        
        // Terms agreement
        if(!$terms){
            $errors[] = "You must agree to the Terms & Conditions.";
        }
        
        // Check if email already exists
        if(empty($errors)){
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if($stmt->rowCount() > 0){
                $errors[] = "Email address is already registered. Please login instead.";
            }
        }
        
        // If no errors, register user
        if(empty($errors)){
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password, role, phone, address, status) VALUES (?, ?, ?, ?, ?, ?, 'active')");
            
            if($stmt->execute([$full_name, $email, $hashed_password, $role, $phone, $address])){
                $user_id = $pdo->lastInsertId(); // Get the new user ID
                $success = "Registration successful! You can now login to your account.";
                
                // Log registration
                error_log("New user registered: $email as $role at " . date('Y-m-d H:i:s'));
                
                // ============================================
                // ADD NOTIFICATION FOR ADMIN (NEW REGISTRATION)
                // ============================================
                // This function is now defined in notifications.php
                notify_new_registration($user_id, $full_name);
                
                // ============================================
                // SEND WELCOME EMAIL (OPTIONAL - requires email config)
                // ============================================
                // Uncomment below when email is configured
                // require_once 'includes/email_notifications.php';
                // send_welcome_email($email, $full_name, $role);
                
            } else {
                $errors[] = "Registration failed. Please try again.";
            }
        }
        
        if(!empty($errors)){
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
    <title>Register - Trans-Phil House Hub</title>
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
            padding: 40px 20px;
        }

        .register-container {
            max-width: 550px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.08);
        }

        .register-header {
            background: linear-gradient(135deg, #1a3a6b 0%, #22508a 100%);
            padding: 40px 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .register-header::before {
            content: '';
            position: absolute;
            top: -50px;
            right: -50px;
            width: 200px;
            height: 200px;
            background: rgba(45, 177, 43, 0.08);
            border-radius: 50%;
        }

        .register-header::after {
            content: '';
            position: absolute;
            bottom: -30px;
            left: -30px;
            width: 150px;
            height: 150px;
            background: rgba(240, 120, 0, 0.08);
            border-radius: 50%;
        }

        .logo-img {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 15px;
            position: relative;
            z-index: 2;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }

        .register-header h1 {
            color: white;
            font-size: 28px;
            margin-bottom: 8px;
            position: relative;
            z-index: 2;
        }

        .register-header h1 span {
            color: #f07800;
        }

        .register-header p {
            color: rgba(255, 255, 255, 0.8);
            font-size: 14px;
            position: relative;
            z-index: 2;
        }

        .register-body {
            padding: 35px;
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

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 8px;
        }

        .form-group label .required {
            color: #dc2626;
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

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 14px 12px 42px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 14px;
            font-family: 'DM Sans', sans-serif;
            transition: all 0.3s ease;
            outline: none;
            background: #faf9f6;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: #2db12b;
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(45, 177, 43, 0.1);
        }

        .form-group textarea {
            padding: 12px 14px;
            resize: vertical;
        }

        .role-selector {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
        }

        .role-option {
            flex: 1;
            position: relative;
            cursor: pointer;
        }

        .role-option input {
            position: absolute;
            opacity: 0;
            cursor: pointer;
        }

        .role-card {
            padding: 15px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            text-align: center;
            transition: all 0.3s ease;
            background: #faf9f6;
        }

        .role-option input:checked + .role-card {
            border-color: #1a3a6b;
            background: linear-gradient(135deg, rgba(26, 58, 107, 0.05), rgba(26, 58, 107, 0.02));
        }

        .role-card i {
            font-size: 32px;
            margin-bottom: 8px;
            display: block;
        }

        .role-card i.fa-user { color: #2db12b; }
        .role-card i.fa-user-tie { color: #f07800; }
        .role-card i.fa-shield-alt { color: #1a3a6b; }

        .role-card .role-title {
            font-weight: 700;
            font-size: 14px;
            color: #1f2937;
        }

        .role-card .role-desc {
            font-size: 11px;
            color: #6b7280;
            margin-top: 4px;
        }

        .password-strength {
            margin-top: 8px;
        }

        .strength-meter {
            height: 4px;
            background: #e5e7eb;
            border-radius: 4px;
            overflow: hidden;
            margin-bottom: 5px;
        }

        .strength-bar {
            height: 100%;
            width: 0%;
            transition: width 0.3s ease;
        }

        .strength-text {
            font-size: 11px;
            color: #6b7280;
        }

        .strength-weak .strength-bar { background: #dc2626; width: 33%; }
        .strength-medium .strength-bar { background: #f07800; width: 66%; }
        .strength-strong .strength-bar { background: #2db12b; width: 100%; }

        .terms-group {
            margin: 20px 0;
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }

        .terms-group input {
            width: 18px;
            height: 18px;
            margin-top: 2px;
            cursor: pointer;
        }

        .terms-group label {
            font-size: 13px;
            color: #374151;
            cursor: pointer;
        }

        .terms-group a {
            color: #f07800;
            text-decoration: none;
            font-weight: 600;
        }

        .terms-group a:hover {
            text-decoration: underline;
        }

        .register-btn {
            width: 100%;
            background: #f07800;
            color: white;
            border: none;
            padding: 14px;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .register-btn:hover {
            background: #d86d00;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(240, 120, 0, 0.3);
        }

        .login-link {
            text-align: center;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            font-size: 14px;
            color: #6b7280;
        }

        .login-link a {
            color: #1a3a6b;
            text-decoration: none;
            font-weight: 600;
        }

        .login-link a:hover {
            color: #2db12b;
        }

        .password-hint {
            font-size: 11px;
            color: #9ca3af;
            margin-top: 5px;
        }

        @media (max-width: 600px) {
            body { padding: 20px; }
            .register-body { padding: 25px; }
            .role-selector { flex-direction: column; gap: 10px; }
        }
    </style>
</head>
<body>

<div class="register-container">
    <div class="register-header">
        <img src="assets/images/logo.jpg" alt="Trans-Phil Logo" class="logo-img">
        <h1>Trans-Phil <span>House Hub</span></h1>
        <p>Create your account to start your property journey</p>
    </div>

    <div class="register-body">
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
                <script>
                    setTimeout(function() {
                        window.location.href = 'login.php';
                    }, 3000);
                </script>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            
            <div class="role-selector">
                <label class="role-option">
                    <input type="radio" name="role" value="client" checked required>
                    <div class="role-card">
                        <i class="fas fa-user"></i>
                        <div class="role-title">Client</div>
                        <div class="role-desc">Buy / Rent Properties</div>
                    </div>
                </label>
                <label class="role-option">
                    <input type="radio" name="role" value="agent" required>
                    <div class="role-card">
                        <i class="fas fa-user-tie"></i>
                        <div class="role-title">Real Estate Agent</div>
                        <div class="role-desc">Sell / Manage Properties</div>
                    </div>
                </label>
            </div>

            <div class="form-group">
                <label>Full Name <span class="required">*</span></label>
                <div class="input-wrapper">
                    <i class="fas fa-user"></i>
                    <input type="text" name="full_name" placeholder="e.g., Juan Dela Cruz" required value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>">
                </div>
            </div>

            <div class="form-group">
                <label>Email Address <span class="required">*</span></label>
                <div class="input-wrapper">
                    <i class="fas fa-envelope"></i>
                    <input type="email" name="email" placeholder="your@email.com" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                </div>
            </div>

            <div class="form-group">
                <label>Contact Number <span class="required">*</span></label>
                <div class="input-wrapper">
                    <i class="fas fa-phone"></i>
                    <input type="tel" name="phone" placeholder="09123456789" required value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                </div>
            </div>

            <div class="form-group">
                <label>Address <span class="required">*</span></label>
                <div class="input-wrapper">
                    <i class="fas fa-map-marker-alt"></i>
                    <textarea name="address" rows="2" placeholder="Your complete address"><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
                </div>
            </div>

            <div class="form-group">
                <label>Password <span class="required">*</span></label>
                <div class="input-wrapper">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="password" id="password" placeholder="Create a strong password" required>
                </div>
                <div class="password-strength">
                    <div class="strength-meter">
                        <div class="strength-bar"></div>
                    </div>
                    <div class="strength-text" id="strengthText">Enter a password</div>
                </div>
                <div class="password-hint">
                    <i class="fas fa-info-circle"></i> Minimum 8 characters with uppercase, lowercase, and number
                </div>
            </div>

            <div class="form-group">
                <label>Confirm Password <span class="required">*</span></label>
                <div class="input-wrapper">
                    <i class="fas fa-check-circle"></i>
                    <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm your password" required>
                </div>
                <div id="passwordMatch" style="font-size: 11px; margin-top: 5px;"></div>
            </div>

            <div class="terms-group">
                <input type="checkbox" name="terms" id="terms" required>
                <label for="terms">
                    I agree to the <a href="#" id="termsLink">Terms & Conditions</a> and 
                    <a href="#" id="privacyLink">Privacy Policy</a>
                </label>
            </div>

            <button type="submit" name="register" class="register-btn">
                <i class="fas fa-user-plus"></i> Create Account
            </button>

            <div class="login-link">
                Already have an account? <a href="login.php">Sign In</a>
            </div>
        </form>
    </div>
</div>

<div id="termsModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 1000; align-items: center; justify-content: center;">
    <div style="background: white; max-width: 500px; width: 90%; max-height: 80vh; overflow-y: auto; border-radius: 16px; padding: 30px;">
        <h2 style="color: #1a3a6b; margin-bottom: 20px;">Terms & Conditions</h2>
        <div style="font-size: 13px; line-height: 1.6; color: #374151;">
            <h3>1. Account Registration</h3>
            <p>By creating an account, you agree to provide accurate and complete information.</p>
            <h3>2. Property Listings</h3>
            <p>All property listings are subject to verification.</p>
            <h3>3. Inquiries and Viewings</h3>
            <p>When you submit an inquiry, you agree to be contacted by our real estate agents.</p>
            <h3>4. Data Privacy</h3>
            <p>Your personal information is protected under the Data Privacy Act of 2012.</p>
            <h3>5. Code of Conduct</h3>
            <p>Users must not misuse the platform or engage in fraudulent activities.</p>
            <h3>6. Limitation of Liability</h3>
            <p>Trans-Phil House Corporation is not liable for any losses arising from property transactions.</p>
        </div>
        <button onclick="closeTermsModal()" style="margin-top: 20px; width: 100%; background: #1a3a6b; color: white; padding: 12px; border: none; border-radius: 8px; cursor: pointer;">I Understand & Accept</button>
    </div>
</div>

<script>
    const password = document.getElementById('password');
    const confirmPassword = document.getElementById('confirm_password');
    const strengthBar = document.querySelector('.strength-bar');
    const strengthText = document.getElementById('strengthText');
    const passwordMatch = document.getElementById('passwordMatch');

    function checkPasswordStrength(pwd) {
        let strength = 0;
        if (pwd.length >= 8) strength++;
        if (pwd.match(/[A-Z]/)) strength++;
        if (pwd.match(/[a-z]/)) strength++;
        if (pwd.match(/[0-9]/)) strength++;
        
        if (pwd.length === 0) return { level: 0, text: 'Enter a password' };
        if (strength === 1) return { level: 1, text: 'Weak - Add uppercase, lowercase, numbers' };
        if (strength === 2) return { level: 1, text: 'Weak - Add more variety' };
        if (strength === 3) return { level: 2, text: 'Medium - Good strength' };
        if (strength === 4) return { level: 3, text: 'Strong - Excellent!' };
        return { level: 0, text: '' };
    }

    password.addEventListener('input', function() {
        const result = checkPasswordStrength(this.value);
        strengthBar.parentElement.parentElement.classList.remove('strength-weak', 'strength-medium', 'strength-strong');
        if (result.level === 1) {
            strengthBar.parentElement.parentElement.classList.add('strength-weak');
        } else if (result.level === 2) {
            strengthBar.parentElement.parentElement.classList.add('strength-medium');
        } else if (result.level === 3) {
            strengthBar.parentElement.parentElement.classList.add('strength-strong');
        }
        strengthText.textContent = result.text;
        checkPasswordMatch();
    });

    function checkPasswordMatch() {
        if (confirmPassword.value.length > 0) {
            if (password.value === confirmPassword.value) {
                passwordMatch.innerHTML = '<i class="fas fa-check-circle" style="color: #2db12b;"></i> Passwords match';
                passwordMatch.style.color = '#2db12b';
            } else {
                passwordMatch.innerHTML = '<i class="fas fa-times-circle" style="color: #dc2626;"></i> Passwords do not match';
                passwordMatch.style.color = '#dc2626';
            }
        } else {
            passwordMatch.innerHTML = '';
        }
    }

    confirmPassword.addEventListener('input', checkPasswordMatch);

    const termsModal = document.getElementById('termsModal');
    
    document.getElementById('termsLink').addEventListener('click', function(e) {
        e.preventDefault();
        termsModal.style.display = 'flex';
    });
    
    document.getElementById('privacyLink').addEventListener('click', function(e) {
        e.preventDefault();
        termsModal.style.display = 'flex';
    });
    
    function closeTermsModal() {
        termsModal.style.display = 'none';
        document.getElementById('terms').checked = true;
    }
    
    termsModal.addEventListener('click', function(e) {
        if (e.target === termsModal) {
            closeTermsModal();
        }
    });
</script>

</body>
</html>