<?php
require_once __DIR__ . '/tcpdf/tcpdf.php';

class MYPDF extends TCPDF
{
    public function Header()
    {
        $this->SetFont('helvetica', 'B', 12);
        $this->Cell(0, 10, 'Laporan Barang', 0, 1, 'C');
        $this->Ln(3);
    }

    public function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Halaman ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, 0, 'C');
    }
}

$pdf = new MYPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetMargins(10, 25, 10);
$pdf->SetAutoPageBreak(TRUE, 20);
$pdf->AddPage();

$pdf->SetFont('helvetica', '', 10);

$html = '<table border="1" cellpadding="4">
            <thead>
                <tr style="background-color:#ddd;">
                    <th width="10%">No</th>
                    <th width="40%">Nama Barang</th>
                    <th width="25%">Kategori</th>
                    <th width="25%">Harga</th>
                </tr>
            </thead>
            <tbody>';

for ($i = 1; $i <= 120; $i++) {
    $html .= "<tr>
                <td>$i</td>
                <td>Barang Ke-$i</td>
                <td>Kategori " . ceil($i / 10) . "</td>
                <td>Rp " . number_format($i * 1000, 0, ',', '.') . "</td>
              </tr>";
}

$html .= '</tbody></table>';

$pdf->writeHTML($html, true, false, true, false, '');

$pdf->Output('laporan.pdf', 'I');
