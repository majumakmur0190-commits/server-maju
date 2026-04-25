<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

require_once 'db.php';

$input = json_decode(file_get_contents("php://input"), true);

// ==============================================
// MODE READ (GET) - kirim daftar pelanggan
// ==============================================
if ($_SERVER["REQUEST_METHOD"] === "GET" || !$input) {
    $sql = "SELECT * FROM pelanggan ORDER BY pelanggan_id ASC";
    $result = $conn->query($sql);

    $list = [];
    while ($row = $result->fetch_assoc()) {
        $list[] = $row;
    }

    echo json_encode([
        "status" => "success",
        "mode" => "read",
        "total" => count($list),
        "pelanggan" => $list
    ]);
    exit;
}

// ==============================================
// MODE SYNC (POST)
// ==============================================
$data = $input["pelanggan"] ?? [];

$inserted = 0;
$updated = 0;
$nochange = 0;

// Prepare statements
$stmtCheckID = $conn->prepare("SELECT * FROM pelanggan WHERE pelanggan_id=? LIMIT 1");
$stmtCheckDup = $conn->prepare("SELECT * FROM pelanggan WHERE nama_pelanggan=? AND no_telepon=? LIMIT 1");


// INSERT pakai ID (offline)
$stmtInsertWithID = $conn->prepare("
    INSERT INTO pelanggan (pelanggan_id, nama_pelanggan, alamat, no_telepon, aktif)
    VALUES (?, ?, ?, ?, ?)
");

// INSERT auto-increment
$stmtInsertAuto = $conn->prepare("
    INSERT INTO pelanggan (nama_pelanggan, alamat, no_telepon, aktif)
    VALUES (?, ?, ?, ?)
");

// UPDATE data
$stmtUpdate = $conn->prepare("
    UPDATE pelanggan SET nama_pelanggan=?, alamat=?, no_telepon=?, aktif=?
    WHERE pelanggan_id=?
");


foreach ($data as $p) {

    $id = $p["pelanggan_id"] ?? null;
    $nama = $p["nama_pelanggan"] ?? "";
    $alamat = $p["alamat"] ?? "";
    $telp = $p["no_telepon"] ?? "";
    $aktif = (int) ($p["aktif"] ?? 1);

    // ==================================================
    // 1️⃣ CEK MENGGUNAKAN ID (OFFLINE ID)
    // ==================================================
    if ($id) {
        $stmtCheckID->bind_param("i", $id);
        $stmtCheckID->execute();
        $res = $stmtCheckID->get_result();

        if ($res->num_rows > 0) {
            // ID ada → cek apakah data sama
            $exist = $res->fetch_assoc();

            if (
                $exist["nama_pelanggan"] === $nama &&
                $exist["alamat"] === $alamat &&
                $exist["no_telepon"] === $telp &&
                (int) $exist["aktif"] === $aktif
            ) {
                $nochange++;
                continue;
            }

            // Update data
            $stmtUpdate->bind_param("sssii", $nama, $alamat, $telp, $aktif, $id);
            $stmtUpdate->execute();
            $updated++;
            continue;
        }
    }

    // ==================================================
    // 2️⃣ CEK DUPLIKAT (nama + telepon)
    // ==================================================
    $stmtCheckDup->bind_param("ss", $nama, $telp);
    $stmtCheckDup->execute();
    $res2 = $stmtCheckDup->get_result();

    if ($res2->num_rows > 0) {
        $exist = $res2->fetch_assoc();
        $idDup = $exist["pelanggan_id"];

        // Jika data identik → tidak ada perubahan
        if (
            $exist["alamat"] === $alamat &&
            (int) $exist["aktif"] === $aktif
        ) {
            $nochange++;
            continue;
        }

        // Update duplikat
        $stmtUpdate->bind_param("sssii", $nama, $alamat, $telp, $aktif, $idDup);
        $stmtUpdate->execute();
        $updated++;
        continue;
    }

    // ==================================================
    // 3️⃣ INSERT BARU (pakai ID offline jika ada)
    // ==================================================
    if ($id) {
        // Insert dengan ID yang dikirim dari offline
        $stmtInsertWithID->bind_param("isssi", $id, $nama, $alamat, $telp, $aktif);
        $stmtInsertWithID->execute();
    } else {
        // Insert auto-increment (tanpa ID)
        $stmtInsertAuto->bind_param("sssi", $nama, $alamat, $telp, $aktif);
        $stmtInsertAuto->execute();
    }

    $inserted++;
}

// ==============================================
// OUTPUT HASIL SYNC
// ==============================================
echo json_encode([
    "status" => "success",
    "mode" => "sync",
    "inserted" => $inserted,
    "updated" => $updated,
    "nochange" => $nochange
]);

$conn->close();
