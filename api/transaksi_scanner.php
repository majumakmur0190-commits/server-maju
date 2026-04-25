<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include 'db.php';

// Fungsi untuk menghitung total penjualan
function calculateSaleTotals($conn, $penjualan_id)
{
    // Hitung subtotal dari detail_penjualan
    $sql_detail_subtotal = "SELECT SUM(subtotal) AS total_detail_subtotal FROM detail_penjualan WHERE penjualan_id = ?";
    $stmt_detail_subtotal = $conn->prepare($sql_detail_subtotal);
    $stmt_detail_subtotal->bind_param("i", $penjualan_id);
    $stmt_detail_subtotal->execute();
    $result_detail_subtotal = $stmt_detail_subtotal->get_result();
    $row_detail_subtotal = $result_detail_subtotal->fetch_assoc();
    $subtotal_penjualan = $row_detail_subtotal['total_detail_subtotal'] ?? 0;

    // Total penjualan sekarang sama dengan subtotal
    $total_penjualan = $subtotal_penjualan;

    // Update tabel penjualan
    $sql_update_penjualan = "UPDATE penjualan SET total = ? WHERE penjualan_id = ?";
    $stmt_update_penjualan = $conn->prepare($sql_update_penjualan);
    $stmt_update_penjualan->bind_param("di", $total_penjualan, $penjualan_id);
    $stmt_update_penjualan->execute();

    return [
        'subtotal' => $subtotal_penjualan,
        'total' => $total_penjualan
    ];
}

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents("php://input"), true);

switch ($method) {
    case 'GET':
        $penjualan_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';

        // Ambil parameter role dan user_id untuk filtering
        $role = isset($_GET['role']) ? $_GET['role'] : '';
        $user_id_filter = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

        if ($penjualan_id > 0) {
            // Ambil detail penjualan tunggal
            $sql_penjualan = "SELECT p.*, u.nama AS user_nama, pl.nama_pelanggan 
                          FROM penjualan p 
                          JOIN users u ON p.user_id = u.user_id 
                          LEFT JOIN pelanggan pl ON p.pelanggan_id = pl.pelanggan_id 
                          WHERE p.penjualan_id = ?";
            $stmt_penjualan = $conn->prepare($sql_penjualan);
            $stmt_penjualan->bind_param("i", $penjualan_id);
            $stmt_penjualan->execute();
            $result_penjualan = $stmt_penjualan->get_result();
            $penjualan_data = $result_penjualan->fetch_assoc();

            if ($penjualan_data) {
                // 🔹 Ambil detail penjualan dan urutkan sesuai detail_urut
                $sql_detail = "SELECT dp.*, b.nama_barang, b.satuan 
                           FROM detail_penjualan dp 
                           JOIN barang b ON dp.barang_id = b.barang_id 
                           WHERE dp.penjualan_id = ? 
                           ORDER BY dp.detail_urut ASC";
                $stmt_detail = $conn->prepare($sql_detail);
                $stmt_detail->bind_param("i", $penjualan_id);
                $stmt_detail->execute();
                $result_detail = $stmt_detail->get_result();
                $penjualan_data['details'] = [];
                while ($row_detail = $result_detail->fetch_assoc()) {
                    $penjualan_data['details'][] = $row_detail;
                }
                echo json_encode(["status" => "success", "data" => $penjualan_data]);
            } else {
                echo json_encode(["status" => "error", "message" => "Penjualan tidak ditemukan."]);
            }
        } else {
            // Ambil semua penjualan atau berdasarkan pencarian
            $where = "WHERE 1=1";

            // Jika role adalah 'kasir', filter berdasarkan user_id
            if ($role === 'kasir' && $user_id_filter > 0) {
                $where .= " AND p.user_id = " . $user_id_filter;
            }

            if (!empty($search)) {
                $where .= " AND (u.nama LIKE '%$search%' OR pl.nama_pelanggan LIKE '%$search%' OR p.penjualan_id LIKE '%$search%')";
            }

            $sql = "SELECT p.*, u.nama AS user_nama, pl.nama_pelanggan 
                FROM penjualan p 
                JOIN users u ON p.user_id = u.user_id 
                LEFT JOIN pelanggan pl ON p.pelanggan_id = pl.pelanggan_id 
                $where 
                ORDER BY p.tanggal DESC";

            $result = $conn->query($sql);

            $data = [];
            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $data[] = $row;
                }
            }
            echo json_encode(["status" => "success", "data" => $data]);
        }
        break;


    case 'POST':
        $conn->begin_transaction();
        try {
            $user_id = intval($input['user_id'] ?? 1); // default user_id 1
            $pelanggan_id = isset($input['pelanggan_id']) && $input['pelanggan_id'] !== '' ? intval($input['pelanggan_id']) : null;
            $items = $input['items'] ?? [];

            if (empty($items)) {
                throw new Exception("Detail penjualan tidak boleh kosong.");
            }

            // 1. Insert header penjualan
            $sql_penjualan = "INSERT INTO penjualan (user_id, pelanggan_id) VALUES (?, ?)";
            $stmt_penjualan = $conn->prepare($sql_penjualan);
            $stmt_penjualan->bind_param("ii", $user_id, $pelanggan_id);
            $stmt_penjualan->execute();
            $penjualan_id = $conn->insert_id;

            // 2. Insert detail penjualan & update stok
            foreach ($items as $item) {
                $detail_urut = intval($item['detail_urut'] ?? 0);
                $barang_id = intval($item['barang_id']);
                $jumlah = intval($item['jumlah']);
                $harga_satuan = floatval($item['harga_satuan']);
                $subtotal = $jumlah * $harga_satuan;

                if ($jumlah <= 0) {
                    throw new Exception("Jumlah barang harus lebih dari 0.");
                }

                // cek stok
                $sql_check_stok = "SELECT stok FROM barang WHERE barang_id = ?";
                $stmt_check_stok = $conn->prepare($sql_check_stok);
                $stmt_check_stok->bind_param("i", $barang_id);
                $stmt_check_stok->execute();
                $res_stok = $stmt_check_stok->get_result()->fetch_assoc();

                if (!$res_stok) {
                    throw new Exception("Barang ID $barang_id tidak ditemukan.");
                }
                $current_stok = $res_stok['stok'];

                if ($current_stok < $jumlah) {
                    throw new Exception("Stok barang $barang_id tidak mencukupi. Tersedia: $current_stok");
                }
 
                // insert detail
                $sql_detail = "INSERT INTO detail_penjualan (penjualan_id, detail_urut, barang_id, jumlah, harga_satuan, subtotal) VALUES (?, ?, ?, ?, ?, ?)";
                $stmt_detail = $conn->prepare($sql_detail);
                $stmt_detail->bind_param("iiiidd", $penjualan_id, $detail_urut, $barang_id, $jumlah, $harga_satuan, $subtotal);
                $stmt_detail->execute();

                // update stok
                $sql_update_stok = "UPDATE barang SET stok = stok - ? WHERE barang_id = ?";
                $stmt_update_stok = $conn->prepare($sql_update_stok);
                $stmt_update_stok->bind_param("ii", $jumlah, $barang_id);
                $stmt_update_stok->execute();
            }

            // 3. Hitung & update total penjualan
            calculateSaleTotals($conn, $penjualan_id);

            $conn->commit();
            echo json_encode(["status" => "success", "message" => "Penjualan berhasil ditambahkan.", "penjualan_id" => $penjualan_id]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(["status" => "error", "message" => "Gagal menambah penjualan: " . $e->getMessage()]);
        }
        break;


    case 'PUT':
        $penjualan_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if ($penjualan_id === 0) {
            echo json_encode(["status" => "error", "message" => "ID Penjualan tidak ditemukan."]);
            exit();
        }

        $conn->begin_transaction();
        try {
            $user_id = intval($input['user_id'] ?? 1);
            $pelanggan_id = isset($input['pelanggan_id']) && $input['pelanggan_id'] !== '' ? intval($input['pelanggan_id']) : null;
            $items = $input['items'] ?? [];

            if (empty($items)) {
                throw new Exception("Detail penjualan tidak boleh kosong.");
            }

            // 1. Kembalikan stok barang lama
            $sql_old_details = "SELECT barang_id, jumlah FROM detail_penjualan WHERE penjualan_id = ?";
            $stmt_old_details = $conn->prepare($sql_old_details);
            $stmt_old_details->bind_param("i", $penjualan_id);
            $stmt_old_details->execute();
            $result_old_details = $stmt_old_details->get_result();
            while ($old_item = $result_old_details->fetch_assoc()) {
                $sql_restore_stok = "UPDATE barang SET stok = stok + ? WHERE barang_id = ?";
                $stmt_restore_stok = $conn->prepare($sql_restore_stok);
                $stmt_restore_stok->bind_param("ii", $old_item['jumlah'], $old_item['barang_id']);
                $stmt_restore_stok->execute();
            }

            // 2. Hapus detail penjualan lama
            $sql_delete_details = "DELETE FROM detail_penjualan WHERE penjualan_id = ?";
            $stmt_delete_details = $conn->prepare($sql_delete_details);
            $stmt_delete_details->bind_param("i", $penjualan_id);
            $stmt_delete_details->execute();

            // 3. Update header penjualan
            $sql_update_header = "UPDATE penjualan SET user_id = ?, pelanggan_id = ? WHERE penjualan_id = ?";
            $stmt_update_header = $conn->prepare($sql_update_header);
            $stmt_update_header->bind_param("iii", $user_id, $pelanggan_id, $penjualan_id);
            $stmt_update_header->execute();

            // 4. Insert detail penjualan baru & update stok
            foreach ($items as $item) {
                $detail_urut = intval($item['detail_urut'] ?? 0);
                $barang_id = intval($item['barang_id']);
                $jumlah = intval($item['jumlah']);
                $harga_satuan = floatval($item['harga_satuan']);
                $subtotal = $jumlah * $harga_satuan;

                if ($jumlah <= 0) {
                    throw new Exception("Jumlah barang harus lebih dari 0.");
                }

                // cek stok
                $sql_check_stok = "SELECT stok FROM barang WHERE barang_id = ?";
                $stmt_check_stok = $conn->prepare($sql_check_stok);
                $stmt_check_stok->bind_param("i", $barang_id);
                $stmt_check_stok->execute();
                $res_stok = $stmt_check_stok->get_result()->fetch_assoc();

                if (!$res_stok) {
                    throw new Exception("Barang ID $barang_id tidak ditemukan.");
                }
                $current_stok = $res_stok['stok'];

                if ($current_stok < $jumlah) {
                    throw new Exception("Stok barang $barang_id tidak mencukupi. Tersedia: $current_stok");
                }

                // insert detail
                $sql_detail = "INSERT INTO detail_penjualan (penjualan_id, detail_urut, barang_id, jumlah, harga_satuan, subtotal) VALUES (?, ?, ?, ?, ?, ?)";
                $stmt_detail = $conn->prepare($sql_detail);
                $stmt_detail->bind_param("iiiidd", $penjualan_id, $detail_urut, $barang_id, $jumlah, $harga_satuan, $subtotal);
                $stmt_detail->execute();

                // update stok
                $sql_update_stok = "UPDATE barang SET stok = stok - ? WHERE barang_id = ?";
                $stmt_update_stok = $conn->prepare($sql_update_stok);
                $stmt_update_stok->bind_param("ii", $jumlah, $barang_id);
                $stmt_update_stok->execute();
            }

            // 5. Hitung & update total penjualan
            calculateSaleTotals($conn, $penjualan_id);

            $conn->commit();
            echo json_encode(["status" => "success", "message" => "Penjualan berhasil diperbarui.", "penjualan_id" => $penjualan_id]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(["status" => "error", "message" => "Gagal memperbarui penjualan: " . $e->getMessage()]);
        }
        break;


    case 'DELETE':
        $penjualan_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if ($penjualan_id === 0) {
            echo json_encode(["status" => "error", "message" => "ID Penjualan tidak ditemukan."]);
            exit();
        }

        $conn->begin_transaction();
        try {
            // 1. Kembalikan stok barang dari detail penjualan
            $sql_details = "SELECT barang_id, jumlah FROM detail_penjualan WHERE penjualan_id = ?";
            $stmt_details = $conn->prepare($sql_details);
            $stmt_details->bind_param("i", $penjualan_id);
            $stmt_details->execute();
            $result_details = $stmt_details->get_result();
            while ($item = $result_details->fetch_assoc()) {
                $sql_restore_stok = "UPDATE barang SET stok = stok + ? WHERE barang_id = ?";
                $stmt_restore_stok = $conn->prepare($sql_restore_stok);
                $stmt_restore_stok->bind_param("ii", $item['jumlah'], $item['barang_id']);
                $stmt_restore_stok->execute();
            }

            // 2. Hapus detail penjualan
            $sql_delete_details = "DELETE FROM detail_penjualan WHERE penjualan_id = ?";
            $stmt_delete_details = $conn->prepare($sql_delete_details);
            $stmt_delete_details->bind_param("i", $penjualan_id);
            $stmt_delete_details->execute();

            // 3. Hapus penjualan (header)
            $sql_delete_penjualan = "DELETE FROM penjualan WHERE penjualan_id = ?";
            $stmt_delete_penjualan = $conn->prepare($sql_delete_penjualan);
            $stmt_delete_penjualan->bind_param("i", $penjualan_id);
            $stmt_delete_penjualan->execute();

            $conn->commit();
            echo json_encode(["status" => "success", "message" => "Penjualan berhasil dihapus."]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(["status" => "error", "message" => "Gagal menghapus penjualan: " . $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(["status" => "error", "message" => "Metode tidak didukung."]);
        break;
}

$conn->close();
