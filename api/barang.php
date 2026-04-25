<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include "db.php";

// Fungsi helper untuk konversi tipe data hasil query
function cast_data_types($row)
{
    if ($row) {
        $row['barcode'] = strval($row['barcode']);
        $row['barang_id'] = intval($row['barang_id']);
        $row['kategori_id'] = intval($row['kategori_id']);
        $row['harga_hna'] = floatval($row['harga_hna']);
        $row['stok'] = intval($row['stok']);
        $row['aktif'] = intval($row['aktif']);
    }
    return $row;
}


$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents("php://input"), true);

switch ($method) {

    // ===================================================
// 🔹 GET DATA
// ===================================================
    case 'GET':
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $searchRaw = isset($_GET['search']) ? trim($_GET['search']) : '';

        if ($id > 0) {

            // 🔹 GET BY ID
            $sql = "SELECT b.*, k.nama_kategori
                FROM barang b
                JOIN kategori k ON b.kategori_id = k.kategori_id
                WHERE b.barang_id = ? AND b.aktif = 1";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $id);

        } elseif (!empty($searchRaw)) {

            // 🔹 MULTI KATA SEARCH (TANPA BATAS KATA)
            $words = preg_split('/\s+/', $searchRaw);

            $conditions = [];
            $params = [];
            $types = "";

            foreach ($words as $word) {
                $conditions[] = "(b.nama_barang LIKE ? OR b.barcode LIKE ? OR k.nama_kategori LIKE ?)";
                $like = "%$word%";
                $params[] = $like;
                $params[] = $like;
                $params[] = $like;
                $types .= "sss";
            }

            $sql = "
        SELECT b.*, k.nama_kategori
        FROM barang b
        JOIN kategori k ON b.kategori_id = k.kategori_id
        WHERE b.aktif = 1
        AND " . implode(" AND ", $conditions) . "
        ORDER BY b.barang_id DESC
        ";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);

        } else {

            // 🔹 GET ALL
            $sql = "SELECT b.*, k.nama_kategori
                FROM barang b
                JOIN kategori k ON b.kategori_id = k.kategori_id
                WHERE b.aktif = 1
                ORDER BY b.barang_id DESC";
            $stmt = $conn->prepare($sql);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        if ($id > 0) {
            $data = cast_data_types($result->fetch_assoc());
            if (!$data) {
                echo json_encode(["status" => "error", "message" => "Barang tidak ditemukan."]);
                exit;
            }
        } else {
            $data = [];
            while ($row = $result->fetch_assoc()) {
                $data[] = cast_data_types($row);
            }
        }

        echo json_encode(["status" => "success", "data" => $data]);
        $stmt->close();
        break;


    // ===================================================
    // 🔹 INSERT DATA
    // ===================================================
    case 'POST':
        if (empty($input['nama_barang']) || empty($input['kategori_id'])) {
            echo json_encode(["status" => "error", "message" => "Nama barang dan kategori wajib diisi."]);
            exit;
        }

        // Ambil dan bersihkan input
        $barcode = isset($input['barcode']) ? strval($input['barcode']) : "";
        $nama_barang = strtoupper(trim($input['nama_barang']));
        $kategori_id = intval($input['kategori_id']);
        $harga_hna = isset($input['harga_hna']) ? floatval($input['harga_hna']) : 0;
        $stok = isset($input['stok']) ? intval($input['stok']) : 0;
        $satuan = isset($input['satuan']) ? strtoupper(trim($input['satuan'])) : "";

        // Validasi angka tidak negatif
        if ($harga_hna < 0 || $stok < 0) {
            echo json_encode(["status" => "error", "message" => "Harga dan stok tidak boleh negatif."]);
            exit;
        }

        // Query insert
        $sql = "INSERT INTO barang 
        (barcode, nama_barang, kategori_id, harga_hna, stok, satuan) 
        VALUES (?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);

        $stmt->bind_param(
            "ssidis",
            $barcode,
            $nama_barang,
            $kategori_id,
            $harga_hna,
            $stok,
            $satuan
        );

        // Eksekusi & error handling
        if ($stmt->execute()) {
            echo json_encode(["status" => "success", "message" => "Barang berhasil ditambahkan."]);
        } elseif ($conn->errno == 1062) {
            echo json_encode(["status" => "error", "message" => "Nama barang sudah digunakan."]);
        } else {
            echo json_encode([
                "status" => "error",
                "message" => "Gagal menambah barang: " . $stmt->error
            ]);
        }

        $stmt->close();
        break;


    // ===================================================
    // 🔹 UPDATE DATA
    // ===================================================
    case 'PUT':
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;

        if ($id === 0 || !$input) {
            echo json_encode(["status" => "error", "message" => "Data tidak lengkap untuk update."]);
            exit;
        }

        // Ambil dan bersihkan input
        $barcode = isset($input['barcode']) ? strval($input['barcode']) : "";
        $nama_barang = strtoupper(trim($input['nama_barang']));
        $kategori_id = intval($input['kategori_id']);
        $harga_hna = isset($input['harga_hna']) ? floatval($input['harga_hna']) : 0;
        $stok = isset($input['stok']) ? intval($input['stok']) : 0;
        $satuan = isset($input['satuan']) ? strtoupper(trim($input['satuan'])) : "";

        // Validasi angka
        if ($harga_hna < 0 || $stok < 0) {
            echo json_encode(["status" => "error", "message" => "Harga dan stok tidak boleh negatif."]);
            exit;
        }

        // Query update
        $sql = "UPDATE barang 
            SET barcode=?, nama_barang=?, kategori_id=?, harga_hna=?, stok=?, satuan=? 
            WHERE barang_id=?";
        $stmt = $conn->prepare($sql);

        $stmt->bind_param(
            "ssidisi",
            $barcode,
            $nama_barang,
            $kategori_id,
            $harga_hna,
            $stok,
            $satuan,
            $id
        );

        // Eksekusi
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                echo json_encode(["status" => "success", "message" => "Barang berhasil diperbarui."]);
            } else {
                echo json_encode(["status" => "info", "message" => "Tidak ada perubahan data atau barang tidak ditemukan."]);
            }
        } elseif ($conn->errno == 1062) {
            echo json_encode(["status" => "error", "message" => "Nama barang sudah digunakan."]);
        } else {
            echo json_encode([
                "status" => "error",
                "message" => "Gagal memperbarui barang: " . $stmt->error
            ]);
        }

        $stmt->close();
        break;


    // ===================================================
    // 🔹 SOFT DELETE
    // ===================================================
    case 'DELETE':
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if ($id === 0) {
            echo json_encode(["status" => "error", "message" => "ID barang tidak valid."]);
            exit;
        }

        $sql = "UPDATE barang SET aktif = 0 WHERE barang_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);

        if ($stmt->execute() && $stmt->affected_rows > 0) {
            echo json_encode(["status" => "success", "message" => "Barang berhasil dihapus."]);
        } else {
            echo json_encode(["status" => "error", "message" => "Barang tidak ditemukan atau sudah dihapus."]);
        }

        $stmt->close();
        break;

    // ===================================================
    // 🔹 METODE LAIN (ERROR)
    // ===================================================
    default:
        http_response_code(405);
        echo json_encode(["status" => "error", "message" => "Metode tidak diizinkan."]);
        break;
}

$conn->close();
?>