<?php

$configFile = __DIR__ . '/database.local.php';

if (!is_file($configFile)) {
    throw new RuntimeException('File konfigurasi database.local.php belum dibuat.');
}

$config = require $configFile;

if (!is_array($config)) {
    throw new RuntimeException('Konfigurasi database tidak valid.');
}

return $config;
