<?php
include "db.php"; // koneksi ke database

// username yang mau diubah
$username = "admin";

// password baru (plaintext)
$newPassword = "12345";

// buat hash
$passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);

// update ke database
$sql = "UPDATE users SET password = ? WHERE username = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $passwordHash, $username);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    echo "Password admin berhasil diupdate!";
} else {
    echo "Gagal mengupdate password (mungkin username tidak ditemukan).";
}

$stmt->close();
$conn->close();
?>