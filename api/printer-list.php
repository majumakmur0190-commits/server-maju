<?php
header('Content-Type: application/json');

exec('wmic printer get name', $output);
$printers = [];

// Mulai dari index ke-1 untuk skip header "Name"
for ($i = 1; $i < count($output); $i++) {
    $name = trim($output[$i]);
    if ($name !== '') {
        $printers[] = $name;
    }
}

echo json_encode($printers);
