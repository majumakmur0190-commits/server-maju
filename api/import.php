<?php
// import.php
header('Content-Type: application/json');

// Basic security: restrict method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// read JSON
$input = file_get_contents('php://input');
if (!$input) {
    echo json_encode(['success' => false, 'error' => 'No input received']);
    exit;
}

$data = json_decode($input, true);
if ($data === null) {
    echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
    exit;
}

$rows = $data['rows'] ?? null;
if (!is_array($rows)) {
    echo json_encode(['success' => false, 'error' => 'Missing rows array']);
    exit;
}

try {
    $databases = require __DIR__ . '/../config/database.php';
    $dbConfig = $databases['default'] ?? null;

    if (!$dbConfig) {
        throw new RuntimeException('Konfigurasi database default tidak ditemukan');
    }

    $dsn = sprintf(
        '%s:host=%s;port=%d;dbname=%s;charset=%s',
        $dbConfig['driver'] ?? 'mysql',
        $dbConfig['host'],
        (int) ($dbConfig['port'] ?? 3306),
        $dbConfig['database'],
        $dbConfig['charset'] ?? 'utf8mb4'
    );

    $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'DB connection error: ' . $e->getMessage()]);
    exit;
}

// Prepare insert statement (8 fields) sesuai struktur tabel
$sql = "INSERT INTO `barang` (`barang_id`,`barcode`, `nama_barang`, `kategori_id`, `harga_hna`, `stok`, `satuan`, `aktif`, `created_at`)
        VALUES (:barang_id, :barcode, :nama_barang, :kategori_id, :harga_hna, :stok, :satuan, :aktif, :created_at)";

$stmt = $pdo->prepare($sql);

$inserted = 0;
$errors = [];

foreach ($rows as $i => $r) {
    // Normalize keys and provide defaults
    $row = [
        'barang_id' => isset($r['barang_id']) ? trim($r['barang_id']) : null,
        'barcode' => isset($r['barcode']) ? trim($r['barcode']) : null,
        'nama_barang' => isset($r['nama_barang']) ? strtoupper(trim($r['nama_barang'])) : null, // Langsung ubah ke uppercase
        'kategori_id' => isset($r['kategori_id']) ? trim($r['kategori_id']) : null,
        'harga_hna' => isset($r['harga_hna']) ? trim($r['harga_hna']) : null,
        'stok' => isset($r['stok']) ? trim($r['stok']) : null,
        'satuan' => isset($r['satuan']) ? strtoupper(trim($r['satuan'])) : null, // Satuan juga dibuat uppercase
        'aktif' => isset($r['aktif']) ? trim($r['aktif']) : null,
        'created_at' => isset($r['created_at']) ? trim($r['created_at']) : null,
    ];

    // Optional: validation (minimal)
    if (empty($row['nama_barang'])) {
        $errors[] = "Row $i: nama_barang kosong, dilewati.";
        continue;
    }

    // Convert empty created_at -> use current timestamp via SQL (we'll bind null and set to NOW() in SQL if null)
    try {
        if ($row['created_at'] === '' || $row['created_at'] === null) {
            // bind NOW() by using SQL function: adjust statement - but simpler: set to current datetime here
            $row['created_at'] = date('Y-m-d H:i:s');
        }

        // Convert harga_hna: remove decimals entirely (ignore anything after comma or dot)
        if (isset($row['harga_hna']) && $row['harga_hna'] !== null && $row['harga_hna'] !== '') {

            // Hapus semua karakter selain angka dan koma/titik
            $clean = preg_replace('/[^0-9\.,]/', '', $row['harga_hna']);

            // Jika ada koma atau titik, ambil bagian sebelum koma/titik
            $clean = preg_split('/[.,]/', $clean)[0];

            // Pastikan hasilnya angka
            if (is_numeric($clean)) {
                $row['harga_hna'] = (int) $clean; // simpan sebagai integer
            } else {
                $row['harga_hna'] = null;
            }

        } else {
            $row['harga_hna'] = null;
        }



        // Execute prepared
        $stmt->execute([
            ':barang_id' => $row['barang_id'],
            ':barcode' => $row['barcode'],
            ':nama_barang' => $row['nama_barang'],
            ':kategori_id' => $row['kategori_id'],
            ':harga_hna' => $row['harga_hna'],
            ':stok' => $row['stok'],
            ':satuan' => $row['satuan'],
            ':aktif' => $row['aktif'],
            ':created_at' => $row['created_at'],
        ]);
        $inserted++;
    } catch (Exception $e) {
        $errors[] = "Row $i: DB error - " . $e->getMessage();
    }
}

echo json_encode(['success' => true, 'inserted' => $inserted, 'errors' => $errors]);
exit;
