<?php
require_once '../includes/db.php';

$query = "
    SELECT 
        properties.title,
        client.full_name AS client_name,
        agent.full_name AS agent_name,
        leads.stage,
        leads.created_at
    FROM leads
    LEFT JOIN properties 
        ON leads.property_id = properties.id
    LEFT JOIN users client
        ON leads.client_id = client.id
    LEFT JOIN users agent
        ON leads.agent_id = agent.id
";

$stmt = $pdo->query($query);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Trans-Phil Reports</title>

    <style>
        body{
            font-family: Arial;
            padding:20px;
        }

        h1{
            text-align:center;
            margin-bottom:30px;
        }

        table{
            width:100%;
            border-collapse: collapse;
        }

        table th,
        table td{
            border:1px solid #ccc;
            padding:10px;
            text-align:left;
        }

        table th{
            background:#2d6a4f;
            color:white;
        }

        @media print{
            button{
                display:none;
            }
        }
    </style>
</head>
<body>

<button onclick="window.print()">
    Print / Save as PDF
</button>

<h1>Trans-Phil Lead Reports</h1>

<table>
    <tr>
        <th>Property</th>
        <th>Client</th>
        <th>Agent</th>
        <th>Stage</th>
        <th>Date</th>
    </tr>

    <?php while($row = $stmt->fetch(PDO::FETCH_ASSOC)): ?>

    <tr>
        <td><?= htmlspecialchars($row['title']) ?></td>
        <td><?= htmlspecialchars($row['client_name']) ?></td>
        <td><?= htmlspecialchars($row['agent_name']) ?></td>
        <td><?= htmlspecialchars($row['stage']) ?></td>
        <td><?= htmlspecialchars($row['created_at']) ?></td>
    </tr>

    <?php endwhile; ?>

</table>

</body>
</html>