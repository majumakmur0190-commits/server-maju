<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include "db.php";

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents("php://input"), true);

switch ($method) {
    case 'GET':
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;

        // Selalu kecualikan password dari hasil GET
        if ($id > 0) {
            $sql = "SELECT user_id, username, nama, role, created_at FROM users WHERE user_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $id);
        } else {
            $sql = "SELECT user_id, username, nama, role, created_at FROM users ORDER BY user_id DESC";
            $stmt = $conn->prepare($sql);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        if ($id > 0) {
            $data = $result->fetch_assoc();
        } else {
            $data = [];
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
        }

        echo json_encode(["status" => "success", "data" => $data]);
        $stmt->close();
        break;

    case 'POST':
        if (empty($input['username']) || empty($input['password']) || empty($input['nama'])) {
            echo json_encode(["status" => "error", "message" => "Username, password, dan nama wajib diisi."]);
            exit;
        }

        $username = $input['username'];
        $password = password_hash($input['password'], PASSWORD_BCRYPT); // Hash password
        $nama = $input['nama'];
        $role = $input['role'] ?? 'kasir';

        $sql = "INSERT INTO users (username, password, nama, role) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssss", $username, $password, $nama, $role);

        if ($stmt->execute()) {
            echo json_encode(["status" => "success", "message" => "User berhasil ditambahkan."]);
        } elseif ($conn->errno == 1062) {
            echo json_encode(["status" => "error", "message" => "Username sudah digunakan."]);
        } else {
            echo json_encode(["status" => "error", "message" => "Gagal menambah user: " . $stmt->error]);
        }
        $stmt->close();
        break;

    case 'PUT':
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if ($id === 0 || !$input) {
            echo json_encode(["status" => "error", "message" => "Data tidak lengkap untuk update."]);
            exit;
        }

        $username = $input['username'];
        $nama = $input['nama'];
        $role = $input['role'];
        $password = $input['password'] ?? '';

        // Bangun query secara dinamis
        $fields = [];
        $params = [];
        $types = "";

        if (!empty($username)) { $fields[] = "username = ?"; $params[] = $username; $types .= "s"; }
        if (!empty($nama)) { $fields[] = "nama = ?"; $params[] = $nama; $types .= "s"; }
        if (!empty($role)) { $fields[] = "role = ?"; $params[] = $role; $types .= "s"; }
        
        // Hanya update password jika diisi
        if (!empty($password)) {
            $fields[] = "password = ?";
            $params[] = password_hash($password, PASSWORD_BCRYPT);
            $types .= "s";
        }

        if (empty($fields)) {
            echo json_encode(["status" => "info", "message" => "Tidak ada data untuk diubah."]);
            exit;
        }

        $params[] = $id;
        $types .= "i";

        $sql = "UPDATE users SET " . implode(", ", $fields) . " WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);

        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                echo json_encode(["status" => "success", "message" => "User berhasil diperbarui."]);
            } else {
                echo json_encode(["status" => "info", "message" => "Tidak ada perubahan data."]);
            }
        } elseif ($conn->errno == 1062) {
            echo json_encode(["status" => "error", "message" => "Username sudah digunakan."]);
        } else {
            echo json_encode(["status" => "error", "message" => "Gagal memperbarui user: " . $stmt->error]);
        }
        $stmt->close();
        break;

    case 'DELETE':
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if ($id === 0) {
            echo json_encode(["status" => "error", "message" => "ID user tidak valid."]);
            exit;
        }

        // Opsi: Cegah user menghapus diri sendiri
        // Anda perlu mengirim ID user yang login dari frontend untuk validasi ini
        // if ($id === $loggedInUserId) {
        //     echo json_encode(["status" => "error", "message" => "Anda tidak dapat menghapus akun Anda sendiri."]);
        //     exit;
        // }

        $sql = "DELETE FROM users WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);

        if ($stmt->execute() && $stmt->affected_rows > 0) {
            echo json_encode(["status" => "success", "message" => "User berhasil dihapus."]);
        } else {
            echo json_encode(["status" => "error", "message" => "User tidak ditemukan atau gagal dihapus."]);
        }
        $stmt->close();
        break;

    default:
        http_response_code(405);
        echo json_encode(["status" => "error", "message" => "Metode tidak diizinkan."]);
        break;
}

$conn->close();
?>