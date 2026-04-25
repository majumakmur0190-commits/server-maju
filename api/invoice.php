<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");

require_once __DIR__ . '/db.php';

/* =======================================================
FUNGSI TERBILANG
======================================================= */
function angkaKeKata($angka)
{
    $angka = abs($angka);
    $huruf = ["", "Satu", "Dua", "Tiga", "Empat", "Lima", "Enam", "Tujuh", "Delapan", "Sembilan", "Sepuluh", "Sebelas"];
    $temp = "";

    if ($angka < 12) {
        $temp = " " . $huruf[$angka];
    } elseif ($angka < 20) {
        $temp = angkaKeKata($angka - 10) . " Belas";
    } elseif ($angka < 100) {
        $temp = angkaKeKata(intval($angka / 10)) . " Puluh" . angkaKeKata($angka % 10);
    } elseif ($angka < 200) {
        $temp = " Seratus" . angkaKeKata($angka - 100);
    } elseif ($angka < 1000) {
        $temp = angkaKeKata(intval($angka / 100)) . " Ratus" . angkaKeKata($angka % 100);
    } elseif ($angka < 2000) {
        $temp = " Seribu" . angkaKeKata($angka - 1000);
    } elseif ($angka < 1000000) {
        $temp = angkaKeKata(intval($angka / 1000)) . " Ribu" . angkaKeKata($angka % 1000);
    } elseif ($angka < 1000000000) {
        $temp = angkaKeKata(intval($angka / 1000000)) . " Juta" . angkaKeKata($angka % 1000000);
    }

    return trim($temp);
}

/* =======================================================
   VALIDASI PARAMETER
======================================================= */
$penjualan_id = intval($_GET['id'] ?? 0);

if ($penjualan_id <= 0) {
    http_response_code(400);
    echo json_encode([
        'status' => false,
        'message' => 'Parameter id tidak valid'
    ]);
    exit;
}

/* =======================================================
   DATA PENJUALAN & PELANGGAN
======================================================= */
$sql = "SELECT 
            p.penjualan_id,
            p.tanggal,
            u.nama AS kasir,
            pl.nama_pelanggan,
            pl.alamat,
            pl.no_telepon
        FROM penjualan p
        JOIN users u ON p.user_id = u.user_id
        LEFT JOIN pelanggan pl ON p.pelanggan_id = pl.pelanggan_id
        WHERE p.penjualan_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $penjualan_id);
$stmt->execute();
$penjualan = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$penjualan) {
    http_response_code(404);
    echo json_encode([
        'status' => false,
        'message' => 'Data penjualan tidak ditemukan'
    ]);
    exit;
}

/* =======================================================
   DATA BARANG
======================================================= */
$sql = "SELECT 
            b.nama_barang,
            k.nama_kategori,
            dp.jumlah,
            dp.harga_satuan,
            dp.subtotal
        FROM detail_penjualan dp
        JOIN barang b ON dp.barang_id = b.barang_id
        LEFT JOIN kategori k ON b.kategori_id = k.kategori_id
        WHERE dp.penjualan_id = ?
        ORDER BY dp.detail_id ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $penjualan_id);
$stmt->execute();
$barang = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();

/* =======================================================
   FORMAT RESPONSE
======================================================= */
$total = 0;
$listBarang = [];

foreach ($barang as $i => $row) {
    $total += $row['subtotal'];

    $listBarang[] = [
        'no' => $i + 1,
        'kategori' => $row['nama_kategori'],
        'nama_barang' => $row['nama_barang'],
        'qty' => (int) $row['jumlah'],
        'harga' => (float) $row['harga_satuan'],
        'subtotal' => (float) $row['subtotal']
    ];
}

/* =======================================================
   RESPONSE API
======================================================= */
$terbilang = angkaKeKata($total) . " Rupiah";

echo json_encode([
    'status' => true,
    'data' => [
        'penjualan' => [
            'penjualan_id' => $penjualan['penjualan_id'],
            'tanggal' => $penjualan['tanggal'],
            'nama_pelanggan' => $penjualan['nama_pelanggan'] ?? 'Umum',
            'alamat' => $penjualan['alamat'],
            'telepon' => $penjualan['no_telepon'],
            'kasir' => $penjualan['kasir']
        ],
        'barang' => $listBarang,
        'total' => $total,
        'terbilang' => $terbilang
    ]
], JSON_PRETTY_PRINT);

