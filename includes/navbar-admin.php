<nav class="navbar navbar-expand-lg navbar-custom">
    <div class="container">
        <a class="navbar-brand" href="dashboard.php">
            <i class=""></i>RHODE
        </a>
        
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="dashboard.php">
                        <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="kelola-produk.php">
                        <i class="fas fa-box me-1"></i>Kelola Produk
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="konfirmasi-pesanan.php">
                        <i class="fas fa-clipboard-check me-1"></i>Konfirmasi Pesanan
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="laporan.php">
                        <i class="fas fa-chart-bar me-1"></i>Laporan
                    </a>
                </li>
            </ul>
            
            <ul class="navbar-nav">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user me-1"></i><?php echo $_SESSION['admin_nama'] ?? 'Admin'; ?>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="logout.php">
                            <i class="fas fa-sign-out-alt me-1"></i>Keluar
                        </a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>