<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Respond with a success message for POST requests
    echo json_encode([
        "status" => "success",
        "message" => "Tersambung"
    ]);
} else {
    // Jika metode bukan POST, kembalikan error 405 Method Not Allowed
    http_response_code(405);

}
?>