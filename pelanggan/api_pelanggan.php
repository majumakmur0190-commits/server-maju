<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include "koneksi.php"; // file koneksi, pastikan $koneksi adalah mysqli connection

$method = $_SERVER['REQUEST_METHOD'];

// untuk preflight OPTIONS
if ($method === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Ambil input JSON (jika ada)
$input = json_decode(file_get_contents("php://input"), true);

// Helper: respon error
function resp($arr, $http_code = 200)
{
    http_response_code($http_code);
    echo json_encode($arr);
    exit;
}

switch ($method) {

    // --------------------------------------------------------
    // READ (ambil semua data)
    // --------------------------------------------------------
    case "GET":
        if (isset($_GET['pelanggan_id'])) {
            $id = $_GET['pelanggan_id'];
            $stmt = $koneksi->prepare("SELECT * FROM pelanggan WHERE pelanggan_id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $data = $result->fetch_assoc();
            if (!$data) {
                resp(["status" => "error", "message" => "Data tidak ditemukan"], 404);
            }
        } else {
            $result = $koneksi->query("SELECT * FROM pelanggan ORDER BY pelanggan_id DESC");
            $data = [];
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $data[] = $row;
                }
            }
        }

        echo json_encode($data);
        break;



    // --------------------------------------------------------
    // CREATE
    // --------------------------------------------------------
    case "POST":
        $nama = $input['nama_pelanggan'] ?? "";
        $alamat = $input['alamat'] ?? "";
        $telepon = $input['no_telepon'] ?? "";
        $aktif = isset($input['aktif']) ? (int) $input['aktif'] : 1;

        // Validasi dasar: nama tidak boleh kosong
        if (empty(trim($nama))) {
            resp(["status" => "error", "message" => "Nama pelanggan tidak boleh kosong"], 400);
        }

        $stmt = $koneksi->prepare("INSERT INTO pelanggan (nama_pelanggan, alamat, no_telepon, aktif) VALUES (?, ?, ?, ?)");
        if (!$stmt)
            resp(["status" => "error", "message" => $koneksi->error]);

        $stmt->bind_param("sssi", $nama, $alamat, $telepon, $aktif);
        if ($stmt->execute()) {
            resp(["status" => "success", "message" => "Data berhasil ditambahkan"], 201);
        } else {
            resp(["status" => "error", "message" => $stmt->error]);
        }
        break;


    // --------------------------------------------------------
// UPDATE
// --------------------------------------------------------
    case "PUT":

        // Ambil ID dari body JSON
        $id = $input['pelanggan_id'] ?? "";

        if ($id === "") {
            resp(["status" => "error", "message" => "ID pelanggan diperlukan di body"], 400);
        }

        $nama = $input['nama_pelanggan'] ?? "";
        $alamat = $input['alamat'] ?? "";
        $telepon = $input['no_telepon'] ?? "";
        $aktif = isset($input['aktif']) ? (int) $input['aktif'] : 1;

        if (empty(trim($nama))) {
            resp(["status" => "error", "message" => "Nama pelanggan tidak boleh kosong"], 400);
        }

        $stmt = $koneksi->prepare("UPDATE pelanggan SET nama_pelanggan=?, alamat=?, no_telepon=?, aktif=? WHERE pelanggan_id=?");
        if (!$stmt)
            resp(["status" => "error", "message" => $koneksi->error]);

        $stmt->bind_param("sssii", $nama, $alamat, $telepon, $aktif, $id);

        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                resp(["status" => "success", "message" => "Data berhasil diperbarui"]);
            } else {
                resp(["status" => "not_found", "message" => "Data tidak ditemukan atau tidak ada perubahan"], 404);
            }
        } else {
            resp(["status" => "error", "message" => $stmt->error]);
        }
        break;



    // --------------------------------------------------------
    // DELETE
    // --------------------------------------------------------
    case "DELETE":
        $id = $_GET['pelanggan_id'] ?? "";
        if ($id === "") {
            resp(["status" => "error", "message" => "ID pelanggan diperlukan"], 400);
        }

        $stmt = $koneksi->prepare("DELETE FROM pelanggan WHERE pelanggan_id=?");
        if (!$stmt)
            resp(["status" => "error", "message" => $koneksi->error]);

        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                resp(["status" => "success", "message" => "Data berhasil dihapus"]);
            } else {
                resp(["status" => "not_found", "message" => "Data tidak ditemukan"], 404);
            }
        } else {
            resp(["status" => "error", "message" => $stmt->error]);
        }
        break;


    default:
        resp(["status" => "error", "message" => "Method tidak valid"], 405);
}
