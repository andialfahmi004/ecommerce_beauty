<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check customer login
check_customer_login();

// Get filter parameters
$kategori_filter = $_GET['kategori'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$sql = "
    SELECT p.*, k.nama_kategori 
    FROM produk p 
    JOIN kategori k ON p.id_kategori = k.id_kategori 
    WHERE p.status = 'aktif'
";
$params = [];

if (!empty($kategori_filter)) {
    $sql .= " AND p.id_kategori = ?";
    $params[] = $kategori_filter;
}

if (!empty($search)) {
    $sql .= " AND (p.nama_produk LIKE ? OR p.deskripsi LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY p.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Get categories for filter
$stmt = $pdo->query("SELECT * FROM kategori ORDER BY nama_kategori");
$categories = $stmt->fetchAll();

// Get selected category name
$selected_category = '';
if (!empty($kategori_filter)) {
    $stmt = $pdo->prepare("SELECT nama_kategori FROM kategori WHERE id_kategori = ?");
    $stmt->execute([$kategori_filter]);
    $cat = $stmt->fetch();
    $selected_category = $cat['nama_kategori'] ?? '';
}

$page_title = "Produk - Toko Kue Icha";
include '../includes/header.php';
include '../includes/navbar-customer.php';
?>

<div class="container my-4">
    <div class="row">
        <div class="col-12">
            <h2 class="fw-bold mb-4">
                <i class="fas fa-birthday-cake me-2 text-primary"></i>
                <?php echo !empty($selected_category) ? 'Kategori: ' . htmlspecialchars($selected_category) : 'Semua Produk'; ?>
            </h2>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card card-custom">
                <div class="card-body">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label for="search" class="form-label">Pencarian</label>
                            <input type="text" class="form-control form-control-custom" id="search" 
                                   name="search" placeholder="Cari nama produk..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        
                        <div class="col-md-4">
                            <label for="kategori" class="form-label">Toko</label>
                            <select class="form-select form-control-custom" id="kategori" name="kategori">
                                <option value="">Semua Toko</option>
                                <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id_kategori']; ?>" 
                                        <?php echo $kategori_filter == $category['id_kategori'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['nama_kategori']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-custom me-2">
                                <i class="fas fa-search me-2"></i>Cari
                            </button>
                            <a href="produk.php" class="btn btn-outline-secondary">
                                <i class="fas fa-refresh me-2"></i>Reset
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Products Grid -->
    <div class="row">
        <?php if (empty($products)): ?>
        <div class="col-12">
            <div class="card card-custom">
                <div class="card-body text-center py-5">
                    <i class="fas fa-search fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">Produk tidak ditemukan</h5>
                    <p class="text-muted">Coba gunakan kata kunci lain atau lihat kategori yang berbeda</p>
                    <a href="produk.php" class="btn btn-outline-custom">
                        <i class="fas fa-arrow-left me-2"></i>Lihat Semua Produk
                    </a>
                </div>
            </div>
        </div>
        <?php else: ?>
        <?php foreach ($products as $product): ?>
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="card product-card h-100">
                <img src="../assets/images/products/<?php echo $product['gambar'] ?: 'default.jpg'; ?>" 
                     class="card-img-top product-img" alt="<?php echo htmlspecialchars($product['nama_produk']); ?>"
                     onerror="this.src='../assets/images/default.jpg'">
                <div class="card-body d-flex flex-column">
                    <span class="badge bg-secondary mb-2 align-self-start"><?php echo htmlspecialchars($product['nama_kategori']); ?></span>
                    <h6 class="card-title"><?php echo htmlspecialchars($product['nama_produk']); ?></h6>
                    <p class="card-text text-muted flex-grow-1"><?php echo htmlspecialchars(substr($product['deskripsi'], 0, 100) . '...'); ?></p>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="product-price"><?php echo format_rupiah($product['harga']); ?></span>
                        <small class="text-muted">
                            <i class="fas fa-box me-1"></i>Stok: <?php echo $product['stok']; ?>
                        </small>
                    </div>
                    
                    <!-- Quantity Input -->
                    <div class="mb-3">
                        <label class="form-label small">Jumlah</label>
                        <div class="input-group input-group-sm">
                            <button class="btn btn-outline-secondary" type="button" 
                                    onclick="changeQuantity('qty_<?php echo $product['id_produk']; ?>', -1)">
                                <i class="fas fa-minus"></i>
                            </button>
                            <input type="number" class="form-control text-center" 
                                   id="qty_<?php echo $product['id_produk']; ?>" 
                                   value="1" min="1" max="<?php echo $product['stok']; ?>">
                            <button class="btn btn-outline-secondary" type="button" 
                                    onclick="changeQuantity('qty_<?php echo $product['id_produk']; ?>', 1)">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                    </div>
                    
                    <?php if ($product['stok'] > 0): ?>
                    <button onclick="addToCartWithQuantity(<?php echo $product['id_produk']; ?>)" 
                            class="btn btn-custom btn-sm w-100">
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
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <!-- Pagination would go here in a real application -->
    <?php if (count($products) > 12): ?>
    <div class="row">
        <div class="col-12">
            <nav aria-label="Product pagination">
                <ul class="pagination justify-content-center">
                    <li class="page-item disabled">
                        <a class="page-link" href="#" tabindex="-1">Previous</a>
                    </li>
                    <li class="page-item active"><a class="page-link" href="#">1</a></li>
                    <li class="page-item"><a class="page-link" href="#">2</a></li>
                    <li class="page-item"><a class="page-link" href="#">3</a></li>
                    <li class="page-item">
                        <a class="page-link" href="#">Next</a>
                    </li>
                </ul>
            </nav>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
// Change quantity function
function changeQuantity(inputId, change) {
    const input = document.getElementById(inputId);
    let currentValue = parseInt(input.value);
    let newValue = currentValue + change;
    
    if (newValue >= 1 && newValue <= parseInt(input.max)) {
        input.value = newValue;
    }
}

// Add to cart with custom quantity
function addToCartWithQuantity(productId) {
    const quantity = parseInt(document.getElementById('qty_' + productId).value);
    addToCart(productId, quantity);
}
</script>

<?php include '../includes/footer.php'; ?>