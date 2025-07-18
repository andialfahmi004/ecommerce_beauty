<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check admin login
check_admin_login();

// Get statistics
$stats = [];

// Total produk
$stmt = $pdo->query("SELECT COUNT(*) as total FROM produk WHERE status = 'aktif'");
$stats['total_produk'] = $stmt->fetch()['total'];

// Total pesanan
$stmt = $pdo->query("SELECT COUNT(*) as total FROM pesanan");
$stats['total_pesanan'] = $stmt->fetch()['total'];

// Total customer
$stmt = $pdo->query("SELECT COUNT(*) as total FROM customer");
$stats['total_customer'] = $stmt->fetch()['total'];

// Total pendapatan
$stmt = $pdo->query("SELECT SUM(total_harga) as total FROM pesanan WHERE status_pesanan = 'selesai'");
$stats['total_pendapatan'] = $stmt->fetch()['total'] ?? 0;

// Pesanan pending
$stmt = $pdo->query("SELECT COUNT(*) as total FROM pesanan WHERE status_pesanan = 'menunggu'");
$stats['pesanan_pending'] = $stmt->fetch()['total'];

// Recent orders
$stmt = $pdo->prepare("
    SELECT p.*, c.nama_lengkap 
    FROM pesanan p 
    JOIN customer c ON p.id_customer = c.id_customer 
    ORDER BY p.tanggal_pesan DESC 
    LIMIT 10
");
$stmt->execute();
$recent_orders = $stmt->fetchAll();

// Top products
$stmt = $pdo->prepare("
    SELECT pr.nama_produk, SUM(dp.jumlah) as total_terjual, SUM(dp.subtotal) as total_pendapatan
    FROM detail_pesanan dp
    JOIN produk pr ON dp.id_produk = pr.id_produk
    JOIN pesanan p ON dp.id_pesanan = p.id_pesanan
    WHERE p.status_pesanan = 'selesai'
    GROUP BY dp.id_produk
    ORDER BY total_terjual DESC
    LIMIT 5
");
$stmt->execute();
$top_products = $stmt->fetchAll();

$page_title = "Dashboard Admin - RHODE";
include '../includes/header.php';
include '../includes/navbar-admin.php';
?>

<div class="container my-4">
    <div class="row">
        <div class="col-12">
            <h2 class="fw-bold mb-4">
                <i class="fas fa-tachometer-alt me-2 text-primary"></i>Dashboard Admin
            </h2>
        </div>
    </div>
    
    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card dashboard-card h-100">
                <div class="card-body text-center">
                    <i class="fas fa-box dashboard-icon"></i>
                    <h3 class="fw-bold"><?php echo $stats['total_produk']; ?></h3>
                    <p class="text-muted mb-0">Total Produk</p>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card dashboard-card h-100">
                <div class="card-body text-center">
                    <i class="fas fa-shopping-cart dashboard-icon"></i>
                    <h3 class="fw-bold"><?php echo $stats['total_pesanan']; ?></h3>
                    <p class="text-muted mb-0">Total Pesanan</p>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card dashboard-card h-100">
                <div class="card-body text-center">
                    <i class="fas fa-users dashboard-icon"></i>
                    <h3 class="fw-bold"><?php echo $stats['total_customer']; ?></h3>
                    <p class="text-muted mb-0">Total Pelanggan</p>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card dashboard-card h-100">
                <div class="card-body text-center">
                    <i class="fas fa-money-bill-wave dashboard-icon"></i>
                    <h3 class="fw-bold"><?php echo format_rupiah($stats['total_pendapatan']); ?></h3>
                    <p class="text-muted mb-0">Total Pendapatan</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Alert for pending orders -->
    <?php if ($stats['pesanan_pending'] > 0): ?>
    <div class="alert alert-warning alert-custom">
        <i class="fas fa-exclamation-triangle me-2"></i>
        Anda memiliki <strong><?php echo $stats['pesanan_pending']; ?></strong> pesanan yang menunggu konfirmasi.
        <a href="konfirmasi-pesanan.php" class="alert-link">Lihat sekarang</a>
    </div>
    <?php endif; ?>
    
    <div class="row">
        <!-- Recent Orders -->
        <div class="col-lg-8 mb-4">
            <div class="card card-custom">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-clock me-2"></i>Pesanan Terbaru
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_orders)): ?>
                    <p class="text-muted text-center">Belum ada pesanan</p>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID Pesanan</th>
                                    <th>Pelanggan</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                    <th>Tanggal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_orders as $order): ?>
                                <tr>
                                    <td><strong>#<?php echo $order['id_pesanan']; ?></strong></td>
                                    <td><?php echo htmlspecialchars($order['nama_lengkap']); ?></td>
                                    <td><?php echo format_rupiah($order['total_harga']); ?></td>
                                    <td>
                                        <span class="badge status-<?php echo $order['status_pesanan']; ?>">
                                            <?php echo ucfirst($order['status_pesanan']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo format_tanggal($order['tanggal_pesan']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Top Products -->
        <div class="col-lg-4 mb-4">
            <div class="card card-custom">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-star me-2"></i>Produk Terlaris
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($top_products)): ?>
                    <p class="text-muted text-center">Belum ada data penjualan</p>
                    <?php else: ?>
                    <?php foreach ($top_products as $index => $product): ?>
                    <div class="d-flex align-items-center mb-3 <?php echo $index < count($top_products) - 1 ? 'border-bottom pb-3' : ''; ?>">
                        <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3" 
                             style="width: 30px; height: 30px; font-size: 14px; font-weight: bold;">
                            <?php echo $index + 1; ?>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-1"><?php echo htmlspecialchars($product['nama_produk']); ?></h6>
                            <small class="text-muted">
                                Terjual: <?php echo $product['total_terjual']; ?> |
                                Pendapatan: <?php echo format_rupiah($product['total_pendapatan']); ?>
                            </small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="row">
        <div class="col-12">
            <div class="card card-custom">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-bolt me-2"></i>Aksi Cepat
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <a href="kelola-produk.php?action=add" class="btn btn-outline-primary w-100">
                                <i class="fas fa-plus-circle me-2"></i>Tambah Produk
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="konfirmasi-pesanan.php" class="btn btn-outline-warning w-100">
                                <i class="fas fa-clipboard-check me-2"></i>Konfirmasi Pesanan
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="laporan.php" class="btn btn-outline-success w-100">
                                <i class="fas fa-chart-bar me-2"></i>Lihat Laporan
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="kelola-produk.php" class="btn btn-outline-secondary w-100">
                                <i class="fas fa-box me-2"></i>Kelola Produk
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>