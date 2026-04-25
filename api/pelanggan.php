<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Jika method OPTIONS (preflight CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include 'db.php';

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents("php://input"), true);

switch ($method) {
    case 'GET':
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $search = isset($_GET['search']) ? "%" . $_GET['search'] . "%" : '';

        if ($id > 0) {
            $sql = "SELECT * FROM pelanggan WHERE pelanggan_id = ? AND aktif = 1";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $id);
        } elseif (!empty($search)) {
            $sql = "SELECT * FROM pelanggan WHERE (nama_pelanggan LIKE ? OR alamat LIKE ? OR no_telepon LIKE ?) AND aktif = 1 ORDER BY pelanggan_id DESC";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sss", $search, $search, $search);
        } else {
            $sql = "SELECT * FROM pelanggan WHERE aktif = 1 ORDER BY pelanggan_id DESC";
            $stmt = $conn->prepare($sql);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        echo json_encode(["status" => "success", "data" => $data]);
        break;

    case 'POST':
        if (empty($input['nama_pelanggan'])) {
            echo json_encode(["status" => "error", "message" => "Nama pelanggan wajib diisi."]);
            exit;
        }

        $nama_pelanggan = strtoupper($input['nama_pelanggan']);

        $sql = "INSERT INTO pelanggan (nama_pelanggan, alamat, no_telepon) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $nama_pelanggan, $input['alamat'], $input['no_telepon']);

        if ($stmt->execute()) {
            echo json_encode(["status" => "success", "message" => "Pelanggan berhasil ditambahkan."]);
        } else {
            echo json_encode(["status" => "error", "message" => "Gagal menambah pelanggan: " . $stmt->error]);
        }
        $stmt->close();
        break;

    case 'PUT':
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if ($id === 0 || !$input) {
            echo json_encode(["status" => "error", "message" => "Data tidak lengkap untuk update."]);
            exit;
        }

        $nama_pelanggan = strtoupper($input['nama_pelanggan']);

        $sql = "UPDATE pelanggan SET nama_pelanggan = ?, alamat = ?, no_telepon = ? WHERE pelanggan_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssi", $nama_pelanggan, $input['alamat'], $input['no_telepon'], $id);

        if ($stmt->execute()) {
            echo json_encode(["status" => "success", "message" => "Pelanggan berhasil diperbarui."]);
        } else {
            echo json_encode(["status" => "error", "message" => "Gagal memperbarui pelanggan: " . $stmt->error]);
        }
        $stmt->close();
        break;

    case 'DELETE':
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if ($id === 0) {
            echo json_encode(["status" => "error", "message" => "ID pelanggan tidak valid."]);
            exit;
        }

        // Soft delete
        $sql = "UPDATE pelanggan SET aktif = 0 WHERE pelanggan_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                echo json_encode(["status" => "success", "message" => "Pelanggan berhasil dihapus."]);
            } else {
                echo json_encode(["status" => "error", "message" => "Pelanggan tidak ditemukan atau sudah dihapus."]);
            }
        } else {
            echo json_encode(["status" => "error", "message" => "Gagal menghapus pelanggan: " . $stmt->error]);
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