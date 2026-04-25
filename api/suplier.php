<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

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
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
        $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
        $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;

        if ($page < 1) $page = 1;
        if ($limit < 1) $limit = 10;
        $offset = ($page - 1) * $limit;

        if ($id > 0) {
            $sql = "SELECT * FROM suplier WHERE suplier_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $data = $result->fetch_assoc();
            $stmt->close();

            if ($data) {
                echo json_encode(["status" => "success", "data" => $data]);
            } else {
                echo json_encode(["status" => "error", "message" => "Suplier tidak ditemukan."]);
            }
        } else {
            // Base query (hanya ambil yang aktif)
            $whereClause = "WHERE aktif = 1";
            $params = [];
            $types = "";

            if (!empty($search)) {
                $whereClause .= " AND (nama_suplier LIKE ? OR kode_suplier LIKE ? OR alamat LIKE ? OR email LIKE ?)";
                $searchTerm = "%$search%";
                $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm];
                $types = "ssss";
            }

            // Hitung total data untuk pagination
            $countSql = "SELECT COUNT(*) as total FROM suplier $whereClause";
            $stmtCount = $conn->prepare($countSql);
            if (!empty($params)) {
                $stmtCount->bind_param($types, ...$params);
            }
            $stmtCount->execute();
            $totalResult = $stmtCount->get_result()->fetch_assoc();
            $totalRows = $totalResult['total'];
            $stmtCount->close();

            // Ambil data dengan limit dan offset
            $sql = "SELECT * FROM suplier $whereClause ORDER BY suplier_id DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
            $types .= "ii";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            $data = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            echo json_encode([
                "status" => "success",
                "data" => $data,
                "pagination" => [
                    "page" => $page,
                    "limit" => $limit,
                    "total_rows" => $totalRows,
                    "total_pages" => ceil($totalRows / $limit)
                ]
            ]);
        }
        break;

    case 'POST':
        if (empty($input['nama_suplier'])) {
            echo json_encode(["status" => "error", "message" => "Nama suplier wajib diisi."]);
            exit;
        }

        // Generate Kode Suplier Otomatis (SUP001, SUP002, dst)
        $sqlLast = "SELECT kode_suplier FROM suplier WHERE kode_suplier LIKE 'SUP%' ORDER BY suplier_id DESC LIMIT 1";
        $resLast = $conn->query($sqlLast);
        $lastNo = 0;
        if ($resLast && $resLast->num_rows > 0) {
            $row = $resLast->fetch_assoc();
            // Ambil angka setelah "SUP" (3 karakter pertama)
            $lastNo = (int)substr($row['kode_suplier'], 3);
        }
        $kode = "SUP" . str_pad($lastNo + 1, 3, "0", STR_PAD_LEFT);

        $nama = $input['nama_suplier'];
        $alamat = $input['alamat'] ?? '';
        $telepon = $input['telepon'] ?? '';
        $email = $input['email'] ?? '';
        $aktif = 1;

        $sql = "INSERT INTO suplier (kode_suplier, nama_suplier, alamat, telepon, email, aktif) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssi", $kode, $nama, $alamat, $telepon, $email, $aktif);

        if ($stmt->execute()) {
            echo json_encode(["status" => "success", "message" => "Suplier berhasil ditambahkan.", "id" => $conn->insert_id]);
        } else {
            echo json_encode(["status" => "error", "message" => "Gagal menambah suplier: " . $stmt->error]);
        }
        $stmt->close();
        break;

    case 'PUT':
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if ($id === 0 || !$input) {
            echo json_encode(["status" => "error", "message" => "ID atau data tidak valid."]);
            exit;
        }

        $kode = $input['kode_suplier'] ?? '';
        $nama = $input['nama_suplier'] ?? '';
        $alamat = $input['alamat'] ?? '';
        $telepon = $input['telepon'] ?? '';
        $email = $input['email'] ?? '';

        $sql = "UPDATE suplier SET kode_suplier=?, nama_suplier=?, alamat=?, telepon=?, email=? WHERE suplier_id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssi", $kode, $nama, $alamat, $telepon, $email, $id);

        if ($stmt->execute()) {
            echo json_encode(["status" => "success", "message" => "Suplier berhasil diperbarui."]);
        } else {
            echo json_encode(["status" => "error", "message" => "Gagal memperbarui suplier: " . $stmt->error]);
        }
        $stmt->close();
        break;

    case 'DELETE':
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if ($id === 0) {
            echo json_encode(["status" => "error", "message" => "ID tidak valid."]);
            exit;
        }

        // Soft delete: set aktif = 0
        $sql = "UPDATE suplier SET aktif = 0 WHERE suplier_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                echo json_encode(["status" => "success", "message" => "Suplier berhasil dinonaktifkan."]);
            } else {
                echo json_encode(["status" => "error", "message" => "Suplier tidak ditemukan atau sudah nonaktif."]);
            }
        } else {
            echo json_encode(["status" => "error", "message" => "Gagal menghapus suplier: " . $stmt->error]);
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