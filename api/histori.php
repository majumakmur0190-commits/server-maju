<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST");
header("Access-Control-Allow-Headers: Content-Type");

include 'db.php';

$aksi = isset($_GET['aksi']) ? $_GET['aksi'] : '';

/* ============================================================
   1. PENCARIAN HISTORI BERDASARKAN ID PELANGGAN
   ============================================================ */
if ($aksi == "cari") {

    if (!isset($_GET['id_pelanggan'])) {
        echo json_encode(["status" => "error", "message" => "id_pelanggan wajib"]);
        exit;
    }
 
    $id_pelanggan = intval($_GET['id_pelanggan']);
    $sql = "SELECT * FROM histori_penjualan WHERE id_pelanggan = $id_pelanggan ORDER BY tanggal DESC";
    $result = $conn->query($sql);

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
   2. GET HARGA HISTORI (pelanggan + barang)
   ============================================================ */
if ($aksi == "getHarga") {

    $id_pelanggan = $_GET['id_pelanggan'];
    $id_barang = $_GET['id_barang'];

    $sql = "SELECT harga_satuan 
            FROM histori_penjualan 
            WHERE id_pelanggan='$id_pelanggan' 
              AND id_barang='$id_barang'
            ORDER BY tanggal DESC 
            LIMIT 1";

    $result = $conn->query($sql);

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

    $id_pelanggan = $post['pelanggan_id'];
    $items = $post['items']; // daftar barang transaksi


    foreach ($items as $row) {

        $id_barang = $row['barang_id'];
        $harga_baru = $row['harga_satuan'];

        // 1. cek histori terakhir
        $cek = $conn->query("
            SELECT id_histori, harga_satuan 
            FROM histori_penjualan 
            WHERE id_pelanggan='$id_pelanggan' AND id_barang='$id_barang'
            ORDER BY tanggal DESC LIMIT 1
        ");

        if ($cek->num_rows == 0) {
            // -------------------------------------------
            // INSERT histori pertama kali
            // -------------------------------------------
            $conn->query("
                INSERT INTO histori_penjualan (id_pelanggan, id_barang, harga_satuan) 
                VALUES ('$id_pelanggan', '$id_barang', '$harga_baru')
            ");

        } else {
            $old = $cek->fetch_assoc();
            $id_histori = $old['id_histori'];
            $harga_lama = $old['harga_satuan'];

            if ($harga_lama != $harga_baru) {
                // -------------------------------------------
                // UPDATE histori jika harga berubah
                // -------------------------------------------
                $conn->query("
                    UPDATE histori_penjualan 
                    SET harga_satuan='$harga_baru', tanggal=NOW() 
                    WHERE id_histori='$id_histori'
                ");
            }
        }
    }

    echo json_encode(["status" => "success", "message" => "Histori disimpan"]);
    exit;
}



/* ============================================================
   4. MANUAL UPDATE HISTORI (OPSIONAL)
   ============================================================ */
if ($aksi == "edit") {

    $json = file_get_contents("php://input");
    $post = json_decode($json, true);

    if (!$post || !isset($post['id_histori'])) {
        echo json_encode(["status" => "error", "message" => "id_histori wajib"]);
        exit;
    }

    $id_histori = $post['id_histori'];
    $id_pelanggan = $post['id_pelanggan'];
    $id_barang = $post['id_barang'];
    $harga_satuan = $post['harga_satuan'];

    $sql = "UPDATE histori_penjualan SET 
                id_pelanggan = '$id_pelanggan',
                id_barang = '$id_barang',
                harga_satuan = '$harga_satuan'
            WHERE id_histori = '$id_histori'";

    if ($conn->query($sql)) {
        echo json_encode(["status" => "success", "message" => "Histori berhasil diperbarui"]);
    } else {
        echo json_encode(["status" => "error", "message" => $conn->error]);
    }

    exit;
}

/* ============================================================
   5. DELETE HISTORI
   ============================================================ */

if ($aksi == "delete") {

    $json = file_get_contents("php://input");
    $post = json_decode($json, true);

    if (!$post || !isset($post['id_barang'])) {
        echo json_encode(["status" => "error", "message" => "id_barang wajib"]);
        exit;
    }

    $id_barang = $post['id_barang'];

    $sql = "DELETE FROM histori_penjualan WHERE id_barang = '$id_barang'";

    if ($conn->query($sql)) {
        echo json_encode(["status" => "success", "message" => "Histori berhasil dihapus"]);
    } else {
        echo json_encode(["status" => "error", "message" => $conn->error]);
    }

    exit;
}

/* ============================================================
   6. HAPUS HISTORI BERDASARKAN ID BARANG (RESET HARGA)
   ============================================================ */
if ($aksi == "hapus_barang") {
    $barang_id = isset($_GET['barang_id']) ? intval($_GET['barang_id']) : 0;

    if ($barang_id > 0) {
        $sql = "DELETE FROM histori_penjualan WHERE id_barang = '$barang_id'";
        if ($conn->query($sql)) {
            echo json_encode(["status" => "success", "message" => "Histori harga barang berhasil direset"]);
        } else {
            echo json_encode(["status" => "error", "message" => $conn->error]);
        }
    }
    exit;
}

// Default
echo json_encode(["status" => "error", "message" => "Aksi tidak ditemukan"]);
exit;

?>