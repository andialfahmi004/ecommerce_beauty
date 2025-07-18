<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check customer login
check_customer_login();

$message = '';
$message_type = '';

// Check if cart is empty
$cart_count = get_cart_count($_SESSION['customer_id']);
if ($cart_count == 0) {
    header("Location: keranjang.php");
    exit();
}

// Get customer data
$stmt = $pdo->prepare("SELECT * FROM customer WHERE id_customer = ?");
$stmt->execute([$_SESSION['customer_id']]);
$customer = $stmt->fetch();

// Get cart items
$stmt = $pdo->prepare("
    SELECT k.*, p.nama_produk, p.harga, p.stok, p.gambar,
           (k.jumlah * p.harga) as subtotal
    FROM keranjang k
    JOIN produk p ON k.id_produk = p.id_produk
    WHERE k.id_customer = ? AND p.status = 'aktif'
    ORDER BY k.created_at DESC
");
$stmt->execute([$_SESSION['customer_id']]);
$cart_items = $stmt->fetchAll();

// Calculate totals
$total_price = get_cart_total($_SESSION['customer_id']);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $catatan = clean_input($_POST['catatan']);
    $metode_pembayaran = clean_input($_POST['metode_pembayaran']);
    
    // Validate stock availability
    $stock_error = false;
    foreach ($cart_items as $item) {
        if ($item['jumlah'] > $item['stok']) {
            $stock_error = true;
            break;
        }
    }
    
    if (!$stock_error) {
        try {
            $pdo->beginTransaction();
            
            // Create order
            $stmt = $pdo->prepare("
                INSERT INTO pesanan (id_customer, total_harga, catatan) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$_SESSION['customer_id'], $total_price, $catatan]);
            $order_id = $pdo->lastInsertId();
            
            // Create order details
            foreach ($cart_items as $item) {
                $stmt = $pdo->prepare("
                    INSERT INTO detail_pesanan (id_pesanan, id_produk, jumlah, harga, subtotal) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $order_id, 
                    $item['id_produk'], 
                    $item['jumlah'], 
                    $item['harga'], 
                    $item['subtotal']
                ]);
                
                // Update product stock
                $stmt = $pdo->prepare("UPDATE produk SET stok = stok - ? WHERE id_produk = ?");
                $stmt->execute([$item['jumlah'], $item['id_produk']]);
            }
            
            // Create payment record
            $stmt = $pdo->prepare("
                INSERT INTO pembayaran (id_pesanan, metode_pembayaran, jumlah_bayar) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$order_id, $metode_pembayaran, $total_price]);
            
            // Clear cart
            $stmt = $pdo->prepare("DELETE FROM keranjang WHERE id_customer = ?");
            $stmt->execute([$_SESSION['customer_id']]);
            
            $pdo->commit();
            
            // Redirect to payment page
            header("Location: pembayaran.php?order_id=" . $order_id);
            exit();
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = 'Gagal memproses pesanan. Silakan coba lagi.';
            $message_type = 'danger';
        }
    } else {
        $message = 'Beberapa produk tidak tersedia atau stok tidak mencukupi. Silakan periksa keranjang Anda.';
        $message_type = 'danger';
    }
}

$page_title = "Pemesanan - Toko Kue Icha";
include '../includes/header.php';
include '../includes/navbar-customer.php';
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
            <h2 class="fw-bold mb-4">
                <i class="fas fa-clipboard-list me-2 text-primary"></i>Pemesanan
            </h2>
        </div>
    </div>
    
    <form method="POST" id="orderForm" data-validate="true">
        <div class="row">
            <!-- Customer Information -->
            <div class="col-lg-6 mb-4">
                <div class="card card-custom">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-user me-2"></i>Informasi Pelanggan
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Nama Lengkap</label>
                            <input type="text" class="form-control form-control-custom" 
                                   value="<?php echo htmlspecialchars($customer['nama_lengkap']); ?>" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control form-control-custom" 
                                   value="<?php echo htmlspecialchars($customer['email']); ?>" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Nomor Telepon</label>
                            <input type="tel" class="form-control form-control-custom" 
                                   value="<?php echo htmlspecialchars($customer['no_telepon']); ?>" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Alamat Pengiriman</label>
                            <textarea class="form-control form-control-custom" rows="3" readonly><?php echo htmlspecialchars($customer['alamat']); ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="catatan" class="form-label">Catatan Pesanan (Opsional)</label>
                            <textarea class="form-control form-control-custom" id="catatan" name="catatan" 
                                      rows="3" placeholder="Tambahkan catatan khusus untuk pesanan Anda..."><?php echo htmlspecialchars($_POST['catatan'] ?? ''); ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="metode_pembayaran" class="form-label">Metode Pembayaran *</label>
                            <select class="form-select form-control-custom" id="metode_pembayaran" name="metode_pembayaran" required>
                                <option value="">Pilih Metode Pembayaran</option>
                                <option value="transfer" <?php echo ($_POST['metode_pembayaran'] ?? '') == 'transfer' ? 'selected' : ''; ?>>Transfer Bank</option>
                                <option value="cash" <?php echo ($_POST['metode_pembayaran'] ?? '') == 'cash' ? 'selected' : ''; ?>>Cash on Delivery (COD)</option>
                                <option value="ewallet" <?php echo ($_POST['metode_pembayaran'] ?? '') == 'ewallet' ? 'selected' : ''; ?>>E-Wallet</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Order Summary -->
            <div class="col-lg-6 mb-4">
                <div class="card card-custom">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-shopping-cart me-2"></i>Ringkasan Pesanan
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($cart_items as $item): ?>
                        <div class="d-flex align-items-center mb-3 pb-3 border-bottom">
                            <img src="../assets/images/products/<?php echo $item['gambar'] ?: 'default.jpg'; ?>" 
                                 alt="<?php echo htmlspecialchars($item['nama_produk']); ?>" 
                                 class="rounded me-3" style="width: 60px; height: 60px; object-fit: cover;"
                                 onerror="this.src='../assets/images/default.jpg'">
                            <div class="flex-grow-1">
                                <h6 class="mb-1"><?php echo htmlspecialchars($item['nama_produk']); ?></h6>
                                <small class="text-muted">
                                    <?php echo format_rupiah($item['harga']); ?> x <?php echo $item['jumlah']; ?>
                                </small>
                            </div>
                            <div class="text-end">
                                <strong><?php echo format_rupiah($item['subtotal']); ?></strong>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        
                        <div class="d-flex justify-content-between mb-2">
                            <span>Subtotal:</span>
                            <span><?php echo format_rupiah($total_price); ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Ongkos Kirim:</span>
                            <span class="text-success">Gratis</span>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between mb-3">
                            <strong>Total:</strong>
                            <strong class="text-primary fs-5"><?php echo format_rupiah($total_price); ?></strong>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-custom btn-lg">
                                <i class="fas fa-credit-card me-2"></i>Buat Pesanan
                            </button>
                            <a href="keranjang.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Kembali ke Keranjang
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Payment Info -->
                <div class="card card-custom mt-3">
                    <div class="card-body">
                        <h6 class="fw-bold">
                            <i class="fas fa-info-circle me-2"></i>Informasi Pembayaran
                        </h6>
                        <small class="text-muted">
                            <strong>Transfer Bank:</strong> Upload bukti transfer setelah pemesanan<br>
                            <strong>COD:</strong> Bayar langsung saat pesanan diantar<br>
                            <strong>E-Wallet:</strong> Pembayaran melalui aplikasi digital
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<?php include '../includes/footer.php'; ?>