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
        $search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';

        if ($id > 0) {
            // Get Single Order with Details
            $sql = "SELECT p.*, pl.nama_pelanggan 
                    FROM pesanan p 
                    LEFT JOIN pelanggan pl ON p.pelanggan_id = pl.pelanggan_id 
                    WHERE p.pesanan_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $pesanan = $result->fetch_assoc();

            if ($pesanan) {
                $sql_detail = "SELECT dp.*, b.nama_barang, b.barcode 
                               FROM detail_pesanan dp 
                               JOIN barang b ON dp.barang_id = b.barang_id 
                               WHERE dp.pesanan_id = ?";
                $stmt_detail = $conn->prepare($sql_detail);
                $stmt_detail->bind_param("i", $id);
                $stmt_detail->execute();
                $pesanan['details'] = $stmt_detail->get_result()->fetch_all(MYSQLI_ASSOC);
                echo json_encode(["status" => "success", "data" => $pesanan]);
            } else {
                echo json_encode(["status" => "error", "message" => "Pesanan tidak ditemukan"]);
            }
        } else {
            // Search and List Orders
            $where = "WHERE 1=1";
            if (!empty($search)) {
                $where .= " AND (p.pelanggan_sales LIKE '%$search%' OR pl.nama_pelanggan LIKE '%$search%' OR p.pesanan_id LIKE '%$search%')";
            }

            $sql = "SELECT p.*, pl.nama_pelanggan 
                    FROM pesanan p 
                    LEFT JOIN pelanggan pl ON p.pelanggan_id = pl.pelanggan_id 
                    $where 
                    ORDER BY p.tanggal_pesanan DESC, p.pesanan_id DESC";
            $result = $conn->query($sql);
            $data = $result->fetch_all(MYSQLI_ASSOC);
            echo json_encode(["status" => "success", "data" => $data]);
        }
        break;

    case 'POST':
        $pelanggan_id = $input['pelanggan_id'] ?? null;
        $pelanggan_sales = $input['pelanggan_sales'] ?? '';
        $tanggal_pesanan = $input['tanggal_pesanan'] ?? date('Y-m-d');
        $grandtotal = $input['grandtotal'] ?? 0;
        $catatan = $input['catatan'] ?? '';
        $items = $input['items'] ?? [];

        if (empty($items)) {
            echo json_encode(["status" => "error", "message" => "Item pesanan tidak boleh kosong"]);
            exit;
        }

        $conn->begin_transaction();
        try {
            $sql = "INSERT INTO pesanan (pelanggan_id, pelanggan_sales, tanggal_pesanan, grandtotal, catatan) VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("issds", $pelanggan_id, $pelanggan_sales, $tanggal_pesanan, $grandtotal, $catatan);
            $stmt->execute();
            $pesanan_id = $conn->insert_id;

            $sql_detail = "INSERT INTO detail_pesanan (pesanan_id, barang_id, jumlah) VALUES (?, ?, ?)";
            $stmt_detail = $conn->prepare($sql_detail);

            foreach ($items as $item) {
                $stmt_detail->bind_param("iii", $pesanan_id, $item['barang_id'], $item['jumlah']);
                $stmt_detail->execute();
            }

            $conn->commit();
            echo json_encode(["status" => "success", "message" => "Pesanan berhasil disimpan", "id" => $pesanan_id]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(["status" => "error", "message" => "Gagal menyimpan: " . $e->getMessage()]);
        }
        break;

    case 'PUT':
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if ($id <= 0) {
            echo json_encode(["status" => "error", "message" => "ID tidak valid"]);
            exit;
        }

        $pelanggan_id = $input['pelanggan_id'] ?? null;
        $pelanggan_sales = $input['pelanggan_sales'] ?? '';
        $tanggal_pesanan = $input['tanggal_pesanan'] ?? date('Y-m-d');
        $grandtotal = $input['grandtotal'] ?? 0;
        $catatan = $input['catatan'] ?? '';
        $items = $input['items'] ?? [];

        $conn->begin_transaction();
        try {
            // Update Header
            $sql = "UPDATE pesanan SET pelanggan_id = ?, pelanggan_sales = ?, tanggal_pesanan = ?, grandtotal = ?, catatan = ? WHERE pesanan_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("issdsi", $pelanggan_id, $pelanggan_sales, $tanggal_pesanan, $grandtotal, $catatan, $id);
            $stmt->execute();

            // Delete Old Details
            $conn->query("DELETE FROM detail_pesanan WHERE pesanan_id = $id");

            // Insert New Details
            $sql_detail = "INSERT INTO detail_pesanan (pesanan_id, barang_id, jumlah) VALUES (?, ?, ?)";
            $stmt_detail = $conn->prepare($sql_detail);
            foreach ($items as $item) {
                $stmt_detail->bind_param("iii", $id, $item['barang_id'], $item['jumlah']);
                $stmt_detail->execute();
            }

            $conn->commit();
            echo json_encode(["status" => "success", "message" => "Pesanan berhasil diperbarui"]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(["status" => "error", "message" => "Gagal memperbarui: " . $e->getMessage()]);
        }
        break;

    case 'DELETE':
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if ($id <= 0) {
            echo json_encode(["status" => "error", "message" => "ID tidak valid"]);
            exit;
        }

        $conn->begin_transaction();
        try {
            // Delete Details first
            $stmt_del_detail = $conn->prepare("DELETE FROM detail_pesanan WHERE pesanan_id = ?");
            $stmt_del_detail->bind_param("i", $id);
            $stmt_del_detail->execute();

            // Delete Header
            $stmt_del_pesanan = $conn->prepare("DELETE FROM pesanan WHERE pesanan_id = ?");
            $stmt_del_pesanan->bind_param("i", $id);
            $stmt_del_pesanan->execute();

            $conn->commit();
            echo json_encode(["status" => "success", "message" => "Pesanan berhasil dihapus"]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(["status" => "error", "message" => "Gagal menghapus: " . $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(["status" => "error", "message" => "Metode tidak didukung"]);
        break;
}
?>
