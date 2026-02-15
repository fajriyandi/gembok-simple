<?php
/**
 * Customers Management
 */

require_once '../includes/auth.php';
requireAdminLogin();

$pageTitle = 'Pelanggan';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $data = [
                    'name' => sanitize($_POST['name']),
                    'phone' => sanitize($_POST['phone']),
                    'pppoe_username' => sanitize($_POST['pppoe_username']),
                    'package_id' => (int)$_POST['package_id'],
                    'isolation_date' => (int)$_POST['isolation_date'],
                    'address' => sanitize($_POST['address']),
                    'lat' => $_POST['lat'] ?? null,
                    'lng' => $_POST['lng'] ?? null,
                    'portal_password' => password_hash('1234', PASSWORD_DEFAULT),
                    'created_at' => date('Y-m-d H:i:s')
                ];
                
                if (insert('customers', $data)) {
                    setFlash('success', 'Pelanggan berhasil ditambahkan');
                    logActivity('ADD_CUSTOMER', "Name: {$data['name']}");
                } else {
                    setFlash('error', 'Gagal menambahkan pelanggan');
                }
                redirect('customers.php');
                break;
                
            case 'edit':
                $customerId = (int)$_POST['customer_id'];
                $data = [
                    'name' => sanitize($_POST['name']),
                    'phone' => sanitize($_POST['phone']),
                    'package_id' => (int)$_POST['package_id'],
                    'isolation_date' => (int)$_POST['isolation_date'],
                    'address' => sanitize($_POST['address']),
                    'lat' => $_POST['lat'] ?? null,
                    'lng' => $_POST['lng'] ?? null,
                    'updated_at' => date('Y-m-d H:i:s')
                ];
                
                if (update('customers', $data, 'id = ?', [$customerId])) {
                    setFlash('success', 'Pelanggan berhasil diperbarui');
                    logActivity('UPDATE_CUSTOMER', "ID: {$customerId}");
                } else {
                    setFlash('error', 'Gagal memperbarui pelanggan');
                }
                redirect('customers.php');
                break;
                
            case 'delete':
                $customerId = (int)$_POST['customer_id'];
                if (delete('customers', 'id = ?', [$customerId])) {
                    setFlash('success', 'Pelanggan berhasil dihapus');
                    logActivity('DELETE_CUSTOMER', "ID: {$customerId}");
                } else {
                    setFlash('error', 'Gagal menghapus pelanggan');
                }
                redirect('customers.php');
                break;
                
            case 'unisolate':
                $customerId = (int)$_POST['customer_id'];
                if (unisolateCustomer($customerId)) {
                    setFlash('success', 'Pelanggan berhasil di-unisolate');
                } else {
                    setFlash('error', 'Gagal meng-unisolate pelanggan');
                }
                redirect('customers.php');
                break;
        }
    }
}

// Get data with pagination
$page = (int)($_GET['page'] ?? 1);
$perPage = ITEMS_PER_PAGE;
$offset = ($page - 1) * $perPage;

$totalCustomers = fetchOne("SELECT COUNT(*) as total FROM customers")['total'] ?? 0;
$totalPages = ceil($totalCustomers / $perPage);

$customers = fetchAll("
    SELECT c.*, p.name as package_name, p.price as package_price 
    FROM customers c 
    LEFT JOIN packages p ON c.package_id = p.id 
    ORDER BY c.created_at DESC
    LIMIT {$perPage} OFFSET {$offset}
");

$packages = fetchAll("SELECT * FROM packages ORDER BY name");

ob_start();
?>

<!-- Stats -->
<div class="stats-grid" style="grid-template-columns: repeat(4, 1fr); margin-bottom: 30px;">
    <div class="stat-card">
        <div class="stat-icon cyan">
            <i class="fas fa-users"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo count($customers); ?></h3>
            <p>Total Pelanggan</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon green">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo count(array_filter($customers, fn($c) => $c['status'] === 'active')); ?></h3>
            <p>Aktif</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon orange">
            <i class="fas fa-ban"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo count(array_filter($customers, fn($c) => $c['status'] === 'isolated')); ?></h3>
            <p>Isolir</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon purple">
            <i class="fas fa-wallet"></i>
        </div>
        <div class="stat-info">
            <?php 
            $totalRevenue = 0;
            foreach ($customers as $c) {
                if ($c['status'] === 'active') {
                    $totalRevenue += $c['package_price'] ?? 0;
                }
            }
            ?>
            <h3><?php echo formatCurrency($totalRevenue); ?></h3>
            <p>Estimasi Pendapatan</p>
        </div>
    </div>
</div>

<!-- Add Customer Form -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-user-plus"></i> Tambah Pelanggan</h3>
    </div>
    
    <form method="POST">
        <input type="hidden" name="action" value="add">
        
        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px;">
            <div class="form-group">
                <label class="form-label">Nama Pelanggan</label>
                <input type="text" name="name" class="form-control" required placeholder="Nama Lengkap">
            </div>
            
            <div class="form-group">
                <label class="form-label">Nomor HP (WhatsApp)</label>
                <input type="text" name="phone" class="form-control" required placeholder="08xxxxxxxxxx">
            </div>
            
            <div class="form-group">
                <label class="form-label">Username PPPoE</label>
                <input type="text" name="pppoe_username" class="form-control" required placeholder="Username di MikroTik">
            </div>
            
            <div class="form-group">
                <label class="form-label">Paket Langganan</label>
                <select name="package_id" class="form-control" required>
                    <option value="">Pilih Paket</option>
                    <?php foreach ($packages as $pkg): ?>
                        <option value="<?php echo $pkg['id']; ?>">
                            <?php echo htmlspecialchars($pkg['name']); ?> (<?php echo formatCurrency($pkg['price']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label">Tanggal Isolir (1-28)</label>
                <input type="number" name="isolation_date" class="form-control" value="20" min="1" max="28" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">Alamat</label>
                <textarea name="address" class="form-control" rows="2" placeholder="Alamat rumah"></textarea>
            </div>
        </div>
        
        <div class="form-group">
            <label class="form-label">Lokasi (Latitude, Longitude)</label>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                <input type="text" name="lat" class="form-control" placeholder="Latitude" readonly>
                <input type="text" name="lng" class="form-control" placeholder="Longitude" readonly>
            </div>
            <small style="color: var(--text-muted);">Klik pada peta untuk set lokasi</small>
        </div>
        
        <div style="height: 300px; margin-top: 15px; border-radius: 8px; overflow: hidden;" id="map-picker"></div>
        
        <button type="submit" class="btn btn-primary" style="margin-top: 20px;">
            <i class="fas fa-save"></i> Simpan Pelanggan
        </button>
    </form>
</div>

<!-- Customers Table -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-users"></i> Daftar Pelanggan</h3>
        <div style="display: flex; gap: 10px;">
            <input type="text" id="searchCustomer" class="form-control" placeholder="Cari pelanggan..." style="width: 250px;">
            <a href="export.php" class="btn btn-primary btn-sm">
                <i class="fas fa-file-excel"></i> Export/Import
            </a>
        </div>
    </div>
    
    <table class="data-table" id="customerTable">
        <thead>
            <tr>
                <th>ID</th>
                <th>Nama & Kontak</th>
                <th>Paket & Tagihan</th>
                <th>Status</th>
                <th>PPPoE</th>
                <th>Tgl Isolir</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($customers)): ?>
                <tr>
                    <td colspan="8" style="text-align: center; color: var(--text-muted); padding: 30px;" data-label="Data">
                        Belum ada data pelanggan
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($customers as $c): ?>
                <tr>
                    <td data-label="ID">#<?php echo $c['id']; ?></td>
                    <td data-label="Nama & Kontak">
                        <strong><?php echo htmlspecialchars($c['name']); ?></strong><br>
                        <small><i class="fab fa-whatsapp"></i> <?php echo htmlspecialchars($c['phone']); ?></small>
                    </td>
                    <td data-label="Paket & Tagihan">
                        <?php echo htmlspecialchars($c['package_name'] ?? 'Tanpa Paket'); ?><br>
                        <small style="color: var(--neon-green);">
                            <?php echo formatCurrency($c['package_price'] ?? 0); ?>
                        </small>
                    </td>
                    <td data-label="Status">
                        <?php if ($c['status'] === 'active'): ?>
                            <span class="badge badge-success">Aktif</span>
                        <?php else: ?>
                            <span class="badge badge-warning">Isolir</span>
                        <?php endif; ?>
                    </td>
                    <td data-label="PPPoE">
                        <code style="background: rgba(255,255,255,0.1); padding: 2px 4px; border-radius: 4px;">
                            <?php echo htmlspecialchars($c['pppoe_username']); ?>
                        </code>
                    </td>
                    <td data-label="Tgl Isolir">
                        <span class="badge badge-info">Tgl <?php echo $c['isolation_date']; ?></span>
                    </td>
                    <td data-label="Aksi">
                        <button class="btn btn-secondary btn-sm" onclick="editCustomer(<?php echo $c['id']; ?>)">
                            <i class="fas fa-edit"></i>
                        </button>
                        <?php if ($c['status'] === 'isolated'): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="unisolate">
                                <input type="hidden" name="customer_id" value="<?php echo $c['id']; ?>">
                                <button type="submit" class="btn btn-success btn-sm" title="Buka Isolir">
                                    <i class="fas fa-unlock"></i>
                                </button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    
    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div style="display: flex; justify-content: center; align-items: center; gap: 10px; margin-top: 20px;">
        <a href="?page=1" class="btn btn-secondary btn-sm" <?php echo $page === 1 ? 'disabled style="opacity: 0.5;"' : ''; ?>>
            <i class="fas fa-angle-double-left"></i>
        </a>
        <a href="?page=<?php echo max(1, $page - 1); ?>" class="btn btn-secondary btn-sm" <?php echo $page === 1 ? 'disabled style="opacity: 0.5;"' : ''; ?>>
            <i class="fas fa-angle-left"></i>
        </a>
        
        <span style="color: var(--text-secondary);">
            Halaman <?php echo $page; ?> dari <?php echo $totalPages; ?>
            (Total: <?php echo $totalCustomers; ?> pelanggan)
        </span>
        
        <a href="?page=<?php echo min($totalPages, $page + 1); ?>" class="btn btn-secondary btn-sm" <?php echo $page === $totalPages ? 'disabled style="opacity: 0.5;"' : ''; ?>>
            <i class="fas fa-angle-right"></i>
        </a>
        <a href="?page=<?php echo $totalPages; ?>" class="btn btn-secondary btn-sm" <?php echo $page === $totalPages ? 'disabled style="opacity: 0.5;"' : ''; ?>>
            <i class="fas fa-angle-double-right"></i>
        </a>
    </div>
    <?php endif; ?>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

<script>
// Initialize map
let map, marker;

function initMap() {
    map = L.map('map-picker').setView([-6.200000, 106.816666], 13);
    
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap'
    }).addTo(map);
    
    map.on('click', function(e) {
        if (marker) {
            map.removeLayer(marker);
        }
        
        marker = L.marker(e.latlng).addTo(map);
        
        document.querySelector('input[name="lat"]').value = e.latlng.lat.toFixed(6);
        document.querySelector('input[name="lng"]').value = e.latlng.lng.toFixed(6);
    });
}

// Search functionality
document.getElementById('searchCustomer').addEventListener('input', function(e) {
    const search = e.target.value.toLowerCase();
    const rows = document.querySelectorAll('#customerTable tbody tr');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(search) ? '' : 'none';
    });
});

// Edit customer (placeholder)
function editCustomer(id) {
    alert('Edit pelanggan #' + id + '\n\nFitur edit akan segera tersedia.');
}

// Initialize map when page loads
setTimeout(initMap, 500);
</script>

<?php
$content = ob_get_clean();
require_once '../includes/layout.php';
