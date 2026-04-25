<?php
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

include "db.php";

$data = json_decode(file_get_contents("php://input"), true);
$username = trim($data["username"] ?? $_POST["username"] ?? "");
$password = trim($data["password"] ?? $_POST["password"] ?? "");

if (empty($username) || empty($password)) {
    echo json_encode(["status" => "error", "message" => "Username dan password wajib diisi!"]);
    exit;
}

$sql = "SELECT * FROM users WHERE username = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(["status" => "error", "message" => "User tidak ditemukan!"]);
    exit;
}

$user = $result->fetch_assoc();

// ✅ Bandingkan password (mendukung hash dan plaintext)
if ($user['password'] === $password || password_verify($password, $user['password'])) {
    echo json_encode([
        "status" => "success",
        "message" => "Login berhasil!",
        "data" => [
            "id" => $user["user_id"],
            "username" => $user["username"],
            "role" => $user["role"]
        ]
    ]);
} else {
    echo json_encode(["status" => "error", "message" => "Password salah!"]);
}
?>