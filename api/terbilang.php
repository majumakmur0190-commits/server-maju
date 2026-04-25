<?php
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
