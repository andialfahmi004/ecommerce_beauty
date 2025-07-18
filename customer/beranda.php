<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check customer login
check_customer_login();

// Get featured products
$stmt = $pdo->prepare("
    SELECT p.*, k.nama_kategori 
    FROM produk p 
    JOIN kategori k ON p.id_kategori = k.id_kategori 
    WHERE p.status = 'aktif' 
    ORDER BY p.created_at DESC 
    LIMIT 8
");
$stmt->execute();
$featured_products = $stmt->fetchAll();

// Get categories
$stmt = $pdo->query("SELECT * FROM kategori ORDER BY nama_kategori");
$categories = $stmt->fetchAll();

$page_title = "Beranda - RHODE";
include '../includes/header.php';
include '../includes/navbar-customer.php';
?>

<div class="container my-4">
    <!-- Welcome Section -->
    <div class="row mb-5">
        <div class="col-12">
            <div class="hero-section rounded-3">
                <div class="container">
                    <div class="text-center">
                        <h1 class="hero-title">Welcome, <?php echo htmlspecialchars($_SESSION['customer_nama']); ?>!</h1>
                        <p class="hero-subtitle">Meriahkan Hari-Hari Mu dengan product 100% Original</p>
                        <a href="produk.php" class="btn btn-custom btn-lg">
                            <i class="fas fa-shopping-bag me-2"></i>Mulai Berbelanja
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Categories -->
    <div class="row mb-5">
        <div class="col-12">
            <h3 class="fw-bold mb-4">
                <i class="fas fa-tags me-2 text-primary"></i>Nama Toko
            </h3>
        </div>
        <?php foreach ($categories as $category): ?>
        <div class="col-lg-2 col-md-4 col-6 mb-3">
            <a href="produk.php?kategori=<?php echo $category['id_kategori']; ?>" class="text-decoration-none">
                <div class="card card-custom text-center h-100">
                    <div class="card-body">
                        <i class="fas fa-spa fa-2x text-primary mb-3"></i>
                        <h6 class="card-title"><?php echo htmlspecialchars($category['nama_kategori']); ?></h6>
                    </div>
                </div>
            </a>
        </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Featured Products -->
    <div class="row mb-5">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3 class="fw-bold">
                    <i class="fas fa-star me-2 text-primary"></i>All Product
                </h3>
                <a href="produk.php" class="btn btn-outline-custom">
                    <i class="fas fa-eye me-2"></i>Lihat Semua
                </a>
            </div>
        </div>
        
        <?php if (empty($featured_products)): ?>
        <div class="col-12">
            <div class="text-center py-5">
                <i class="fas fa-box fa-3x text-muted mb-3"></i>
                <p class="text-muted">Produk sedang tidak tersedia</p>
            </div>
        </div>
        <?php else: ?>
        <?php foreach ($featured_products as $product): ?>
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="card product-card h-100">
                <img src="../assets/images/products/<?php echo $product['gambar'] ?: 'default.jpg'; ?>" 
                     class="card-img-top product-img" alt="<?php echo htmlspecialchars($product['nama_produk']); ?>"
                     onerror="this.src='../assets/images/default.jpg'">
                <div class="card-body d-flex flex-column">
                    <span class="badge bg-secondary mb-2 align-self-start"><?php echo htmlspecialchars($product['nama_kategori']); ?></span>
                    <h6 class="card-title"><?php echo htmlspecialchars($product['nama_produk']); ?></h6>
                    <p class="card-text text-muted flex-grow-1"><?php echo htmlspecialchars(substr($product['deskripsi'], 0, 80) . '...'); ?></p>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="product-price"><?php echo format_rupiah($product['harga']); ?></span>
                        <small class="text-muted">Stok: <?php echo $product['stok']; ?></small>
                    </div>
                    <div class="mt-3">
                        <?php if ($product['stok'] > 0): ?>
                        <button onclick="addToCart(<?php echo $product['id_produk']; ?>)" class="btn btn-custom btn-sm w-100">
                            <i class="fas fa-cart-plus me-2"></i>Tambah ke Keranjang
                        </button>
                        <?php else: ?>
                        <button class="btn btn-secondary btn-sm w-100" disabled>
                            <i class="fas fa-times me-2"></i>Stok Habis
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

<?php include '../includes/footer.php'; ?>