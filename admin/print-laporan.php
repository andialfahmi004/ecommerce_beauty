<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check admin login
check_admin_login();

// Get date filter
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');

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

// Monthly sales for comparison
$stmt = $pdo->prepare("
    SELECT 
        DATE_FORMAT(tanggal_pesan, '%Y-%m') as bulan,
        COUNT(*) as jumlah_pesanan,
        SUM(total_harga) as total_pendapatan
    FROM pesanan 
    WHERE status_pesanan = 'selesai'
    AND tanggal_pesan >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(tanggal_pesan, '%Y-%m')
    ORDER BY bulan ASC
");
$stmt->execute();
$monthly_sales = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Penjualan - Toko Kue Icha</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #FF69B4;
            --secondary-color: #FFB6C1;
            --accent-color: #fff5f8;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --border-color: #fce7f3;
            --success-color: #10b981;
            --warning-color: #f59e0b;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            line-height: 1.6;
            color: var(--text-primary);
            background-color: #ffffff;
        }

        .report-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px;
            background: white;
            min-height: 100vh;
        }

        /* Header Styles */
        .report-header {
            text-align: center;
            margin-bottom: 50px;
            border-bottom: 3px solid var(--primary-color);
            padding-bottom: 30px;
        }

        .company-logo {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            color: white;
            font-size: 32px;
        }

        .company-name {
            font-size: 28px;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }

        .report-title {
            font-size: 20px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 15px;
        }

        .report-meta {
            color: var(--text-secondary);
            font-size: 14px;
        }

        .report-meta .period {
            font-weight: 600;
            color: var(--primary-color);
        }

        /* Control Buttons */
        .print-controls {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            background: white;
            padding: 15px;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            border: 1px solid var(--border-color);
        }

        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: #e91e63;
            color: white;
        }

        .btn-secondary {
            background: var(--accent-color);
            color: var(--text-primary);
        }

        .btn-secondary:hover {
            background: var(--secondary-color);
            color: white;
        }

        /* Summary Cards */
        .summary-section {
            margin-bottom: 50px;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .summary-card {
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .summary-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
        }

        .summary-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            color: white;
            font-size: 24px;
        }

        .summary-value {
            font-size: 32px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 8px;
            line-height: 1;
        }

        .summary-label {
            color: var(--text-secondary);
            font-size: 16px;
            font-weight: 500;
        }

        /* Section Styles */
        .section {
            margin-bottom: 50px;
        }

        .section-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--accent-color);
        }

        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0;
        }

        .section-icon {
            color: var(--primary-color);
            font-size: 20px;
        }

        /* Table Styles */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
            background: white;
        }

        .data-table thead th {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            font-weight: 600;
            font-size: 14px;
            padding: 16px;
            text-align: left;
            letter-spacing: 0.5px;
        }

        .data-table tbody td {
            padding: 16px;
            border-bottom: 1px solid var(--border-color);
            font-size: 14px;
        }

        .data-table tbody tr:hover {
            background-color: var(--accent-color);
        }

        .data-table tbody tr:last-child td {
            border-bottom: none;
        }

        .table-number {
            text-align: right;
            font-weight: 500;
        }

        .table-center {
            text-align: center;
        }

        .rank-badge {
            background: var(--primary-color);
            color: white;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 600;
        }

        .total-row {
            background: var(--accent-color);
            font-weight: 600;
        }

        .total-row td {
            border-top: 2px solid var(--primary-color);
        }

        /* Grid Layout */
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
        }

        /* Footer */
        .report-footer {
            margin-top: 80px;
            padding-top: 30px;
            border-top: 2px solid var(--border-color);
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            align-items: end;
        }

        .company-info h4 {
            color: var(--primary-color);
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .company-info p {
            color: var(--text-secondary);
            font-size: 14px;
            margin-bottom: 4px;
        }

        .signature-area {
            text-align: right;
        }

        .signature-line {
            border-top: 1px solid var(--text-primary);
            width: 200px;
            margin: 60px 0 15px auto;
        }

        .signature-title {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 5px;
        }

        .signature-date {
            color: var(--text-secondary);
            font-size: 14px;
        }

        /* Print Styles */
        @media print {
            .print-controls {
                display: none !important;
            }

            .report-container {
                padding: 20px;
                max-width: none;
            }

            body {
                font-size: 12px;
            }

            .summary-value {
                font-size: 24px;
            }

            .company-name {
                font-size: 24px;
            }

            .section {
                page-break-inside: avoid;
                margin-bottom: 30px;
            }

            .data-table {
                font-size: 11px;
            }

            .data-table thead th,
            .data-table tbody td {
                padding: 10px;
            }

            .grid-2 {
                gap: 20px;
            }

            * {
                -webkit-print-color-adjust: exact !important;
                color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .report-container {
                padding: 20px;
            }

            .summary-grid {
                grid-template-columns: 1fr;
            }

            .grid-2 {
                grid-template-columns: 1fr;
                gap: 30px;
            }

            .print-controls {
                position: relative;
                margin-bottom: 30px;
                text-align: center;
            }

            .report-footer {
                grid-template-columns: 1fr;
                gap: 30px;
                text-align: center;
            }

            .signature-area {
                text-align: center;
            }

            .signature-line {
                margin: 60px auto 15px;
            }
        }
    </style>
</head>

<body>
    <!-- Print Controls -->
    <div class="print-controls">
        <button onclick="window.print()" class="btn btn-primary">
            <i class="fas fa-print"></i>
            Cetak Laporan
        </button>
        <a href="laporan.php?start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" 
           class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i>
            Kembali
        </a>
    </div>

    <div class="report-container">
        <!-- Header -->
        <div class="report-header">
            <div class="company-logo">
                <i class="fas fa-birthday-cake"></i>
            </div>
            <h1 class="company-name">TOKO KUE ICHA</h1>
            <h2 class="report-title">LAPORAN PENJUALAN</h2>
            <div class="report-meta">
                <div class="period">Periode: <?php echo format_tanggal($start_date) . ' - ' . format_tanggal($end_date); ?></div>
                <div>Dicetak pada: <?php echo date('d F Y, H:i'); ?> WIB</div>
            </div>
        </div>

        <!-- Summary Section -->
        <div class="summary-section">
            <div class="summary-grid">
                <div class="summary-card">
                    <div class="summary-icon">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="summary-value"><?php echo number_format($summary['total_pesanan'] ?? 0); ?></div>
                    <div class="summary-label">Total Pesanan</div>
                </div>
                
                <div class="summary-card">
                    <div class="summary-icon">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="summary-value"><?php echo format_rupiah($summary['total_pendapatan'] ?? 0); ?></div>
                    <div class="summary-label">Total Pendapatan</div>
                </div>
                
                <div class="summary-card">
                    <div class="summary-icon">
                        <i class="fas fa-calculator"></i>
                    </div>
                    <div class="summary-value"><?php echo format_rupiah($summary['rata_rata_pesanan'] ?? 0); ?></div>
                    <div class="summary-label">Rata-rata per Pesanan</div>
                </div>
            </div>
        </div>

        <!-- Daily Sales Section -->
        <?php if (!empty($daily_sales)): ?>
        <div class="section">
            <div class="section-header">
                <i class="fas fa-chart-line section-icon"></i>
                <h3 class="section-title">Rincian Penjualan Harian</h3>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 25%;">Tanggal</th>
                        <th style="width: 20%;">Hari</th>
                        <th style="width: 20%;" class="table-center">Jumlah Pesanan</th>
                        <th style="width: 35%;" class="table-number">Total Pendapatan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($daily_sales as $day): ?>
                    <tr>
                        <td><?php echo format_tanggal($day['tanggal']); ?></td>
                        <td><?php echo format_hari($day['tanggal']); ?></td>
                        <td class="table-center"><?php echo number_format($day['jumlah_pesanan']); ?></td>
                        <td class="table-number"><?php echo format_rupiah($day['total_pendapatan']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr class="total-row">
                        <td colspan="2"><strong>TOTAL KESELURUHAN</strong></td>
                        <td class="table-center"><strong><?php echo number_format(array_sum(array_column($daily_sales, 'jumlah_pesanan'))); ?></strong></td>
                        <td class="table-number"><strong><?php echo format_rupiah(array_sum(array_column($daily_sales, 'total_pendapatan'))); ?></strong></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- Grid Layout for Products and Customers -->
        <div class="grid-2">
            <!-- Top Products Section -->
            <?php if (!empty($top_products)): ?>
            <div class="section">
                <div class="section-header">
                    <i class="fas fa-star section-icon"></i>
                    <h3 class="section-title">Produk Terlaris</h3>
                </div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width: 15%;" class="table-center">Rank</th>
                            <th style="width: 40%;">Nama Produk</th>
                            <th style="width: 20%;" class="table-center">Terjual</th>
                            <th style="width: 25%;" class="table-number">Pendapatan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($top_products as $index => $product): ?>
                        <tr>
                            <td class="table-center">
                                <span class="rank-badge"><?php echo $index + 1; ?></span>
                            </td>
                            <td><?php echo htmlspecialchars($product['nama_produk']); ?></td>
                            <td class="table-center"><?php echo number_format($product['total_terjual']); ?></td>
                            <td class="table-number"><?php echo format_rupiah($product['total_pendapatan']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <!-- Top Customers Section -->
            <?php if (!empty($top_customers)): ?>
            <div class="section">
                <div class="section-header">
                    <i class="fas fa-users section-icon"></i>
                    <h3 class="section-title">Pelanggan Teratas</h3>
                </div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width: 15%;" class="table-center">Rank</th>
                            <th style="width: 40%;">Nama Pelanggan</th>
                            <th style="width: 20%;" class="table-center">Pesanan</th>
                            <th style="width: 25%;" class="table-number">Total Belanja</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($top_customers as $index => $customer): ?>
                        <tr>
                            <td class="table-center">
                                <span class="rank-badge"><?php echo $index + 1; ?></span>
                            </td>
                            <td><?php echo htmlspecialchars($customer['nama_lengkap']); ?></td>
                            <td class="table-center"><?php echo number_format($customer['total_pesanan']); ?></td>
                            <td class="table-number"><?php echo format_rupiah($customer['total_belanja']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <!-- Monthly Performance Section -->
        <?php if (!empty($monthly_sales)): ?>
        <div class="section">
            <div class="section-header">
                <i class="fas fa-calendar section-icon"></i>
                <h3 class="section-title">Performa 6 Bulan Terakhir</h3>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 40%;">Bulan</th>
                        <th style="width: 30%;" class="table-center">Jumlah Pesanan</th>
                        <th style="width: 30%;" class="table-number">Total Pendapatan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($monthly_sales as $month): ?>
                    <tr>
                        <td><?php echo date('F Y', strtotime($month['bulan'] . '-01')); ?></td>
                        <td class="table-center"><?php echo number_format($month['jumlah_pesanan']); ?></td>
                        <td class="table-number"><?php echo format_rupiah($month['total_pendapatan']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- Footer -->
        <div class="report-footer">
            <div class="company-info">
                <h4>Toko Kue Icha</h4>
                <p>Jl. Raya Manis No. 123, Kota Bandung</p>
                <p>Telp: (022) 1234-5678</p>
                <p>Email: info@tokokueicha.com</p>
                <p>Website: www.tokokueicha.com</p>
            </div>
            <div class="signature-area">
                <div class="signature-title">Mengetahui,</div>
                <div class="signature-line"></div>
                <div class="signature-title">Manager Toko Kue Icha</div>
                <div class="signature-date">Tanggal: <?php echo date('d F Y'); ?></div>
            </div>
        </div>
    </div>
</body>
</html>

<?php
// Helper function if not exists in functions.php
if (!function_exists('format_hari')) {
    function format_hari($tanggal) {
        $hari = array(
            'Sunday' => 'Minggu',
            'Monday' => 'Senin', 
            'Tuesday' => 'Selasa',
            'Wednesday' => 'Rabu',
            'Thursday' => 'Kamis',
            'Friday' => 'Jumat',
            'Saturday' => 'Sabtu'
        );
        return $hari[date('l', strtotime($tanggal))];
    }
}
?>