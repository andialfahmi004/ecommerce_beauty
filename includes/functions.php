<?php
require_once '../config/database.php';

// Fungsi untuk upload gambar
function upload_image($file, $target_dir = "../assets/images/products/") {
    $target_file = $target_dir . basename($file["name"]);
    $uploadOk = 1;
    $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
    
    // Check if image file is actual image
    if(isset($_POST["submit"])) {
        $check = getimagesize($file["tmp_name"]);
        if($check !== false) {
            $uploadOk = 1;
        } else {
            $uploadOk = 0;
        }
    }
    
    // Check file size (max 5MB)
    if ($file["size"] > 5000000) {
        $uploadOk = 0;
    }
    
    // Allow certain file formats
    if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "gif" ) {
        $uploadOk = 0;
    }
    
    // Generate unique filename
    $filename = time() . '_' . basename($file["name"]);
    $target_file = $target_dir . $filename;
    
    if ($uploadOk == 0) {
        return false;
    } else {
        if (move_uploaded_file($file["tmp_name"], $target_file)) {
            return $filename;
        } else {
            return false;
        }
    }
}

// Fungsi untuk mendapatkan jumlah item di keranjang
function get_cart_count($customer_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT SUM(jumlah) as total FROM keranjang WHERE id_customer = ?");
    $stmt->execute([$customer_id]);
    $result = $stmt->fetch();
    return $result['total'] ?? 0;
}

// Fungsi untuk mendapatkan total harga keranjang
function get_cart_total($customer_id) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT SUM(k.jumlah * p.harga) as total 
        FROM keranjang k 
        JOIN produk p ON k.id_produk = p.id_produk 
        WHERE k.id_customer = ?
    ");
    $stmt->execute([$customer_id]);
    $result = $stmt->fetch();
    return $result['total'] ?? 0;
}

// Fungsi untuk generate kode pesanan
function generate_order_code() {
    return 'TKI' . date('Ymd') . rand(1000, 9999);
}
?>