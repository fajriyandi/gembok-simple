<?php
/**
 * PPPoE Profile Management
 */

require_once '../includes/auth.php';
requireAdminLogin();

$pageTitle = 'PPPoE Profiles';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $name = sanitize($_POST['name'] ?? '');
        $rate = sanitize($_POST['rate_limit'] ?? '');
        $local = sanitize($_POST['local_address'] ?? '');
        $pool = sanitize($_POST['remote_pool'] ?? 'none');
        $dns = sanitize($_POST['dns_server'] ?? '');

        $data = [
            'name' => $name
        ];
        if ($rate !== '') {
            $data['rate-limit'] = $rate;
        }
        if ($local !== '') {
            $data['local-address'] = $local;
        }
        if ($pool !== '' && $pool !== 'none') {
            $data['remote-address'] = $pool;
        }
        if ($dns !== '') {
            $data['dns-server'] = $dns;
        }

        if ($action === 'add') {
            if (mikrotikAddPppoeProfile($data)) {
                setFlash('success', "Profile {$name} berhasil ditambahkan.");
            } else {
                setFlash('error', "Gagal menambahkan profile.");
            }
        } else {
            $id = $_POST['id'] ?? '';
            if (mikrotikUpdatePppoeProfile($id, $data)) {
                setFlash('success', "Profile {$name} berhasil diperbarui.");
            } else {
                setFlash('error', "Gagal memperbarui profile.");
            }
        }
        redirect('pppoe-profile.php');
    }

    if ($action === 'delete') {
        $id = $_POST['id'] ?? '';
        if (mikrotikDeletePppoeProfile($id)) {
            setFlash('success', "Profile berhasil dihapus.");
        } else {
            setFlash('error', "Gagal menghapus profile.");
        }
        redirect('pppoe-profile.php');
    }
}

$profiles = mikrotikGetProfiles();
$addressPools = mikrotikGetAddressPools();

ob_start();
?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-plus-circle"></i> Tambah/Edit Profile</h3>
    </div>
    <form method="POST" id="profileForm">
        <input type="hidden" name="action" value="add" id="formAction">
        <input type="hidden" name="id" id="profileId">

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px;">
            <div class="form-group">
                <label class="form-label">Name</label>
                <input type="text" name="name" id="pName" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label">Rate Limit (e.g. 10M/10M)</label>
                <input type="text" name="rate_limit" id="pRate" class="form-control" placeholder="10M/10M">
            </div>
            <div class="form-group">
                <label class="form-label">Local Address (optional)</label>
                <input type="text" name="local_address" id="pLocal" class="form-control" placeholder="10.10.10.1">
            </div>
            <div class="form-group">
                <label class="form-label">Remote Address Pool (optional)</label>
                <select name="remote_pool" id="pPool" class="form-control">
                    <option value="none">none</option>
                    <?php foreach ($addressPools as $pool): ?>
                        <option value="<?php echo htmlspecialchars($pool['name']); ?>">
                            <?php echo htmlspecialchars($pool['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">DNS Server (optional)</label>
                <input type="text" name="dns_server" id="pDns" class="form-control" placeholder="1.1.1.1,8.8.8.8">
            </div>
        </div>

        <div style="display: flex; gap: 10px; margin-top: 10px;">
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan Profile</button>
            <button type="button" class="btn btn-secondary" onclick="resetForm()">Reset</button>
        </div>
    </form>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-list"></i> Daftar PPPoE Profile</h3>
    </div>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Rate Limit</th>
                    <th>Local</th>
                    <th>Remote Pool</th>
                    <th>DNS</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($profiles as $p): ?>
                    <tr>
                        <td data-label="Name"><strong><?php echo htmlspecialchars($p['name'] ?? ''); ?></strong></td>
                        <td data-label="Rate Limit"><?php echo htmlspecialchars($p['rate-limit'] ?? ''); ?></td>
                        <td data-label="Local"><?php echo htmlspecialchars($p['local-address'] ?? ''); ?></td>
                        <td data-label="Remote Pool"><?php echo htmlspecialchars($p['remote-address'] ?? ''); ?></td>
                        <td data-label="DNS"><?php echo htmlspecialchars($p['dns-server'] ?? ''); ?></td>
                        <td data-label="Aksi">
                            <div style="display: flex; gap: 5px;">
                                <button onclick='editProfile(<?php echo json_encode($p); ?>)'
                                    class="btn btn-secondary btn-sm"><i class="fas fa-edit"></i></button>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Hapus profile ini?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($p['.id'] ?? ''); ?>">
                                    <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    function editProfile(p) {
        document.getElementById('formAction').value = 'edit';
        document.getElementById('profileId').value = p['.id'] || '';
        document.getElementById('pName').value = p['name'] || '';
        document.getElementById('pRate').value = p['rate-limit'] || '';
        document.getElementById('pLocal').value = p['local-address'] || '';
        document.getElementById('pPool').value = p['remote-address'] || 'none';
        document.getElementById('pDns').value = p['dns-server'] || '';
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function resetForm() {
        document.getElementById('profileForm').reset();
        document.getElementById('formAction').value = 'add';
        document.getElementById('profileId').value = '';
    }
</script>

<?php
$content = ob_get_clean();
require_once '../includes/layout.php';

