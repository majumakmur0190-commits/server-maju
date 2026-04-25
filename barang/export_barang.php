<?php
include "koneksi.php";

header("Content-Type: text/csv");
header("Content-Disposition: attachment; filename=barang_export.csv");

$output = fopen("php://output", "w");

// Header Excel
fputcsv($output, [
    "ID",
    "Barcode",
    "Nama Barang",
    "Kategori",
    "Harga HNA",
    "Stok",
    "Satuan",
    "Aktif",
    "Created At"
]);

$where = [];

if (!empty($_GET['nama_barang'])) {
    $nama = mysqli_real_escape_string($conn, $_GET['nama_barang']);
    $where[] = "nama_barang LIKE '%$nama%'";
}

if (!empty($_GET['kategori_id'])) {
    $kategori = intval($_GET['kategori_id']);
    $where[] = "kategori_id = $kategori";
}

if (isset($_GET['aktif']) && $_GET['aktif'] !== "") {
    $aktif = intval($_GET['aktif']);
    $where[] = "aktif = $aktif";
}

$sql = "SELECT * FROM barang";
if ($where) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

$query = mysqli_query($conn, $sql);

while ($row = mysqli_fetch_assoc($query)) {
    fputcsv($output, [
        $row['barang_id'],
        $row['barcode'],
        $row['nama_barang'],
        $row['kategori_id'],
        $row['harga_hna'],
        $row['stok'],
        $row['satuan'],
        $row['aktif'],
        $row['created_at']
    ]);
}

fclose($output);
exit;
