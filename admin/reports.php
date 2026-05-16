<?php
require_once '../includes/db.php';
session_start();

if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin'){
    header("Location: ../login.php");
    exit;
}

/* ─────────────────────────────────────────
   STATS
───────────────────────────────────────── */
$total_users        = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_agents       = $pdo->query("SELECT COUNT(*) FROM users WHERE role='agent'")->fetchColumn();
$total_clients      = $pdo->query("SELECT COUNT(*) FROM users WHERE role='client'")->fetchColumn();

$total_properties   = $pdo->query("SELECT COUNT(*) FROM properties")->fetchColumn();
$available_props    = $pdo->query("SELECT COUNT(*) FROM properties WHERE status='available'")->fetchColumn();
$sold_props         = $pdo->query("SELECT COUNT(*) FROM properties WHERE status='sold'")->fetchColumn();

$total_leads        = $pdo->query("SELECT COUNT(*) FROM leads")->fetchColumn();
$closed_leads       = $pdo->query("SELECT COUNT(*) FROM leads WHERE stage='closed'")->fetchColumn();

$conversion_rate    = $total_leads > 0
    ? round(($closed_leads / $total_leads) * 100)
    : 0;

$total_appts        = $pdo->query("SELECT COUNT(*) FROM appointments")->fetchColumn();
$confirmed_appts    = $pdo->query("SELECT COUNT(*) FROM appointments WHERE status='confirmed'")->fetchColumn();

$appt_success = $total_appts > 0
    ? round(($confirmed_appts / $total_appts) * 100)
    : 0;

$pending_reviews    = $pdo->query("SELECT COUNT(*) FROM reviews WHERE is_approved=0")->fetchColumn();

/* ─────────────────────────────────────────
   LEAD STAGES
───────────────────────────────────────── */
$stage_data = [];

foreach(['new','contacted','viewing','negotiation','closed'] as $stage){
    $stage_data[$stage] = (int)$pdo
        ->query("SELECT COUNT(*) FROM leads WHERE stage='$stage'")
        ->fetchColumn();
}

/* ─────────────────────────────────────────
   AGENT PERFORMANCE
───────────────────────────────────────── */
$agent_perf = $pdo->query("
    SELECT 
        u.full_name,
        COUNT(l.id) AS total_leads,
        SUM(l.stage='closed') AS closed_leads
    FROM users u
    LEFT JOIN leads l ON l.agent_id = u.id
    WHERE u.role='agent'
    GROUP BY u.id
    ORDER BY closed_leads DESC
")->fetchAll();

/* ─────────────────────────────────────────
   PROPERTY TYPES
───────────────────────────────────────── */
$prop_types = $pdo->query("
    SELECT type, COUNT(*) AS cnt
    FROM properties
    GROUP BY type
")->fetchAll();

/* ─────────────────────────────────────────
   RECENT LEADS
───────────────────────────────────────── */
$recent_leads = $pdo->query("
    SELECT 
        l.stage,
        l.created_at,
        c.full_name AS client,
        p.title AS property
    FROM leads l
    LEFT JOIN users c ON l.client_id = c.id
    LEFT JOIN properties p ON l.property_id = p.id
    ORDER BY l.created_at DESC
    LIMIT 7
")->fetchAll();

/* ─────────────────────────────────────────
   MONTHLY LEADS
───────────────────────────────────────── */
$monthly = $pdo->query("
    SELECT 
        DATE_FORMAT(created_at,'%b %Y') AS month,
        COUNT(*) AS cnt
    FROM leads
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(created_at,'%Y-%m')
    ORDER BY MIN(created_at)
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Reports & Analytics</title>

<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>

<style>

:root{
    --navy:#1a3a6b;
    --green:#2db12b;
    --orange:#f07800;
    --bg:#f3f4f8;
    --card:#ffffff;
    --border:#e6e8ef;
    --text:#1f2330;
    --muted:#72768a;
    --radius:16px;
    --shadow:0 4px 18px rgba(0,0,0,.06);
}

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
}

body{
    font-family:'DM Sans',sans-serif;
    background:var(--bg);
    color:var(--text);
}

/* SIDEBAR */

.sidebar{
    position:fixed;
    left:0;
    top:0;
    width:250px;
    height:100vh;
    background:#11294b;
    color:#fff;
    padding-top:20px;
}

.logo{
    padding:20px;
    font-size:22px;
    font-weight:700;
    color:#fff;
}

.logo span{
    color:var(--orange);
}

.nav{
    margin-top:10px;
}

.nav a{
    display:flex;
    align-items:center;
    gap:12px;
    padding:14px 22px;
    color:rgba(255,255,255,.7);
    text-decoration:none;
    transition:.2s;
    font-size:14px;
}

.nav a:hover,
.nav a.active{
    background:rgba(255,255,255,.08);
    color:#fff;
    border-left:4px solid var(--green);
}

/* MAIN */

.main{
    margin-left:250px;
    padding:28px;
}

.topbar{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:24px;
}

.topbar h1{
    font-size:28px;
    color:var(--navy);
}

.topbar p{
    color:var(--muted);
    margin-top:5px;
}

/* EXPORT */

.export-bar{
    display:flex;
    gap:12px;
    margin-bottom:24px;
    flex-wrap:wrap;
}

.exp-btn{
    background:#fff;
    border:1px solid var(--border);
    padding:12px 18px;
    border-radius:10px;
    text-decoration:none;
    color:var(--text);
    font-weight:600;
    transition:.2s;
}

.exp-btn:hover{
    background:var(--orange);
    color:#fff;
}

/* KPI */

.kpi-grid{
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:18px;
    margin-bottom:24px;
}

.kpi{
    background:var(--card);
    border-radius:var(--radius);
    padding:22px;
    box-shadow:var(--shadow);
    transition:.2s;
}

.kpi:hover{
    transform:translateY(-4px);
}

.kpi-num{
    font-size:32px;
    font-weight:700;
    color:var(--navy);
}

.kpi-label{
    margin-top:6px;
    color:var(--muted);
    font-size:13px;
}

.kpi-sub{
    margin-top:8px;
    font-size:12px;
    font-weight:600;
}

/* GRID */

.grid-2{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:20px;
    margin-bottom:24px;
}

.card{
    background:#fff;
    border-radius:var(--radius);
    padding:24px;
    box-shadow:var(--shadow);
}

.card h2{
    margin-bottom:18px;
    color:var(--navy);
    font-size:18px;
}

/* TABLE */

table{
    width:100%;
    border-collapse:collapse;
}

table th{
    background:#f7f8fc;
    text-align:left;
    padding:12px;
    font-size:12px;
    color:var(--muted);
}

table td{
    padding:12px;
    border-top:1px solid var(--border);
    font-size:13px;
}

/* BADGES */

.badge{
    padding:5px 10px;
    border-radius:20px;
    font-size:11px;
    font-weight:600;
}

.b-new{background:#e5ecff;color:#1a3a6b;}
.b-contacted{background:#fff1df;color:#c76a00;}
.b-viewing{background:#e5f8e5;color:#228822;}
.b-negotiation{background:#fff8da;color:#8c7000;}
.b-closed{background:#ddffdd;color:#167516;}

/* INSIGHTS */

.insights{
    display:grid;
    grid-template-columns:repeat(3,1fr);
    gap:18px;
    margin-top:24px;
}

.insight{
    background:#fff;
    border-radius:var(--radius);
    padding:20px;
    box-shadow:var(--shadow);
}

.insight h3{
    color:var(--navy);
    margin-bottom:10px;
}

/* PRINT */

@media print{
    .sidebar,
    .export-bar{
        display:none;
    }

    .main{
        margin-left:0;
        padding:0;
    }
}

/* RESPONSIVE */

@media(max-width:1100px){
    .kpi-grid{
        grid-template-columns:repeat(2,1fr);
    }

    .grid-2{
        grid-template-columns:1fr;
    }

    .insights{
        grid-template-columns:1fr;
    }
}

@media(max-width:768px){

    .sidebar{
        display:none;
    }

    .main{
        margin-left:0;
    }

    .kpi-grid{
        grid-template-columns:1fr;
    }
}

</style>
</head>

<body>

<div class="sidebar">

    <div class="logo">
        Trans-Phil <span>Hub</span>
    </div>

    <div class="nav">
        <a href="dashboard.php">
            <i class="fas fa-chart-pie"></i>
            Dashboard
        </a>

        <a href="properties.php">
            <i class="fas fa-building"></i>
            Properties
        </a>

        <a href="leads.php">
            <i class="fas fa-users"></i>
            Leads
        </a>

        <a href="users.php">
            <i class="fas fa-user"></i>
            Users
        </a>

        <a href="reports.php" class="active">
            <i class="fas fa-chart-line"></i>
            Reports
        </a>

        <a href="../logout.php">
            <i class="fas fa-sign-out-alt"></i>
            Logout
        </a>
    </div>

</div>

<div class="main">

    <div class="topbar">

        <div>
            <h1>Reports & Analytics</h1>
            <p>
                Generated on <?php echo date('F d, Y h:i A'); ?>
            </p>
        </div>

    </div>

    <!-- EXPORT -->

    <div class="export-bar">

        <a href="export_csv.php" class="exp-btn">
            <i class="fas fa-file-csv"></i>
            Export CSV
        </a>

        <a href="print_report.php" target="_blank" class="exp-btn">
            <i class="fas fa-print"></i>
            Print / PDF
        </a>

        <a href="reports.php" class="exp-btn">
            <i class="fas fa-sync"></i>
            Refresh
        </a>

    </div>

    <!-- KPI -->

    <div class="kpi-grid">

        <div class="kpi">
            <div class="kpi-num"><?php echo $total_users; ?></div>
            <div class="kpi-label">Total Users</div>
            <div class="kpi-sub" style="color:var(--green)">
                <?php echo $total_agents; ?> agents · <?php echo $total_clients; ?> clients
            </div>
        </div>

        <div class="kpi">
            <div class="kpi-num"><?php echo $total_properties; ?></div>
            <div class="kpi-label">Properties</div>
            <div class="kpi-sub" style="color:var(--orange)">
                <?php echo $available_props; ?> available · <?php echo $sold_props; ?> sold
            </div>
        </div>

        <div class="kpi">
            <div class="kpi-num"><?php echo $conversion_rate; ?>%</div>
            <div class="kpi-label">Lead Conversion</div>
            <div class="kpi-sub" style="color:var(--green)">
                <?php echo $closed_leads; ?> successful deals
            </div>
        </div>

        <div class="kpi">
            <div class="kpi-num"><?php echo $appt_success; ?>%</div>
            <div class="kpi-label">Appointment Success</div>
            <div class="kpi-sub" style="color:var(--navy)">
                <?php echo $confirmed_appts; ?> confirmed
            </div>
        </div>

    </div>

    <!-- CHARTS -->

    <div class="grid-2">

        <div class="card">
            <h2>Lead Stage Breakdown</h2>
            <canvas id="stageChart"></canvas>
        </div>

        <div class="card">
            <h2>Monthly Lead Trend</h2>
            <canvas id="monthChart"></canvas>
        </div>

    </div>

    <!-- PROPERTY TYPES + ACTIVITY -->

    <div class="grid-2">

        <div class="card">
            <h2>Property Types</h2>
            <canvas id="propChart"></canvas>
        </div>

        <div class="card">

            <h2>Recent Lead Activity</h2>

            <table>

                <thead>
                    <tr>
                        <th>Client</th>
                        <th>Property</th>
                        <th>Stage</th>
                        <th>Date</th>
                    </tr>
                </thead>

                <tbody>

                <?php if(empty($recent_leads)): ?>

                    <tr>
                        <td colspan="4">
                            No activity yet.
                        </td>
                    </tr>

                <?php else: ?>

                    <?php foreach($recent_leads as $lead): ?>

                    <tr>

                        <td>
                            <?php echo htmlspecialchars($lead['client'] ?? '—'); ?>
                        </td>

                        <td>
                            <?php echo htmlspecialchars($lead['property'] ?? '—'); ?>
                        </td>

                        <td>
                            <span class="badge b-<?php echo $lead['stage']; ?>">
                                <?php echo ucfirst($lead['stage']); ?>
                            </span>
                        </td>

                        <td>
                            <?php echo date('M d', strtotime($lead['created_at'])); ?>
                        </td>

                    </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

    <!-- INSIGHTS -->

    <div class="insights">

        <div class="insight">
            <h3>System Summary</h3>
            <p>
                The platform currently manages 
                <strong><?php echo $total_properties; ?></strong> properties
                and serves 
                <strong><?php echo $total_clients; ?></strong> clients.
            </p>
        </div>

        <div class="insight">
            <h3>Lead Performance</h3>
            <p>
                Current conversion rate is 
                <strong><?php echo $conversion_rate; ?>%</strong>
                with 
                <strong><?php echo $closed_leads; ?></strong>
                successful transactions.
            </p>
        </div>

        <div class="insight">
            <h3>Pending Reviews</h3>
            <p>
                There are currently 
                <strong><?php echo $pending_reviews; ?></strong>
                reviews awaiting admin approval.
            </p>
        </div>

    </div>

</div>

<script>

/* STAGE */

new Chart(document.getElementById('stageChart'),{
    type:'doughnut',
    data:{
        labels: <?php echo json_encode(array_keys($stage_data)); ?>,
        datasets:[{
            data: <?php echo json_encode(array_values($stage_data)); ?>,
            backgroundColor:[
                '#1a3a6b',
                '#f07800',
                '#2db12b',
                '#d9a404',
                '#218f1f'
            ]
        }]
    }
});

/* MONTHLY */

new Chart(document.getElementById('monthChart'),{
    type:'line',
    data:{
        labels: <?php echo json_encode(array_column($monthly,'month')); ?>,
        datasets:[{
            label:'Leads',
            data: <?php echo json_encode(array_column($monthly,'cnt')); ?>,
            borderColor:'#f07800',
            backgroundColor:'rgba(240,120,0,.1)',
            fill:true,
            tension:.4
        }]
    }
});

/* PROPERTY */

new Chart(document.getElementById('propChart'),{
    type:'bar',
    data:{
        labels: <?php echo json_encode(array_column($prop_types,'type')); ?>,
        datasets:[{
            data: <?php echo json_encode(array_column($prop_types,'cnt')); ?>,
            backgroundColor:[
                '#1a3a6b',
                '#f07800',
                '#2db12b'
            ],
            borderRadius:8
        }]
    },
    options:{
        plugins:{
            legend:{
                display:false
            }
        }
    }
});

</script>

</body>
</html>