<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check customer login
check_customer_login();

$message = '';
$message_type = '';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action == 'add') {
        $id_produk = clean_input($_POST['id_produk']);
        $jumlah = clean_input($_POST['jumlah']) ?: 1;
        
        // Check if product exists and has enough stock
        $stmt = $pdo->prepare("SELECT * FROM produk WHERE id_produk = ? AND status = 'aktif'");
        $stmt->execute([$id_produk]);
        $product = $stmt->fetch();
        
        if ($product && $product['stok'] >= $jumlah) {
            // Check if item already in cart
            $stmt = $pdo->prepare("SELECT * FROM keranjang WHERE id_customer = ? AND id_produk = ?");
            $stmt->execute([$_SESSION['customer_id'], $id_produk]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                // Update quantity
                $new_quantity = $existing['jumlah'] + $jumlah;
                if ($new_quantity <= $product['stok']) {
                    $stmt = $pdo->prepare("UPDATE keranjang SET jumlah = ? WHERE id_keranjang = ?");
                    $stmt->execute([$new_quantity, $existing['id_keranjang']]);
                    echo json_encode(['success' => true, 'message' => 'Produk berhasil ditambahkan ke keranjang']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Stok tidak mencukupi']);
                }
            } else {
                // Add new item
                $stmt = $pdo->prepare("INSERT INTO keranjang (id_customer, id_produk, jumlah) VALUES (?, ?, ?)");
                if ($stmt->execute([$_SESSION['customer_id'], $id_produk, $jumlah])) {
                    echo json_encode(['success' => true, 'message' => 'Produk berhasil ditambahkan ke keranjang']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Gagal menambahkan produk ke keranjang']);
                }
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Produk tidak tersedia atau stok tidak mencukupi']);
        }
        exit;
    }
    
    if ($action == 'update') {
        $id_keranjang = clean_input($_POST['id_keranjang']);
        $jumlah = clean_input($_POST['jumlah']);
        
        if ($jumlah > 0) {
            // Check stock availability
            $stmt = $pdo->prepare("
                SELECT p.stok, p.nama_produk 
                FROM keranjang k 
                JOIN produk p ON k.id_produk = p.id_produk 
                WHERE k.id_keranjang = ? AND k.id_customer = ?
            ");
            $stmt->execute([$id_keranjang, $_SESSION['customer_id']]);
            $product = $stmt->fetch();
            
            if ($product && $jumlah <= $product['stok']) {
                $stmt = $pdo->prepare("UPDATE keranjang SET jumlah = ? WHERE id_keranjang = ? AND id_customer = ?");
                if ($stmt->execute([$jumlah, $id_keranjang, $_SESSION['customer_id']])) {
                    echo json_encode(['success' => true]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Gagal mengupdate quantity']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Stok tidak mencukupi. Stok tersedia: ' . ($product['stok'] ?? 0)]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Jumlah harus lebih dari 0']);
        }
        exit;
    }
    
    if ($action == 'remove') {
        $id_keranjang = clean_input($_POST['id_keranjang']);
        
        $stmt = $pdo->prepare("DELETE FROM keranjang WHERE id_keranjang = ? AND id_customer = ?");
        if ($stmt->execute([$id_keranjang, $_SESSION['customer_id']])) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal menghapus produk']);
        }
        exit;
    }
    
    if ($action == 'clear') {
        $stmt = $pdo->prepare("DELETE FROM keranjang WHERE id_customer = ?");
        if ($stmt->execute([$_SESSION['customer_id']])) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal mengosongkan keranjang']);
        }
        exit;
    }
    
    if ($action == 'count') {
        $count = get_cart_count($_SESSION['customer_id']);
        echo json_encode(['count' => $count]);
        exit;
    }
}

// Handle clear cart from URL
if (isset($_GET['action']) && $_GET['action'] == 'clear') {
    $stmt = $pdo->prepare("DELETE FROM keranjang WHERE id_customer = ?");
    if ($stmt->execute([$_SESSION['customer_id']])) {
        $message = 'Keranjang berhasil dikosongkan!';
        $message_type = 'success';
    } else {
        $message = 'Gagal mengosongkan keranjang!';
        $message_type = 'danger';
    }
}

// Get cart items with updated stock validation
$stmt = $pdo->prepare("
    SELECT k.*, p.nama_produk, p.harga, p.stok, p.gambar, p.deskripsi, p.status,
           (k.jumlah * p.harga) as subtotal,
           CASE 
               WHEN p.status != 'aktif' THEN 'inactive'
               WHEN k.jumlah > p.stok THEN 'insufficient_stock'
               ELSE 'ok'
           END as item_status
    FROM keranjang k
    JOIN produk p ON k.id_produk = p.id_produk
    WHERE k.id_customer = ?
    ORDER BY k.created_at DESC
");
$stmt->execute([$_SESSION['customer_id']]);
$cart_items = $stmt->fetchAll();

// Calculate totals and check for issues
$total_items = 0;
$total_price = 0;
$has_issues = false;

foreach ($cart_items as $item) {
    if ($item['item_status'] == 'ok') {
        $total_items += $item['jumlah'];
        $total_price += $item['subtotal'];
    } else {
        $has_issues = true;
    }
}

$page_title = "Keranjang Belanja - Toko Kue Icha";
include '../includes/header.php';
include '../includes/navbar-customer.php';
?>

<div class="container my-4">
    <!-- Progress Indicator -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card card-custom">
                <div class="card-body py-3">
                    <div class="row text-center">
                        <div class="col-md-3">
                            <div class="d-flex align-items-center justify-content-center">
                                <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 30px; height: 30px;">
                                    <i class="fas fa-shopping-cart"></i>
                                </div>
                                <span class="fw-bold text-primary">Keranjang</span>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="d-flex align-items-center justify-content-center">
                                <div class="bg-light text-muted rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 30px; height: 30px;">
                                    <i class="fas fa-clipboard-list"></i>
                                </div>
                                <span class="text-muted">Pemesanan</span>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="d-flex align-items-center justify-content-center">
                                <div class="bg-light text-muted rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 30px; height: 30px;">
                                    <i class="fas fa-credit-card"></i>
                                </div>
                                <span class="text-muted">Pembayaran</span>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="d-flex align-items-center justify-content-center">
                                <div class="bg-light text-muted rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 30px; height: 30px;">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <span class="text-muted">Selesai</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($message)): ?>
    <div class="alert alert-<?php echo $message_type; ?> alert-custom alert-dismissible fade show">
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <?php if ($has_issues): ?>
    <div class="alert alert-warning alert-custom">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <strong>Perhatian!</strong> Ada beberapa item di keranjang Anda yang memiliki masalah. Silakan periksa dan perbaiki sebelum melanjutkan ke pemesanan.
    </div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-12">
            <h2 class="fw-bold mb-4">
                <i class="fas fa-shopping-cart me-2 text-primary"></i>Keranjang Belanja
                <?php if (!empty($cart_items)): ?>
                <span class="badge bg-primary"><?php echo count($cart_items); ?> item</span>
                <?php endif; ?>
            </h2>
        </div>
    </div>
    
    <?php if (empty($cart_items)): ?>
    <!-- Empty Cart -->
    <div class="row">
        <div class="col-12">
            <div class="card card-custom">
                <div class="card-body text-center py-5">
                    <i class="fas fa-shopping-cart fa-4x text-muted mb-4"></i>
                    <h4 class="text-muted mb-3">Keranjang Anda Kosong</h4>
                    <p class="text-muted mb-4">Belum ada produk dalam keranjang belanja Anda</p>
                    <a href="produk.php" class="btn btn-custom btn-lg">
                        <i class="fas fa-shopping-bag me-2"></i>Mulai Berbelanja
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <?php else: ?>
    <!-- Cart Items -->
    <div class="row">
        <div class="col-lg-8">
            <div class="card card-custom">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-list me-2"></i>Daftar Produk
                    </h5>
                    <button onclick="clearAllCart()" class="btn btn-outline-danger btn-sm">
                        <i class="fas fa-trash me-1"></i>Kosongkan Semua
                    </button>
                </div>
                <div class="card-body">
                    <?php foreach ($cart_items as $item): ?>
                    <div class="cart-item border rounded p-3 mb-3 <?php echo $item['item_status'] != 'ok' ? 'bg-light' : ''; ?>" 
                         id="cart-item-<?php echo $item['id_keranjang']; ?>">
                        
                        <!-- Status Warning -->
                        <?php if ($item['item_status'] != 'ok'): ?>
                        <div class="alert alert-sm alert-warning mb-2">
                            <?php if ($item['item_status'] == 'inactive'): ?>
                                <i class="fas fa-exclamation-triangle me-1"></i>Produk tidak aktif
                            <?php elseif ($item['item_status'] == 'insufficient_stock'): ?>
                                <i class="fas fa-exclamation-triangle me-1"></i>Stok tidak mencukupi (tersedia: <?php echo $item['stok']; ?>)
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        
                        <div class="row align-items-center">
                            <!-- Product Image -->
                            <div class="col-md-2 col-3 text-center mb-3 mb-md-0">
                                <img src="../assets/images/products/<?php echo $item['gambar'] ?: 'default.jpg'; ?>" 
                                     alt="<?php echo htmlspecialchars($item['nama_produk']); ?>" 
                                     class="img-fluid rounded shadow-sm" style="height: 80px; object-fit: cover;"
                                     onerror="this.src='https://via.placeholder.com/80x80/FFB6C1/FFFFFF?text=No+Image'">
                            </div>
                            
                            <!-- Product Info -->
                            <div class="col-md-4 col-9">
                                <h6 class="fw-bold mb-1"><?php echo htmlspecialchars($item['nama_produk']); ?></h6>
                                <p class="text-muted small mb-2"><?php echo htmlspecialchars(substr($item['deskripsi'], 0, 100) . '...'); ?></p>
                                <div class="d-flex align-items-center">
                                    <span class="badge bg-secondary me-2">Stok: <?php echo $item['stok']; ?></span>
                                    <?php if ($item['item_status'] == 'ok'): ?>
                                        <span class="badge bg-success">✓ OK</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Price -->
                            <div class="col-md-2 col-6 text-center">
                                <p class="fw-bold mb-0 text-primary"><?php echo format_rupiah($item['harga']); ?></p>
                                <small class="text-muted">per item</small>
                            </div>
                            
                            <!-- Quantity -->
                            <div class="col-md-2 col-6">
                                <label class="form-label small">Jumlah</label>
                                <div class="input-group input-group-sm">
                                    <button class="btn btn-outline-secondary" type="button" 
                                            onclick="updateCartQuantity(<?php echo $item['id_keranjang']; ?>, <?php echo $item['jumlah'] - 1; ?>)"
                                            <?php echo $item['jumlah'] <= 1 ? 'disabled' : ''; ?>>
                                        <i class="fas fa-minus"></i>
                                    </button>
                                    <input type="number" class="form-control text-center" 
                                           id="qty_<?php echo $item['id_keranjang']; ?>"
                                           value="<?php echo $item['jumlah']; ?>" 
                                           min="1" max="<?php echo $item['stok']; ?>"
                                           onchange="updateCartQuantity(<?php echo $item['id_keranjang']; ?>, this.value)"
                                           <?php echo $item['item_status'] != 'ok' ? 'disabled' : ''; ?>>
                                    <button class="btn btn-outline-secondary" type="button" 
                                            onclick="updateCartQuantity(<?php echo $item['id_keranjang']; ?>, <?php echo $item['jumlah'] + 1; ?>)"
                                            <?php echo $item['jumlah'] >= $item['stok'] ? 'disabled' : ''; ?>>
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Subtotal & Actions -->
                            <div class="col-md-2 col-12 text-center">
                                <p class="fw-bold mb-2 fs-5 text-success" id="subtotal_<?php echo $item['id_keranjang']; ?>">
                                    <?php echo $item['item_status'] == 'ok' ? format_rupiah($item['subtotal']) : '-'; ?>
                                </p>
                                <button onclick="removeCartItem(<?php echo $item['id_keranjang']; ?>)" 
                                        class="btn btn-outline-danger btn-sm">
                                    <i class="fas fa-trash"></i> Hapus
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <!-- Navigation Buttons -->
                    <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
                        <a href="produk.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Lanjut Belanja
                        </a>
                        
                        <div class="text-muted">
                            <small>
                                <i class="fas fa-info-circle me-1"></i>
                                Tip: Klik tombol + / - untuk mengubah jumlah
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Order Summary -->
        <div class="col-lg-4">
            <div class="card card-custom sticky-top" style="top: 20px;">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-calculator me-2"></i>Ringkasan Pesanan
                    </h5>
                </div>
                <div class="card-body">
                    <!-- Summary Details -->
                    <div class="summary-row d-flex justify-content-between mb-2">
                        <span>Total Item Valid:</span>
                        <span id="total-items" class="fw-bold"><?php echo $total_items; ?></span>
                    </div>
                    
                    <div class="summary-row d-flex justify-content-between mb-2">
                        <span>Subtotal:</span>
                        <span id="subtotal"><?php echo format_rupiah($total_price); ?></span>
                    </div>
                    
                    <div class="summary-row d-flex justify-content-between mb-2">
                        <span>Ongkos Kirim:</span>
                        <span class="text-success">Gratis</span>
                    </div>
                    
                    <div class="summary-row d-flex justify-content-between mb-2">
                        <span>Diskon:</span>
                        <span class="text-success">Rp 0</span>
                    </div>
                    
                    <hr>
                    
                    <div class="summary-row d-flex justify-content-between mb-4">
                        <strong>Total Pembayaran:</strong>
                        <strong id="total-price" class="text-primary fs-4"><?php echo format_rupiah($total_price); ?></strong>
                    </div>
                    
                    <!-- Action Buttons -->
                    <?php if ($has_issues): ?>
                    <div class="alert alert-warning alert-sm">
                        <small>
                            <i class="fas fa-exclamation-triangle me-1"></i>
                            Perbaiki masalah di keranjang sebelum melanjutkan
                        </small>
                    </div>
                    <button class="btn btn-secondary w-100" disabled>
                        <i class="fas fa-exclamation-triangle me-2"></i>Ada Masalah di Keranjang
                    </button>
                    
                    <?php elseif ($total_items == 0): ?>
                    <button class="btn btn-secondary w-100" disabled>
                        <i class="fas fa-shopping-cart me-2"></i>Keranjang Kosong
                    </button>
                    
                    <?php else: ?>
                    <button onclick="proceedToCheckout()" class="btn btn-custom btn-lg w-100">
                        <i class="fas fa-arrow-right me-2"></i>Lanjut ke Pemesanan
                    </button>
                    <?php endif; ?>
                    
                    <!-- Security Badge -->
                    <div class="text-center mt-3">
                        <small class="text-muted">
                            <i class="fas fa-shield-alt me-1 text-success"></i>
                            Transaksi Aman & Terpercaya
                        </small>
                    </div>
                </div>
            </div>
            
            <!-- Delivery Info -->
            <div class="card card-custom mt-3">
                <div class="card-body">
                    <h6 class="fw-bold mb-3">
                        <i class="fas fa-truck me-2 text-primary"></i>Informasi Pengiriman
                    </h6>
                    <div class="d-flex align-items-start mb-2">
                        <i class="fas fa-check-circle text-success me-2 mt-1"></i>
                        <small>Gratis ongkir untuk pembelian di atas Rp 100.000</small>
                    </div>
                    <div class="d-flex align-items-start mb-2">
                        <i class="fas fa-clock text-info me-2 mt-1"></i>
                        <small>Estimasi pengiriman: 1-2 hari kerja</small>
                    </div>
                    <div class="d-flex align-items-start">
                        <i class="fas fa-credit-card text-warning me-2 mt-1"></i>
                        <small>Pembayaran: Transfer, COD, atau E-Wallet</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Loading Modal -->
<div class="modal fade" id="loadingModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body text-center py-4">
                <div class="spinner-border text-primary mb-3" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mb-0">Memproses permintaan...</p>
            </div>
        </div>
    </div>
</div>

<script>
// Show loading modal
function showLoading() {
    const loadingModal = new bootstrap.Modal(document.getElementById('loadingModal'));
    loadingModal.show();
}

// Hide loading modal
function hideLoading() {
    const loadingModal = bootstrap.Modal.getInstance(document.getElementById('loadingModal'));
    if (loadingModal) {
        loadingModal.hide();
    }
}

// Update cart quantity
function updateCartQuantity(cartId, quantity) {
    if (quantity < 1) {
        if (confirm('Hapus produk dari keranjang?')) {
            removeCartItem(cartId);
        } else {
            // Reset to original value
            location.reload();
        }
        return;
    }
    
    showLoading();
    
    $.ajax({
        url: 'keranjang.php',
        method: 'POST',
        data: {
            action: 'update',
            id_keranjang: cartId,
            jumlah: quantity
        },
        dataType: 'json',
        success: function(response) {
            hideLoading();
            if (response.success) {
                location.reload();
            } else {
                showAlert(response.message || 'Gagal mengupdate quantity!', 'danger');
                location.reload();
            }
        },
        error: function() {
            hideLoading();
            showAlert('Terjadi kesalahan sistem!', 'danger');
            location.reload();
        }
    });
}

// Remove cart item
function removeCartItem(cartId) {
    if (confirm('Hapus produk dari keranjang?')) {
        showLoading();
        
        $.ajax({
            url: 'keranjang.php',
            method: 'POST',
            data: {
                action: 'remove',
                id_keranjang: cartId
            },
            dataType: 'json',
            success: function(response) {
                hideLoading();
                if (response.success) {
                    // Animate removal
                    $('#cart-item-' + cartId).fadeOut(300, function() {
                        location.reload();
                    });
                } else {
                    showAlert(response.message || 'Gagal menghapus produk!', 'danger');
                }
            },
            error: function() {
                hideLoading();
                showAlert('Terjadi kesalahan sistem!', 'danger');
            }
        });
    }
}

// Clear entire cart
function clearAllCart() {
    if (confirm('Kosongkan semua produk dari keranjang?')) {
        showLoading();
        
        $.ajax({
            url: 'keranjang.php',
            method: 'POST',
            data: { action: 'clear' },
            dataType: 'json',
            success: function(response) {
                hideLoading();
                if (response.success) {
                    location.reload();
                } else {
                    showAlert(response.message || 'Gagal mengosongkan keranjang!', 'danger');
                }
            },
            error: function() {
                hideLoading();
                showAlert('Terjadi kesalahan sistem!', 'danger');
            }
        });
    }
}

// Proceed to checkout
function proceedToCheckout() {
    showLoading();
    
    // Validate cart before proceeding
    $.ajax({
        url: 'keranjang.php',
        method: 'POST',
        data: { action: 'validate' },
        dataType: 'json',
        success: function(response) {
            hideLoading();
            if (response.success) {
                window.location.href = 'pemesanan.php';
            } else {
                showAlert(response.message || 'Ada masalah dengan keranjang Anda. Silakan refresh halaman.', 'warning');
                setTimeout(() => location.reload(), 2000);
            }
        },
        error: function() {
            hideLoading();
            // Proceed anyway if validation fails
            window.location.href = 'pemesanan.php';
        }
    });
}

// Auto-save cart changes
$(document).ready(function() {
    // Update cart badge on page load
    updateCartBadge();
    
    // Auto-refresh every 30 seconds to check stock
    setInterval(function() {
        updateCartBadge();
    }, 30000);
});
</script>

<?php include '../includes/footer.php'; ?>