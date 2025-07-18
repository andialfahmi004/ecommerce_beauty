<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check admin login
check_admin_login();

// Get date filter
$start_date = $_GET['start_date'] ?? date('Y-m-01'); // First day of current month
$end_date = $_GET['end_date'] ?? date('Y-m-d'); // Today

// Sales summary
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_pesanan,
        SUM(total_harga) as total_pendapatan,
        AVG(total_harga) as rata_rata_pesanan
    FROM pesanan 
    WHERE status_pesanan = 'selesai' 
    AND DATE(tanggal_pesan) BETWEEN ? AND ?
");
$stmt->execute([$start_date, $end_date]);
$summary = $stmt->fetch();

// Top selling products
$stmt = $pdo->prepare("
    SELECT 
        pr.nama_produk,
        SUM(dp.jumlah) as total_terjual,
        SUM(dp.subtotal) as total_pendapatan
    FROM detail_pesanan dp
    JOIN produk pr ON dp.id_produk = pr.id_produk
    JOIN pesanan p ON dp.id_pesanan = p.id_pesanan
    WHERE p.status_pesanan = 'selesai'
    AND DATE(p.tanggal_pesan) BETWEEN ? AND ?
    GROUP BY dp.id_produk
    ORDER BY total_terjual DESC
    LIMIT 10
");
$stmt->execute([$start_date, $end_date]);
$top_products = $stmt->fetchAll();

// Monthly sales chart data
$stmt = $pdo->prepare("
    SELECT 
        DATE_FORMAT(tanggal_pesan, '%Y-%m') as bulan,
        COUNT(*) as jumlah_pesanan,
        SUM(total_harga) as total_pendapatan
    FROM pesanan 
    WHERE status_pesanan = 'selesai'
    AND tanggal_pesan >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(tanggal_pesan, '%Y-%m')
    ORDER BY bulan ASC
");
$stmt->execute();
$monthly_sales = $stmt->fetchAll();

// Daily sales for current period
$stmt = $pdo->prepare("
    SELECT 
        DATE(tanggal_pesan) as tanggal,
        COUNT(*) as jumlah_pesanan,
        SUM(total_harga) as total_pendapatan
    FROM pesanan 
    WHERE status_pesanan = 'selesai'
    AND DATE(tanggal_pesan) BETWEEN ? AND ?
    GROUP BY DATE(tanggal_pesan)
    ORDER BY tanggal ASC
");
$stmt->execute([$start_date, $end_date]);
$daily_sales = $stmt->fetchAll();

// Customer analysis
$stmt = $pdo->prepare("
    SELECT 
        c.nama_lengkap,
        COUNT(p.id_pesanan) as total_pesanan,
        SUM(p.total_harga) as total_belanja
    FROM customer c
    JOIN pesanan p ON c.id_customer = p.id_customer
    WHERE p.status_pesanan = 'selesai'
    AND DATE(p.tanggal_pesan) BETWEEN ? AND ?
    GROUP BY c.id_customer
    ORDER BY total_belanja DESC
    LIMIT 10
");
$stmt->execute([$start_date, $end_date]);
$top_customers = $stmt->fetchAll();

$page_title = "Laporan Penjualan - Admin Toko Kue Icha";
include '../includes/header.php';
include '../includes/navbar-admin.php';
?>

<div class="container my-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="fw-bold">
                    <i class="fas fa-chart-bar me-2 text-primary"></i>Laporan Penjualan
                </h2>
                
                <!-- Print Button -->
                <div>
                    <a href="print-laporan.php?start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" 
                       target="_blank" class="btn btn-outline-primary">
                        <i class="fas fa-print me-2"></i>Cetak
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Date Filter -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card card-custom">
                <div class="card-body">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label for="start_date" class="form-label">Tanggal Mulai</label>
                            <input type="date" class="form-control form-control-custom" id="start_date" 
                                   name="start_date" value="<?php echo $start_date; ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="end_date" class="form-label">Tanggal Akhir</label>
                            <input type="date" class="form-control form-control-custom" id="end_date" 
                                   name="end_date" value="<?php echo $end_date; ?>">
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-custom">
                                <i class="fas fa-filter me-2"></i>Filter
                            </button>
                            <a href="laporan.php" class="btn btn-outline-secondary">
                                <i class="fas fa-refresh me-2"></i>Reset
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-lg-4 col-md-6 mb-3">
            <div class="card dashboard-card h-100">
                <div class="card-body text-center">
                    <i class="fas fa-shopping-cart dashboard-icon"></i>
                    <h3 class="fw-bold"><?php echo $summary['total_pesanan'] ?? 0; ?></h3>
                    <p class="text-muted mb-0">Total Pesanan</p>
                    <small class="text-muted">Periode: <?php echo format_tanggal($start_date) . ' - ' . format_tanggal($end_date); ?></small>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4 col-md-6 mb-3">
            <div class="card dashboard-card h-100">
                <div class="card-body text-center">
                    <i class="fas fa-money-bill-wave dashboard-icon"></i>
                    <h3 class="fw-bold"><?php echo format_rupiah($summary['total_pendapatan'] ?? 0); ?></h3>
                    <p class="text-muted mb-0">Total Pendapatan</p>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4 col-md-6 mb-3">
            <div class="card dashboard-card h-100">
                <div class="card-body text-center">
                    <i class="fas fa-calculator dashboard-icon"></i>
                    <h3 class="fw-bold"><?php echo format_rupiah($summary['rata_rata_pesanan'] ?? 0); ?></h3>
                    <p class="text-muted mb-0">Rata-rata Pesanan</p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Sales Chart -->
        <div class="col-lg-8 mb-4">
            <div class="card card-custom">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-chart-line me-2"></i>Grafik Penjualan Harian
                    </h5>
                </div>
                <div class="card-body">
                    <canvas id="salesChart" height="300"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Top Products -->
        <div class="col-lg-4 mb-4">
            <div class="card card-custom">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-star me-2"></i>Produk Terlaris
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($top_products)): ?>
                    <p class="text-muted text-center">Tidak ada data penjualan</p>
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
                                <?php echo format_rupiah($product['total_pendapatan']); ?>
                            </small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Top Customers -->
        <div class="col-lg-6 mb-4">
            <div class="card card-custom">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-users me-2"></i>Pelanggan Teratas
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($top_customers)): ?>
                    <p class="text-muted text-center">Tidak ada data pelanggan</p>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Nama Pelanggan</th>
                                    <th>Pesanan</th>
                                    <th>Total Belanja</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($top_customers as $customer): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($customer['nama_lengkap']); ?></td>
                                    <td><span class="badge bg-primary"><?php echo $customer['total_pesanan']; ?></span></td>
                                    <td><strong><?php echo format_rupiah($customer['total_belanja']); ?></strong></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Monthly Performance -->
        <div class="col-lg-6 mb-4">
            <div class="card card-custom">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-calendar me-2"></i>Performa Bulanan (12 Bulan Terakhir)
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($monthly_sales)): ?>
                    <p class="text-muted text-center">Tidak ada data penjualan bulanan</p>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Bulan</th>
                                    <th>Pesanan</th>
                                    <th>Pendapatan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($monthly_sales as $month): ?>
                                <tr>
                                    <td><?php echo date('M Y', strtotime($month['bulan'] . '-01')); ?></td>
                                    <td><?php echo $month['jumlah_pesanan']; ?></td>
                                    <td><?php echo format_rupiah($month['total_pendapatan']); ?></td>
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
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Sales Chart
const ctx = document.getElementById('salesChart').getContext('2d');
const salesChart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: [
            <?php foreach ($daily_sales as $day): ?>
            '<?php echo date('d/m', strtotime($day['tanggal'])); ?>',
            <?php endforeach; ?>
        ],
        datasets: [{
            label: 'Pendapatan (Rp)',
            data: [
                <?php foreach ($daily_sales as $day): ?>
                <?php echo $day['total_pendapatan']; ?>,
                <?php endforeach; ?>
            ],
            borderColor: '#FF69B4',
            backgroundColor: 'rgba(255, 105, 180, 0.1)',
            tension: 0.4,
            fill: true
        }, {
            label: 'Jumlah Pesanan',
            data: [
                <?php foreach ($daily_sales as $day): ?>
                <?php echo $day['jumlah_pesanan']; ?>,
                <?php endforeach; ?>
            ],
            borderColor: '#FFB6C1',
            backgroundColor: 'rgba(255, 182, 193, 0.1)',
            tension: 0.4,
            yAxisID: 'y1'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                type: 'linear',
                display: true,
                position: 'left',
                title: {
                    display: true,
                    text: 'Pendapatan (Rp)'
                }
            },
            y1: {
                type: 'linear',
                display: true,
                position: 'right',
                title: {
                    display: true,
                    text: 'Jumlah Pesanan'
                },
                grid: {
                    drawOnChartArea: false,
                }
            }
        },
        plugins: {
            legend: {
                position: 'top'
            }
        }
    }
});
</script>

<?php include '../includes/footer.php'; ?>