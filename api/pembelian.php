<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

include 'db.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;

    if ($id > 0) {
        // Detail Pembelian
        $sql = "SELECT p.*, s.nama_suplier, u.nama AS user_nama 
                FROM pembelian p 
                LEFT JOIN suplier s ON p.suplier_id = s.suplier_id 
                LEFT JOIN users u ON p.user_id = u.user_id 
                WHERE p.pembelian_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $pembelian = $result->fetch_assoc();

        if ($pembelian) {
            // Ambil item detail
            // Asumsi kolom foreign key di detail_pembelian adalah pembelian_id
            $sql_detail = "SELECT dp.*, b.nama_barang, b.barcode 
                           FROM detail_pembelian dp 
                           JOIN barang b ON dp.barang_id = b.barang_id 
                           WHERE dp.pembelian_id = ? 
                           ORDER BY dp.detail_urut ASC";
            $stmt_detail = $conn->prepare($sql_detail);
            $stmt_detail->bind_param("i", $id);
            $stmt_detail->execute();
            $details = $stmt_detail->get_result()->fetch_all(MYSQLI_ASSOC);
            
            $pembelian['details'] = $details;
            echo json_encode(["status" => "success", "data" => $pembelian]);
        } else {
            echo json_encode(["status" => "error", "message" => "Pembelian tidak ditemukan"]);
        }
    } else {
        // List Pembelian
        $sql = "SELECT p.*, s.nama_suplier, u.nama AS user_nama 
                FROM pembelian p 
                LEFT JOIN suplier s ON p.suplier_id = s.suplier_id 
                LEFT JOIN users u ON p.user_id = u.user_id 
                ORDER BY p.tanggal DESC, p.pembelian_id DESC";
        $result = $conn->query($sql);
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        echo json_encode(["status" => "success", "data" => $data]);
    }
} elseif ($method === 'POST') {
    // Buat Pembelian Baru
    $input = json_decode(file_get_contents("php://input"), true);
    
    $user_id = $input['user_id'] ?? 0;
    $suplier_id = $input['suplier_id'] ?? null;
    $tanggal = $input['tanggal'] ?? date('Y-m-d');
    $keterangan = $input['keterangan'] ?? '';
    $items = $input['items'] ?? [];

    if (!$suplier_id) {
        echo json_encode(["status" => "error", "message" => "Suplier wajib dipilih."]);
        exit;
    }

    if (empty($items)) {
        echo json_encode(["status" => "error", "message" => "Item pembelian kosong"]);
        exit;
    }

    // Validasi Expired
    foreach ($items as $item) {
        if (empty($item['pembelian_expired'])) {
            echo json_encode(["status" => "error", "message" => "Tanggal expired wajib diisi untuk semua barang."]);
            exit;
        }
    }

    $conn->begin_transaction();
    try {
        // Hitung total
        $total_barang = 0;
        foreach ($items as $item) {
            $total_barang += $item['subtotal'];
        }
        $total_akhir = $total_barang;

        // Insert Pembelian
        $sql = "INSERT INTO pembelian (tanggal, user_id, suplier_id, total, keterangan, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("siids", $tanggal, $user_id, $suplier_id, $total_akhir, $keterangan);
        $stmt->execute();
        $pembelian_id = $conn->insert_id;

        // Insert Detail & Update Stok (BERTAMBAH)
        $sql_detail = "INSERT INTO detail_pembelian (pembelian_id, detail_urut, barang_id, jumlah, harga_satuan, subtotal, pembelian_expired) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt_detail = $conn->prepare($sql_detail);

        $sql_update_stok = "UPDATE barang SET stok = stok + ?, harga_beli = ? WHERE barang_id = ?";
        $stmt_stok = $conn->prepare($sql_update_stok);

        foreach ($items as $index => $item) {
            $urut = $index + 1;
            $expired = !empty($item['pembelian_expired']) ? $item['pembelian_expired'] : null;
            $stmt_detail->bind_param("iiidids", $pembelian_id, $urut, $item['barang_id'], $item['jumlah'], $item['harga_satuan'], $item['subtotal'], $expired);
            $stmt_detail->execute();

            // Tambah stok barang
            $stmt_stok->bind_param("idi", $item['jumlah'], $item['harga_satuan'], $item['barang_id']);
            $stmt_stok->execute();
        }

        $conn->commit();
        echo json_encode(["status" => "success", "message" => "Pembelian berhasil disimpan", "id" => $pembelian_id]);

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(["status" => "error", "message" => "Gagal menyimpan: " . $e->getMessage()]);
    }

} elseif ($method === 'PUT') {
    // Update Pembelian
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    $input = json_decode(file_get_contents("php://input"), true);

    if ($id <= 0 || empty($input['items'])) {
        echo json_encode(["status" => "error", "message" => "Data tidak valid"]);
        exit;
    }

    $suplier_id = $input['suplier_id'] ?? null;
    $tanggal = $input['tanggal'] ?? date('Y-m-d');
    $keterangan = $input['keterangan'] ?? '';
    $items = $input['items'];

    if (!$suplier_id) {
        echo json_encode(["status" => "error", "message" => "Suplier wajib dipilih."]);
        exit;
    }

    // Validasi Expired
    foreach ($items as $item) {
        if (empty($item['pembelian_expired'])) {
            echo json_encode(["status" => "error", "message" => "Tanggal expired wajib diisi untuk semua barang."]);
            exit;
        }
    }

    $conn->begin_transaction();
    try {
        // 1. Kembalikan Stok Lama (Kurangi stok karena pembelian lama dihapus/diganti)
        $sql_old = "SELECT barang_id, jumlah FROM detail_pembelian WHERE pembelian_id = ?";
        $stmt_old = $conn->prepare($sql_old);
        $stmt_old->bind_param("i", $id);
        $stmt_old->execute();
        $res_old = $stmt_old->get_result();

        $sql_revert_stok = "UPDATE barang SET stok = stok - ? WHERE barang_id = ?";
        $stmt_revert = $conn->prepare($sql_revert_stok);

        while ($row = $res_old->fetch_assoc()) {
            $stmt_revert->bind_param("ii", $row['jumlah'], $row['barang_id']);
            $stmt_revert->execute();
        }

        // 2. Hapus Detail Lama
        $conn->query("DELETE FROM detail_pembelian WHERE pembelian_id = $id");

        // 3. Hitung Total Baru & Update Tabel Utama
        $total_barang = 0;
        foreach ($items as $item) $total_barang += $item['subtotal'];
        $total_akhir = $total_barang;

        $sql_update = "UPDATE pembelian SET tanggal=?, suplier_id=?, total=?, keterangan=? WHERE pembelian_id=?";
        $stmt_up = $conn->prepare($sql_update);
        $stmt_up->bind_param("sidsi", $tanggal, $suplier_id, $total_akhir, $keterangan, $id);
        $stmt_up->execute();

        // 4. Insert Detail Baru & Tambah Stok Baru
        $sql_ins_detail = "INSERT INTO detail_pembelian (pembelian_id, detail_urut, barang_id, jumlah, harga_satuan, subtotal, pembelian_expired) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt_ins = $conn->prepare($sql_ins_detail);
        $sql_add_stok = "UPDATE barang SET stok = stok + ?, harga_beli = ? WHERE barang_id = ?";
        $stmt_add = $conn->prepare($sql_add_stok);

        foreach ($items as $idx => $item) {
            $urut = $idx + 1;
            $expired = !empty($item['pembelian_expired']) ? $item['pembelian_expired'] : null;
            $stmt_ins->bind_param("iiidids", $id, $urut, $item['barang_id'], $item['jumlah'], $item['harga_satuan'], $item['subtotal'], $expired);
            $stmt_ins->execute();

            $stmt_add->bind_param("idi", $item['jumlah'], $item['harga_satuan'], $item['barang_id']);
            $stmt_add->execute();
        }

        $conn->commit();
        echo json_encode(["status" => "success", "message" => "Pembelian berhasil diperbarui"]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(["status" => "error", "message" => "Gagal update: " . $e->getMessage()]);
    }

} elseif ($method === 'DELETE') {
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if ($id <= 0) {
        echo json_encode(["status" => "error", "message" => "ID tidak valid"]);
        exit;
    }

    $conn->begin_transaction();
    try {
        // 1. Ambil detail untuk MENGURANGI stok (karena pembelian dibatalkan)
        $sql_get = "SELECT barang_id, jumlah FROM detail_pembelian WHERE pembelian_id = ?";
        $stmt_get = $conn->prepare($sql_get);
        $stmt_get->bind_param("i", $id);
        $stmt_get->execute();
        $result = $stmt_get->get_result();

        $sql_stok = "UPDATE barang SET stok = stok - ? WHERE barang_id = ?";
        $stmt_stok = $conn->prepare($sql_stok);

        while ($row = $result->fetch_assoc()) {
            $stmt_stok->bind_param("ii", $row['jumlah'], $row['barang_id']);
            $stmt_stok->execute();
        }

        // 2. Hapus detail
        $sql_del_detail = "DELETE FROM detail_pembelian WHERE pembelian_id = ?";
        $stmt_del_detail = $conn->prepare($sql_del_detail);
        $stmt_del_detail->bind_param("i", $id);
        $stmt_del_detail->execute();

        // 3. Hapus pembelian
        $sql_del = "DELETE FROM pembelian WHERE pembelian_id = ?";
        $stmt_del = $conn->prepare($sql_del);
        $stmt_del->bind_param("i", $id);
        $stmt_del->execute();

        $conn->commit();
        echo json_encode(["status" => "success", "message" => "Pembelian berhasil dihapus"]);

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(["status" => "error", "message" => "Gagal menghapus: " . $e->getMessage()]);
    }
}
?>