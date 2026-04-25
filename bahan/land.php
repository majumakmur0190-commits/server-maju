<?php
require_once __DIR__ . '/tcpdf/tcpdf.php';

$pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('Invoice Generator');
$pdf->SetAuthor('PT. Sample Sarana Abadi');
$pdf->SetTitle('landscape.pdf');
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins(8, 6, 8);
$pdf->AddPage();
$pdf->SetFont('helvetica', '', 10);

// ===== HEADER UTAMA =====
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 5, 'INVOICE', 0, 1, 'C');
$pdf->Ln(2);

// ===== HEADER INFO (Perusahaan, Invoice, Pelanggan) =====
$pdf->SetFont('helvetica', '', 10);

// Definisikan lebar kolom
$col1_width = 130;
$col2_width = 50;
$col3_width = 0; // 0 = sampai akhir halaman
$line_height = 4;

// Simpan posisi Y saat ini
$startY = $pdf->GetY();

// Kolom 1: Info Perusahaan
$company_info = "PT. SAMPLE SARANA ABADI\n" .
                "Ruko Graha Arteri Mas, Jl. Panjang Blok 101 No.1, Jakarta 12223\n" .
                "Phone : (62-21) 58305758 (Hunting) | Fax : (62-21) 5845581";
$pdf->MultiCell($col1_width, $line_height, $company_info, 0, 'L', 0, 0);

// Kolom 2: Detail Invoice
$invoice_details = "Number : 00000145\nPayment Term : Cash\nSalesman : DIMAS TYO";
$pdf->MultiCell($col2_width, $line_height, $invoice_details, 0, 'C', 0, 0);

// Kolom 3: Tanggal dan Mata Uang
$date_details = "Inv. Date : 23 Oct 2025\nDue Date : 23 Oct 2025\nCurrency : SGD";
$pdf->MultiCell($col3_width, $line_height, $date_details, 0, 'R', 0, 1);

// Info Pelanggan
$customer_info = "Customer : SAMPLE INDONESIA, PT.\nPhone : 021-3985656   Fax : 35425521";
$pdf->MultiCell(0, $line_height, $customer_info, 0, 'L', 0, 1);
$pdf->Ln(2);

// ===== HEADER TABEL =====
$pdf->SetFont('helvetica', 'B', 8);
$cols = [10, 163, 15, 15, 22, 22, 12, 22]; // Lebar kolom 'Product Description' disesuaikan agar memenuhi halaman
$headers = ['No', 'Product Description', 'Qty', 'UOM', 'Unit Price', 'Gross', 'Disc%', 'Net'];
foreach ($headers as $i => $h) {
    $pdf->Cell($cols[$i], 5, $h, 1, 0, 'C');
}
$pdf->Ln();

// ===== DATA BARANG RANDOM =====
$pdf->SetFont('helvetica', '', 8);
$totalGross = 0;
$totalNet = 0;

for ($i = 1; $i <= 27; $i++) {
    $nama = 'Barang-' . chr(64 + ($i % 26)) . rand(10, 99);
    $qty = rand(1, 12);
    $uom = rand(0, 1) ? 'Pcs' : 'Box';
    $price = rand(50, 800) / 10;
    $gross = $qty * $price;
    $disc = rand(0, 1) ? 5 : 0;
    $net = $gross - ($gross * $disc / 100);

    $pdf->Cell($cols[0], 4, $i, 1, 0, 'C');
    $pdf->Cell($cols[1], 4, $nama, 1, 0, 'L');
    $pdf->Cell($cols[2], 4, $qty, 1, 0, 'C');
    $pdf->Cell($cols[3], 4, $uom, 1, 0, 'C');
    $pdf->Cell($cols[4], 4, number_format($price, 2), 1, 0, 'R');
    $pdf->Cell($cols[5], 4, number_format($gross, 2), 1, 0, 'R');
    $pdf->Cell($cols[6], 4, $disc, 1, 0, 'C');
    $pdf->Cell($cols[7], 4, number_format($net, 2), 1, 1, 'R');

    $totalGross += $gross;
    $totalNet += $net;
}

// ===== TOTAL =====
$pdf->SetFont('helvetica', 'B', 8);
$pdf->Cell(array_sum($cols) - $cols[7], 5, 'Net Total', 1, 0, 'R');
$pdf->Cell($cols[7], 5, number_format($totalNet, 2), 1, 1, 'R');
$pdf->Ln(1);

// ===== INWORD DAN CATATAN =====
$pdf->SetFont('helvetica', 'B', 10);
$pdf->MultiCell(0, 4, "Gross Total : " . number_format($totalGross, 2) . "\nDiscount : 0.00\nTax : 0.00", 0, 'R', 0, 1);
$pdf->Ln(2);

$pdf->SetFont('helvetica', 'I', 5);
$pdf->MultiCell(180, 4, "Inword: Tiga Ribu Lima Ratus Tiga Belas Dollar Koma Lima Ratus Tiga Puluh Tiga Sen", 0, 'L', 0, 1);
$pdf->MultiCell(0, 4, 'Remark: Pembayaran dengan cheque / BG dianggap lunas apabila sudah dapat diuangkan.', 0, 'L');

// ===== TRANSFER INFO =====
$pdf->Ln(1);
$pdf->SetFont('helvetica', '', 5);
$pdf->MultiCell(0, 4, "TRANSFER VIA\nBCA - IDR\nA/C : 164-800-3321\nA/N : PT. SAMPLE SARANA ABADI", 0, 'L');

// ===== SIGNATURE =====
$pdf->SetXY(250, 185);
$pdf->SetFont('helvetica', '', 8);
$pdf->Cell(40, 4, 'Yuda Haryanto', 0, 1, 'C');
$pdf->Line(250, 184, 285, 184);


// Cetak langsung
$pdf->IncludeJS("print(true);");
$pdf->Output('invoice_native.pdf', 'I');
