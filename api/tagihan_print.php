<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include 'db.php';

// Ambil ID Tagihan dari parameter GET
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    echo json_encode([
        "status" => "error",
        "message" => "ID Tagihan tidak valid atau tidak ditemukan."
    ]);
    exit;
}

try {
    // 1. Ambil Data Header Tagihan
    $sqlHeader = "SELECT * FROM tagihan WHERE tagihan_id = ? AND tagihan_aktif = 1";
    $stmt = $conn->prepare($sqlHeader);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $resHeader = $stmt->get_result();
    $tagihan = $resHeader->fetch_assoc();
    $stmt->close();

    if (!$tagihan) {
        throw new Exception("Data tagihan tidak ditemukan atau sudah dihapus.");
    }

    // 2. Ambil Data Detail Tagihan (Join Pelanggan dan Penjualan)
    $sqlDetail = "SELECT dt.*, p.nama_pelanggan, pj.tanggal AS tanggal_transaksi 
                  FROM detail_tagihan dt 
                  LEFT JOIN pelanggan p ON dt.tagihan_pelanggan_id = p.pelanggan_id 
                  LEFT JOIN penjualan pj ON dt.penjualan_id = pj.penjualan_id 
                  WHERE dt.tagihan_id = ? 
                  ORDER BY dt.detail_tagihan_id ASC";
    $stmtDetail = $conn->prepare($sqlDetail);
    $stmtDetail->bind_param("i", $id);
    $stmtDetail->execute();
    $resDetail = $stmtDetail->get_result();
    
    $tagihan['details'] = $resDetail->fetch_all(MYSQLI_ASSOC);
    $stmtDetail->close();

    echo json_encode(["status" => "success", "data" => $tagihan]);

} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>
