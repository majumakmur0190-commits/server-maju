<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST");
header("Access-Control-Allow-Headers: Content-Type");

include 'db.php';

$aksi = isset($_GET['aksi']) ? $_GET['aksi'] : '';

/* ============================================================
   1. PENCARIAN HISTORI BERDASARKAN ID SUPLIER
   ============================================================ */
if ($aksi == "cari") {

    if (!isset($_GET['id_suplier'])) {
        echo json_encode(["status" => "error", "message" => "id_suplier wajib"]);
        exit;
    }
 
    $id_suplier = intval($_GET['id_suplier']);
    
    // Sesuai request: SELECT `id_histori`, `id_suplier`, `id_barang`, `harga_satuan`, `tanggal` FROM `histori_pembelian` WHERE 1
    $sql = "SELECT id_histori, id_suplier, id_barang, harga_satuan, tanggal 
            FROM histori_pembelian 
            WHERE id_suplier = ? 
            ORDER BY tanggal DESC";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id_suplier);
    $stmt->execute();
    $result = $stmt->get_result();

    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
 
    echo json_encode([
        "status" => "success",
        "jumlah_data" => count($data),
        "data" => $data
    ]);
    exit;
}

/* ============================================================
   2. GET HARGA TERAKHIR (SUPLIER + BARANG)
   ============================================================ */
if ($aksi == "getHarga") {

    $id_suplier = isset($_GET['id_suplier']) ? intval($_GET['id_suplier']) : 0;
    $id_barang = isset($_GET['id_barang']) ? intval($_GET['id_barang']) : 0;

    $sql = "SELECT harga_satuan 
            FROM histori_pembelian 
            WHERE id_suplier = ? 
              AND id_barang = ?
            ORDER BY tanggal DESC 
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $id_suplier, $id_barang);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        echo json_encode(["status" => "ada", "harga" => $row['harga_satuan']]);
    } else {
        echo json_encode(["status" => "tidak_ada"]);
    }

    exit;
}

/* ============================================================
   3. SAVE HISTORI (INSERT / UPDATE OTOMATIS)
   ============================================================ */
if ($aksi == "saveHistori") {

    $json = file_get_contents("php://input");
    $post = json_decode($json, true);

    if (!$post) {
        echo json_encode(["status" => "error", "message" => "JSON tidak valid"]);
        exit;
    }

    $id_suplier = isset($post['suplier_id']) ? intval($post['suplier_id']) : 0;
    $items = $post['items'] ?? []; // daftar barang pembelian

    if ($id_suplier == 0 || empty($items)) {
        echo json_encode(["status" => "error", "message" => "Data tidak lengkap"]);
        exit;
    }

    foreach ($items as $row) {
        $id_barang = intval($row['barang_id']);
        $harga_baru = floatval($row['harga_satuan']);

        // 1. Cek histori terakhir untuk suplier & barang ini
        $sql_cek = "SELECT id_histori, harga_satuan 
                    FROM histori_pembelian 
                    WHERE id_suplier = ? AND id_barang = ?
                    ORDER BY tanggal DESC LIMIT 1";
        
        $stmt_cek = $conn->prepare($sql_cek);
        $stmt_cek->bind_param("ii", $id_suplier, $id_barang);
        $stmt_cek->execute();
        $cek = $stmt_cek->get_result();

        if ($cek->num_rows == 0) {
            // INSERT histori pertama kali
            $sql_insert = "INSERT INTO histori_pembelian (id_suplier, id_barang, harga_satuan, tanggal) VALUES (?, ?, ?, NOW())";
            $stmt_insert = $conn->prepare($sql_insert);
            $stmt_insert->bind_param("iid", $id_suplier, $id_barang, $harga_baru);
            $stmt_insert->execute();
        } else {
            $old = $cek->fetch_assoc();
            $id_histori = $old['id_histori'];
            $harga_lama = floatval($old['harga_satuan']);

            if ($harga_lama != $harga_baru) {
                // UPDATE histori jika harga berubah (update tanggal juga)
                $sql_update = "UPDATE histori_pembelian SET harga_satuan = ?, tanggal = NOW() WHERE id_histori = ?";
                $stmt_update = $conn->prepare($sql_update);
                $stmt_update->bind_param("di", $harga_baru, $id_histori);
                $stmt_update->execute();
            }
        }
    }

    echo json_encode(["status" => "success", "message" => "Histori pembelian berhasil disimpan"]);
    exit;
}

/* ============================================================
   4. DEFAULT: LIST ALL (Jika tanpa parameter aksi)
   ============================================================ */
if ($aksi == '') {
    // Default query sesuai request user
    $sql = "SELECT id_histori, id_suplier, id_barang, harga_satuan, tanggal FROM histori_pembelian WHERE 1 ORDER BY tanggal DESC LIMIT 100";
    $result = $conn->query($sql);
    $data = $result->fetch_all(MYSQLI_ASSOC);
    echo json_encode(["status" => "success", "data" => $data]);
}
?>