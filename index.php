<?php
require_once 'config/database.php';

// Get featured products
$stmt = $pdo->prepare("SELECT p.*, k.nama_kategori FROM produk p JOIN kategori k ON p.id_kategori = k.id_kategori WHERE p.status = 'aktif' ORDER BY p.created_at DESC LIMIT 6");
$stmt->execute();
$featured_products = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RHODE - 100% Original</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-custom">
        <div class="container">
            <a class="navbar-brand" >
                <i class=""></i>RHODE
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="index.php">
                            <i class="fas fa-home me-1"></i>Beranda
                        </a>
                    </li>
                </ul>
                
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="customer/login.php">
                            <i class="fas fa-sign-in-alt me-1"></i>Masuk
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="customer/register.php">
                            <i class="fas fa-user-plus me-1"></i>Daftar
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="admin/login.php">
                            <i class="fas fa-user-shield me-1"></i>Admin
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h1 class="hero-title">RHODE</h1>
                    <p class="hero-subtitle">Kami Menjual Produk yang Terpercaya 100% Original</p>
                    <div class="hero-buttons">
                        <a href="customer/produk.php" class="btn btn-custom me-3">
                            <i class="fas fa-shopping-bag me-2"></i>Lihat Produk
                        </a>
                        <a href="customer/register.php" class="btn btn-outline-custom">
                            <i class="fas fa-user-plus me-2"></i>Daftar Sekarang
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Featured Products -->
    <section class="py-5">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="fw-bold">Produk Unggulan</h2>
                <p class="text-muted">Pilihan terbaik dari koleksi kue kami</p>
            </div>
            
            <div class="row">
                <?php foreach($featured_products as $product): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="card product-card h-100">
                        <img src="assets/images/products/<?php echo $product['gambar'] ?: 'default.jpg'; ?>" 
                             class="card-img-top product-img" alt="<?php echo htmlspecialchars($product['nama_produk']); ?>">
                        <div class="card-body d-flex flex-column">
                            <span class="badge bg-secondary mb-2 align-self-start"><?php echo htmlspecialchars($product['nama_kategori']); ?></span>
                            <h5 class="card-title"><?php echo htmlspecialchars($product['nama_produk']); ?></h5>
                            <p class="card-text text-muted flex-grow-1"><?php echo htmlspecialchars(substr($product['deskripsi'], 0, 100) . '...'); ?></p>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="product-price"><?php echo format_rupiah($product['harga']); ?></span>
                                <a href="customer/login.php" class="btn btn-custom btn-sm">
                                    <i class="fas fa-shopping-cart me-1"></i>Beli
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-light py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5 class="text-primary">RHODE</h5>
                    <p class="text-muted">100% Product Original</p>
                </div>
                <div class="col-md-6">
                    <h6>Kontak Kami</h6>
                    <p class="text-muted">
                        <i class="fas fa-phone"></i> +62 82 2928 3108<br>
                        <i class="fas fa-envelope"></i> rhode@gmail.com<br>
                        <i class="fas fa-map-marker-alt"></i> Jl. Contoh No. 123, Jakarta
                    </p>
                </div>
            </div>
            <hr>
            <div class="text-center">
                <p class="text-muted mb-0">&copy; 2025 Rhode. Semua hak dilindungi.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="assets/js/script.js"></script>
</body>
</html>