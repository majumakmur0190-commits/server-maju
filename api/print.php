<?php
require_once __DIR__ . '/../tcpdf/tcpdf.php';

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
        $temp = angkaKeKata(floor($angka / 10)) . " Puluh" . angkaKeKata($angka % 10);
    } elseif ($angka < 200) {
        $temp = " Seratus" . angkaKeKata($angka - 100);
    } elseif ($angka < 1000) {
        $temp = angkaKeKata(floor($angka / 100)) . " Ratus" . angkaKeKata($angka % 100);
    } elseif ($angka < 2000) {
        $temp = " Seribu" . angkaKeKata($angka - 1000);
    } elseif ($angka < 1000000) {
        $temp = angkaKeKata(floor($angka / 1000)) . " Ribu" . angkaKeKata($angka % 1000);
    } elseif ($angka < 1000000000) {
        $temp = angkaKeKata(floor($angka / 1000000)) . " Juta" . angkaKeKata($angka % 1000000);
    } elseif ($angka < 1000000000000) {
        $temp = angkaKeKata(floor($angka / 1000000000)) . " Milyar" . angkaKeKata($angka % 1000000000);
    }
    return trim($temp);
}

include 'db.php';

/* =======================================================
   AMBIL DATA PENJUALAN
======================================================= */
$penjualan_id = intval($_GET['id'] ?? 0);
if ($penjualan_id === 0)
    die("ID Penjualan tidak ditemukan.");

$sql_penjualan = "SELECT p.*, u.nama AS user_nama,
                         pl.nama_pelanggan, 
                         pl.alamat AS pelanggan_alamat, 
                         pl.no_telepon AS pelanggan_telepon
                  FROM penjualan p
                  JOIN users u ON p.user_id = u.user_id
                  LEFT JOIN pelanggan pl ON p.pelanggan_id = pl.pelanggan_id
                  WHERE p.penjualan_id = ?";


$stmt = $conn->prepare($sql_penjualan);
$stmt->bind_param("i", $penjualan_id);
$stmt->execute();
$penjualan_data = $stmt->get_result()->fetch_assoc();
$stmt->close();

/* =======================================================
   DETAIL PENJUALAN
======================================================= */
$sql_detail = "SELECT dp.*, b.nama_barang, b.satuan, k.nama_kategori
               FROM detail_penjualan dp
               JOIN barang b ON dp.barang_id = b.barang_id
               LEFT JOIN kategori k ON b.kategori_id = k.kategori_id
               WHERE dp.penjualan_id = ?
               ORDER BY dp.detail_id ASC";

$stmt = $conn->prepare($sql_detail);
$stmt->bind_param("i", $penjualan_id);
$stmt->execute();
$penjualan_details = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();

/* =======================================================
   CUSTOM PDF CLASS
======================================================= */
class MYPDF extends TCPDF
{
    public $tableYstart = 29;
    public $penjualan_data;
    public $subtotalPage = 0;

    public function Header()
    {
        global $penjualan_data;

        $noinvoice = "INV" . str_pad($this->penjualan_data['penjualan_id'], 6, '0', STR_PAD_LEFT);

        // ================================
        //  SETTING POSISI MANUAL
        // ================================
        $titleY = 8;

        // KIRI
        $leftX = 4;
        $leftY = 16;
        $leftW = 120;

        // KANAN
        $rightX = 132;
        $rightY = 16;
        $rightW = 50;
        // ================================


        // ========== TITLE ==========
        $this->SetY($titleY);
        $this->SetFont('helvetica', '', 13);
        $this->Cell(0, 6, 'INVOICE : ' . $noinvoice, 0, 1, 'C');

        $this->Ln(2);


        // ========== DATA PELANGGAN (KIRI) ==========
        $customer = $this->penjualan_data['nama_pelanggan'] ?? 'Umum';
        $alamat = $this->penjualan_data['pelanggan_alamat'];
        $telepon = $this->penjualan_data['pelanggan_telepon'];

        $this->SetFont('helvetica', '', 9);

        // Label kiri
        $this->SetXY($leftX, $leftY);
        $this->Cell($leftW, 4, "Kepada Yth:", 0, 1);

        // ========== 3 BARIS PELANGGAN ==========

        // Baris 1 — Nama
        $this->SetX($leftX);
        $this->Cell($leftW, 4, $customer, 0, 1);

        // Baris 2 — Alamat (dipotong jika terlalu panjang)
        $this->SetX($leftX);
        $this->MultiCell($leftW, 4, $alamat, 0, 'L');

        // Baris 3 — Telepon
        $this->SetX($leftX);
        $this->Cell($leftW, 4, "Telp: " . $telepon, 0, 1);

        $this->SetY($rightY); // Reset Y position for right column

        // ========== DATA TANGGAL (KANAN) ==========
        $invoiceDate = new DateTime($penjualan_data['tanggal']);
        $tanggalkirim = (clone $invoiceDate)->modify('+1 days');
        $tanggaltagih = (clone $invoiceDate)->modify('+22 days');

        // Baris 1
        $this->SetXY($rightX, $rightY);
        $this->Cell($rightW, 4, "Tanggal Invoice", 0, 0, 'L');
        $this->Cell(5, 4, ":", 0, 0, 'C');
        $this->Cell(30, 4, $tanggalkirim->format('d M Y'), 0, 1);

        // Baris 2
        $this->SetX($rightX);
        $this->Cell($rightW, 4, "Jatuh Tempo", 0, 0, 'L');
        $this->Cell(5, 4, ":", 0, 0, 'C');
        $this->Cell(30, 4, $tanggaltagih->format('d M Y'), 0, 1);

        // Baris 3
        $this->SetX($rightX);
        $this->Cell($rightW - 30, 4, "Toko  :", 0, 0, 'L');
        // type huruf untuk nama toko bold       $this->SetFont('helvetica', 'B', 9);
        $this->SetFont('helvetica', 'B', 10);
        $this->Cell(3, 4, $customer, 0, 1);

        // ========== TABLE HEADER ==========
        $this->SetY(36);
        $this->SetX($leftX + 16);
        $this->SetFont('helvetica', '', 10);

        $cols = [10, 96, 22, 10, 22];
        $headers = ['No', 'Nama Barang', 'Harga', 'Qty', 'Total'];

        foreach ($headers as $i => $h) {
            $this->Cell($cols[$i], 5, $h, 1, 0, 'C');
        }
        $this->Ln();
    }


    // ========== FOOTER ==========
    public function Footer()
    {
        // tanda tangan penerima
        $this->SetY(-12);
        $this->SetX(146);
        $this->Cell(50, 7, '', 'T', 1, 'C');
        $this->SetXY(146, $this->GetY() - 8);
        $this->Cell(50, 7, '(Penerima)', 0, 1, 'C');

        $this->SetY(-10);
        $this->SetFont('helvetica', 'I', 5);
        $this->Cell(0, 10, "Halaman " . $this->getAliasNumPage() . "/" . $this->getAliasNbPages(), 0, 0, 'C');
    }

    public function renderSubtotalPage()
    {
        if ($this->subtotalPage > 0) {
            $this->setX(20);
            $this->SetFont('helvetica', '', 9);
            $this->Cell(138, 6, "Subtotal Halaman", 1, 0, "R");
            $this->Cell(22, 6, number_format($this->subtotalPage, 0), 1, 1, "R");
            $this->Ln(2);
        }
    }

    public function finalFooter($total)
    {
        $this->Ln(0);

        $this->SetFont('helvetica', '', 9);
        $this->MultiCell(
            148,
            5,
            "Terbilang:\n (" . angkaKeKata($total) . " Rupiah)\n" .
            "Note:\n Barang yang sudah dibeli tidak dapat dikembalikan kecuali ada perjanjian.",
            0,
            'L',
            0

        );

        $this->SetXY(136, $this->GetY() - 14);
        $this->SetFont('helvetica', '', 10);
        $this->Cell(30, 7, "Grand Total:", 0, 0);
        $this->SetFont('helvetica', '', 12);
        $this->Cell(30, 7, number_format($total, 0), 0, 1, "R");
    }
}

/* =======================================================
   GENERATE PDF
======================================================= */
$pdf = new MYPDF('P', 'mm', [210, 280], true, 'UTF-8', false);
$pdf->penjualan_data = $penjualan_data; // Inject data into PDF object
$pdf->SetMargins(5, 3, 5);
$pdf->SetAutoPageBreak(true, 20);
$pdf->AddPage();

$pdf->SetFont('helvetica', '', 9);
$pdf->SetY($pdf->tableYstart + 12);
$cols = [10, 96, 22, 10, 22];
$totalNet = 0;

/* =======================================================
   RENDER TABLE ROWS
======================================================= */
foreach ($penjualan_details as $i => $item) {

    $no = $i + 1;
    $nama = $item['nama_barang'];
    $qty = $item['jumlah'];
    $price = $item['harga_satuan'];
    $net = $item['subtotal'];
    $totalNet += $net;

    // Set posisi X agar sama dengan header (20)
    $pdf->SetX(20);

    // Simpan posisi Y awal baris
    $y_start = $pdf->GetY();
    // Posisi X awal baris
    $x_start = $pdf->GetX();
    // Hitung tinggi yang dibutuhkan untuk sel nama barang
    // 4.5 adalah tinggi baris default
    $row_height = max(4.5, $pdf->getStringHeight($cols[1], $nama));

    // Cek lagi apakah baris ini akan melebihi batas halaman
    if ($y_start + $row_height > 240) { // Angka 240 adalah batas bawah halaman sebelum footer
        $pdf->renderSubtotalPage();
        $pdf->AddPage();
        $pdf->SetY($pdf->tableYstart + 12);
        $pdf->SetX($x_start);
        $pdf->subtotalPage = 0; // Reset subtotal untuk halaman baru
    }

    // Tambahkan nilai item ke subtotal halaman saat ini
    $pdf->subtotalPage += $net;

    // Gambar setiap sel dengan tinggi yang sama (row_height)
    $pdf->MultiCell($cols[0], $row_height, $no, 1, 'C', false, 0);
    $pdf->MultiCell($cols[1], $row_height, $nama, 1, 'L', false, 0);
    $pdf->MultiCell($cols[2], $row_height, number_format($price, 0), 1, 'R', false, 0);
    $pdf->MultiCell($cols[3], $row_height, $qty, 1, 'C', false, 0);
    $pdf->MultiCell($cols[4], $row_height, number_format($net, 0), 1, 'R', false, 1);
}


/* =======================================================
   FINAL OUTPUT
======================================================= */

$pdf->renderSubtotalPage();
$pdf->subtotalPage = 0;

$pdf->finalFooter($totalNet);

$pdf->Output("invoice_{$penjualan_id}.pdf", 'I');
