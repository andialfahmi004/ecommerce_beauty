<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check admin login
check_admin_login();

/**
 * Kelas untuk mengelola operasi pesanan
 */
class OrderManager {
    private $pdo;
    
    // Konstanta untuk status pesanan
    const STATUS_MENUNGGU = 'menunggu';
    const STATUS_DIKONFIRMASI = 'dikonfirmasi';
    const STATUS_DIPROSES = 'diproses';
    const STATUS_SELESAI = 'selesai';
    const STATUS_DIBATALKAN = 'dibatalkan';
    
    // Status yang diizinkan untuk dihapus - SEMUA STATUS BISA DIHAPUS untuk testing
    const DELETABLE_STATUSES = [
        self::STATUS_DIBATALKAN, 
        self::STATUS_MENUNGGU,
        self::STATUS_DIKONFIRMASI,
        self::STATUS_DIPROSES,
        self::STATUS_SELESAI
    ];
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Mendapatkan semua status yang valid
     */
    public static function getValidStatuses() {
        return [
            self::STATUS_MENUNGGU,
            self::STATUS_DIKONFIRMASI, 
            self::STATUS_DIPROSES,
            self::STATUS_SELESAI,
            self::STATUS_DIBATALKAN
        ];
    }
    
    /**
     * Cek apakah pesanan dapat dihapus
     */
    public function canDeleteOrder($id_pesanan) {
        try {
            $stmt = $this->pdo->prepare("SELECT status_pesanan FROM pesanan WHERE id_pesanan = ?");
            $stmt->execute([$id_pesanan]);
            $order = $stmt->fetch();
            
            if (!$order) {
                return false;
            }
            
            // Untuk debugging - bisa dihapus setelah testing
            error_log("Order ID: $id_pesanan, Status: " . $order['status_pesanan'] . ", Can delete: " . (in_array($order['status_pesanan'], self::DELETABLE_STATUSES) ? 'YES' : 'NO'));
            
            return in_array($order['status_pesanan'], self::DELETABLE_STATUSES);
        } catch (Exception $e) {
            error_log("Error in canDeleteOrder: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update status pesanan
     */
    public function updateOrderStatus($id_pesanan, $new_status) {
        try {
            if (!in_array($new_status, self::getValidStatuses())) {
                throw new Exception('Status tidak valid');
            }
            
            // Cek apakah pesanan ada
            if (!$this->orderExists($id_pesanan)) {
                throw new Exception('Pesanan tidak ditemukan');
            }
            
            $stmt = $this->pdo->prepare("UPDATE pesanan SET status_pesanan = ? WHERE id_pesanan = ?");
            $result = $stmt->execute([$new_status, $id_pesanan]);
            
            if (!$result) {
                throw new Exception('Gagal mengupdate status pesanan');
            }
            
            return true;
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    /**
     * Hapus pesanan (hanya untuk status tertentu)
     */
    public function deleteOrder($id_pesanan) {
        try {
            // Cek apakah pesanan dapat dihapus
            if (!$this->canDeleteOrder($id_pesanan)) {
                throw new Exception('Pesanan tidak dapat dihapus. Status pesanan tidak memungkinkan untuk dihapus.');
            }
            
            // Mulai transaksi
            $this->pdo->beginTransaction();
            
            // Hapus pembayaran terkait (jika ada)
            $stmt = $this->pdo->prepare("DELETE FROM pembayaran WHERE id_pesanan = ?");
            $stmt->execute([$id_pesanan]);
            
            // Hapus detail pesanan
            $stmt = $this->pdo->prepare("DELETE FROM detail_pesanan WHERE id_pesanan = ?");
            $stmt->execute([$id_pesanan]);
            
            // Hapus pesanan utama
            $stmt = $this->pdo->prepare("DELETE FROM pesanan WHERE id_pesanan = ?");
            $result = $stmt->execute([$id_pesanan]);
            
            if (!$result || $stmt->rowCount() === 0) {
                throw new Exception('Gagal menghapus pesanan atau pesanan tidak ditemukan');
            }
            
            // Commit transaksi
            $this->pdo->commit();
            return true;
            
        } catch (Exception $e) {
            // Rollback jika terjadi error
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollback();
            }
            throw $e;
        }
    }
    
    /**
     * Cek apakah pesanan exists
     */
    private function orderExists($id_pesanan) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM pesanan WHERE id_pesanan = ?");
        $stmt->execute([$id_pesanan]);
        return $stmt->fetchColumn() > 0;
    }
    
    /**
     * Mendapatkan daftar pesanan berdasarkan filter
     */
    public function getOrders($status_filter = 'all') {
        try {
            $sql = "
                SELECT p.*, c.nama_lengkap, c.no_telepon, c.alamat,
                       pay.metode_pembayaran, pay.status_pembayaran, pay.bukti_pembayaran,
                       COUNT(dp.id_detail) as total_items
                FROM pesanan p
                JOIN customer c ON p.id_customer = c.id_customer
                LEFT JOIN pembayaran pay ON p.id_pesanan = pay.id_pesanan
                LEFT JOIN detail_pesanan dp ON p.id_pesanan = dp.id_pesanan
            ";
            
            $params = [];
            if ($status_filter != 'all' && in_array($status_filter, self::getValidStatuses())) {
                $sql .= " WHERE p.status_pesanan = ?";
                $params[] = $status_filter;
            }
            
            $sql .= " GROUP BY p.id_pesanan ORDER BY p.tanggal_pesan DESC";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll();
        } catch (Exception $e) {
            throw new Exception('Gagal mengambil data pesanan: ' . $e->getMessage());
        }
    }
    
    /**
     * Mendapatkan detail pesanan
     */
    public function getOrderDetails($id_pesanan) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT dp.*, pr.nama_produk, pr.gambar
                FROM detail_pesanan dp 
                JOIN produk pr ON dp.id_produk = pr.id_produk 
                WHERE dp.id_pesanan = ?
                ORDER BY dp.id_detail
            ");
            $stmt->execute([$id_pesanan]);
            
            return $stmt->fetchAll();
        } catch (Exception $e) {
            throw new Exception('Gagal mengambil detail pesanan: ' . $e->getMessage());
        }
    }
    
    /**
     * Mendapatkan statistik pesanan
     */
    public function getOrderStatistics() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    status_pesanan,
                    COUNT(*) as total,
                    SUM(total_harga) as total_nilai
                FROM pesanan 
                GROUP BY status_pesanan
            ");
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        } catch (Exception $e) {
            return [];
        }
    }
}

/**
 * Kelas untuk menangani response dan pesan
 */
class ResponseHandler {
    /**
     * Mendapatkan pesan berdasarkan aksi
     */
    public static function getStatusMessage($action, $success = true) {
        $messages = [
            'konfirmasi' => [
                'success' => 'Pesanan berhasil dikonfirmasi!',
                'error' => 'Gagal mengkonfirmasi pesanan!'
            ],
            'proses' => [
                'success' => 'Pesanan sedang diproses!',
                'error' => 'Gagal memproses pesanan!'
            ],
            'selesai' => [
                'success' => 'Pesanan telah selesai!',
                'error' => 'Gagal menyelesaikan pesanan!'
            ],
            'batal' => [
                'success' => 'Pesanan telah dibatalkan!',
                'error' => 'Gagal membatalkan pesanan!'
            ],
            'hapus' => [
                'success' => 'Pesanan berhasil dihapus!',
                'error' => 'Gagal menghapus pesanan!'
            ]
        ];
        
        return $messages[$action][$success ? 'success' : 'error'] ?? 'Operasi tidak dikenal';
    }
    
    /**
     * Mendapatkan tipe pesan untuk alert
     */
    public static function getMessageType($action, $success = true) {
        if (!$success) return 'danger';
        
        return match($action) {
            'konfirmasi', 'proses', 'selesai' => 'success',
            'batal' => 'warning',
            'hapus' => 'info',
            default => 'info'
        };
    }
}

/**
 * Kelas untuk mengelola UI Components
 */
class UIComponentManager {
    
    /**
     * Render filter status
     */
    public static function renderStatusFilter($current_filter) {
        $filters = [
            'all' => ['label' => 'Semua', 'class' => 'primary'],
            'menunggu' => ['label' => 'Menunggu', 'class' => 'warning'],
            'dikonfirmasi' => ['label' => 'Dikonfirmasi', 'class' => 'info'],
            'diproses' => ['label' => 'Diproses', 'class' => 'primary'],
            'selesai' => ['label' => 'Selesai', 'class' => 'success'],
            'dibatalkan' => ['label' => 'Dibatalkan', 'class' => 'danger']
        ];
        
        echo '<div class="btn-group" role="group" aria-label="Filter Status">';
        foreach ($filters as $status => $config) {
            $is_active = ($current_filter === $status);
            $btn_class = $is_active ? "btn-{$config['class']}" : "btn-outline-{$config['class']}";
            
            echo "<a href=\"?status={$status}\" class=\"btn {$btn_class} btn-sm\">";
            echo htmlspecialchars($config['label']);
            echo "</a>";
        }
        echo '</div>';
    }
    
    /**
     * Render empty state
     */
    public static function renderEmptyState() {
        echo '
        <div class="col-12">
            <div class="card card-custom">
                <div class="card-body text-center py-5">
                    <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">Tidak ada pesanan</h5>
                    <p class="text-muted">Belum ada pesanan dengan status yang dipilih</p>
                </div>
            </div>
        </div>';
    }
    
    /**
     * Render order statistics
     */
    public static function renderOrderStatistics($statistics) {
        if (empty($statistics)) return;
        
        echo '<div class="row mb-4">';
        echo '<div class="col-12">';
        echo '<div class="card card-custom">';
        echo '<div class="card-body">';
        echo '<h6 class="card-title"><i class="fas fa-chart-bar me-2"></i>Statistik Pesanan</h6>';
        echo '<div class="row text-center">';
        
        $status_config = [
            'menunggu' => ['label' => 'Menunggu', 'color' => 'warning'],
            'dikonfirmasi' => ['label' => 'Dikonfirmasi', 'color' => 'info'],
            'diproses' => ['label' => 'Diproses', 'color' => 'primary'],
            'selesai' => ['label' => 'Selesai', 'color' => 'success'],
            'dibatalkan' => ['label' => 'Dibatalkan', 'color' => 'danger']
        ];
        
        foreach ($status_config as $status => $config) {
            $count = $statistics[$status] ?? 0;
            echo '<div class="col">';
            echo "<div class=\"border-start border-{$config['color']} border-3 ps-3\">";
            echo "<div class=\"text-{$config['color']} fw-bold fs-4\">{$count}</div>";
            echo "<div class=\"small text-muted\">{$config['label']}</div>";
            echo '</div>';
            echo '</div>';
        }
        
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
}

// Inisialisasi
$orderManager = new OrderManager($pdo);
$message = '';
$message_type = '';

// Menangani POST request untuk update status pesanan
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = clean_input($_POST['action'] ?? '');
        $id_pesanan = clean_input($_POST['id_pesanan'] ?? '');
        
        // Debug log
        error_log("POST Request - Action: $action, ID: $id_pesanan");
        
        // Validasi input
        if (empty($action) || empty($id_pesanan)) {
            throw new Exception('Data tidak lengkap');
        }
        
        if (!is_numeric($id_pesanan)) {
            throw new Exception('ID pesanan tidak valid');
        }
        
        // Handle different actions
        switch ($action) {
            case 'hapus':
                error_log("Attempting to delete order: $id_pesanan");
                $orderManager->deleteOrder($id_pesanan);
                error_log("Order deleted successfully: $id_pesanan");
                break;
                
            default:
                // Mapping action ke status
                $status_mapping = [
                    'konfirmasi' => OrderManager::STATUS_DIKONFIRMASI,
                    'proses' => OrderManager::STATUS_DIPROSES,
                    'selesai' => OrderManager::STATUS_SELESAI,
                    'batal' => OrderManager::STATUS_DIBATALKAN
                ];
                
                if (!isset($status_mapping[$action])) {
                    throw new Exception('Aksi tidak valid');
                }
                
                $orderManager->updateOrderStatus($id_pesanan, $status_mapping[$action]);
                break;
        }
        
        $message = ResponseHandler::getStatusMessage($action, true);
        $message_type = ResponseHandler::getMessageType($action, true);
        
    } catch (Exception $e) {
        error_log("Error in POST request: " . $e->getMessage());
        $message = $e->getMessage();
        $message_type = 'danger';
    }
}

// Mendapatkan filter status
$status_filter = clean_input($_GET['status'] ?? 'all');
$valid_filters = array_merge(['all'], OrderManager::getValidStatuses());
if (!in_array($status_filter, $valid_filters)) {
    $status_filter = 'all';
}

// Mendapatkan data pesanan dan statistik
try {
    $orders = $orderManager->getOrders($status_filter);
    $statistics = $orderManager->getOrderStatistics();
} catch (Exception $e) {
    $message = $e->getMessage();
    $message_type = 'danger';
    $orders = [];
    $statistics = [];
}

$page_title = "Manajemen Pesanan - Admin Toko Kue Icha";
include '../includes/header.php';
include '../includes/navbar-admin.php';
?>

<div class="container my-4">
    <!-- Alert Messages -->
    <?php if (!empty($message)): ?>
    <div class="alert alert-<?php echo $message_type; ?> alert-custom alert-dismissible fade show">
        <i class="fas fa-<?php echo $message_type === 'danger' ? 'exclamation-triangle' : 'check-circle'; ?> me-2"></i>
        <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <!-- Header Section -->
    <div class="row">
        <div class="col-12">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4">
                <div class="mb-3 mb-md-0">
                    <h2 class="fw-bold mb-1">
                        <i class="fas fa-clipboard-check me-2 text-primary"></i>Manajemen Pesanan
                    </h2>
                    <p class="text-muted mb-0">Kelola dan pantau semua pesanan pelanggan</p>
                </div>
                
                <!-- Filter Status -->
                <?php UIComponentManager::renderStatusFilter($status_filter); ?>
            </div>
        </div>
    </div>
    
    <!-- Statistics Section -->
    <?php UIComponentManager::renderOrderStatistics($statistics); ?>
    
    <!-- Orders Section -->
    <div class="row">
        <?php if (empty($orders)): ?>
            <?php UIComponentManager::renderEmptyState(); ?>
        <?php else: ?>
            <?php foreach ($orders as $order): ?>
                <?php renderOrderCard($order, $orderManager); ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Konfirmasi -->
<div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="confirmModalLabel">
                    <i class="fas fa-exclamation-triangle text-warning me-2"></i>Konfirmasi Aksi
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p id="confirmMessage" class="mb-0">Apakah Anda yakin ingin melakukan aksi ini?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>Batal
                </button>
                <button type="button" class="btn btn-danger" id="confirmButton">
                    <i class="fas fa-check me-1"></i>Ya, Lanjutkan
                </button>
            </div>
        </div>
    </div>
</div>

<script>
/**
 * Manajemen JavaScript untuk interaksi
 */
class OrderInteractionManager {
    constructor() {
        this.init();
    }
    
    init() {
        // Setup modal if Bootstrap is loaded
        if (typeof bootstrap !== 'undefined') {
            this.confirmModal = new bootstrap.Modal(document.getElementById('confirmModal'));
            this.confirmButton = document.getElementById('confirmButton');
            this.confirmMessage = document.getElementById('confirmMessage');
            this.pendingForm = null;
            
            // Setup confirm button click handler
            this.confirmButton.addEventListener('click', () => {
                if (this.pendingForm) {
                    console.log('Submitting form:', this.pendingForm);
                    this.pendingForm.submit();
                }
                this.confirmModal.hide();
            });
        }
        
        // Auto-hide alerts after 5 seconds
        this.setupAutoHideAlerts();
        
        // Debug: Log all delete buttons
        const deleteButtons = document.querySelectorAll('button[onclick*="hapus"]');
        console.log('Found delete buttons:', deleteButtons.length);
    }
    
    confirmAction(form, message) {
        console.log('confirmAction called with:', form, message);
        
        if (this.confirmModal) {
            this.pendingForm = form;
            this.confirmMessage.textContent = message;
            this.confirmModal.show();
        } else {
            // Fallback jika Bootstrap modal tidak tersedia
            if (confirm(message)) {
                form.submit();
            }
        }
    }
    
    setupAutoHideAlerts() {
        const alerts = document.querySelectorAll('.alert-dismissible');
        alerts.forEach(alert => {
            setTimeout(() => {
                if (alert.classList.contains('show')) {
                    if (typeof bootstrap !== 'undefined') {
                        const bsAlert = new bootstrap.Alert(alert);
                        bsAlert.close();
                    } else {
                        alert.remove();
                    }
                }
            }, 5000);
        });
    }
}

// Inisialisasi manager
document.addEventListener('DOMContentLoaded', function() {
    window.orderManager = new OrderInteractionManager();
});

// Fungsi global untuk kompatibilitas
function confirmAction(form, message) {
    console.log('Global confirmAction called');
    if (window.orderManager) {
        window.orderManager.confirmAction(form, message);
    } else {
        // Fallback
        if (confirm(message)) {
            form.submit();
        }
    }
}

// Debug function
function testDeleteButton(orderId) {
    console.log('Testing delete for order:', orderId);
    const form = document.getElementById('form_hapus_' + orderId);
    if (form) {
        console.log('Form found:', form);
        confirmAction(form, 'Test delete message');
    } else {
        console.log('Form not found for order:', orderId);
    }
}
</script>

<style>
.status-menunggu {
    background-color: #ffc107;
    color: #000;
}

.status-dikonfirmasi {
    background-color: #17a2b8;
    color: #fff;
}

.status-diproses {
    background-color: #007bff;
    color: #fff;
}

.status-selesai {
    background-color: #28a745;
    color: #fff;
}

.status-dibatalkan {
    background-color: #dc3545;
    color: #fff;
}

.card-custom {
    border: none;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    transition: all 0.15s ease-in-out;
}

.card-custom:hover {
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
}

.btn-action {
    min-width: 80px;
}

.table-responsive {
    border-radius: 0.375rem;
}

.border-start {
    border-left-width: 3px !important;
}

/* Debug styles */
.debug-info {
    background-color: #f8f9fa;
    border: 1px solid #dee2e6;
    padding: 10px;
    margin: 10px 0;
    border-radius: 4px;
}
</style>

<?php
/**
 * Fungsi helper untuk rendering order card
 */
function renderOrderCard($order, $orderManager) {
    echo '<div class="col-lg-6 mb-4">';
    echo '<div class="card card-custom h-100">';
    
    // Header
    renderOrderHeader($order);
    
    echo '<div class="card-body d-flex flex-column">';
    
    // Debug info - hapus setelah testing
    echo '<div class="debug-info small">';
    echo 'ID: ' . $order['id_pesanan'] . ' | Status: ' . $order['status_pesanan'];
    echo ' | Can Delete: ' . ($orderManager->canDeleteOrder($order['id_pesanan']) ? 'YES' : 'NO');
    echo '</div>';
    
    // Customer Info
    renderCustomerInfo($order);
    
    // Order Details
    renderOrderDetails($order, $orderManager);
    
    // Payment Info
    renderPaymentInfo($order);
    
    // Order Info
    renderOrderInfo($order);
    
    // Action Buttons
    echo '<div class="mt-auto">';
    renderActionButtons($order, $orderManager);
    echo '</div>';
    
    echo '</div>';
    echo '</div>';
    echo '</div>';
}

function renderOrderHeader($order) {
    echo '<div class="card-header d-flex justify-content-between align-items-center bg-light">';
    echo '<h6 class="mb-0 fw-bold">';
    echo '<i class="fas fa-receipt me-2"></i>Pesanan #' . htmlspecialchars($order['id_pesanan']);
    echo '</h6>';
    echo '<span class="badge status-' . htmlspecialchars($order['status_pesanan']) . ' fs-6">';
    echo ucfirst(htmlspecialchars($order['status_pesanan']));
    echo '</span>';
    echo '</div>';
}

function renderCustomerInfo($order) {
    echo '<div class="mb-3">';
    echo '<h6 class="fw-bold text-primary border-bottom pb-2">';
    echo '<i class="fas fa-user me-2"></i>Informasi Pelanggan';
    echo '</h6>';
    echo '<div class="row">';
    echo '<div class="col-sm-4"><small class="text-muted">Nama:</small></div>';
    echo '<div class="col-sm-8"><small>' . htmlspecialchars($order['nama_lengkap']) . '</small></div>';
    echo '<div class="col-sm-4"><small class="text-muted">Telepon:</small></div>';
    echo '<div class="col-sm-8"><small>' . htmlspecialchars($order['no_telepon']) . '</small></div>';
    echo '<div class="col-sm-4"><small class="text-muted">Alamat:</small></div>';
    echo '<div class="col-sm-8"><small>' . htmlspecialchars($order['alamat']) . '</small></div>';
    echo '</div>';
    echo '</div>';
}

function renderOrderDetails($order, $orderManager) {
    try {
        $details = $orderManager->getOrderDetails($order['id_pesanan']);
        
        echo '<div class="mb-3">';
        echo '<h6 class="fw-bold text-primary border-bottom pb-2">';
        echo '<i class="fas fa-shopping-cart me-2"></i>Detail Pesanan (' . count($details) . ' item)';
        echo '</h6>';
        
        if (!empty($details)) {
            echo '<div class="table-responsive">';
            echo '<table class="table table-sm table-hover">';
            echo '<thead class="table-light">';
            echo '<tr><th>Produk</th><th>Qty</th><th>Harga</th><th>Subtotal</th></tr>';
            echo '</thead>';
            echo '<tbody>';
            
            foreach ($details as $detail) {
                echo '<tr>';
                echo '<td><small>' . htmlspecialchars($detail['nama_produk']) . '</small></td>';
                echo '<td><small>' . htmlspecialchars($detail['jumlah']) . '</small></td>';
                echo '<td><small>' . format_rupiah($detail['harga']) . '</small></td>';
                echo '<td><small class="fw-bold">' . format_rupiah($detail['subtotal']) . '</small></td>';
                echo '</tr>';
            }
            
            echo '</tbody>';
            echo '<tfoot class="table-light">';
            echo '<tr><th colspan="3">Total</th><th>' . format_rupiah($order['total_harga']) . '</th></tr>';
            echo '</tfoot>';
            echo '</table>';
            echo '</div>';
        } else {
            echo '<div class="alert alert-warning">Detail pesanan tidak ditemukan</div>';
        }
        
        echo '</div>';
        
    } catch (Exception $e) {
        echo '<div class="alert alert-danger">Gagal memuat detail pesanan</div>';
    }
}

function renderPaymentInfo($order) {
    if ($order['metode_pembayaran']) {
        echo '<div class="mb-3">';
        echo '<h6 class="fw-bold text-primary border-bottom pb-2">';
        echo '<i class="fas fa-credit-card me-2"></i>Informasi Pembayaran';
        echo '</h6>';
        
        echo '<div class="row">';
        echo '<div class="col-sm-4"><small class="text-muted">Metode:</small></div>';
        echo '<div class="col-sm-8"><small>' . ucfirst(htmlspecialchars($order['metode_pembayaran'])) . '</small></div>';
        
        echo '<div class="col-sm-4"><small class="text-muted">Status:</small></div>';
        echo '<div class="col-sm-8">';
        $status_class = $order['status_pembayaran'] == 'lunas' ? 'success' : 'warning';
        echo '<span class="badge bg-' . $status_class . '">' . ucfirst(htmlspecialchars($order['status_pembayaran'])) . '</span>';
        echo '</div>';
        
        if ($order['bukti_pembayaran']) {
            echo '<div class="col-sm-4"><small class="text-muted">Bukti:</small></div>';
            echo '<div class="col-sm-8">';
            $bukti_path = '../assets/images/payments/' . htmlspecialchars($order['bukti_pembayaran']);
            echo '<a href="' . $bukti_path . '" target="_blank" class="btn btn-sm btn-outline-primary">';
            echo '<i class="fas fa-eye me-1"></i>Lihat Bukti</a>';
            echo '</div>';
        }
        echo '</div>';
        echo '</div>';
    }
}

function renderOrderInfo($order) {
    echo '<div class="mb-3">';
    echo '<small class="text-muted d-block">';
    echo '<i class="fas fa-clock me-1"></i>Dipesan: ' . format_tanggal($order['tanggal_pesan']);
    echo '</small>';
    
    if ($order['catatan']) {
        echo '<small class="text-muted d-block mt-1">';
        echo '<i class="fas fa-comment me-1"></i>Catatan: ' . htmlspecialchars($order['catatan']);
        echo '</small>';
    }
    echo '</div>';
}

function renderActionButtons($order, $orderManager) {
    echo '<div class="d-flex flex-wrap gap-2">';
    
    $status = $order['status_pesanan'];
    $order_id = $order['id_pesanan'];
    
    // Status-specific buttons
    switch ($status) {
        case OrderManager::STATUS_MENUNGGU:
            renderActionButton('konfirmasi', $order_id, 'Konfirmasi', 'info', 'check');
            renderActionButton('batal', $order_id, 'Batalkan', 'warning', 'times', true, 'Yakin ingin membatalkan pesanan ini?');
            break;
            
        case OrderManager::STATUS_DIKONFIRMASI:
            renderActionButton('proses', $order_id, 'Proses', 'primary', 'cog');
            break;
            
        case OrderManager::STATUS_DIPROSES:
            renderActionButton('selesai', $order_id, 'Selesai', 'success', 'check-circle');
            break;
    }
    
    // Delete button - SELALU TAMPIL untuk testing
    renderActionButton('hapus', $order_id, 'Hapus', 'danger', 'trash', true, 'Yakin ingin menghapus pesanan ini? Aksi ini tidak dapat dibatalkan!');
}

function renderActionButton($action, $order_id, $label, $color, $icon, $confirm = false, $message = null) {
    $form_id = "form_{$action}_{$order_id}";
    
    echo "<form method=\"POST\" id=\"{$form_id}\" class=\"d-inline\">";
    echo "<input type=\"hidden\" name=\"action\" value=\"{$action}\">";
    echo "<input type=\"hidden\" name=\"id_pesanan\" value=\"{$order_id}\">";
    
    $onclick = '';
    if ($confirm) {
        $confirm_message = $message ?: "Yakin ingin {$label} pesanan ini?";
        $onclick = "onclick=\"event.preventDefault(); confirmAction(document.getElementById('{$form_id}'), '{$confirm_message}'); return false;\"";
    }
    
    echo "<button type=\"submit\" class=\"btn btn-{$color} btn-sm btn-action\" {$onclick}>";
    echo "<i class=\"fas fa-{$icon} me-1\"></i>{$label}";
    echo "</button>";
    echo "</form>";
}

include '../includes/footer.php';
?>