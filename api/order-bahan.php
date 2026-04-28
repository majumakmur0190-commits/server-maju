<?php

try {
    $databases = require __DIR__ . '/../config/database.php';
    $sourceConfig = $databases['gudang'] ?? null;
    $targetConfig = $databases['default'] ?? null;

    if (!$sourceConfig || !$targetConfig) {
        throw new RuntimeException('Konfigurasi database gudang/default tidak ditemukan.');
    }

    $dsn = sprintf(
        '%s:host=%s;port=%d;dbname=%s;charset=%s',
        $sourceConfig['driver'] ?? 'mysql',
        $sourceConfig['host'],
        (int) ($sourceConfig['port'] ?? 3306),
        $sourceConfig['database'],
        $sourceConfig['charset'] ?? 'utf8mb4'
    );

    $pdo = new PDO($dsn, $sourceConfig['username'], $sourceConfig['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Query untuk join antar database
    // Kita memanggil tabel dengan format: nama_database.nama_tabel
    $sql = "SELECT 
                p.kode_barang, 
                b.nama_barang, 
                p.jumlah_order, 
                p.total_harga,
                b.barcode
            FROM {$sourceConfig['database']}.pembelian AS p
            INNER JOIN {$targetConfig['database']}.barang AS b ON p.kode_barang = b.barcode";

    $stmt = $pdo->query($sql);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Menampilkan data dalam tabel HTML
    if ($results) {
        echo "<table border='1' cellpadding='10'>";
        echo "<tr>
                <th>Barcode/Kode</th>
                <th>Nama Barang</th>
                <th>Jumlah Order</th>
                <th>Total Harga</th>
              </tr>";
        foreach ($results as $row) {
            echo "<tr>
                    <td>{$row['barcode']}</td>
                    <td>{$row['nama_barang']}</td>
                    <td>{$row['jumlah_order']}</td>
                    <td>" . number_format($row['total_harga'], 0, ',', '.') . "</td>
                  </tr>";
        }
        echo "</table>";
    } else {
        echo "Tidak ada data yang cocok ditemukan.";
    }

} catch (PDOException $e) {
    echo "Kesalahan Koneksi: " . $e->getMessage();
}
?>
