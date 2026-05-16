<?php
require_once '../includes/db.php';

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename=transphil_reports.csv');

$output = fopen('php://output', 'w');

fputcsv($output, [
    'Property',
    'Client',
    'Agent',
    'Stage',
    'Date'
]);

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

while($row = $stmt->fetch(PDO::FETCH_ASSOC)){
    fputcsv($output, $row);
}

fclose($output);
exit;
?>