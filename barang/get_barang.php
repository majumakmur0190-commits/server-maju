<?php
header("Content-Type: application/json");
include "koneksi.php";

$where = [];

if (!empty($_GET['nama_barang'])) {
    $nama = mysqli_real_escape_string($conn, $_GET['nama_barang']);
    $where[] = "nama_barang LIKE '%$nama%'";
}

if (!empty($_GET['kategori_id'])) {
    $kategori = intval($_GET['kategori_id']);
    $where[] = "kategori_id = $kategori";
}

if (!empty($_GET['barcode'])) {
    $barcode = mysqli_real_escape_string($conn, $_GET['barcode']);
    $where[] = "barcode = $barcode";
}

if (isset($_GET['aktif']) && $_GET['aktif'] !== "") {
    $aktif = intval($_GET['aktif']);
    $where[] = "aktif = $aktif";
}

$sql = "SELECT barang_id, barcode, nama_barang, kategori_id, harga_hna, stok, satuan, aktif, created_at FROM barang";

if ($where) {
    $sql .= " WHERE " . implode(" AND ", $where) ;
}

$query = mysqli_query($conn, $sql);

$data = [];
while ($row = mysqli_fetch_assoc($query)) {
    $data[] = $row;
}

echo json_encode([
    "status" => true,
    "data" => $data
]);
