<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check admin login
check_admin_login();

$message = '';
$message_type = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'];
    
    if ($action == 'add' || $action == 'edit') {
        $nama_produk = clean_input($_POST['nama_produk']);
        $deskripsi = clean_input($_POST['deskripsi']);
        $harga = clean_input(str_replace(['Rp', '.', ' '], '', $_POST['harga']));
        $stok = clean_input($_POST['stok']);
        $id_kategori = clean_input($_POST['id_kategori']);
        $status = clean_input($_POST['status']);
        
        $gambar = '';
        if (isset($_FILES['gambar']) && $_FILES['gambar']['size'] > 0) {
            $gambar = upload_image($_FILES['gambar']);
            if (!$gambar) {
                $message = 'Gagal mengupload gambar!';
                $message_type = 'danger';
            }
        }
        
        if (empty($message)) {
            if ($action == 'add') {
                $sql = "INSERT INTO produk (nama_produk, deskripsi, harga, stok, id_kategori, status";
                $params = [$nama_produk, $deskripsi, $harga, $stok, $id_kategori, $status];
                
                if ($gambar) {
                    $sql .= ", gambar";
                    $params[] = $gambar;
                }
                
                $sql .= ") VALUES (" . str_repeat('?,', count($params) - 1) . "?)";
                
                $stmt = $pdo->prepare($sql);
                if ($stmt->execute($params)) {
                    $message = 'Produk berhasil ditambahkan!';
                    $message_type = 'success';
                } else {
                    $message = 'Gagal menambahkan produk!';
                    $message_type = 'danger';
                }
                
            } else if ($action == 'edit') {
                $id_produk = clean_input($_POST['id_produk']);
                
                $sql = "UPDATE produk SET nama_produk=?, deskripsi=?, harga=?, stok=?, id_kategori=?, status=?";
                $params = [$nama_produk, $deskripsi, $harga, $stok, $id_kategori, $status];
                
                if ($gambar) {
                    $sql .= ", gambar=?";
                    $params[] = $gambar;
                }
                
                $sql .= " WHERE id_produk=?";
                $params[] = $id_produk;
                
                $stmt = $pdo->prepare($sql);
                if ($stmt->execute($params)) {
                    $message = 'Produk berhasil diperbarui!';
                    $message_type = 'success';
                } else {
                    $message = 'Gagal memperbarui produk!';
                    $message_type = 'danger';
                }
            }
        }
    }
    
    if ($action == 'delete') {
        $id_produk = clean_input($_POST['id_produk']);
        $force_delete = isset($_POST['force_delete']) ? true : false;
        
        try {
            // Get product info first
            $stmt = $pdo->prepare("SELECT nama_produk FROM produk WHERE id_produk = ?");
            $stmt->execute([$id_produk]);
            $product_info = $stmt->fetch();
            
            if (!$product_info) {
                $message = 'Produk tidak ditemukan!';
                $message_type = 'danger';
            } else {
                // Check usage
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM detail_pesanan WHERE id_produk = ?");
                $stmt->execute([$id_produk]);
                $usage_count = $stmt->fetch()['count'];
                
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM keranjang WHERE id_produk = ?");
                $stmt->execute([$id_produk]);
                $cart_count = $stmt->fetch()['count'];
                
                // Always allow force delete
                $pdo->beginTransaction();
                
                // Delete from detail_pesanan first (if any)
                if ($usage_count > 0) {
                    $stmt = $pdo->prepare("DELETE FROM detail_pesanan WHERE id_produk = ?");
                    $stmt->execute([$id_produk]);
                    
                    // Recalculate order totals
                    $stmt = $pdo->prepare("
                        UPDATE pesanan p 
                        SET total_harga = (
                            SELECT COALESCE(SUM(subtotal), 0) 
                            FROM detail_pesanan dp 
                            WHERE dp.id_pesanan = p.id_pesanan
                        )
                    ");
                    $stmt->execute();
                    
                    // Update payment amounts to match new totals
                    $stmt = $pdo->prepare("
                        UPDATE pembayaran pay
                        JOIN pesanan p ON pay.id_pesanan = p.id_pesanan
                        SET pay.jumlah_bayar = p.total_harga
                    ");
                    $stmt->execute();
                }
                
                // Delete from keranjang
                if ($cart_count > 0) {
                    $stmt = $pdo->prepare("DELETE FROM keranjang WHERE id_produk = ?");
                    $stmt->execute([$id_produk]);
                }
                
                // Finally delete the product
                $stmt = $pdo->prepare("DELETE FROM produk WHERE id_produk = ?");
                if ($stmt->execute([$id_produk])) {
                    $pdo->commit();
                    
                    if ($usage_count > 0) {
                        $message = 'Produk "' . htmlspecialchars($product_info['nama_produk']) . '" berhasil dihapus secara paksa beserta ' . $usage_count . ' riwayat pesanan dan ' . $cart_count . ' item keranjang.';
                        $message_type = 'warning';
                    } else {
                        $message = 'Produk "' . htmlspecialchars($product_info['nama_produk']) . '" berhasil dihapus!';
                        $message_type = 'success';
                    }
                } else {
                    $pdo->rollBack();
                    $message = 'Gagal menghapus produk!';
                    $message_type = 'danger';
                }
            }
        } catch (PDOException $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $message = 'Gagal menghapus produk: ' . $e->getMessage();
            $message_type = 'danger';
        }
    }
    
    if ($action == 'soft_delete') {
        $id_produk = clean_input($_POST['id_produk']);
        
        try {
            $stmt = $pdo->prepare("SELECT nama_produk FROM produk WHERE id_produk = ?");
            $stmt->execute([$id_produk]);
            $product_info = $stmt->fetch();
            
            if ($product_info) {
                $stmt = $pdo->prepare("UPDATE produk SET status = 'nonaktif' WHERE id_produk = ?");
                if ($stmt->execute([$id_produk])) {
                    $message = 'Produk "' . htmlspecialchars($product_info['nama_produk']) . '" berhasil dinonaktifkan. Produk tidak akan muncul di katalog tetapi riwayat pesanan tetap tersimpan.';
                    $message_type = 'success';
                } else {
                    $message = 'Gagal menonaktifkan produk!';
                    $message_type = 'danger';
                }
            } else {
                $message = 'Produk tidak ditemukan!';
                $message_type = 'danger';
            }
        } catch (PDOException $e) {
            $message = 'Gagal menonaktifkan produk: ' . $e->getMessage();
            $message_type = 'danger';
        }
    }
}

// Get action for form display
$action = $_GET['action'] ?? 'list';
$edit_product = null;

if ($action == 'edit' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM produk WHERE id_produk = ?");
    $stmt->execute([$_GET['id']]);
    $edit_product = $stmt->fetch();
    
    if (!$edit_product) {
        $action = 'list';
    }
}

// Get products and categories
$stmt = $pdo->prepare("
    SELECT p.*, k.nama_kategori,
           (SELECT COUNT(*) FROM detail_pesanan dp WHERE dp.id_produk = p.id_produk) as order_count,
           (SELECT COUNT(*) FROM keranjang kr WHERE kr.id_produk = p.id_produk) as cart_count
    FROM produk p 
    JOIN kategori k ON p.id_kategori = k.id_kategori 
    ORDER BY p.created_at DESC
");
$stmt->execute();
$products = $stmt->fetchAll();

$stmt = $pdo->query("SELECT * FROM kategori ORDER BY nama_kategori");
$categories = $stmt->fetchAll();

$page_title = "Kelola Produk - Admin Toko Kue Icha";
include '../includes/header.php';
include '../includes/navbar-admin.php';
?>

<div class="container my-4">
    <?php if (!empty($message)): ?>
    <div class="alert alert-<?php echo $message_type; ?> alert-custom alert-dismissible fade show">
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="fw-bold">
                    <i class="fas fa-box me-2 text-primary"></i>Kelola Produk
                </h2>
                <?php if ($action == 'list'): ?>
                <a href="?action=add" class="btn btn-custom">
                    <i class="fas fa-plus me-2"></i>Tambah Produk
                </a>
                <?php else: ?>
                <a href="kelola-produk.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Kembali
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php if ($action == 'add' || $action == 'edit'): ?>
    <!-- Form Add/Edit Product -->
    <div class="row">
        <div class="col-lg-8">
            <div class="card card-custom">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-<?php echo $action == 'add' ? 'plus' : 'edit'; ?> me-2"></i>
                        <?php echo $action == 'add' ? 'Tambah' : 'Edit'; ?> Produk
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data" id="productForm" data-validate="true">
                        <input type="hidden" name="action" value="<?php echo $action; ?>">
                        <?php if ($action == 'edit'): ?>
                        <input type="hidden" name="id_produk" value="<?php echo $edit_product['id_produk']; ?>">
                        <?php endif; ?>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="nama_produk" class="form-label">Nama Produk *</label>
                                <input type="text" class="form-control form-control-custom" id="nama_produk" 
                                       name="nama_produk" required 
                                       value="<?php echo htmlspecialchars($edit_product['nama_produk'] ?? ''); ?>">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="id_kategori" class="form-label">Kategori *</label>
                                <select class="form-select form-control-custom" id="id_kategori" name="id_kategori" required>
                                    <option value="">Pilih Kategori</option>
                                    <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id_kategori']; ?>" 
                                            <?php echo ($edit_product['id_kategori'] ?? '') == $category['id_kategori'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['nama_kategori']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="deskripsi" class="form-label">Deskripsi</label>
                            <textarea class="form-control form-control-custom" id="deskripsi" name="deskripsi" 
                                      rows="3"><?php echo htmlspecialchars($edit_product['deskripsi'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="harga" class="form-label">Harga *</label>
                                <input type="text" class="form-control form-control-custom" id="harga" 
                                       name="harga" required placeholder="25000"
                                       value="<?php echo $edit_product['harga'] ?? ''; ?>">
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label for="stok" class="form-label">Stok *</label>
                                <input type="number" class="form-control form-control-custom" id="stok" 
                                       name="stok" required min="0"
                                       value="<?php echo $edit_product['stok'] ?? ''; ?>">
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label for="status" class="form-label">Status *</label>
                                <select class="form-select form-control-custom" id="status" name="status" required>
                                    <option value="aktif" <?php echo ($edit_product['status'] ?? '') == 'aktif' ? 'selected' : ''; ?>>Aktif</option>
                                    <option value="nonaktif" <?php echo ($edit_product['status'] ?? '') == 'nonaktif' ? 'selected' : ''; ?>>Non-aktif</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="gambar" class="form-label">Gambar Produk</label>
                            <input type="file" class="form-control form-control-custom" id="gambar" 
                                   name="gambar" accept="image/*" onchange="previewImage(this, 'imagePreview')">
                            <small class="text-muted">Format: JPG, PNG, GIF. Maksimal 5MB</small>
                        </div>
                        
                        <?php if ($action == 'edit' && !empty($edit_product['gambar'])): ?>
                        <div class="mb-3">
                            <label class="form-label">Gambar Saat Ini</label><br>
                            <img src="../assets/images/products/<?php echo $edit_product['gambar']; ?>" 
                                 alt="Current Image" class="img-thumbnail" style="max-width: 200px;" id="imagePreview">
                        </div>
                        <?php else: ?>
                        <div class="mb-3" style="display: none;" id="previewContainer">
                            <label class="form-label">Preview Gambar</label><br>
                            <img id="imagePreview" class="img-thumbnail" style="max-width: 200px;">
                        </div>
                        <?php endif; ?>
                        
                        <div class="d-flex justify-content-end">
                            <a href="kelola-produk.php" class="btn btn-outline-secondary me-2">Batal</a>
                            <button type="submit" class="btn btn-custom">
                                <i class="fas fa-save me-2"></i><?php echo $action == 'add' ? 'Tambah' : 'Perbarui'; ?> Produk
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <?php else: ?>
    <!-- Product List -->
    <div class="row">
        <div class="col-12">
            <div class="card table-custom">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-list me-2"></i>Daftar Produk
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($products)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-box fa-3x text-muted mb-3"></i>
                        <p class="text-muted">Belum ada produk. <a href="?action=add">Tambah produk pertama</a></p>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Gambar</th>
                                    <th>Nama Produk</th>
                                    <th>Kategori</th>
                                    <th>Harga</th>
                                    <th>Stok</th>
                                    <th>Status</th>
                                    <th>Usage</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($products as $product): ?>
                                <tr>
                                    <td>
                                        <?php if (!empty($product['gambar'])): ?>
                                        <img src="../assets/images/products/<?php echo $product['gambar']; ?>" 
                                             alt="<?php echo htmlspecialchars($product['nama_produk']); ?>" 
                                             class="img-thumbnail" style="width: 60px; height: 60px; object-fit: cover;">
                                        <?php else: ?>
                                        <div class="bg-light d-flex align-items-center justify-content-center" 
                                             style="width: 60px; height: 60px;">
                                            <i class="fas fa-image text-muted"></i>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($product['nama_produk']); ?></strong>
                                        <?php if (!empty($product['deskripsi'])): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars(substr($product['deskripsi'], 0, 50) . '...'); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($product['nama_kategori']); ?></td>
                                    <td><strong><?php echo format_rupiah($product['harga']); ?></strong></td>
                                    <td>
                                        <span class="badge bg-<?php echo $product['stok'] > 0 ? 'success' : 'danger'; ?>">
                                            <?php echo $product['stok']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $product['status'] == 'aktif' ? 'success' : 'secondary'; ?>">
                                            <?php echo ucfirst($product['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?php if ($product['order_count'] > 0): ?>
                                            <i class="fas fa-shopping-cart text-warning"></i> <?php echo $product['order_count']; ?> pesanan<br>
                                            <?php endif; ?>
                                            <?php if ($product['cart_count'] > 0): ?>
                                            <i class="fas fa-cart-arrow-down text-info"></i> <?php echo $product['cart_count']; ?> keranjang<br>
                                            <?php endif; ?>
                                            <?php if ($product['order_count'] == 0 && $product['cart_count'] == 0): ?>
                                            <span class="text-success"><i class="fas fa-check"></i> Aman dihapus</span>
                                            <?php endif; ?>
                                        </small>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <!-- Edit Button -->
                                            <a href="?action=edit&id=<?php echo $product['id_produk']; ?>" 
                                               class="btn btn-outline-primary" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            
                                            <!-- Soft Delete Button -->
                                            <?php if ($product['status'] == 'aktif'): ?>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Nonaktifkan produk ini? Produk tidak akan muncul di katalog tetapi riwayat pesanan tetap tersimpan.')">
                                                <input type="hidden" name="action" value="soft_delete">
                                                <input type="hidden" name="id_produk" value="<?php echo $product['id_produk']; ?>">
                                                <button type="submit" class="btn btn-outline-warning" title="Nonaktifkan">
                                                    <i class="fas fa-eye-slash"></i>
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                            
                                            <!-- Force Delete Button -->
                                            <form method="POST" class="d-inline" onsubmit="return confirmForceDelete('<?php echo htmlspecialchars($product['nama_produk']); ?>', <?php echo $product['order_count']; ?>, <?php echo $product['cart_count']; ?>)">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id_produk" value="<?php echo $product['id_produk']; ?>">
                                                <input type="hidden" name="force_delete" value="1">
                                                <button type="submit" class="btn btn-outline-danger" title="Hapus Paksa">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Force Delete Modal -->
<div class="modal fade" id="forceDeleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <i class="fas fa-exclamation-triangle me-2"></i>Konfirmasi Hapus Paksa
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    <strong>PERINGATAN:</strong> Tindakan ini tidak dapat dibatalkan!
                </div>
                <p id="delete-message"></p>
                <div class="bg-light p-3 rounded">
                    <h6>Yang akan dihapus:</h6>
                    <ul id="delete-items"></ul>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-danger" id="confirm-force-delete">
                    <i class="fas fa-trash-alt me-2"></i>Ya, Hapus Paksa
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let currentDeleteForm = null;

// Preview image function
function previewImage(input, previewId) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById(previewId).src = e.target.result;
            const container = document.getElementById('previewContainer');
            if (container) {
                container.style.display = 'block';
            }
        }
        reader.readAsDataURL(input.files[0]);
    }
}

// Format harga input
document.getElementById('harga')?.addEventListener('input', function() {
    let value = this.value.replace(/[^0-9]/g, '');
    this.value = value;
});

// Force delete confirmation
function confirmForceDelete(productName, orderCount, cartCount) {
    currentDeleteForm = event.target.closest('form');
    event.preventDefault();
    
    let message = `Anda akan menghapus produk "<strong>${productName}</strong>" secara permanen.`;
    let items = [];
    
    if (orderCount > 0) {
        items.push(`${orderCount} riwayat pesanan (data transaksi akan hilang)`);
    }
    if (cartCount > 0) {
        items.push(`${cartCount} item di keranjang pelanggan`);
    }
    items.push('Data produk dari database');
    
    document.getElementById('delete-message').innerHTML = message;
    
    let itemsList = '';
    items.forEach(item => {
        itemsList += `<li>${item}</li>`;
    });
    document.getElementById('delete-items').innerHTML = itemsList;
    
    const modal = new bootstrap.Modal(document.getElementById('forceDeleteModal'));
    modal.show();
    
    return false;
}

// Confirm force delete button
document.getElementById('confirm-force-delete').addEventListener('click', function() {
    if (currentDeleteForm) {
        currentDeleteForm.submit();
    }
});

// Auto dismiss alerts
setTimeout(function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        if (alert.classList.contains('alert-success') || alert.classList.contains('alert-warning')) {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        }
    });
}, 5000);
</script>

<?php include '../includes/footer.php'; ?>