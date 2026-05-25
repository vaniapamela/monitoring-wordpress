<?php
// =========================================================================
// 1. PROTEKSI KEAMANAN KETAT (CLI ONLY)
// =========================================================================
// Memastikan skrip HANYA bisa dijalankan lewat Terminal / Git Hooks / Cron Job.
// Jika diakses lewat browser (HTTP/HTTPS), otomatis langsung diblokir.
if (php_sapi_name() !== 'cli' || isset($_SERVER['REQUEST_METHOD'])) {
    header('HTTP/1.1 403 Forbidden');
    echo "<h1>403 Forbidden</h1>";
    echo "Akses ditolak. Skrip ini hanya dapat dijalankan melalui Terminal (CLI).";
    exit();
}

// =========================================================================
// 2. KONFIGURASI UTAMA
// =========================================================================
$host        = 'localhost';
$username    = 'root';
$password    = '';
$dbName      = 'ourstudy';
$sqlFileName = 'ourstudy.sql';

$gantiUrl        = false; 
$urlLama         = ''; // Bisa dikosongkan
$urlBaruProxmox  = ''; // Bisa dikosongkan

$sqlFilePath = __DIR__ . '/' . $sqlFileName; 
$scriptPath  = __FILE__;

try {
    echo "==================================================\n";
    echo "  START: SECURE REFRESH DATABASE & SELF-DESTRUCT \n";
    echo "==================================================\n";

    // Keamanan Tambahan: Validasi keberadaan file SQL sebelum menghapus DB lama
    if (!file_exists($sqlFilePath)) {
        throw new Exception("CRITICAL: File '$sqlFileName' tidak ditemukan!\nProses dibatalkan demi keamanan agar database lama tidak hilang sia-sia.");
    }

    // 3. PROSES DATABASE
    $pdo = new PDO("mysql:host=$host", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");

    echo "[-] Menghapus database lama `$dbName`...\n";
    $pdo->exec("DROP DATABASE IF EXISTS `$dbName`");
    
    echo "[+] Membuat database baru `$dbName`...\n";
    $pdo->exec("CREATE DATABASE `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
    
    $pdo->exec("USE `$dbName`");
    echo "[✓] Reset database berhasil.\n\n";

    echo "[-] Mengeksekusi $sqlFileName... ";
    $sqlContent = file_get_contents($sqlFilePath);
    
    if (trim($sqlContent) !== '') {
        $pdo->exec($sqlContent);
        echo "[SUKSES]\n";
        
        // Penyesuaian URL WordPress
        if ($gantiUrl) {
            echo "[-] Menyesuaikan URL WordPress untuk Server Proxmox...\n";
            $stmt1 = $pdo->prepare("UPDATE wp_options SET option_value = ? WHERE option_name = 'siteurl' OR option_name = 'home'");
            $stmt1->execute([$urlBaruProxmox]);
            
            $stmt2 = $pdo->prepare("UPDATE wp_posts SET post_content = REPLACE(post_content, ?, ?)");
            $stmt2->execute([$urlLama, $urlBaruProxmox]);
            echo "    -> [✓] URL berhasil diubah ke $urlBaruProxmox\n";
        }
    } else {
        echo "[KOSONG]\n";
    }

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
    echo "[✓] WordPress Database siap digunakan.\n\n";

    // 4. SELF DESTRUCTION & CLEANUP (Pembersihan Jejak)
    echo "[-] Memulai proses pembersihan file rahasia...\n";
    
    if (file_exists($sqlFilePath)) {
        unlink($sqlFilePath);
        echo "    -> [✓] File $sqlFileName berhasil dimusnahkan.\n";
    }
    
    echo "    -> [✓] Skrip keamanan ini akan menghapus dirinya sendiri sekarang.\n";
    echo "==================================================\n";
    echo " [✓] SELESAI: Server bersih dari file instalasi.  \n";
    echo "==================================================\n";
    
    unlink($scriptPath);
    exit();

} catch (PDOException $e) {
    if (isset($pdo)) { $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;"); }
    die("\n[DATABASE ERROR]: " . $e->getMessage() . "\n");
} catch (Exception $e) {
    die("\n[SECURITY/ERROR]: " . $e->getMessage() . "\n");
}