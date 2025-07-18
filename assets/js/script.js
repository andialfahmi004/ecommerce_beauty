// Custom JavaScript untuk Toko Kue Icha

// Function untuk konfirmasi hapus
function confirmDelete(message = 'Apakah Anda yakin ingin menghapus data ini?') {
    return confirm(message);
}

// Function untuk format rupiah
function formatRupiah(angka, prefix = 'Rp ') {
    let number_string = angka.replace(/[^,\d]/g, '').toString();
    let split = number_string.split(',');
    let sisa = split[0].length % 3;
    let rupiah = split[0].substr(0, sisa);
    let ribuan = split[0].substr(sisa).match(/\d{3}/gi);

    if (ribuan) {
        separator = sisa ? '.' : '';
        rupiah += separator + ribuan.join('.');
    }

    rupiah = split[1] != undefined ? rupiah + ',' + split[1] : rupiah;
    return prefix == undefined ? rupiah : (rupiah ? prefix + rupiah : '');
}

// Function untuk validasi form
function validateForm(formId) {
    const form = document.getElementById(formId);
    const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
    let isValid = true;

    inputs.forEach(input => {
        if (!input.value.trim()) {
            input.classList.add('is-invalid');
            isValid = false;
        } else {
            input.classList.remove('is-invalid');
        }
    });

    return isValid;
}

// Function untuk preview gambar
function previewImage(input, previewId) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById(previewId).src = e.target.result;
        }
        reader.readAsDataURL(input.files[0]);
    }
}

// Function untuk tambah ke keranjang
function addToCart(productId, quantity = 1) {
    $.ajax({
        url: 'keranjang.php',
        method: 'POST',
        data: {
            action: 'add',
            id_produk: productId,
            jumlah: quantity
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // Update cart badge
                updateCartBadge();
                
                // Show success message
                showAlert('Produk berhasil ditambahkan ke keranjang!', 'success');
            } else {
                showAlert(response.message || 'Gagal menambahkan produk ke keranjang!', 'danger');
            }
        },
        error: function() {
            showAlert('Terjadi kesalahan sistem!', 'danger');
        }
    });
}

// Function untuk update badge keranjang
function updateCartBadge() {
    $.ajax({
        url: 'keranjang.php',
        method: 'POST',
        data: { action: 'count' },
        dataType: 'json',
        success: function(response) {
            const badge = document.querySelector('.cart-badge');
            if (response.count > 0) {
                if (badge) {
                    badge.textContent = response.count;
                } else {
                    // Create badge if doesn't exist
                    const cartLink = document.querySelector('a[href="keranjang.php"]');
                    if (cartLink) {
                        cartLink.innerHTML += `<span class="cart-badge">${response.count}</span>`;
                    }
                }
            } else if (badge) {
                badge.remove();
            }
        }
    });
}

// Function untuk show alert
function showAlert(message, type = 'info') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(alertDiv);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.remove();
        }
    }, 5000);
}

// Document ready
$(document).ready(function() {
    // Update cart badge on page load
    if (typeof updateCartBadge === 'function') {
        updateCartBadge();
    }
    
    // Auto hide alerts after 5 seconds
    $('.alert').delay(5000).fadeOut();
    
    // Format input harga
    $('input[name="harga"]').on('input', function() {
        let value = this.value.replace(/[^0-9]/g, '');
        this.value = formatRupiah(value, '');
    });
    
    // Confirmation for delete buttons
    $('.btn-delete').on('click', function(e) {
        if (!confirm('Apakah Anda yakin ingin menghapus data ini?')) {
            e.preventDefault();
        }
    });
    
    // Form validation on submit
    $('form[data-validate="true"]').on('submit', function(e) {
        if (!validateForm(this.id)) {
            e.preventDefault();
            showAlert('Mohon lengkapi semua field yang wajib diisi!', 'danger');
        }
    });
});

// Function untuk update quantity di keranjang
function updateQuantity(cartId, quantity) {
    if (quantity < 1) {
        if (confirm('Hapus produk dari keranjang?')) {
            removeFromCart(cartId);
        }
        return;
    }
    
    $.ajax({
        url: 'keranjang.php',
        method: 'POST',
        data: {
            action: 'update',
            id_keranjang: cartId,
            jumlah: quantity
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                location.reload();
            } else {
                showAlert(response.message || 'Gagal mengupdate quantity!', 'danger');
            }
        }
    });
}

// Function untuk hapus dari keranjang
function removeFromCart(cartId) {
    $.ajax({
        url: 'keranjang.php',
        method: 'POST',
        data: {
            action: 'remove',
            id_keranjang: cartId
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                location.reload();
            } else {
                showAlert(response.message || 'Gagal menghapus produk!', 'danger');
            }
        }
    });
}