<?php 
include 'includes/header.php';

// Get dynamic metrics from database
require_once 'includes/db.php';

// Total properties count
$stmt = $pdo->query("SELECT COUNT(*) FROM properties");
$total_properties = $stmt->fetchColumn();

// Total agents count (active only)
$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'agent' AND status = 'active'");
$total_agents = $stmt->fetchColumn();

// Total closed deals (completed transactions)
$stmt = $pdo->query("SELECT COUNT(*) FROM leads WHERE stage = 'closed'");
$closed_deals = $stmt->fetchColumn();

// Get featured properties (limit 3)
$featured_properties = $pdo->query("SELECT * FROM properties WHERE is_featured = 1 ORDER BY created_at DESC LIMIT 3")->fetchAll();

// If no featured properties, get latest 3 properties
if(empty($featured_properties)){
    $featured_properties = $pdo->query("SELECT * FROM properties ORDER BY created_at DESC LIMIT 3")->fetchAll();
}

// Get the admin-selected featured review (is_featured = 1)
$featured_review = $pdo->query("
    SELECT r.*, u.full_name as client_name, a.full_name as agent_name
    FROM reviews r
    JOIN users u ON r.client_id = u.id
    JOIN users a ON r.agent_id = a.id
    WHERE r.is_approved = 1 AND r.is_featured = 1
    LIMIT 1
")->fetch();

// Get regular approved reviews for display (limit 3)
$reviews = $pdo->query("
    SELECT r.*, u.full_name as client_name, a.full_name as agent_name
    FROM reviews r
    JOIN users u ON r.client_id = u.id
    JOIN users a ON r.agent_id = a.id
    WHERE r.is_approved = 1 AND (r.is_featured = 0 OR r.is_featured IS NULL)
    ORDER BY r.created_at DESC
    LIMIT 3
")->fetchAll();

// If not enough reviews, get more
if(count($reviews) < 3 && $featured_review){
    $remaining = 3 - count($reviews);
    $extra = $pdo->query("
        SELECT r.*, u.full_name as client_name, a.full_name as agent_name
        FROM reviews r
        JOIN users u ON r.client_id = u.id
        JOIN users a ON r.agent_id = a.id
        WHERE r.is_approved = 1 AND r.id != {$featured_review['id']}
        ORDER BY r.created_at DESC
        LIMIT $remaining
    ")->fetchAll();
    $reviews = array_merge($reviews, $extra);
}
?>

<div class="hero">
    <div class="hero-badge">
        🏠 Trusted Real Estate 
    </div>
    
    <h1>
        Find Your Dream<br>
        <span>Property</span> With Us
    </h1>
    
    <p>
        A secure, cloud-based real estate platform
        for Trans-Phil House Corporation —
        connecting buyers, renters, and agents seamlessly.
    </p>
    
    <div class="hero-btns">
        <a href="properties.php" class="hbtn1">
            Browse Properties
        </a>
    </div>
    
    <div class="hero-stats">
        <div class="hstat">
            <div class="hstat-num"><?php echo number_format($total_properties); ?>+</div>
            <div class="hstat-label">Properties Listed</div>
        </div>
        <div class="hstat">
            <div class="hstat-num"><?php echo number_format($total_agents); ?></div>
            <div class="hstat-label">Expert Agents</div>
        </div>
        <div class="hstat">
            <div class="hstat-num"><?php echo number_format($closed_deals); ?>+</div>
            <div class="hstat-label">Closed Deals</div>
        </div>
    </div>
</div>

<div class="search-bar">
    <div class="sb-group">
        <div class="sb-label">Location</div>
        <input class="sb-input" placeholder="e.g. Makati, BGC, Pasig">
    </div>
    <div class="sb-group">
        <div class="sb-label">Property Type</div>
        <input class="sb-input" placeholder="For Sale / Rent / Project">
    </div>
    <div class="sb-group">
        <div class="sb-label">Price Range</div>
        <input class="sb-input" placeholder="₱ Min — Max">
    </div>
    <button class="sb-btn" onclick="window.location.href='properties.php'">Search</button>
</div>

<div class="section">
    <div class="sec-header">
        <div class="sec-title">
            Featured <span>Properties</span>
        </div>
        <a href="properties.php" class="sec-link">View All →</a>
    </div>
    
    <div class="cards">
        <?php if(count($featured_properties) > 0): ?>
            <?php foreach($featured_properties as $property): ?>
                <div class="card">
                    <div class="card-img">
                        <?php 
                        $img = !empty($property['image']) ? $property['image'] : 'property1.png';
                        ?>
                        <img src="assets/images/<?php echo $img; ?>" onerror="this.src='assets/images/property1.png'">
                        <span class="card-badge badge-<?php echo $property['type']; ?>">
                            <?php echo $property['type'] == 'sale' ? 'For Sale' : ($property['type'] == 'rent' ? 'For Rent' : 'Project'); ?>
                        </span>
                        <?php if($property['is_featured']): ?>
                            <span class="card-feat">★ Featured</span>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <div class="card-price">
                            ₱ <?php echo number_format($property['price'], 0); ?>
                            <span><?php echo $property['type'] == 'rent' ? '/ mo' : ($property['type'] == 'sale' ? '/ unit' : ''); ?></span>
                        </div>
                        <div class="card-title"><?php echo htmlspecialchars($property['title']); ?></div>
                        <div class="card-loc">📍 <?php echo htmlspecialchars($property['location']); ?></div>
                        <?php if(isset($_SESSION['user_id']) && $_SESSION['role'] == 'client'): ?>
                            <a href="inquiry.php?property_id=<?php echo $property['id']; ?>" class="property-btn">Inquire Now</a>
                        <?php elseif(isset($_SESSION['user_id'])): ?>
                            <a href="inquiry.php?property_id=<?php echo $property['id']; ?>" class="property-btn">Send Inquiry</a>
                        <?php else: ?>
                            <a href="login.php" class="property-btn">Login to Inquire</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <!-- Fallback static cards if no properties in database -->
            <div class="card">
                <div class="card-img">
                    <img src="assets/images/property1.png">
                    <span class="card-badge badge-sale">For Sale</span>
                    <span class="card-feat">★ Featured</span>
                </div>
                <div class="card-body">
                    <div class="card-price">₱ 4,500,000 <span>/ unit</span></div>
                    <div class="card-title">3BR Townhouse — San Antonio</div>
                    <div class="card-loc">📍 Makati City</div>
                    <?php if(isset($_SESSION['user_id'])): ?>
                        <a href="inquiry.php?property_id=1" class="property-btn">Inquire Now</a>
                    <?php else: ?>
                        <a href="login.php" class="property-btn">Login to Inquire</a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card">
                <div class="card-img">
                    <img src="assets/images/property2.png">
                    <span class="card-badge badge-rent">For Rent</span>
                </div>
                <div class="card-body">
                    <div class="card-price">₱ 18,000 <span>/ mo</span></div>
                    <div class="card-title">2BR Condo Unit — Chino Roces</div>
                    <div class="card-loc">📍 Makati City</div>
                    <?php if(isset($_SESSION['user_id'])): ?>
                        <a href="inquiry.php?property_id=2" class="property-btn">Inquire Now</a>
                    <?php else: ?>
                        <a href="login.php" class="property-btn">Login to Inquire</a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card">
                <div class="card-img">
                    <img src="assets/images/property3.png">
                    <span class="card-badge badge-proj">Project</span>
                </div>
                <div class="card-body">
                    <div class="card-price">₱ 6,200,000 <span>/ unit</span></div>
                    <div class="card-title">4BR House & Lot — Bagtikan</div>
                    <div class="card-loc">📍 San Antonio, Makati</div>
                    <?php if(isset($_SESSION['user_id'])): ?>
                        <a href="inquiry.php?property_id=3" class="property-btn">Inquire Now</a>
                    <?php else: ?>
                        <a href="login.php" class="property-btn">Login to Inquire</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- FEATURED REVIEW SECTION (Admin selected) -->
<?php if($featured_review): ?>
<div class="featured-review-section">
    <div class="featured-review-badge">
        <i class="fas fa-star"></i> Client Spotlight
    </div>
    <div class="featured-review-content">
        <i class="fas fa-quote-left featured-quote"></i>
        <p class="featured-review-text">
            "<?php echo htmlspecialchars($featured_review['comment']); ?>"
        </p>
        <div class="featured-review-stars">
            <?php for($i = 1; $i <= 5; $i++): ?>
                <i class="fas fa-star <?php echo $i <= $featured_review['rating'] ? '' : 'empty'; ?>"></i>
            <?php endfor; ?>
        </div>
        <div class="featured-review-client">
            <strong><?php echo htmlspecialchars($featured_review['client_name']); ?></strong>
            <span>— Reviewed <?php echo date('M d, Y', strtotime($featured_review['created_at'])); ?></span>
        </div>
        <div class="featured-review-agent">
            <i class="fas fa-user-tie"></i> Assisted by: <?php echo htmlspecialchars($featured_review['agent_name']); ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Regular Reviews Section -->
<div class="reviews-section">
    <div class="reviews-header">
        <h2>What Our <span>Clients Say</span></h2>
        <p>Real stories from our happy homeowners and investors</p>
    </div>
    
    <div class="reviews-grid">
        <?php if(count($reviews) > 0): ?>
            <?php foreach($reviews as $review): ?>
                <div class="review-card">
                    <div class="review-quote">
                        <i class="fas fa-quote-left"></i>
                    </div>
                    <div class="review-text">
                        "<?php echo htmlspecialchars(substr($review['comment'], 0, 150)); ?>..."
                    </div>
                    <div class="review-stars">
                        <?php for($i = 1; $i <= 5; $i++): ?>
                            <i class="fas fa-star <?php echo $i <= $review['rating'] ? '' : 'empty'; ?>"></i>
                        <?php endfor; ?>
                    </div>
                    <div class="review-client">
                        <strong><?php echo htmlspecialchars($review['client_name']); ?></strong>
                        <span>via <?php echo htmlspecialchars($review['agent_name']); ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <!-- Sample reviews if no reviews in database -->
            <div class="review-card">
                <div class="review-quote">
                    <i class="fas fa-quote-left"></i>
                </div>
                <div class="review-text">
                    "The team at Trans-Phil helped us find our dream home in just 2 weeks! Professional, responsive, and very knowledgeable about the Makati real estate market."
                </div>
                <div class="review-stars">
                    <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                </div>
                <div class="review-client">
                    <strong>Maria Santos</strong>
                    <span>Homeowner</span>
                </div>
            </div>
            <div class="review-card">
                <div class="review-quote">
                    <i class="fas fa-quote-left"></i>
                </div>
                <div class="review-text">
                    "Excellent service! Their agents were very accommodating and helped us find the perfect condo unit within our budget. Highly recommended!"
                </div>
                <div class="review-stars">
                    <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                </div>
                <div class="review-client">
                    <strong>John Dela Cruz</strong>
                    <span>Investor</span>
                </div>
            </div>
            <div class="review-card">
                <div class="review-quote">
                    <i class="fas fa-quote-left"></i>
                </div>
                <div class="review-text">
                    "I've been using Trans-Phil for my real estate investments. They always provide accurate listings and professional service. 5 stars!"
                </div>
                <div class="review-stars">
                    <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                </div>
                <div class="review-client">
                    <strong>Anna Reyes</strong>
                    <span>Property Investor</span>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
/* Reviews Section Styles */
.reviews-section {
    max-width: 1200px;
    margin: 60px auto;
    padding: 0 24px;
}

.reviews-header {
    text-align: center;
    margin-bottom: 40px;
}

.reviews-header h2 {
    font-size: 32px;
    color: #1a3a6b;
    font-weight: 700;
}

.reviews-header h2 span {
    color: #f07800;
}

.reviews-header p {
    color: #6b7280;
    font-size: 14px;
    margin-top: 8px;
}

.reviews-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    gap: 30px;
}

.review-card {
    background: white;
    border-radius: 16px;
    padding: 30px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    border: 1px solid #e4e2ee;
    transition: transform 0.3s;
}

.review-card:hover {
    transform: translateY(-5px);
}

.review-quote i {
    font-size: 32px;
    color: #f07800;
    opacity: 0.5;
    margin-bottom: 15px;
}

.review-text {
    font-size: 14px;
    line-height: 1.7;
    color: #1e1c2e;
    margin-bottom: 20px;
    font-style: italic;
}

.review-stars {
    margin-bottom: 15px;
}

.review-stars i {
    color: #fbbf24;
    font-size: 14px;
    margin-right: 2px;
}

.review-stars i.empty {
    color: #e5e7eb;
}

.review-client {
    border-top: 1px solid #e4e2ee;
    padding-top: 15px;
}

.review-client strong {
    display: block;
    color: #1a3a6b;
    font-size: 14px;
    margin-bottom: 4px;
}

.review-client span {
    font-size: 11px;
    color: #6b7280;
}

/* Featured Review Section Styles */
.featured-review-section {
    max-width: 900px;
    margin: 50px auto;
    background: linear-gradient(135deg, #1a3a6b 0%, #22508a 100%);
    border-radius: 20px;
    padding: 40px;
    position: relative;
    box-shadow: 0 10px 30px rgba(0,0,0,0.15);
}

.featured-review-badge {
    position: absolute;
    top: -15px;
    left: 50%;
    transform: translateX(-50%);
    background: #f07800;
    color: white;
    padding: 5px 20px;
    border-radius: 30px;
    font-size: 12px;
    font-weight: 600;
    white-space: nowrap;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.featured-review-content {
    text-align: center;
    color: white;
}

.featured-quote {
    font-size: 48px;
    color: rgba(255,255,255,0.2);
    margin-bottom: 20px;
}

.featured-review-text {
    font-size: 20px;
    line-height: 1.6;
    margin-bottom: 25px;
    font-style: italic;
    font-weight: 500;
}

.featured-review-stars {
    margin-bottom: 15px;
}

.featured-review-stars i {
    color: #fbbf24;
    font-size: 18px;
    margin: 0 2px;
}

.featured-review-stars i.empty {
    color: rgba(255,255,255,0.3);
}

.featured-review-client {
    margin-bottom: 10px;
}

.featured-review-client strong {
    font-size: 16px;
    display: block;
    margin-bottom: 5px;
}

.featured-review-client span {
    font-size: 12px;
    opacity: 0.8;
}

.featured-review-agent {
    font-size: 12px;
    opacity: 0.7;
    display: inline-block;
    background: rgba(255,255,255,0.15);
    padding: 5px 15px;
    border-radius: 20px;
}

@media (max-width: 768px) {
    .reviews-grid {
        grid-template-columns: 1fr;
    }
    .reviews-section {
        padding: 0 16px;
    }
    .featured-review-section {
        margin: 30px 16px;
        padding: 30px 20px;
    }
    .featured-review-text {
        font-size: 16px;
    }
    .featured-quote {
        font-size: 32px;
    }
}
</style>

<?php include 'includes/footer.php'; ?>