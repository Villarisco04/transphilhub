<?php
require_once '../includes/db.php';
session_start();

if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'client'){
    header("Location: ../login.php");
    exit;
}

$client_id = $_SESSION['user_id'];
$agent_id = isset($_GET['agent_id']) ? (int)$_GET['agent_id'] : 0;
$agent = null;
$existing_review = null;
$completed_transaction = null;

// Get agent details
if($agent_id) {
    $stmt = $pdo->prepare("SELECT id, full_name, email, phone, avg_rating, total_reviews FROM users WHERE id = ? AND role = 'agent'");
    $stmt->execute([$agent_id]);
    $agent = $stmt->fetch();
}

if(!$agent) {
    header("Location: dashboard.php");
    exit;
}

// CHECK IF CLIENT HAS A COMPLETED TRANSACTION WITH THIS AGENT
$stmt = $pdo->prepare("
    SELECT l.*, p.title as property_title, p.price
    FROM leads l
    JOIN properties p ON l.property_id = p.id
    WHERE l.client_id = ? AND l.agent_id = ? AND l.stage = 'closed'
    ORDER BY l.created_at DESC
    LIMIT 1
");
$stmt->execute([$client_id, $agent_id]);
$completed_transaction = $stmt->fetch();

if(!$completed_transaction) {
    $_SESSION['error'] = "You can only review agents after completing a transaction.";
    header("Location: dashboard.php");
    exit;
}

// Check if already reviewed
$stmt = $pdo->prepare("SELECT * FROM reviews WHERE client_id = ? AND agent_id = ?");
$stmt->execute([$client_id, $agent_id]);
$existing_review = $stmt->fetch();

// Handle review submission
$error = '';
$success = '';

if(isset($_POST['submit_review'])){
    $rating = (int)$_POST['rating'];
    $comment = trim($_POST['comment']);
    
    if($rating < 1 || $rating > 5){
        $error = "Please select a valid rating.";
    } elseif(empty($comment)){
        $error = "Please write a review comment.";
    } elseif(strlen($comment) < 10){
        $error = "Review must be at least 10 characters.";
    } else {
        if($existing_review){
            $stmt = $pdo->prepare("UPDATE reviews SET rating = ?, comment = ? WHERE client_id = ? AND agent_id = ?");
            $stmt->execute([$rating, $comment, $client_id, $agent_id]);
            $success = "Your review has been updated!";
        } else {
            $stmt = $pdo->prepare("INSERT INTO reviews (client_id, agent_id, rating, comment) VALUES (?, ?, ?, ?)");
            $stmt->execute([$client_id, $agent_id, $rating, $comment]);
            $success = "Thank you for your review! It will be visible after admin approval.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rate Your Agent - Trans-Phil House Hub</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root{--navy:#1a3a6b;--navy2:#0f2340;--green:#2db12b;--orange:#f07800;--bg:#f0eff5;--card:#ffffff;--border:#e4e2ee;--radius:14px;--shadow:0 2px 12px rgba(26,58,107,.07);}
        *{margin:0;padding:0;box-sizing:border-box;}
        body{font-family:'DM Sans',sans-serif;background:var(--bg);padding:40px 20px;}
        .container{max-width:800px;margin:0 auto;}
        .back-link{display:inline-flex;align-items:center;gap:8px;background:var(--card);padding:10px 20px;border-radius:30px;text-decoration:none;color:var(--navy);margin-bottom:25px;border:1px solid var(--border);}
        .card{background:var(--card);border-radius:var(--radius);border:1px solid var(--border);overflow:hidden;box-shadow:var(--shadow);}
        .card-header{background:linear-gradient(135deg,var(--navy2),var(--navy));padding:30px;text-align:center;color:white;}
        .card-header h1{font-size:24px;}
        .agent-info{display:flex;align-items:center;gap:20px;padding:25px;background:#f8f7fc;border-bottom:1px solid var(--border);}
        .agent-avatar{width:70px;height:70px;background:var(--navy);border-radius:50%;display:flex;align-items:center;justify-content:center;color:white;font-size:28px;font-weight:700;}
        .transaction-badge{background:#d1fae5;color:#065f46;padding:5px 12px;border-radius:20px;font-size:12px;display:inline-block;margin-top:8px;}
        .form-section{padding:30px;}
        .star-rating{display:flex;justify-content:center;gap:12px;flex-direction:row-reverse;margin:20px 0;}
        .star-rating input{display:none;}
        .star-rating label{font-size:32px;color:#e5e7eb;cursor:pointer;}
        .star-rating label:hover,.star-rating label:hover~label,.star-rating input:checked~label{color:#fbbf24;}
        .form-group{margin-bottom:20px;}
        .form-group label{display:block;font-size:13px;font-weight:600;margin-bottom:8px;}
        .form-group textarea{width:100%;padding:12px;border:2px solid var(--border);border-radius:12px;font-family:inherit;resize:vertical;}
        .btn-submit{width:100%;background:var(--orange);color:white;padding:14px;border:none;border-radius:12px;font-weight:700;cursor:pointer;}
        .alert{padding:12px 16px;border-radius:10px;margin-bottom:20px;}
        .alert-error{background:#fef2f2;border-left:4px solid #dc2626;color:#991b1b;}
        .alert-success{background:#ecfdf5;border-left:4px solid #10b981;color:#065f46;}
        @media(max-width:600px){.agent-info{flex-direction:column;text-align:center;}}
    </style>
</head>
<body>
<div class="container">
    <a href="dashboard.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
    <div class="card">
        <div class="card-header">
            <h1><i class="fas fa-star"></i> Rate Your Agent</h1>
        </div>
        <div class="agent-info">
            <div class="agent-avatar"><?php echo strtoupper(substr($agent['full_name'],0,1)); ?></div>
            <div>
                <h3><?php echo htmlspecialchars($agent['full_name']); ?></h3>
                <div class="transaction-badge">
                    <i class="fas fa-check-circle"></i> Completed: <?php echo htmlspecialchars($completed_transaction['property_title']); ?>
                </div>
            </div>
        </div>
        <div class="form-section">
            <?php if($error): ?>
                <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
            <?php endif; ?>
            <?php if($success): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div>
                <script>setTimeout(()=>window.location.href='dashboard.php',2000);</script>
            <?php endif; ?>
            <?php if(!$success): ?>
            <form method="POST">
                <div class="star-rating">
                    <input type="radio" name="rating" value="5" id="star5"><label for="star5"><i class="fas fa-star"></i></label>
                    <input type="radio" name="rating" value="4" id="star4"><label for="star4"><i class="fas fa-star"></i></label>
                    <input type="radio" name="rating" value="3" id="star3"><label for="star3"><i class="fas fa-star"></i></label>
                    <input type="radio" name="rating" value="2" id="star2"><label for="star2"><i class="fas fa-star"></i></label>
                    <input type="radio" name="rating" value="1" id="star1"><label for="star1"><i class="fas fa-star"></i></label>
                </div>
                <div class="form-group">
                    <label>Your Review</label>
                    <textarea name="comment" rows="5" placeholder="Share your experience with this agent..."></textarea>
                </div>
                <button type="submit" name="submit_review" class="btn-submit"><i class="fas fa-paper-plane"></i> Submit Review</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>