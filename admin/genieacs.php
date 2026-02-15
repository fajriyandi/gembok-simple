<?php
/**
 * GenieACS Device Management
 */

require_once '../includes/auth.php';
requireAdminLogin();

$pageTitle = 'GenieACS';

// Get devices from GenieACS
$devices = genieacsGetDevices();
$totalDevices = count($devices);

// Calculate stats
$onlineCount = 0;
$offlineCount = 0;
$weakSignalCount = 0;

foreach ($devices as $device) {
    $lastInform = $device['_lastInform'] ?? null;
    if ($lastInform && (time() - strtotime($lastInform)) < 300) {
        $onlineCount++;
    } else {
        $offlineCount++;
    }
    
    $rxPower = $device['VirtualParameters']['RXPower'] ?? 0;
    if ($rxPower < -25 && $rxPower != 0) {
        $weakSignalCount++;
    }
}

ob_start();
?>

<!-- Stats -->
<div class="stats-grid" style="grid-template-columns: repeat(4, 1fr); margin-bottom: 30px;">
    <div class="stat-card">
        <div class="stat-icon cyan">
            <i class="fas fa-satellite-dish"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $totalDevices; ?></h3>
            <p>Total Device</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon green">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $onlineCount; ?></h3>
            <p>Online</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon orange">
            <i class="fas fa-times-circle"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $offlineCount; ?></h3>
            <p>Offline</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon purple">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $weakSignalCount; ?></h3>
            <p>Signal Lemah</p>
        </div>
    </div>
</div>

<!-- Connection Status -->
<?php if (!empty(GENIEACS_URL)): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        GenieACS Connected: <?php echo GENIEACS_URL; ?>
    </div>
<?php else: ?>
    <div class="alert alert-error">
        <i class="fas fa-exclamation-circle"></i>
        GenieACS tidak terkonfigurasi. Silakan setup di Settings.
    </div>
<?php endif; ?>

<!-- Devices Table -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-server"></i> Daftar Device ONU</h3>
        <div style="display: flex; gap: 10px;">
            <input type="text" id="searchDevice" class="form-control" placeholder="Cari device..." style="width: 250px;">
            <button class="btn btn-primary btn-sm" onclick="loadDevices()">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>
    
    <table class="data-table">
        <thead>
            <tr>
                <th>Serial Number</th>
                <th>Model</th>
                <th>Manufacturer</th>
                <th>Status</th>
                <th>Signal (dBm)</th>
                <th>SSID</th>
                <th>Last Inform</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($devices)): ?>
                <tr>
                    <td colspan="8" style="text-align: center; color: var(--text-muted); padding: 30px;">
                        <i class="fas fa-server" style="font-size: 2rem; margin-bottom: 10px; display: block;"></i>
                        Tidak ada device ditemukan atau GenieACS tidak terkoneksi
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($devices as $device): ?>
                <tr>
                    <td>
                        <code style="color: var(--neon-cyan);">
                            <?php echo htmlspecialchars($device['_deviceId']['_SerialNumber'] ?? '-'); ?>
                        </code>
                    </td>
                    <td><?php echo htmlspecialchars($device['InternetGatewayDevice']['DeviceInfo']['ModelName'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($device['InternetGatewayDevice']['DeviceInfo']['Manufacturer'] ?? 'N/A'); ?></td>
                    <td>
                        <?php 
                        $lastInform = $device['_lastInform'] ?? null;
                        if ($lastInform && (time() - strtotime($lastInform)) < 300): ?>
                            <span class="badge badge-success">Online</span>
                        <?php else: ?>
                            <span class="badge badge-danger">Offline</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo $device['VirtualParameters']['RXPower'] ?? 'N/A'; ?></td>
                    <td><?php echo htmlspecialchars($device['InternetGatewayDevice']['LANDevice.1.WLANConfiguration.1.SSID'] ?? '-'); ?></td>
                    <td><?php echo $lastInform ? formatDate($lastInform, 'd M Y H:i') : 'Never'; ?></td>
                    <td>
                        <button class="btn btn-secondary btn-sm" onclick="rebootDevice('<?php echo htmlspecialchars($device['_deviceId']['_SerialNumber'] ?? ''); ?>')">
                            <i class="fas fa-redo"></i> Reboot
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
function loadDevices() {
    location.reload();
}

function rebootDevice(serial) {
    if (!confirm('Reboot device ' + serial + '?')) {
        return;
    }
    
    fetch('<?php echo APP_URL; ?>/api/genieacs.php?action=reboot', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ serial: serial })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Reboot berhasil dijalankan untuk device ' + serial);
        } else {
            alert('Gagal reboot: ' + data.message);
        }
    })
    .catch(error => {
        alert('Terjadi kesalahan: ' + error.message);
    });
}

document.getElementById('searchDevice').addEventListener('input', function(e) {
    const search = e.target.value.toLowerCase();
    const rows = document.querySelectorAll('.data-table tbody tr');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(search) ? '' : 'none';
    });
});
</script>

<?php
$content = ob_get_clean();
require_once '../includes/layout.php';
