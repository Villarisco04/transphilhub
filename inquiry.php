<?php
// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/csrf.php';
require_once 'includes/db.php';

// Redirect if not logged in
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'client'){
    header("Location: login.php");
    exit;
}

// Generate CSRF token for the form
$csrf_token = CSRFToken::generate();

$property_id = isset($_GET['property_id']) ? (int)$_GET['property_id'] : 0;
$property = null;

// Get property details
if($property_id) {
    $stmt = $pdo->prepare("SELECT * FROM properties WHERE id = ?");
    $stmt->execute([$property_id]);
    $property = $stmt->fetch();
}

if(!$property) {
    header("Location: properties.php");
    exit;
}

// Handle inquiry submission
$error = '';
$success = '';

if(isset($_POST['submit_inquiry'])){
    // 🔒 CSRF PROTECTION - Verify token FIRST
    if(!isset($_POST['csrf_token']) || !CSRFToken::verify($_POST['csrf_token'])){
        $error = "Security validation failed. Please refresh the page and try again.";
    } else {
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $message = trim($_POST['message']);
        $inquiry_type = $_POST['inquiry_type'];
        $preferred_date = $_POST['preferred_date'];
        $preferred_time = $_POST['preferred_time'];
        $budget = $_POST['budget'];
        $preferred_contact = $_POST['preferred_contact'];
        
        // Validation
        $errors = [];
        if(empty($full_name)) $errors[] = "Full name is required";
        if(empty($email)) $errors[] = "Email address is required";
        if(!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email format";
        if(empty($phone)) $errors[] = "Contact number is required";
        if(!preg_match("/^[0-9]{11}$/", $phone)) $errors[] = "Contact number must be 11 digits";
        if(empty($message)) $errors[] = "Message is required";
        
        if(empty($errors)){
            // Insert lead with all details
            $notes = "=== INQUIRY DETAILS ===\n";
            $notes .= "Inquiry Type: " . ucfirst($inquiry_type) . "\n";
            $notes .= "Preferred Date: " . ($preferred_date ?: 'Not specified') . "\n";
            $notes .= "Preferred Time: " . ($preferred_time ?: 'Not specified') . "\n";
            $notes .= "Budget Range: " . ($budget ?: 'Not specified') . "\n";
            $notes .= "Preferred Contact: " . ($preferred_contact ?: 'Not specified') . "\n";
            $notes .= "\n=== MESSAGE ===\n";
            $notes .= $message . "\n";
            $notes .= "\n=== CONTACT INFO ===\n";
            $notes .= "Email: $email\n";
            $notes .= "Phone: $phone";
            
            $stmt = $pdo->prepare("INSERT INTO leads (client_id, property_id, stage, notes) VALUES (?, ?, 'new', ?)");
            
            if($stmt->execute([$_SESSION['user_id'], $property_id, $notes])){
                $success = true;
            } else {
                $error = "Failed to submit inquiry. Please try again.";
            }
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
    <title>Property Inquiry - Trans-Phil House Hub</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --navy: #1a3a6b;
            --navy2: #0f2340;
            --green: #2db12b;
            --green2: #218f1f;
            --orange: #f07800;
            --orange2: #d86d00;
            --white: #ffffff;
            --bg: #f0eff5;
            --card: #ffffff;
            --border: #e4e2ee;
            --text: #1e1c2e;
            --muted: #6b6880;
            --radius: 14px;
            --shadow: 0 2px 12px rgba(26,58,107,.07);
            --shadow-lg: 0 8px 30px rgba(0,0,0,0.12);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--bg);
            min-height: 100vh;
            padding: 40px 20px;
        }

        .inquiry-container {
            max-width: 1300px;
            margin: 0 auto;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: var(--card);
            padding: 10px 22px;
            border-radius: 40px;
            text-decoration: none;
            color: var(--navy);
            font-weight: 500;
            font-size: 13px;
            margin-bottom: 25px;
            transition: all 0.3s ease;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
        }

        .back-link:hover {
            transform: translateX(-5px);
            color: var(--orange);
            border-color: var(--orange);
        }

        .inquiry-grid {
            display: grid;
            grid-template-columns: 1fr 1.2fr;
            gap: 30px;
        }

        .property-card {
            background: var(--card);
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: var(--shadow-lg);
            position: sticky;
            top: 20px;
            border: 1px solid var(--border);
        }

        .property-image {
            height: 280px;
            overflow: hidden;
            position: relative;
            background: linear-gradient(135deg, var(--navy), var(--navy2));
        }

        .property-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .property-image:hover img {
            transform: scale(1.05);
        }

        .property-type {
            position: absolute;
            top: 15px;
            left: 15px;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            z-index: 2;
        }

        .type-sale { background: var(--green); color: white; }
        .type-rent { background: var(--orange); color: white; }
        .type-project { background: var(--navy); color: white; }

        .property-details {
            padding: 25px;
        }

        .property-price {
            font-size: 28px;
            font-weight: 800;
            color: var(--navy);
            margin-bottom: 8px;
        }

        .property-price span {
            font-size: 13px;
            font-weight: 400;
            color: var(--muted);
        }

        .property-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 8px;
        }

        .property-location {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--muted);
            font-size: 13px;
            margin-bottom: 18px;
            padding-bottom: 18px;
            border-bottom: 1px solid var(--border);
        }

        .property-description {
            color: var(--muted);
            line-height: 1.7;
            font-size: 13px;
            margin-bottom: 18px;
        }

        .property-features {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid var(--border);
        }

        .feature {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            color: var(--text);
            background: var(--bg);
            padding: 6px 12px;
            border-radius: 20px;
        }

        .feature i {
            color: var(--orange);
            font-size: 12px;
        }

        .form-card {
            background: var(--card);
            border-radius: var(--radius);
            padding: 32px;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border);
        }

        .form-header {
            text-align: center;
            margin-bottom: 28px;
        }

        .form-header h2 {
            font-size: 26px;
            color: var(--navy);
            margin-bottom: 8px;
            font-weight: 700;
        }

        .form-header p {
            color: var(--muted);
            font-size: 13px;
        }

        .alert {
            padding: 14px 18px;
            border-radius: 12px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 13px;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from { transform: translateY(-10px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .alert-success {
            background: #ecfdf5;
            border-left: 4px solid var(--green);
            color: #065f46;
        }

        .alert-error {
            background: #fef2f2;
            border-left: 4px solid #dc2626;
            color: #991b1b;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
            margin-bottom: 18px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            font-size: 12px;
            color: var(--text);
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-group label .required {
            color: #dc2626;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 14px;
            border: 2px solid var(--border);
            border-radius: 12px;
            font-size: 13px;
            font-family: 'DM Sans', sans-serif;
            transition: all 0.3s ease;
            background: #fafaf8;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--orange);
            background: white;
            box-shadow: 0 0 0 3px rgba(240, 120, 0, 0.1);
        }

        .schedule-section {
            background: linear-gradient(135deg, #fafaf8, #f5f4f0);
            border-radius: 16px;
            padding: 20px;
            margin: 20px 0;
            border: 1px solid var(--border);
        }

        .schedule-section h4 {
            color: var(--navy);
            margin-bottom: 15px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 700;
        }

        .schedule-section h4 i {
            color: var(--orange);
        }

        .submit-btn {
            width: 100%;
            background: linear-gradient(135deg, var(--orange), var(--orange2));
            color: white;
            padding: 14px;
            border: none;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 10px;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(240, 120, 0, 0.3);
        }

        .contact-info {
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid var(--border);
            text-align: center;
        }

        .contact-info p {
            font-size: 11px;
            color: var(--muted);
            margin-top: 8px;
        }

        .contact-info i {
            color: var(--green);
            margin-right: 5px;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(4px);
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            max-width: 450px;
            width: 90%;
            padding: 35px;
            border-radius: 24px;
            text-align: center;
            animation: modalSlideIn 0.3s ease;
        }

        @keyframes modalSlideIn {
            from {
                transform: scale(0.9);
                opacity: 0;
            }
            to {
                transform: scale(1);
                opacity: 1;
            }
        }

        .modal-icon {
            width: 80px;
            height: 80px;
            background: #ecfdf5;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }

        .modal-icon i {
            font-size: 40px;
            color: var(--green);
        }

        .modal-content h3 {
            font-size: 22px;
            color: var(--navy);
            margin-bottom: 10px;
        }

        .modal-content p {
            color: var(--muted);
            font-size: 13px;
            margin-bottom: 25px;
            line-height: 1.6;
        }

        .modal-buttons {
            display: flex;
            gap: 12px;
            justify-content: center;
        }

        .modal-btn {
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
            font-family: 'DM Sans', sans-serif;
            text-decoration: none;
            display: inline-block;
        }

        .modal-btn-primary {
            background: var(--orange);
            color: white;
        }

        .modal-btn-primary:hover {
            background: var(--orange2);
            transform: translateY(-2px);
        }

        @media (max-width: 900px) {
            .inquiry-grid {
                grid-template-columns: 1fr;
            }
            .property-card {
                position: relative;
                top: 0;
            }
            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }
            body {
                padding: 20px;
            }
            .form-card {
                padding: 25px;
            }
        }

        @media (max-width: 480px) {
            .form-card {
                padding: 20px;
            }
            .property-price {
                font-size: 24px;
            }
            .property-title {
                font-size: 18px;
            }
        }
    </style>
</head>
<body>

<div class="inquiry-container">
    <a href="properties.php" class="back-link">
        <i class="fas fa-arrow-left"></i> Back to Properties
    </a>

    <div class="inquiry-grid">
        <div class="property-card">
            <div class="property-image">
                <?php 
                $img = !empty($property['image']) ? $property['image'] : 'property1.png';
                $image_path = 'assets/images/' . $img;
                if(!file_exists($image_path) && !empty($property['image']) && file_exists('assets/uploads/' . $property['image'])){
                    $image_path = 'assets/uploads/' . $property['image'];
                }
                ?>
                <img src="<?php echo $image_path; ?>" alt="<?php echo htmlspecialchars($property['title']); ?>" onerror="this.src='assets/images/property1.png'">
                <span class="property-type type-<?php echo $property['type']; ?>">
                    <?php echo $property['type'] == 'sale' ? 'For Sale' : ($property['type'] == 'rent' ? 'For Rent' : 'Development Project'); ?>
                </span>
            </div>
            <div class="property-details">
                <div class="property-price">
                    ₱ <?php echo number_format($property['price'], 0); ?>
                    <span><?php echo $property['type'] == 'rent' ? '/month' : ($property['type'] == 'sale' ? '' : '/starting price'); ?></span>
                </div>
                <h1 class="property-title"><?php echo htmlspecialchars($property['title']); ?></h1>
                <div class="property-location">
                    <i class="fas fa-map-marker-alt"></i>
                    <?php echo htmlspecialchars($property['location']); ?>
                </div>
                <div class="property-description">
                    <?php echo htmlspecialchars($property['description'] ?: 'A premium property offering modern amenities and prime location. Perfect for families and investors seeking value and comfort.'); ?>
                </div>
                <div class="property-features">
                    <div class="feature"><i class="fas fa-bed"></i> <?php echo $property['bedrooms'] ?? '3-4'; ?> Bedrooms</div>
                    <div class="feature"><i class="fas fa-bath"></i> <?php echo $property['bathrooms'] ?? '2-3'; ?> Bathrooms</div>
                    <div class="feature"><i class="fas fa-ruler-combined"></i> <?php echo $property['area'] ?? '60-120'; ?> sqm</div>
                    <div class="feature"><i class="fas fa-parking"></i> Parking Available</div>
                </div>
            </div>
        </div>

        <div class="form-card">
            <div class="form-header">
                <h2>Schedule a Viewing</h2>
                <p>Fill out the form below and our agent will contact you within 24 hours</p>
            </div>

            <?php if($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    Inquiry submitted successfully! Our agent will contact you soon.
                </div>
            <?php endif; ?>

            <form method="POST" id="inquiryForm">
                <!-- CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Full Name <span class="required">*</span></label>
                        <input type="text" name="full_name" value="<?php echo htmlspecialchars($_SESSION['full_name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Email Address <span class="required">*</span></label>
                        <input type="email" name="email" placeholder="your@email.com" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Contact Number <span class="required">*</span></label>
                        <input type="tel" name="phone" placeholder="09123456789" required>
                    </div>
                    <div class="form-group">
                        <label>Inquiry Type</label>
                        <select name="inquiry_type">
                            <option value="viewing">📅 Schedule a Viewing</option>
                            <option value="information">ℹ️ Request More Information</option>
                            <option value="negotiation">💬 Price Negotiation</option>
                            <option value="financing">💰 Financing Assistance</option>
                        </select>
                    </div>
                </div>

                <div class="schedule-section">
                    <h4><i class="fas fa-calendar-alt"></i> Preferred Schedule</h4>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Preferred Date</label>
                            <input type="date" name="preferred_date" min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="form-group">
                            <label>Preferred Time</label>
                            <select name="preferred_time">
                                <option value="">Select Time</option>
                                <option value="9:00 AM - 10:00 AM">9:00 AM - 10:00 AM</option>
                                <option value="10:00 AM - 11:00 AM">10:00 AM - 11:00 AM</option>
                                <option value="11:00 AM - 12:00 PM">11:00 AM - 12:00 PM</option>
                                <option value="1:00 PM - 2:00 PM">1:00 PM - 2:00 PM</option>
                                <option value="2:00 PM - 3:00 PM">2:00 PM - 3:00 PM</option>
                                <option value="3:00 PM - 4:00 PM">3:00 PM - 4:00 PM</option>
                                <option value="4:00 PM - 5:00 PM">4:00 PM - 5:00 PM</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Budget Range</label>
                        <select name="budget">
                            <option value="Not Specified">💰 Select Budget Range</option>
                            <option value="Below ₱1M">Below ₱1M</option>
                            <option value="₱1M - ₱3M">₱1M - ₱3M</option>
                            <option value="₱3M - ₱5M">₱3M - ₱5M</option>
                            <option value="₱5M - ₱10M">₱5M - ₱10M</option>
                            <option value="Above ₱10M">Above ₱10M</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Preferred Contact Method</label>
                        <select name="preferred_contact">
                            <option value="Phone Call">📞 Phone Call</option>
                            <option value="Email">✉️ Email</option>
                            <option value="SMS/Text">📱 SMS/Text</option>
                            <option value="Viber/WhatsApp">💬 Viber/WhatsApp</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Message / Questions <span class="required">*</span></label>
                    <textarea name="message" rows="4" placeholder="Tell us more about what you're looking for..."></textarea>
                </div>

                <button type="submit" name="submit_inquiry" class="submit-btn">
                    <i class="fas fa-paper-plane"></i> Submit Inquiry
                </button>

                <div class="contact-info">
                    <p><i class="fas fa-shield-alt"></i> Your information is secure and will only be used by our agents to assist you</p>
                    <p><i class="fas fa-clock"></i> Response time: Within 24 hours</p>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="successModal" class="modal">
    <div class="modal-content">
        <div class="modal-icon">
            <i class="fas fa-check-circle"></i>
        </div>
        <h3>Inquiry Submitted!</h3>
        <p>Thank you for your interest. One of our real estate agents will contact you within 24 hours to schedule your property viewing.</p>
        <div class="modal-buttons">
            <a href="properties.php" class="modal-btn modal-btn-primary">Browse More Properties</a>
        </div>
    </div>
</div>

<script>
    <?php if($success && !$error): ?>
    document.getElementById('successModal').classList.add('active');
    
    document.getElementById('successModal').addEventListener('click', function(e) {
        if(e.target === this) {
            this.classList.remove('active');
        }
    });
    <?php endif; ?>
</script>

</body>
</html>