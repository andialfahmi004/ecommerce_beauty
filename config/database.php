<?php
// Konfigurasi koneksi database
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'rhode';

try {
    // Membuat koneksi PDO
    $pdo = new PDO("mysql:host=$host;dbname=$database;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}

// Fungsi untuk membersihkan input
function clean_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Fungsi untuk format rupiah
function format_rupiah($angka) {
    return "Rp " . number_format($angka, 0, ',', '.');
}

// Fungsi untuk format tanggal Indonesia
function format_tanggal($tanggal) {
    $bulan = array(
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    );
    
    $date = new DateTime($tanggal);
    $hari = $date->format('j');
    $bulan_num = $date->format('n');
    $tahun = $date->format('Y');
    
    return $hari . ' ' . $bulan[$bulan_num] . ' ' . $tahun;
}

// Session management
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Fungsi untuk cek login admin
function check_admin_login() {
    if (!isset($_SESSION['admin_id'])) {
        header("Location: login.php");
        exit();
    }
}

// Fungsi untuk cek login customer
function check_customer_login() {
    if (!isset($_SESSION['customer_id'])) {
        header("Location: login.php");
        exit();
    }
}
?>