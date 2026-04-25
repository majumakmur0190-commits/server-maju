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
        $offset = ($page - 1) * $limit;

        if ($id > 0) {
            // Ambil Data Header Tagihan
            $sql = "SELECT * FROM tagihan WHERE tagihan_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $data = $result->fetch_assoc();
            $stmt->close();

            if ($data) {
                // Ambil Data Detail Tagihan (Join Pelanggan untuk nama)
                $sqlDetail = "SELECT dt.*, p.nama_pelanggan, pj.tanggal AS tanggal_penjualan 
                              FROM detail_tagihan dt 
                              LEFT JOIN pelanggan p ON dt.tagihan_pelanggan_id = p.pelanggan_id 
                              LEFT JOIN penjualan pj ON dt.penjualan_id = pj.penjualan_id 
                              WHERE dt.tagihan_id = ?";
                $stmtDetail = $conn->prepare($sqlDetail);
                $stmtDetail->bind_param("i", $id);
                $stmtDetail->execute();
                $resDetail = $stmtDetail->get_result();
                $details = [];
                while ($row = $resDetail->fetch_assoc()) {
                    // Gunakan tanggal tagihan sebagai fallback untuk tampilan
                    $row['tanggal_transaksi'] = $row['tanggal_penjualan'] ?? $data['tagihan_tanggal'];
                    $details[] = $row;
                }
                $data['details'] = $details;

                echo json_encode(["status" => "success", "data" => $data]);
            } else {
                echo json_encode(["status" => "error", "message" => "Tagihan tidak ditemukan."]);
            }
        } else {
            // List Tagihan dengan Pagination & Pencarian
            $where = "WHERE tagihan_aktif = 1";
            $params = [];
            $types = "";

            if (!empty($search)) {
                $where .= " AND tagihan_nama LIKE ?";
                $searchTerm = "%$search%";
                $params[] = $searchTerm;
                $types .= "s";
            }

            // Hitung Total Data
            $sqlCount = "SELECT COUNT(*) as total FROM tagihan $where";
            $stmtCount = $conn->prepare($sqlCount);
            if (!empty($params)) {
                $stmtCount->bind_param($types, ...$params);
            }
            $stmtCount->execute();
            $resCount = $stmtCount->get_result();
            $totalRows = $resCount->fetch_assoc()['total'];
            $stmtCount->close();

            // Ambil Data
            $sql = "SELECT * FROM tagihan $where ORDER BY tagihan_id DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
            $types .= "ii";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $res = $stmt->get_result();
            $data = [];
            while ($row = $res->fetch_assoc()) {
                $data[] = $row;
            }
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
        $nama = $input['tagihan_nama'] ?? '';
        $tanggal = $input['tagihan_tanggal'] ?? date('Y-m-d');
        $setor = $input['tagihan_setor'] ?? 0;
        $details = $input['details'] ?? [];

        if (empty($nama)) {
            echo json_encode(["status" => "error", "message" => "Nama tagihan wajib diisi."]);
            exit;
        }

        $conn->begin_transaction();
        try {
            // 1. Insert Header (Total & Jumlah 0 dulu)
            $sql = "INSERT INTO tagihan (tagihan_tanggal, tagihan_nama, tagihan_jumlah, tagihan_total, tagihan_setor, tagihan_aktif) VALUES (?, ?, 0, 0, ?, 1)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssd", $tanggal, $nama, $setor);
            $stmt->execute();
            $tagihan_id = $conn->insert_id;
            $stmt->close();

            $total = 0;
            $jumlah = 0;

            // 2. Insert Details
            $sqlDetail = "INSERT INTO detail_tagihan (tagihan_id, tagihan_pelanggan_id, tagihan_subtotal, tagihan_lunas, penjualan_id) VALUES (?, ?, ?, ?, ?)";
            $stmtDetail = $conn->prepare($sqlDetail);

            foreach ($details as $d) {
                $pelanggan_id = $d['tagihan_pelanggan_id'];
                $subtotal = $d['tagihan_subtotal'];
                $lunas = $d['tagihan_lunas'] ?? 0;
                $penjualan_id = $d['penjualan_id'] ?? null;

                $stmtDetail->bind_param("iiddi", $tagihan_id, $pelanggan_id, $subtotal, $lunas, $penjualan_id);
                $stmtDetail->execute();

                $total += $subtotal;
                $jumlah++;
            }
            $stmtDetail->close();

            // 3. Update Header dengan Total & Jumlah yang dihitung
            $sqlUpdate = "UPDATE tagihan SET tagihan_jumlah = ?, tagihan_total = ? WHERE tagihan_id = ?";
            $stmtUpdate = $conn->prepare($sqlUpdate);
            $stmtUpdate->bind_param("idi", $jumlah, $total, $tagihan_id);
            $stmtUpdate->execute();
            $stmtUpdate->close();

            $conn->commit();
            echo json_encode(["status" => "success", "message" => "Tagihan berhasil dibuat.", "id" => $tagihan_id]);

        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(["status" => "error", "message" => "Gagal membuat tagihan: " . $e->getMessage()]);
        }
        break;

    case 'PUT':
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if ($id === 0) {
            echo json_encode(["status" => "error", "message" => "ID tidak valid."]);
            exit;
        }

        $nama = $input['tagihan_nama'] ?? '';
        $tanggal = $input['tagihan_tanggal'] ?? date('Y-m-d');
        $setor = $input['tagihan_setor'] ?? 0;
        $details = $input['details'] ?? null; // Jika null, detail tidak diubah

        $conn->begin_transaction();
        try {
            // Update Header
            $sql = "UPDATE tagihan SET tagihan_tanggal = ?, tagihan_nama = ?, tagihan_setor = ? WHERE tagihan_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssdi", $tanggal, $nama, $setor, $id);
            $stmt->execute();
            $stmt->close();

            // Jika ada data details, replace semua detail lama
            if ($details !== null) {
                // Hapus detail lama
                $conn->query("DELETE FROM detail_tagihan WHERE tagihan_id = $id");

                $total = 0;
                $jumlah = 0;
                $stmtDetail = $conn->prepare("INSERT INTO detail_tagihan (tagihan_id, tagihan_pelanggan_id, tagihan_subtotal, tagihan_lunas, penjualan_id) VALUES (?, ?, ?, ?, ?)");

                foreach ($details as $d) {
                    $penjualan_id = $d['penjualan_id'] ?? null;
                    $stmtDetail->bind_param("iiddi", $id, $d['tagihan_pelanggan_id'], $d['tagihan_subtotal'], $d['tagihan_lunas'], $penjualan_id);
                    $stmtDetail->execute();
                    $total += $d['tagihan_subtotal'];
                    $jumlah++;
                }
                $stmtDetail->close();

                // Update Total Header
                $conn->query("UPDATE tagihan SET tagihan_jumlah = $jumlah, tagihan_total = $total WHERE tagihan_id = $id");
            }

            $conn->commit();
            echo json_encode(["status" => "success", "message" => "Tagihan berhasil diperbarui."]);

        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(["status" => "error", "message" => "Gagal memperbarui tagihan: " . $e->getMessage()]);
        }
        break;

    case 'DELETE':
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if ($id === 0) {
            echo json_encode(["status" => "error", "message" => "ID tidak valid."]);
            exit;
        }

        // Soft Delete
        $sql = "UPDATE tagihan SET tagihan_aktif = 0 WHERE tagihan_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            echo json_encode(["status" => "success", "message" => "Tagihan berhasil dihapus."]);
        } else {
            echo json_encode(["status" => "error", "message" => "Gagal menghapus tagihan."]);
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