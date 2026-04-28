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
            $sql_detail = "SELECT dp.*, b.nama_barang, b.barcode,
                           dp.dis_1 AS diskon1, dp.dis_2 AS diskon2, dp.dis_3 AS diskon3, dp.dis_4 AS diskon4
                           FROM detail_pembelian dp 
                           JOIN barang b ON dp.barang_id = b.barang_id 
                           WHERE dp.pembelian_id = ? 
                           ORDER BY dp.detail_urut ASC";
            $stmt_detail = $conn->prepare($sql_detail);
            $stmt_detail->bind_param("i", $id);
            $stmt_detail->execute();
            $details = $stmt_detail->get_result()->fetch_all(MYSQLI_ASSOC);

            $pembelian['details'] = $details;

            // Decode data diskon tambahan agar bisa dibaca langsung oleh frontend
            $dis_data = json_decode($pembelian['dis_tambahan'] ?? '{}', true);
            if ($dis_data) {
                $pembelian = array_merge($pembelian, $dis_data);
            }

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
    $globalDiskon = $input['globalDiskon'] ?? [];

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

        // Kalkulasi Total Akhir dengan Diskon Global (Persen lalu Nominal)
        $g_persen = floatval($globalDiskon['global_diskon_persen'] ?? 0);
        $g_nominal = floatval($globalDiskon['global_diskon_nominal'] ?? 0);
        
        $total_akhir = $total_barang * (1 - $g_persen / 100);
        $total_akhir -= $g_nominal;
        if ($total_akhir < 0) $total_akhir = 0;

        $dis_tambahan_json = json_encode($globalDiskon);

        // Insert Pembelian
        $sql = "INSERT INTO pembelian (tanggal, user_id, suplier_id, total, dis_tambahan, keterangan, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("siidss", $tanggal, $user_id, $suplier_id, $total_akhir, $dis_tambahan_json, $keterangan);
        $stmt->execute();
        $pembelian_id = $conn->insert_id;

        // Insert Detail & Update Stok (BERTAMBAH)
        $sql_detail = "INSERT INTO detail_pembelian (pembelian_id, detail_urut, barang_id, jumlah, dis_1, dis_2, dis_3, dis_4, harga_satuan, subtotal, pembelian_expired) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_detail = $conn->prepare($sql_detail);

        $sql_update_stok = "UPDATE barang SET stok = stok + ?, harga_beli = ? WHERE barang_id = ?";
        $stmt_stok = $conn->prepare($sql_update_stok);

        foreach ($items as $index => $item) {
            $urut = $index + 1;
            $expired = !empty($item['pembelian_expired']) ? $item['pembelian_expired'] : null;
            $d1 = $item['diskon1'] ?? 0;
            $d2 = $item['diskon2'] ?? 0;
            $d3 = $item['diskon3'] ?? 0;
            $d4 = $item['diskon4'] ?? 0;

            $stmt_detail->bind_param("iiiidddddds", $pembelian_id, $urut, $item['barang_id'], $item['jumlah'], $d1, $d2, $d3, $d4, $item['harga_satuan'], $item['subtotal'], $expired);
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
    $globalDiskon = $input['globalDiskon'] ?? [];

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
        foreach ($items as $item)
            $total_barang += $item['subtotal'];

        // Kalkulasi Total Akhir dengan Diskon Global
        $g_persen = floatval($globalDiskon['global_diskon_persen'] ?? 0);
        $g_nominal = floatval($globalDiskon['global_diskon_nominal'] ?? 0);
        
        $total_akhir = $total_barang * (1 - $g_persen / 100);
        $total_akhir -= $g_nominal;
        if ($total_akhir < 0) $total_akhir = 0;

        $dis_tambahan_json = json_encode($globalDiskon);

        $sql_update = "UPDATE pembelian SET tanggal=?, suplier_id=?, total=?, dis_tambahan=?, keterangan=? WHERE pembelian_id=?";
        $stmt_up = $conn->prepare($sql_update);
        $stmt_up->bind_param("sidssi", $tanggal, $suplier_id, $total_akhir, $dis_tambahan_json, $keterangan, $id);
        $stmt_up->execute();

        // 4. Insert Detail Baru & Tambah Stok Baru
        $sql_ins_detail = "INSERT INTO detail_pembelian (pembelian_id, detail_urut, barang_id, jumlah, dis_1, dis_2, dis_3, dis_4, harga_satuan, subtotal, pembelian_expired) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_ins = $conn->prepare($sql_ins_detail);
        $sql_add_stok = "UPDATE barang SET stok = stok + ?, harga_beli = ? WHERE barang_id = ?";
        $stmt_add = $conn->prepare($sql_add_stok);

        foreach ($items as $idx => $item) {
            $urut = $idx + 1;
            $expired = !empty($item['pembelian_expired']) ? $item['pembelian_expired'] : null;
            $d1 = $item['diskon1'] ?? 0;
            $d2 = $item['diskon2'] ?? 0;
            $d3 = $item['diskon3'] ?? 0;
            $d4 = $item['diskon4'] ?? 0;

            $stmt_ins->bind_param("iiiidddddds", $id, $urut, $item['barang_id'], $item['jumlah'], $d1, $d2, $d3, $d4, $item['harga_satuan'], $item['subtotal'], $expired);
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