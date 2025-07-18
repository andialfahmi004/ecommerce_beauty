<?php
require_once '../config/database.php';

$error_message = '';
$success_message = '';

// Check for logout message
if (isset($_GET['message']) && $_GET['message'] == 'logout_success') {
    $success_message = 'Anda telah berhasil keluar.';
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = clean_input($_POST['username']);
    $password = clean_input($_POST['password']);
    
    if (!empty($username) && !empty($password)) {
        $stmt = $pdo->prepare("SELECT * FROM customer WHERE username = ? AND password = ?");
        $stmt->execute([$username, $password]);
        $customer = $stmt->fetch();
        
        if ($customer) {
            $_SESSION['customer_id'] = $customer['id_customer'];
            $_SESSION['customer_nama'] = $customer['nama_lengkap'];
            $_SESSION['customer_username'] = $customer['username'];
            
            // Redirect to intended page or beranda
            $redirect = $_GET['redirect'] ?? 'beranda.php';
            header("Location: " . $redirect);
            exit();
        } else {
            $error_message = "Username atau password salah!";
        }
    } else {
        $error_message = "Mohon lengkapi semua field!";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Pelanggan - Toko Kue Icha</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="login-container">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-6 col-lg-4">
                    <div class="login-card">
                        <div class="text-center mb-4">
                            <i class="fas fa-user fa-3x text-primary mb-3"></i>
                            <h3 class="fw-bold">Login Pelanggan</h3>
                            <p class="text-muted">Masuk ke akun rhode</p>
                        </div>
                        
                        <?php if (!empty($error_message)): ?>
                        <div class="alert alert-danger alert-custom">
                            <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($success_message)): ?>
                        <div class="alert alert-success alert-custom">
                            <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                        </div>
                        <?php endif; ?>
                        
                        <form method="POST" id="loginForm" data-validate="true">
                            <div class="mb-3">
                                <label for="username" class="form-label">
                                    <i class="fas fa-user me-2"></i>Username
                                </label>
                                <input type="text" class="form-control form-control-custom" id="username" 
                                       name="username" required value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                            </div>
                            
                            <div class="mb-4">
                                <label for="password" class="form-label">
                                    <i class="fas fa-lock me-2"></i>Password
                                </label>
                                <input type="password" class="form-control form-control-custom" id="password" 
                                       name="password" required>
                            </div>
                            
                            <button type="submit" class="btn btn-custom w-100 mb-3">
                                <i class="fas fa-sign-in-alt me-2"></i>Masuk
                            </button>
                        </form>
                        
                        <div class="text-center">
                            <p class="text-muted">
                                Belum punya akun? 
                                <a href="register.php" class="text-decoration-none fw-bold">Daftar di sini</a>
                            </p>
                            <small class="text-muted">
                                <a href="../index.php" class="text-decoration-none">
                                    <i class="fas fa-home me-1"></i>Kembali ke Beranda
                                </a>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="../assets/js/script.js"></script>
</body>
</html>