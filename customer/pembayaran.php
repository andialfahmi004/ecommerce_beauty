<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check customer login
check_customer_login();

$message = '';
$message_type = '';

// Get order ID
$order_id = $_GET['order_id'] ?? 0;

// Validate order
$stmt = $pdo->prepare("
    SELECT p.*, c.nama_lengkap, c.email, c.no_telepon, c.alamat,
           pay.metode_pembayaran, pay.status_pembayaran, pay.bukti_pembayaran
    FROM pesanan p
    JOIN customer c ON p.id_customer = c.id_customer
    LEFT JOIN pembayaran pay ON p.id_pesanan = pay.id_pesanan
    WHERE p.id_pesanan = ? AND p.id_customer = ?
");
$stmt->execute([$order_id, $_SESSION['customer_id']]);
$order = $stmt->fetch();

if (!$order) {
    header("Location: beranda.php");
    exit();
}

// Get order details
$stmt = $pdo->prepare("
    SELECT dp.*, pr.nama_produk, pr.gambar
    FROM detail_pesanan dp
    JOIN produk pr ON dp.id_produk = pr.id_produk
    WHERE dp.id_pesanan = ?
");
$stmt->execute([$order_id]);
$order_details = $stmt->fetchAll();

// Handle payment upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_payment'])) {
    if (isset($_FILES['bukti_pembayaran']) && $_FILES['bukti_pembayaran']['size'] > 0) {
        $bukti_pembayaran = upload_image($_FILES['bukti_pembayaran'], "../assets/images/payments/");
        
        if ($bukti_pembayaran) {
            $stmt = $pdo->prepare("
                UPDATE pembayaran 
                SET bukti_pembayaran = ?, status_pembayaran = 'menunggu' 
                WHERE id_pesanan = ?
            ");
            if ($stmt->execute([$bukti_pembayaran, $order_id])) {
                $message = 'Bukti pembayaran berhasil diupload. Pesanan Anda akan segera diproses.';
                $message_type = 'success';
                
                // Refresh order data
                $stmt = $pdo->prepare("
                    SELECT p.*, c.nama_lengkap, c.email, c.no_telepon, c.alamat,
                           pay.metode_pembayaran, pay.status_pembayaran, pay.bukti_pembayaran
                    FROM pesanan p
                    JOIN customer c ON p.id_customer = c.id_customer
                    LEFT JOIN pembayaran pay ON p.id_pesanan = pay.id_pesanan
                    WHERE p.id_pesanan = ? AND p.id_customer = ?
                ");
                $stmt->execute([$order_id, $_SESSION['customer_id']]);
                $order = $stmt->fetch();
            } else {
                $message = 'Gagal mengupload bukti pembayaran.';
                $message_type = 'danger';
            }
        } else {
            $message = 'Gagal mengupload file. Pastikan format file JPG, PNG, atau GIF dengan ukuran maksimal 5MB.';
            $message_type = 'danger';
        }
    } else {
        $message = 'Silakan pilih file bukti pembayaran.';
        $message_type = 'danger';
    }
}

$page_title = "Pembayaran - Toko Kue Icha";
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
                <i class="fas fa-credit-card me-2 text-primary"></i>Pembayaran
            </h2>
        </div>
    </div>
    
    <div class="row">
        <!-- Order Information -->
        <div class="col-lg-8 mb-4">
            <div class="card card-custom">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-receipt me-2"></i>Detail Pesanan #<?php echo $order['id_pesanan']; ?>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h6 class="fw-bold">Informasi Pelanggan</h6>
                            <p class="mb-1"><strong>Nama:</strong> <?php echo htmlspecialchars($order['nama_lengkap']); ?></p>
                            <p class="mb-1"><strong>Email:</strong> <?php echo htmlspecialchars($order['email']); ?></p>
                            <p class="mb-1"><strong>Telepon:</strong> <?php echo htmlspecialchars($order['no_telepon']); ?></p>
                            <p class="mb-1"><strong>Alamat:</strong> <?php echo htmlspecialchars($order['alamat']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <h6 class="fw-bold">Informasi Pesanan</h6>
                            <p class="mb-1"><strong>Tanggal:</strong> <?php echo format_tanggal($order['tanggal_pesan']); ?></p>
                            <p class="mb-1"><strong>Status:</strong> 
                                <span class="badge status-<?php echo $order['status_pesanan']; ?>">
                                    <?php echo ucfirst($order['status_pesanan']); ?>
                                </span>
                            </p>
                            <p class="mb-1"><strong>Metode Pembayaran:</strong> <?php echo ucfirst($order['metode_pembayaran']); ?></p>
                            <p class="mb-1"><strong>Status Pembayaran:</strong> 
                                <span class="badge bg-<?php echo $order['status_pembayaran'] == 'lunas' ? 'success' : 'warning'; ?>">
                                    <?php echo ucfirst($order['status_pembayaran']); ?>
                                </span>
                            </p>
                            <?php if ($order['catatan']): ?>
                            <p class="mb-1"><strong>Catatan:</strong> <?php echo htmlspecialchars($order['catatan']); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <h6 class="fw-bold">Produk yang Dipesan</h6>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Produk</th>
                                    <th>Harga</th>
                                    <th>Qty</th>
                                    <th>Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($order_details as $detail): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <img src="../assets/images/products/<?php echo $detail['gambar'] ?: 'default.jpg'; ?>" 
                                                 alt="<?php echo htmlspecialchars($detail['nama_produk']); ?>" 
                                                 class="rounded me-3" style="width: 50px; height: 50px; object-fit: cover;"
                                                 onerror="this.src='../assets/images/default.jpg'">
                                            <span><?php echo htmlspecialchars($detail['nama_produk']); ?></span>
                                        </div>
                                    </td>
                                    <td><?php echo format_rupiah($detail['harga']); ?></td>
                                    <td><?php echo $detail['jumlah']; ?></td>
                                    <td><strong><?php echo format_rupiah($detail['subtotal']); ?></strong></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="3">Total</th>
                                    <th class="text-primary"><?php echo format_rupiah($order['total_harga']); ?></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Payment Section -->
        <div class="col-lg-4 mb-4">
            <?php if ($order['metode_pembayaran'] == 'transfer' && $order['status_pembayaran'] != 'lunas'): ?>
            <!-- Upload Payment Proof -->
            <div class="card card-custom">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-upload me-2"></i>Upload Bukti Pembayaran
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <h6 class="fw-bold">Informasi Transfer</h6>
                        <div class="bg-light p-3 rounded">
                            <p class="mb-1"><strong>Bank BCA</strong></p>
                            <p class="mb-1">No. Rekening: <strong>1234567890</strong></p>
                            <p class="mb-1">Atas Nama: <strong>RHODE GROUP</strong></p>
                            <p class="mb-0">Jumlah: <strong class="text-primary"><?php echo format_rupiah($order['total_harga']); ?></strong></p>
                        </div>
                    </div>
                    
                    <?php if (!$order['bukti_pembayaran']): ?>
                    <form method="POST" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="bukti_pembayaran" class="form-label">Bukti Pembayaran</label>
                            <input type="file" class="form-control form-control-custom" id="bukti_pembayaran" 
                                   name="bukti_pembayaran" accept="image/*" required>
                            <small class="text-muted">Format: JPG, PNG, GIF. Maksimal 5MB</small>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" name="upload_payment" class="btn btn-custom">
                                <i class="fas fa-upload me-2"></i>Upload Bukti Pembayaran
                            </button>
                        </div>
                    </form>
                    <?php else: ?>
                    <div class="mb-3">
                        <h6 class="fw-bold">Bukti Pembayaran</h6>
                        <img src="../assets/images/payments/<?php echo $order['bukti_pembayaran']; ?>" 
                             alt="Bukti Pembayaran" class="img-fluid rounded">
                        <p class="text-success mt-2">
                            <i class="fas fa-check-circle me-1"></i>
                            Bukti pembayaran telah diupload dan sedang diverifikasi
                        </p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php elseif ($order['metode_pembayaran'] == 'cash'): ?>
            <!-- COD Information -->
            <div class="card card-custom">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-truck me-2"></i>Cash on Delivery (COD)
                    </h5>
                </div>
                <div class="card-body">
                    <div class="text-center mb-3">
                        <i class="fas fa-money-bill-wave fa-3x text-success mb-3"></i>
                        <h6 class="fw-bold">Pembayaran Tunai</h6>
                    </div>
                    <div class="bg-light p-3 rounded">
                        <p class="mb-1"><strong>Total yang harus dibayar:</strong></p>
                        <h4 class="text-primary mb-2"><?php echo format_rupiah($order['total_harga']); ?></h4>
                        <p class="mb-0 small text-muted">
                            Siapkan uang pas untuk memudahkan transaksi dengan kurir
                        </p>
                    </div>
                    <div class="mt-3">
                        <p class="text-success">
                            <i class="fas fa-info-circle me-1"></i>
                            Pesanan Anda akan segera diproses dan diantarkan ke alamat tujuan
                        </p>
                    </div>
                </div>
            </div>
            
            <?php else: ?>
            <!-- E-Wallet Information -->
            <div class="card card-custom">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-mobile-alt me-2"></i>Pembayaran E-Wallet
                    </h5>
                </div>
                <div class="card-body">
                    <div class="text-center mb-3">
                        <i class="fas fa-qrcode fa-3x text-primary mb-3"></i>
                        <h6 class="fw-bold">Scan QR Code</h6>
                    </div>
                    <div class="bg-light p-3 rounded text-center">
                        <p class="mb-1"><strong>Total Pembayaran:</strong></p>
                        <h4 class="text-primary mb-2"><?php echo format_rupiah($order['total_harga']); ?></h4>
                        <p class="mb-0 small text-muted">
                            Scan QR code dengan aplikasi e-wallet Anda
                        </p>
                    </div>
                    <div class="mt-3">
                        <p class="text-info">
                            <i class="fas fa-info-circle me-1"></i>
                            Hubungi kami jika mengalami kesulitan pembayaran
                        </p>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Contact Information -->
            <div class="card card-custom mt-3">
                <div class="card-body">
                    <h6 class="fw-bold">
                        <i class="fas fa-phone me-2"></i>Butuh Bantuan?
                    </h6>
                    <p class="mb-1">WhatsApp: <strong>+62 82 2928 31308</strong></p>
                    <p class="mb-1">Email: <strong>info@rhode@gmail.com</strong></p>
                    <small class="text-muted">Layanan pelanggan 24/7</small>
                </div>
            </div>
            
            <!-- Back to Home -->
            <div class="d-grid mt-3">
                <a href="beranda.php" class="btn btn-outline-secondary">
                    <i class="fas fa-home me-2"></i>Kembali ke Beranda
                </a>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>