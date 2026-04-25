<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// If OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include 'db.php';

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents("php://input"), true);

// === KONVERSI KE HURUF BESAR ===
if ($input) {
    foreach ($input as $key => $val) {
        if (is_string($val)) {
            $input[$key] = strtoupper($val);
        }
    }
}

switch ($method) {
    case 'GET':
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $search = isset($_GET['search']) ? "%" . $_GET['search'] . "%" : '';

        if ($id > 0) {
            $sql = "SELECT * FROM kategori WHERE kategori_id = ? AND aktif = 1";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $id);
        } elseif (!empty($search)) {
            $sql = "SELECT * FROM kategori WHERE (nama_kategori LIKE ? OR deskripsi LIKE ?) AND aktif = 1 ORDER BY kategori_id DESC";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $search, $search);
        } else {
            $sql = "SELECT * FROM kategori WHERE aktif = 1 ORDER BY kategori_id DESC";
            $stmt = $conn->prepare($sql);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        echo json_encode(["status" => "success", "data" => $data]);
        break;

    case 'POST':
        if (empty($input['nama_kategori'])) {
            echo json_encode(["status" => "error", "message" => "Nama kategori wajib diisi."]);
            exit;
        }

        $sql = "INSERT INTO kategori (nama_kategori, deskripsi) VALUES (?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $input['nama_kategori'], $input['deskripsi']);

        if ($stmt->execute()) {
            echo json_encode(["status" => "success", "message" => "Kategori berhasil ditambahkan."]);
        } else {
            echo json_encode(["status" => "error", "message" => "Gagal menambah kategori: " . $stmt->error]);
        }
        $stmt->close();
        break;

    case 'PUT':
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if ($id === 0 || !$input) {
            echo json_encode(["status" => "error", "message" => "Data tidak lengkap untuk update."]);
            exit;
        }

        $sql = "UPDATE kategori SET nama_kategori = ?, deskripsi = ? WHERE kategori_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssi", $input['nama_kategori'], $input['deskripsi'], $id);

        if ($stmt->execute()) {
            echo json_encode(["status" => "success", "message" => "Kategori berhasil diperbarui."]);
        } else {
            echo json_encode(["status" => "error", "message" => "Gagal memperbarui kategori: " . $stmt->error]);
        }
        $stmt->close();
        break;

    case 'DELETE':
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if ($id === 0) {
            echo json_encode(["status" => "error", "message" => "ID kategori tidak valid."]);
            exit;
        }

        $sql = "UPDATE kategori SET aktif = 0 WHERE kategori_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                echo json_encode(["status" => "success", "message" => "Kategori berhasil dihapus."]);
            } else {
                echo json_encode(["status" => "error", "message" => "Kategori tidak ditemukan atau sudah dihapus."]);
            }
        } else {
            echo json_encode(["status" => "error", "message" => "Gagal menghapus kategori: " . $stmt->error]);
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
