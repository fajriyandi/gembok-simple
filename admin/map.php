<?php
/**
 * Map - ONU Location Management with GenieACS Integration
 */

require_once '../includes/auth.php';
requireAdminLogin();

$pageTitle = 'Peta ONU';

// Get ONU locations
$onuLocations = fetchAll("SELECT * FROM onu_locations ORDER BY name");

// Calculate stats
$totalOnu = count($onuLocations);
$onlineOnu = 0;
$offlineOnu = 0;

// Get GenieACS device info for each ONU
$onuData = [];
foreach ($onuLocations as $onu) {
    $deviceInfo = genieacsGetDeviceInfo($onu['serial_number']);
    
    $onuItem = [
        'id' => $onu['id'],
        'name' => $onu['name'],
        'serial_number' => $onu['serial_number'],
        'lat' => $onu['lat'],
        'lng' => $onu['lng'],
        'status' => 'unknown',
        'device_info' => null
    ];
    
    if ($deviceInfo) {
        $onuItem['status'] = $deviceInfo['status'];
        $onuItem['device_info'] = $deviceInfo;
        
        if ($deviceInfo['status'] === 'online') {
            $onlineOnu++;
        } else {
            $offlineOnu++;
        }
    } else {
        $offlineOnu++;
    }
    
    $onuData[] = $onuItem;
}

ob_start();
?>

<!-- Stats -->
<div class="stats-grid" style="grid-template-columns: repeat(3, 1fr); margin-bottom: 30px;">
    <div class="stat-card">
        <div class="stat-icon cyan">
            <i class="fas fa-satellite-dish"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $totalOnu; ?></h3>
            <p>Total ONU</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon green">
            <i class="fas fa-wifi"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $onlineOnu; ?></h3>
            <p>Online</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon orange">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $offlineOnu; ?></h3>
            <p>Offline</p>
        </div>
    </div>
</div>

<!-- Map Card -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-map-marked-alt"></i> Lokasi ONU</h3>
        <div style="display: flex; gap: 10px;">
            <button class="btn btn-secondary btn-sm" onclick="location.reload()">
                <i class="fas fa-redo"></i> Reload
            </button>
            <button class="btn btn-secondary btn-sm" id="toggleLayer">
                <i class="fas fa-layer-group"></i> Satellite
            </button>
            <button class="btn btn-secondary btn-sm" onclick="resetMap()">
                <i class="fas fa-crosshairs"></i> Reset
            </button>
            <button class="btn btn-primary btn-sm" onclick="loadMarkers()">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>
    
    <div id="mapContainer" style="position: relative;">
        <div id="map" style="height: 500px;"></div>
    </div>
    
    <p style="margin-top: 10px; color: var(--text-muted); font-size: 0.85rem;">
        💡 <strong>Tip:</strong> Klik marker untuk melihat detail ONU, edit WiFi SSID & Password, atau reboot ONU.
    </p>
</div>

<!-- ONU List -->
<div class="card" style="margin-top: 20px;">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-list"></i> ONU Terdaftar (<?php echo $totalOnu; ?>)</h3>
        <input type="text" id="onuSearch" class="form-control" placeholder="Cari ONU..." style="width: 200px;">
    </div>
    
    <table class="data-table">
        <thead>
            <tr>
                <th>Nama</th>
                <th>Serial Number</th>
                <th>Model</th>
                <th>IP Address</th>
                <th>SSID</th>
                <th>RX/TX Power</th>
                <th>Status</th>
                <th>Last Inform</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($onuData)): ?>
                <tr>
                    <td colspan="8" style="text-align: center; color: var(--text-muted); padding: 30px;">
                        Belum ada ONU terdaftar
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($onuData as $onu): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($onu['name']); ?></strong></td>
                    <td><code><?php echo htmlspecialchars($onu['serial_number']); ?></code></td>
                    <td>
                        <?php 
                        if ($onu['device_info']) {
                            $model = trim(($onu['device_info']['manufacturer'] ?? '') . ' ' . ($onu['device_info']['model'] ?? ''));
                            echo htmlspecialchars($model ?: '-');
                        } else {
                            echo '<span style="color: var(--text-muted);">-</span>';
                        }
                        ?>
                    </td>
                    <td>
                        <?php 
                        if ($onu['device_info'] && $onu['device_info']['ip_address']) {
                            echo '<code style="background: rgba(0, 245, 255, 0.1); padding: 2px 6px; border-radius: 4px;">' . htmlspecialchars($onu['device_info']['ip_address']) . '</code>';
                        } else {
                            echo '<span style="color: var(--text-muted);">-</span>';
                        }
                        ?>
                    </td>
                    <td>
                        <?php 
                        if ($onu['device_info'] && $onu['device_info']['ssid']) {
                            echo '<span style="color: var(--neon-green);"><i class="fas fa-wifi"></i> ' . htmlspecialchars($onu['device_info']['ssid']) . '</span>';
                        } else {
                            echo '<span style="color: var(--text-muted);">-</span>';
                        }
                        ?>
                    </td>
                    <td>
                        <?php 
                        if ($onu['device_info']) {
                            $rx = $onu['device_info']['rx_power'];
                            $tx = $onu['device_info']['tx_power'];
                            if ($rx || $tx) {
                                $rxColor = $rx > -25 ? 'var(--neon-green)' : ($rx > -30 ? 'orange' : 'var(--danger)');
                                echo '<span style="color: ' . $rxColor . ';">RX: ' . htmlspecialchars($rx ?? '-') . ' dBm</span><br>';
                                echo '<span style="color: var(--text-secondary);">TX: ' . htmlspecialchars($tx ?? '-') . ' dBm</span>';
                            } else {
                                echo '<span style="color: var(--text-muted);">-</span>';
                            }
                        } else {
                            echo '<span style="color: var(--text-muted);">-</span>';
                        }
                        ?>
                    </td>
                    <td>
                        <?php 
                        if ($onu['status'] === 'online') {
                            echo '<span class="badge badge-success"><i class="fas fa-circle" style="font-size: 8px; margin-right: 4px;"></i>Online</span>';
                        } elseif ($onu['status'] === 'offline') {
                            echo '<span class="badge badge-danger"><i class="fas fa-circle" style="font-size: 8px; margin-right: 4px;"></i>Offline</span>';
                        } else {
                            echo '<span class="badge badge-warning"><i class="fas fa-question" style="font-size: 8px; margin-right: 4px;"></i>Unknown</span>';
                        }
                        ?>
                    </td>
                    <td>
                        <?php 
                        if ($onu['device_info'] && $onu['device_info']['last_inform']) {
                            $lastInform = strtotime($onu['device_info']['last_inform']);
                            $diff = time() - $lastInform;
                            if ($diff < 60) {
                                echo '<span style="color: var(--neon-green);">' . $diff . ' detik lalu</span>';
                            } elseif ($diff < 3600) {
                                echo '<span style="color: var(--neon-green);">' . floor($diff / 60) . ' menit lalu</span>';
                            } elseif ($diff < 86400) {
                                echo '<span style="color: var(--text-secondary);">' . floor($diff / 3600) . ' jam lalu</span>';
                            } else {
                                echo '<span style="color: var(--text-muted);">' . date('d/m/Y H:i', $lastInform) . '</span>';
                            }
                        } else {
                            echo '<span style="color: var(--text-muted);">-</span>';
                        }
                        ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Edit ONU Modal -->
<div id="onuModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.8); z-index: 2000; align-items: center; justify-content: center;">
    <div class="card" style="width: 450px; max-width: 90%; margin: 2rem; max-height: 90vh; overflow-y: auto;">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-satellite-dish"></i> Detail ONU</h3>
            <button onclick="closeOnuModal()" style="background: none; border: none; color: var(--text-secondary); cursor: pointer; font-size: 1.25rem;">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div id="onuDetails" style="margin-bottom: 15px;">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                <div>
                    <p><strong>Nama:</strong></p>
                    <p id="modalName" style="color: var(--neon-cyan);">-</p>
                </div>
                <div>
                    <p><strong>Serial:</strong></p>
                    <p><code id="modalSerial" style="background: rgba(0, 245, 255, 0.1); padding: 2px 4px; border-radius: 4px; color: var(--neon-cyan);">-</code></p>
                </div>
                <div>
                    <p><strong>Status:</strong></p>
                    <p id="modalStatus">-</p>
                </div>
                <div>
                    <p><strong>Last Inform:</strong></p>
                    <p id="modalLastInform" style="color: var(--text-secondary);">-</p>
                </div>
                <div>
                    <p><strong>Model:</strong></p>
                    <p id="modalModel" style="color: var(--text-secondary);">-</p>
                </div>
                <div>
                    <p><strong>IP Address:</strong></p>
                    <p id="modalIP" style="color: var(--neon-cyan);">-</p>
                </div>
                <div>
                    <p><strong>RX Power:</strong></p>
                    <p id="modalRxPower">-</p>
                </div>
                <div>
                    <p><strong>TX Power:</strong></p>
                    <p id="modalTxPower" style="color: var(--text-secondary);">-</p>
                </div>
            </div>
        </div>
        
        <hr style="border-color: var(--border-color); margin: 15px 0;">
        
        <h4 style="color: var(--neon-green); margin-bottom: 10px;"><i class="fas fa-wifi"></i> WiFi Settings</h4>
        
        <div class="form-group">
            <label class="form-label">SSID WiFi</label>
            <input type="text" id="wifiSsid" class="form-control" placeholder="Masukkan SSID baru">
        </div>
        
        <div class="form-group">
            <label class="form-label">Password WiFi</label>
            <div style="display: flex; gap: 10px;">
                <input type="password" id="wifiPassword" class="form-control" placeholder="Masukkan password baru">
                <button type="button" class="btn btn-secondary" onclick="togglePassword()" style="padding: 10px 15px;">
                    <i class="fas fa-eye" id="passwordToggleIcon"></i>
                </button>
            </div>
        </div>
        
        <div style="display: flex; gap: 10px; margin-top: 15px; flex-wrap: wrap;">
            <button type="button" class="btn btn-secondary" onclick="closeOnuModal()">Batal</button>
            <button type="button" class="btn btn-primary" onclick="saveWifiSettings()">
                <i class="fas fa-save"></i> Simpan WiFi
            </button>
            <button type="button" class="btn btn-danger" onclick="rebootOnu()">
                <i class="fas fa-redo"></i> Reboot
            </button>
        </div>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

<script>
let map, markers = [];
let currentLayer = 'osm';
let osmLayer, satelliteLayer;
let currentOnuSerial = null;

function initMap() {
    map = L.map('map').setView([-6.200000, 106.816666], 13);
    
    osmLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap'
    });
    
    satelliteLayer = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
        attribution: 'Tiles © Esri'
    });
    
    osmLayer.addTo(map);
    
    loadMarkers();
}

function loadMarkers() {
    // Clear existing markers
    markers.forEach(marker => map.removeLayer(marker));
    markers = [];
    
    // Fetch ONU locations from API
    fetch('<?php echo APP_URL; ?>/api/onu_locations.php')
        .then(response => response.json())
        .then(result => {
            if (result.success && result.data) {
                result.data.forEach(onu => {
                    const isOnline = onu.status === 'online';
                    const marker = L.marker([onu.lat, onu.lng], {
                        icon: L.divIcon({
                            className: 'custom-marker',
                            html: '<div style="background: ' + (isOnline ? '#00ff88' : '#ff4757') + '; width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 12px; border: 2px solid #fff; box-shadow: 0 2px 5px rgba(0,0,0,0.3);"><i class="fas fa-satellite-dish"></i></div>'
                        })
                    });
                    
                    marker.on('click', function() {
                        showOnuDetails(onu);
                    });
                    
                    markers.push(marker);
                    marker.addTo(map);
                });
            }
        });
}

function showOnuDetails(onu) {
    currentOnuSerial = onu.serial_number || onu.serial;
    
    document.getElementById('modalName').textContent = onu.name || '-';
    document.getElementById('modalSerial').textContent = currentOnuSerial;
    
    // Status badge
    const statusEl = document.getElementById('modalStatus');
    if (onu.status === 'online') {
        statusEl.innerHTML = '<span class="badge badge-success"><i class="fas fa-circle" style="font-size: 8px; margin-right: 4px;"></i>Online</span>';
    } else if (onu.status === 'offline') {
        statusEl.innerHTML = '<span class="badge badge-danger"><i class="fas fa-circle" style="font-size: 8px; margin-right: 4px;"></i>Offline</span>';
    } else {
        statusEl.innerHTML = '<span class="badge badge-warning"><i class="fas fa-question" style="font-size: 8px; margin-right: 4px;"></i>Unknown</span>';
    }
    
    // Device info
    const info = onu.device_info || {};
    
    document.getElementById('modalLastInform').textContent = info.last_inform ? formatTimeAgo(info.last_inform) : '-';
    document.getElementById('modalModel').textContent = (info.manufacturer ? info.manufacturer + ' ' : '') + (info.model || '-');
    document.getElementById('modalIP').textContent = info.ip_address || '-';
    
    // RX/TX Power with color coding
    const rxPowerEl = document.getElementById('modalRxPower');
    if (info.rx_power) {
        const rxValue = parseFloat(info.rx_power);
        let color = 'var(--neon-green)';
        if (rxValue < -27) color = 'var(--danger)';
        else if (rxValue < -25) color = 'orange';
        rxPowerEl.innerHTML = '<span style="color: ' + color + ';">' + info.rx_power + ' dBm</span>';
    } else {
        rxPowerEl.textContent = '-';
    }
    
    document.getElementById('modalTxPower').textContent = info.tx_power ? info.tx_power + ' dBm' : '-';
    
    // WiFi settings
    document.getElementById('wifiSsid').value = info.ssid || onu.ssid || '';
    document.getElementById('wifiPassword').value = info.wifi_password || onu.password || '';
    
    document.getElementById('onuModal').style.display = 'flex';
}

function formatTimeAgo(dateStr) {
    const date = new Date(dateStr);
    const now = new Date();
    const diff = Math.floor((now - date) / 1000);
    
    if (diff < 60) return diff + ' detik lalu';
    if (diff < 3600) return Math.floor(diff / 60) + ' menit lalu';
    if (diff < 86400) return Math.floor(diff / 3600) + ' jam lalu';
    return date.toLocaleDateString('id-ID') + ' ' + date.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
}

function closeOnuModal() {
    document.getElementById('onuModal').style.display = 'none';
    currentOnuSerial = null;
}

function togglePassword() {
    const input = document.getElementById('wifiPassword');
    const icon = document.getElementById('passwordToggleIcon');
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'fas fa-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'fas fa-eye';
    }
}

function saveWifiSettings() {
    const serial = currentOnuSerial;
    const ssid = document.getElementById('wifiSsid').value;
    const password = document.getElementById('wifiPassword').value;
    
    if (ssid && ssid.length < 3) {
        alert('SSID minimal 3 karakter');
        return;
    }
    
    if (password && password.length < 8) {
        alert('Password minimal 8 karakter');
        return;
    }
    
    // Call API to update WiFi settings
    fetch('<?php echo APP_URL; ?>/api/onu_wifi.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ serial, ssid, password })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('WiFi berhasil diperbarui');
            closeOnuModal();
            loadMarkers();
        } else {
            alert('Gagal memperbarui WiFi: ' + data.message);
        }
    })
    .catch(error => {
        alert('Terjadi kesalahan: ' + error.message);
    });
}

function rebootOnu() {
    if (!confirm('Yakin ingin reboot ONU ini?')) return;
    
    const serial = currentOnuSerial;
    
    fetch('<?php echo APP_URL; ?>/api/genieacs.php?action=reboot', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ serial })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Reboot berhasil dijalankan');
            closeOnuModal();
        } else {
            alert('Gagal reboot: ' + data.message);
        }
    })
    .catch(error => {
        alert('Terjadi kesalahan: ' + error.message);
    });
}

function toggleLayer() {
    if (currentLayer === 'osm') {
        map.removeLayer(osmLayer);
        satelliteLayer.addTo(map);
        currentLayer = 'satellite';
        document.getElementById('toggleLayer').innerHTML = '<i class="fas fa-layer-group"></i> Street';
    } else {
        map.removeLayer(satelliteLayer);
        osmLayer.addTo(map);
        currentLayer = 'osm';
        document.getElementById('toggleLayer').innerHTML = '<i class="fas fa-layer-group"></i> Satellite';
    }
}

function resetMap() {
    map.setView([-6.200000, 106.816666], 13);
}

document.getElementById('onuSearch').addEventListener('input', function(e) {
    const search = e.target.value.toLowerCase();
    const rows = document.querySelectorAll('.data-table tbody tr');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(search) ? '' : 'none';
    });
});

document.getElementById('onuModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeOnuModal();
    }
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeOnuModal();
    }
});

// Initialize map when page loads
setTimeout(initMap, 500);
</script>

<?php
$content = ob_get_clean();
require_once '../includes/layout.php';
