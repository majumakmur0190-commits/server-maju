<?php
require_once __DIR__ . '/tcpdf/tcpdf.php';

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
        $temp = angkaKeKata($angka / 10) . " Puluh" . angkaKeKata($angka % 10);
    } elseif ($angka < 200) {
        $temp = " Seratus" . angkaKeKata($angka - 100);
    } elseif ($angka < 1000) {
        $temp = angkaKeKata($angka / 100) . " Ratus" . angkaKeKata($angka % 100);
    } elseif ($angka < 2000) {
        $temp = " Seribu" . angkaKeKata($angka - 1000);
    } elseif ($angka < 1000000) {
        $temp = angkaKeKata($angka / 1000) . " Ribu" . angkaKeKata($angka % 1000);
    } elseif ($angka < 1000000000) {
        $temp = angkaKeKata($angka / 1000000) . " Juta" . angkaKeKata($angka % 1000000);
    } elseif ($angka < 1000000000000) {
        $temp = angkaKeKata($angka / 1000000000) . " Milyar" . angkaKeKata($angka % 1000000000);
    } elseif ($angka < 1000000000000000) {
        $temp = angkaKeKata($angka / 1000000000000) . " Triliun" . angkaKeKata($angka % 1000000000000);
    }

    return trim($temp);
}


class MYPDF extends TCPDF
{
    public $totalGross = 0;
    public $totalNet = 0;

    // ===== HEADER =====
    public function Header()
    {
        $noinvoice = "INV" . rand(111111, 999999);
        $this->SetY(8); // lebih dekat ke atas
        $this->SetFont('helvetica', 'B', 12);
        $this->Cell(0, 5, 'INVOICE : ' . $noinvoice . '', 0, 1, 'C');
        $this->Ln(1);

        $this->SetFont('helvetica', '', 9);
        $col1_width = 120;
        $col2_width = 0;
        $line_height = 3.8;

        // --- BAGIAN KIRI: Info Pelanggan (dengan perataan) ---
        $initialY = $this->GetY();
        $labelWidth = 15; // Lebar untuk label seperti "Kepada"
        $contentWidth = $col1_width - $labelWidth; // Lebar untuk isi

        // Baris 1: Kepada
        $this->Cell($labelWidth, $line_height, 'Kepada :', 0, 0, 'L');
        $this->MultiCell($contentWidth, $line_height, 'SAMPLE INDONESIA, PT.', 0, 'L', 0, 0);

        // Simpan posisi X setelah titik dua untuk baris berikutnya
        $contentX = $this->GetX() - $contentWidth;
        $this->SetY($this->GetY() + $line_height); // Pindah ke baris baru

        // Baris 2: Alamat (disejajarkan dengan baris 1)
        $this->SetX($contentX);
        $this->MultiCell($contentWidth, $line_height, 'Jl. Jend. Sudirman Kav. 52-53, Jakarta 12190', 0, 'L', 0, 1);
        $this->SetX($contentX);
        $this->MultiCell($contentWidth, $line_height, '(0341)123546', 0, 'L', 0, 0);
        $this->SetY($this->GetY() + $line_height * 2); // Pindah ke baris baru

        // --- BAGIAN KANAN: Info Tanggal ---
        // Buat tanggal invoice (hari ini) dan tanggal jatuh tempo (+21 hari)
        $invoiceDate = new DateTime();
        $dueDate = clone $invoiceDate;
        $dueDate->modify('+21 days');

        $date_details = "Tanggal Inv : " . $invoiceDate->format('d M Y') . "\nTanggal Jatuh Tempo : " . $dueDate->format('d M Y');
        $this->SetY($initialY); // Kembalikan Y ke posisi awal untuk kolom kanan
        $this->MultiCell($col2_width, $line_height, $date_details, 0, 'R', 0, 1);
        $this->Ln(5);

        // Header tabel
        $this->SetFont('helvetica', 'B', 10);
        $cols = [8, 88, 10, 32, 18, 34];
        $headers = ['No', 'Nama Barang', 'Qty', 'Harga', 'Dis%', 'Total'];
        foreach ($headers as $i => $h) {
            $this->Cell($cols[$i], 5.5, $h, 1, 0, 'C');
        }
        $this->Ln(0);
    }

    // ===== FOOTER =====
    public function Footer()
    {
        // Posisikan 10mm dari bawah
        $this->SetY(-10);
        $this->SetFont('helvetica', 'I', 8);
        // Teks nomor halaman
        $this->Cell(0, 10, 'Halaman ' . $this->getAliasNumPage() . ' dari ' . $this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }

    // ===== FOOTER FINAL (hanya di akhir dokumen) =====
    public function PrintFinalFooter($grandTotalGross, $grandTotalNet)
    {
        // Perkirakan tinggi total blok footer (dalam mm)
        $footerHeight = 0;
        $pageHeight = $this->getPageHeight();
        $bottomMargin = $this->getBreakMargin();
        $currentY = $this->GetY();

        // Cek apakah footer muat di sisa halaman.
        if ($currentY + $footerHeight >= $pageHeight - $bottomMargin) {
            // Jika tidak muat, tambahkan halaman baru.
            $this->addPage();
        }

        // ===== TOTAL KESELURUHAN =====
        $this->SetFont('helvetica', 'B', 10);
        $cols = [8, 88, 10, 32, 18, 34];
        $this->Cell(array_sum($cols) - $cols[5], 5.5, 'Net Total', 1, 0, 'R');
        $this->Cell($cols[5], 5.5, number_format($grandTotalNet, 2), 1, 1, 'R');
        $this->Ln(1);

        $diskon = rand(1, 9);
        $ppn = 11;
        $nilaiDiskon = ($grandTotalNet * $diskon) / 100;
        $totalSetelahDiskon = $grandTotalNet - $nilaiDiskon;
        $nilaiPPN = ($totalSetelahDiskon * $ppn) / 100;
        $totalAkhir = $totalSetelahDiskon + $nilaiPPN;


        // --- BAGIAN KIRI: Terbilang & Remark ---
        $this->SetFont('helvetica', '', 8);
        $this->MultiCell(
            100,
            3.5,
            "Terbilang : (" . angkaKeKata($totalAkhir) . " Rupiah)\n",
            0,
            'L',
            0,
            0
        );

        // --- BAGIAN KANAN: Rincian Total ---
        $this->SetFont('helvetica', 'B', 9.5);
        $this->MultiCell(
            0,
            4,
            "Discount : " . number_format($nilaiDiskon, 2) .
            "\nPPN " . $ppn . "% : " . number_format($nilaiPPN, 2) .
            "\nGrand Total : " . number_format($totalAkhir, 2),
            0,
            'R',
            0,
            1
        );

        // --- INFO TRANSFER & TANDA TANGAN (Posisi lebih stabil) ---
        $startY = $this->GetY() + 5; // Posisi Y awal untuk blok ini

        // Info Transfer di kiri
        $this->SetFont('helvetica', '', 6);
        $this->SetXY($this->GetX(), $startY);
        $this->MultiCell(100, 4, "Note : Barang yang sudah di beli tidak dapat dikembalikan.\nKecuali ada perjanjian sebelumnya", 0, 'L');

        // Tanda Tangan di kanan
        $this->SetFont('helvetica', '', 8);
        $this->SetXY(150, $startY);
        $this->Cell(50, 3.5, '(Penerima)', 0, 1, 'C');
        $this->Line(150, $this->GetY() + 30, 200, $this->GetY() + 30);
    }
}

// === INISIALISASI PDF ===
$pdf = new MYPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('Invoice Generator');
$pdf->SetAuthor('PT. Sample Sarana Abadi');
$pdf->SetTitle('Invoice Portrait - Compact Fit');
$pdf->SetMargins(10, 33, 10); // margin atas diperkecil
$pdf->SetHeaderMargin(5);
$pdf->SetAutoPageBreak(TRUE, 35); // footer lebih ke bawah
$pdf->AddPage();

$pdf->SetFont('helvetica', '', 9.5);
$cols = [8, 88, 10, 32, 18, 34];
$totalGross = 0;
$totalNet = 0;
 
$rows = 40; // Anda bisa ubah jumlah baris ini (misal: 15 atau 45)
$maxPerPage = 36;
$currentRow = 0;

for ($i = 1; $i <= $rows; $i++) {
    $currentRow++;

    if ($currentRow > $maxPerPage) {
        $pdf->AddPage();
        $currentRow = 1;
    }

    $nama = 'Barang-' . chr(64 + ($i % 26)) . rand(10, 99);
    $qty = rand(1, 12);
    $price = rand(500000, 800000);
    $gross = $qty * $price;
    $disc = rand(0, 1) ? 5 : 0;
    $net = $gross - ($gross * $disc / 100);

    $pdf->Cell($cols[0], 5.5, $i, 1, 0, 'C');
    $pdf->Cell($cols[1], 5.5, $nama, 1, 0, 'L');
    $pdf->Cell($cols[2], 5.5, $qty, 1, 0, 'C');
    $pdf->Cell($cols[3], 5.5, number_format($price, 2), 1, 0, 'R');
    $pdf->Cell($cols[4], 5.5, $disc, 1, 0, 'C');
    $pdf->Cell($cols[5], 5.5, number_format($net, 2), 1, 1, 'R');

    $totalGross += $gross;
    $totalNet += $net;

}

// ===== CETAK FOOTER FINAL SETELAH SEMUA ITEM SELESAI =====
$pdf->PrintFinalFooter($totalGross, $totalNet);

$pdf->IncludeJS("print(true);");
$pdf->Output('invoice_30rows_tight.pdf', 'I');
