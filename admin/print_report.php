<?php
require_once '../includes/db.php';
session_start();

if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin'){
    header("Location: ../login.php");
    exit;
}

$date_from = isset($_GET['from']) ? $_GET['from'] : date('Y-m-01');
$date_to = isset($_GET['to']) ? $_GET['to'] : date('Y-m-d');

// Fetch all data for comprehensive report

// 1. Properties Data
$stmt = $pdo->prepare("
    SELECT * FROM properties 
    WHERE created_at BETWEEN ? AND ?
    ORDER BY created_at DESC
");
$stmt->execute([$date_from, $date_to . ' 23:59:59']);
$properties = $stmt->fetchAll();

// 2. Property Type Distribution
$type_stats = $pdo->query("
    SELECT type, COUNT(*) as count, SUM(price) as total_value 
    FROM properties 
    GROUP BY type
")->fetchAll();

// 3. Users Data
$stmt = $pdo->prepare("
    SELECT id, full_name, email, role, status, phone, created_at 
    FROM users 
    WHERE created_at BETWEEN ? AND ?
    ORDER BY created_at DESC
");
$stmt->execute([$date_from, $date_to . ' 23:59:59']);
$users = $stmt->fetchAll();

// 4. Role Distribution
$role_stats = $pdo->query("
    SELECT role, COUNT(*) as count 
    FROM users 
    GROUP BY role
")->fetchAll();

// 5. Leads Data with full details
$stmt = $pdo->prepare("
    SELECT l.*, 
           c.full_name as client_name, c.email as client_email,
           p.title as property_title, p.price as property_price,
           a.full_name as agent_name
    FROM leads l
    LEFT JOIN users c ON l.client_id = c.id
    LEFT JOIN properties p ON l.property_id = p.id
    LEFT JOIN users a ON l.agent_id = a.id
    WHERE l.created_at BETWEEN ? AND ?
    ORDER BY l.created_at DESC
");
$stmt->execute([$date_from, $date_to . ' 23:59:59']);
$leads = $stmt->fetchAll();

// 6. Lead Stage Distribution
$stage_stats = $pdo->query("
    SELECT stage, COUNT(*) as count 
    FROM leads 
    GROUP BY stage
")->fetchAll();

// 7. Agent Performance
$agent_performance = $pdo->query("
    SELECT u.id, u.full_name,
           COUNT(l.id) as total_leads,
           SUM(l.stage = 'closed') as closed_deals,
           ROUND(AVG(r.rating), 1) as avg_rating
    FROM users u
    LEFT JOIN leads l ON l.agent_id = u.id
    LEFT JOIN reviews r ON r.agent_id = u.id AND r.is_approved = 1
    WHERE u.role = 'agent'
    GROUP BY u.id
    ORDER BY closed_deals DESC
")->fetchAll();

// 8. Reviews Data
$stmt = $pdo->prepare("
    SELECT r.*, c.full_name as client_name, a.full_name as agent_name, p.title as property_title
    FROM reviews r
    JOIN users c ON r.client_id = c.id
    JOIN users a ON r.agent_id = a.id
    LEFT JOIN properties p ON r.property_id = p.id
    WHERE r.created_at BETWEEN ? AND ?
    ORDER BY r.created_at DESC
");
$stmt->execute([$date_from, $date_to . ' 23:59:59']);
$reviews = $stmt->fetchAll();

// 9. Financial Summary
$financial = [];
$financial['total_property_value'] = $pdo->query("SELECT SUM(price) FROM properties WHERE type='sale'")->fetchColumn() ?: 0;
$financial['avg_property_price'] = $pdo->query("SELECT AVG(price) FROM properties")->fetchColumn() ?: 0;
$financial['sold_value'] = $pdo->query("SELECT SUM(price) FROM properties WHERE status='sold'")->fetchColumn() ?: 0;

// 10. Monthly Trends
$monthly_leads = $pdo->query("
    SELECT DATE_FORMAT(created_at, '%b %Y') as month, COUNT(*) as count
    FROM leads
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY MIN(created_at)
")->fetchAll();

// Statistics summary
$stats = [
    'total_properties' => count($properties),
    'total_users' => count($users),
    'total_leads' => count($leads),
    'closed_leads' => $pdo->prepare("SELECT COUNT(*) FROM leads WHERE stage='closed' AND created_at BETWEEN ? AND ?")->execute([$date_from, $date_to . ' 23:59:59']) ? $pdo->prepare("SELECT COUNT(*) FROM leads WHERE stage='closed' AND created_at BETWEEN ? AND ?")->fetchColumn() : 0,
    'total_reviews' => count($reviews),
    'avg_rating' => $pdo->query("SELECT AVG(rating) FROM reviews WHERE is_approved=1")->fetchColumn() ?: 0,
];

$stats['conversion_rate'] = $stats['total_leads'] > 0 ? round(($stats['closed_leads'] / $stats['total_leads']) * 100) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprehensive System Report - Trans-Phil House Hub</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'DM Sans', 'Helvetica Neue', 'Arial', sans-serif;
            background: #fff;
            padding: 40px;
            color: #1e1c2e;
        }
        
        @media print {
            body {
                padding: 0;
                margin: 0;
            }
            .no-print {
                display: none;
            }
            .page-break {
                page-break-before: always;
            }
            .report-container {
                margin: 0;
                padding: 20px;
            }
            table {
                page-break-inside: avoid;
            }
        }
        
        .report-container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
        }
        
        .report-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #1a3a6b;
        }
        
        .logo {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            margin-bottom: 15px;
        }
        
        .report-title {
            font-size: 24px;
            font-weight: 700;
            color: #1a3a6b;
            margin-bottom: 5px;
        }
        
        .report-subtitle {
            font-size: 14px;
            color: #6b7280;
            margin-bottom: 10px;
        }
        
        .report-date {
            font-size: 12px;
            color: #9ca3af;
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #1a3a6b, #22508a);
            color: white;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
        }
        
        .stat-number {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 12px;
            opacity: 0.85;
        }
        
        /* Section Headers */
        .section-title {
            font-size: 18px;
            font-weight: 700;
            color: #1a3a6b;
            margin: 25px 0 15px 0;
            padding-bottom: 8px;
            border-bottom: 2px solid #f07800;
            display: inline-block;
        }
        
        /* Tables */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        th {
            background: #f8f7fc;
            padding: 12px;
            text-align: left;
            font-size: 12px;
            font-weight: 600;
            color: #374151;
            border-bottom: 1px solid #e5e7eb;
        }
        
        td {
            padding: 10px 12px;
            font-size: 13px;
            border-bottom: 1px solid #e5e7eb;
        }
        
        tr:hover {
            background: #f9fafb;
        }
        
        /* Badges */
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .badge-sale { background: #e0e8f7; color: #1a3a6b; }
        .badge-rent { background: #fff0df; color: #f07800; }
        .badge-project { background: #e6f7e6; color: #2db12b; }
        .badge-active { background: #d1fae5; color: #065f46; }
        .badge-inactive { background: #fee2e2; color: #991b1b; }
        .badge-new { background: #e0e8f7; color: #1a3a6b; }
        .badge-closed { background: #d1fae5; color: #065f46; }
        .badge-high { background: #fee2e2; color: #dc2626; }
        .badge-medium { background: #fff0df; color: #f07800; }
        .badge-low { background: #d1fae5; color: #065f46; }
        
        .print-btn {
            background: #f07800;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 20px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .print-btn:hover {
            background: #d86d00;
        }
        
        @media (max-width: 768px) {
            body {
                padding: 20px;
            }
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            table {
                font-size: 12px;
            }
            td, th {
                padding: 8px;
            }
        }
    </style>
</head>
<body>

<div class="report-container">
    <div class="no-print" style="text-align: right; margin-bottom: 20px;">
        <button onclick="window.print()" class="print-btn">
            <i class="fas fa-print"></i> Save as PDF / Print
        </button>
    </div>
    
    <div class="report-header">
        <img src="../assets/images/logo.jpg" alt="Trans-Phil Logo" class="logo" onerror="this.style.display='none'">
        <div class="report-title">Trans-Phil House Hub</div>
        <div class="report-subtitle">Comprehensive System Performance Report</div>
        <div class="report-date">
            Period: <?php echo date('F d, Y', strtotime($date_from)); ?> - <?php echo date('F d, Y', strtotime($date_to)); ?>
            <br>Generated: <?php echo date('F d, Y h:i A'); ?> | By: <?php echo htmlspecialchars($_SESSION['full_name']); ?>
        </div>
    </div>
    
    <!-- Executive Summary -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-number"><?php echo $stats['total_properties']; ?></div>
            <div class="stat-label">Total Properties</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $stats['total_users']; ?></div>
            <div class="stat-label">Total Users</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $stats['total_leads']; ?></div>
            <div class="stat-label">Total Leads</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $stats['conversion_rate']; ?>%</div>
            <div class="stat-label">Conversion Rate</div>
        </div>
    </div>
    
    <!-- SECTION 1: PROPERTY REPORT -->
    <h2 class="section-title"><i class="fas fa-building"></i> 1. Property Listings Report</h2>
    <table>
        <thead>
            <tr>
                <th>Title</th>
                <th>Type</th>
                <th>Price (₱)</th>
                <th>Location</th>
                <th>Status</th>
                <th>Featured</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($properties as $prop): ?>
            <tr>
                <td><?php echo htmlspecialchars($prop['title']); ?></td>
                <td><span class="badge badge-<?php echo $prop['type']; ?>"><?php echo ucfirst($prop['type']); ?></span></td>
                <td>₱ <?php echo number_format($prop['price'], 0); ?></td>
                <td><?php echo htmlspecialchars($prop['location']); ?></td>
                <td><?php echo ucfirst($prop['status']); ?></td>
                <td><?php echo $prop['is_featured'] ? 'Yes' : 'No'; ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <!-- Property Type Distribution -->
    <h2 class="section-title"><i class="fas fa-chart-pie"></i> Property Type Distribution</h2>
    <table>
        <thead><tr><th>Type</th><th>Count</th><th>Total Value (₱)</th></tr></thead>
        <tbody>
            <?php foreach($type_stats as $type): ?>
            <tr>
                <td><?php echo ucfirst($type['type']); ?></td>
                <td><?php echo $type['count']; ?></td>
                <td>₱ <?php echo number_format($type['total_value'], 0); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <!-- SECTION 2: LEAD MANAGEMENT REPORT -->
    <h2 class="section-title"><i class="fas fa-funnel-dollar"></i> 2. Lead Management Report</h2>
    <table>
        <thead>
            <tr>
                <th>Client</th>
                <th>Property</th>
                <th>Agent</th>
                <th>Stage</th>
                <th>Priority</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($leads as $lead): ?>
            <tr>
                <td><?php echo htmlspecialchars($lead['client_name'] ?? '—'); ?></td>
                <td><?php echo htmlspecialchars($lead['property_title'] ?? '—'); ?></td>
                <td><?php echo htmlspecialchars($lead['agent_name'] ?? 'Unassigned'); ?></td>
                <td><span class="badge badge-<?php echo $lead['stage']; ?>"><?php echo ucfirst($lead['stage']); ?></span></td>
                <td><span class="badge badge-<?php echo $lead['priority']; ?>"><?php echo ucfirst($lead['priority']); ?></span></td>
                <td><?php echo date('M d, Y', strtotime($lead['created_at'])); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <!-- Lead Stage Distribution -->
    <h2 class="section-title"><i class="fas fa-chart-line"></i> Lead Pipeline</h2>
    </table>
        <thead><tr><th>Stage</th><th>Count</th><th>Percentage</th></tr></thead>
        <tbody>
            <?php 
            $total_leads = array_sum(array_column($stage_stats, 'count'));
            foreach($stage_stats as $stage): 
                $pct = $total_leads > 0 ? round(($stage['count'] / $total_leads) * 100) : 0;
            ?>
            <tr>
                <td><?php echo ucfirst($stage['stage']); ?></td>
                <td><?php echo $stage['count']; ?></td>
                <td><?php echo $pct; ?>%</td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <!-- SECTION 3: AGENT PERFORMANCE REPORT -->
    <div class="page-break"></div>
    <h2 class="section-title"><i class="fas fa-user-tie"></i> 3. Agent Performance Report</h2>
    <table>
        <thead>
            <tr>
                <th>Agent Name</th>
                <th>Total Leads</th>
                <th>Closed Deals</th>
                <th>Conversion Rate</th>
                <th>Avg Rating</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($agent_performance as $agent): ?>
            <tr>
                <td><?php echo htmlspecialchars($agent['full_name']); ?></td>
                <td><?php echo $agent['total_leads'] ?: 0; ?></td>
                <td><?php echo $agent['closed_deals'] ?: 0; ?></td>
                <td><?php echo $agent['total_leads'] > 0 ? round(($agent['closed_deals'] / $agent['total_leads']) * 100) : 0; ?>%</td>
                <td><?php echo $agent['avg_rating'] ? number_format($agent['avg_rating'], 1) . ' ★' : 'No ratings'; ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <!-- SECTION 4: USER REPORT -->
    <h2 class="section-title"><i class="fas fa-users"></i> 4. User Management Report</h2>
    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Status</th>
                <th>Phone</th>
                <th>Registered</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($users as $user): ?>
            <tr>
                <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                <td><?php echo htmlspecialchars($user['email']); ?></td>
                <td><?php echo ucfirst($user['role']); ?></td>
                <td><span class="badge badge-<?php echo $user['status']; ?>"><?php echo ucfirst($user['status']); ?></span></td>
                <td><?php echo htmlspecialchars($user['phone'] ?? '—'); ?></td>
                <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <!-- Role Distribution -->
    <h2 class="section-title"><i class="fas fa-chart-bar"></i> User Role Distribution</h2>
    <table>
        <thead><tr><th>Role</th><th>Count</th></tr></thead>
        <tbody>
            <?php foreach($role_stats as $role): ?>
            <tr>
                <td><?php echo ucfirst($role['role']); ?></td>
                <td><?php echo $role['count']; ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <!-- SECTION 5: REVIEWS & RATINGS REPORT -->
    <h2 class="section-title"><i class="fas fa-star"></i> 5. Client Reviews Report</h2>
    <table>
        <thead>
            <tr>
                <th>Client</th>
                <th>Agent</th>
                <th>Property</th>
                <th>Rating</th>
                <th>Comment</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($reviews as $review): ?>
            <tr>
                <td><?php echo htmlspecialchars($review['client_name']); ?></td>
                <td><?php echo htmlspecialchars($review['agent_name']); ?></td>
                <td><?php echo htmlspecialchars($review['property_title'] ?? '—'); ?></td>
                <td>
                    <?php for($i = 1; $i <= 5; $i++): ?>
                        <i class="fas fa-star" style="color: <?php echo $i <= $review['rating'] ? '#fbbf24' : '#e5e7eb'; ?>; font-size: 11px;"></i>
                    <?php endfor; ?>
                </td>
                <td><?php echo htmlspecialchars(substr($review['comment'], 0, 50)); ?>...</td>
                <td><?php echo date('M d, Y', strtotime($review['created_at'])); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <!-- SECTION 6: FINANCIAL SUMMARY -->
    <h2 class="section-title"><i class="fas fa-chart-line"></i> 6. Financial Summary</h2>
    <table>
        <thead><tr><th>Metric</th><th>Value</th></tr></thead>
        <tbody>
            <tr><td>Total Property Portfolio Value</td><td>₱ <?php echo number_format($financial['total_property_value'], 2); ?></td></tr>
            <tr><td>Average Property Price</td><td>₱ <?php echo number_format($financial['avg_property_price'], 2); ?></td></tr>
            <tr><td>Total Sold Properties Value</td><td>₱ <?php echo number_format($financial['sold_value'], 2); ?></td></tr>
            <tr><td>Average Client Rating</td><td><?php echo number_format($stats['avg_rating'], 1); ?> / 5.0 ★</td></tr>
            <tr><td>Overall Lead Conversion Rate</td><td><?php echo $stats['conversion_rate']; ?>%</td></tr>
        </tbody>
    </table>
    
    <!-- SECTION 7: MONTHLY TRENDS -->
    <h2 class="section-title"><i class="fas fa-chart-line"></i> 7. Monthly Lead Trends</h2>
    <table>
        <thead><tr><th>Month</th><th>Leads Generated</th></tr></thead>
        <tbody>
            <?php foreach($monthly_leads as $month): ?>
            <tr><td><?php echo $month['month']; ?></td><td><?php echo $month['count']; ?></td></tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <!-- Footer -->
    <div class="report-footer">
        <p>Trans-Phil House Corporation - 1177 Bagtikan St, San Antonio Village, Makati City</p>
        <p>This is a system-generated report. For inquiries, contact info@transphilhouse.com</p>
        <p>© <?php echo date('Y'); ?> Trans-Phil House Corporation. All rights reserved.</p>
    </div>
</div>

</body>
</html>