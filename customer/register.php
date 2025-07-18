<?php
require_once '../config/database.php';

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama_lengkap = clean_input($_POST['nama_lengkap']);
    $email = clean_input($_POST['email']);
    $no_telepon = clean_input($_POST['no_telepon']);
    $alamat = clean_input($_POST['alamat']);
    $username = clean_input($_POST['username']);
    $password = clean_input($_POST['password']);
    $confirm_password = clean_input($_POST['confirm_password']);
    
    // Validasi
    $errors = [];
    
    if (empty($nama_lengkap)) $errors[] = "Nama lengkap harus diisi";
    if (empty($email)) $errors[] = "Email harus diisi";
    if (empty($no_telepon)) $errors[] = "Nomor telepon harus diisi";
    if (empty($alamat)) $errors[] = "Alamat harus diisi";
    if (empty($username)) $errors[] = "Username harus diisi";
    if (empty($password)) $errors[] = "Password harus diisi";
    if ($password !== $confirm_password) $errors[] = "Konfirmasi password tidak cocok";
    
    // Check email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Format email tidak valid";
    }
    
    // Check existing username and email
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id_customer FROM customer WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            $errors[] = "Username atau email sudah digunakan";
        }
    }
    
    if (empty($errors)) {
        $stmt = $pdo->prepare("
            INSERT INTO customer (nama_lengkap, email, no_telepon, alamat, username, password) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        if ($stmt->execute([$nama_lengkap, $email, $no_telepon, $alamat, $username, $password])) {
            $message = 'Registrasi berhasil! Silakan login dengan akun Anda.';
            $message_type = 'success';
            
            // Clear form data
            $_POST = [];
        } else {
            $message = 'Gagal melakukan registrasi. Silakan coba lagi.';
            $message_type = 'danger';
        }
    } else {
        $message = implode('<br>', $errors);
        $message_type = 'danger';
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Akun - RHODE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="login-container">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-8 col-lg-6">
                    <div class="login-card">
                        <div class="text-center mb-4">
                            <i class="fas fa-user-plus fa-3x text-primary mb-3"></i>
                            <h3 class="fw-bold">Daftar Akun Baru</h3>
                            <p class="text-muted">Bergabunglah denga RHODE</p>
                        </div>
                        
                        <?php if (!empty($message)): ?>
                        <div class="alert alert-<?php echo $message_type; ?> alert-custom">
                            <i class="fas fa-<?php echo $message_type == 'success' ? 'check-circle' : 'exclamation-circle'; ?> me-2"></i>
                            <?php echo $message; ?>
                        </div>
                        <?php endif; ?>
                        
                        <form method="POST" id="registerForm" data-validate="true">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="nama_lengkap" class="form-label">
                                        <i class="fas fa-user me-2"></i>Nama Lengkap *
                                    </label>
                                    <input type="text" class="form-control form-control-custom" id="nama_lengkap" 
                                           name="nama_lengkap" required value="<?php echo htmlspecialchars($_POST['nama_lengkap'] ?? ''); ?>">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">
                                        <i class="fas fa-envelope me-2"></i>Email *
                                    </label>
                                    <input type="email" class="form-control form-control-custom" id="email" 
                                           name="email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="no_telepon" class="form-label">
                                        <i class="fas fa-phone me-2"></i>Nomor Telepon *
                                    </label>
                                    <input type="tel" class="form-control form-control-custom" id="no_telepon" 
                                           name="no_telepon" required value="<?php echo htmlspecialchars($_POST['no_telepon'] ?? ''); ?>">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="username" class="form-label">
                                        <i class="fas fa-user-circle me-2"></i>Username *
                                    </label>
                                    <input type="text" class="form-control form-control-custom" id="username" 
                                           name="username" required value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="alamat" class="form-label">
                                    <i class="fas fa-map-marker-alt me-2"></i>Alamat Lengkap *
                                </label>
                                <textarea class="form-control form-control-custom" id="alamat" name="alamat" 
                                          rows="3" required><?php echo htmlspecialchars($_POST['alamat'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="password" class="form-label">
                                        <i class="fas fa-lock me-2"></i>Password *
                                    </label>
                                    <input type="password" class="form-control form-control-custom" id="password" 
                                           name="password" required>
                                </div>
                                
                                <div class="col-md-6 mb-4">
                                    <label for="confirm_password" class="form-label">
                                        <i class="fas fa-lock me-2"></i>Konfirmasi Password *
                                    </label>
                                    <input type="password" class="form-control form-control-custom" id="confirm_password" 
                                           name="confirm_password" required>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-custom w-100 mb-3">
                                <i class="fas fa-user-plus me-2"></i>Daftar Sekarang
                            </button>
                        </form>
                        
                        <div class="text-center">
                            <p class="text-muted">
                                Sudah punya akun? 
                                <a href="login.php" class="text-decoration-none fw-bold">Masuk di sini</a>
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
    
    <script>
    // Password confirmation validation
    document.getElementById('confirm_password').addEventListener('input', function() {
        const password = document.getElementById('password').value;
        const confirmPassword = this.value;
        
        if (password !== confirmPassword) {
            this.classList.add('is-invalid');
        } else {
            this.classList.remove('is-invalid');
        }
    });
    </script>
</body>
</html>