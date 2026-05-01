<?php
session_start();
require_once '../../config/database.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header("Location: ../../login.php"); exit; }

$search_query  = $_GET['search'] ?? '';
$filter_role   = $_GET['role'] ?? '';
$filter_class  = $_GET['class_id'] ?? '';
$filter_gender = $_GET['gender'] ?? '';
$filter_subject= $_GET['subject_id'] ?? '';

$classes  = $pdo->query("SELECT * FROM classes ORDER BY grade_level, LENGTH(name), name")->fetchAll();
$subjects = $pdo->query("SELECT * FROM subjects ORDER BY name")->fetchAll();

// Handle Delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $u  = $pdo->prepare("SELECT role, username FROM users WHERE id=?"); $u->execute([$id]); $check = $u->fetch();
    if ($check && strtolower($check['role']) !== 'admin' && strtolower($check['username']) !== 'admin') {
        $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
        header("Location: manage_users.php?msg=deleted"); exit;
    }
    header("Location: manage_users.php?msg=cannot_delete_admin"); exit;
}

// Handle Bulk Delete
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='delete_all') {
    $role_del  = $_POST['role_to_delete'] ?? '';
    $class_del = $_POST['class_to_delete'] ?? '';
    if (in_array($role_del, ['siswa','guru'])) {
        if ($role_del==='siswa' && $class_del) {
            $pdo->prepare("DELETE FROM users WHERE role='siswa' AND class_id=? AND username!='admin'")->execute([$class_del]);
            header("Location: manage_users.php?msg=siswa_class_deleted");
        } else {
            $pdo->prepare("DELETE FROM users WHERE role=? AND username!='admin' AND role!='admin'")->execute([$role_del]);
            header("Location: manage_users.php?msg={$role_del}_deleted");
        }
        exit;
    }
}

// Fetch users
$sql = "SELECT users.*, classes.name as class_name, subjects.name as subject_name
        FROM users LEFT JOIN classes ON users.class_id=classes.id
        LEFT JOIN subjects ON users.subject_id=subjects.id WHERE 1=1";
$params = [];
if ($search_query) { $sql.=" AND (users.full_name LIKE ? OR users.username LIKE ? OR users.nip LIKE ? OR users.nis LIKE ?)"; $p="%$search_query%"; $params=array_merge($params,[$p,$p,$p,$p]); }
if ($filter_role)    { $sql.=" AND users.role=?";       $params[]=$filter_role; }
if ($filter_class)   { $sql.=" AND users.class_id=?";   $params[]=$filter_class; }
if ($filter_gender)  { $sql.=" AND users.gender=?";     $params[]=$filter_gender; }
if ($filter_subject) { $sql.=" AND users.subject_id=?"; $params[]=$filter_subject; }
$sql .= " ORDER BY users.created_at DESC";
$stmt = $pdo->prepare($sql); $stmt->execute($params); $users = $stmt->fetchAll();

$msgs = ['deleted'=>'Pengguna berhasil dihapus.','siswa_deleted'=>'Semua siswa berhasil dihapus.',
         'siswa_class_deleted'=>'Siswa kelas terpilih berhasil dihapus.','guru_deleted'=>'Semua guru berhasil dihapus.',
         'cannot_delete_admin'=>'Akun admin tidak dapat dihapus.'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Kelola Pengguna — Admin</title>
<link rel="stylesheet" href="/public/assets/css/style.css">
</head>
<body>
<div class="app-container">
<?php include '../templates/sidebar.php'; ?>
<main class="main-content">

    <!-- Toolbar -->
    <div class="page-toolbar">
        <div class="page-toolbar-left">
            <h1 class="page-title">Kelola Pengguna</h1>
            <p class="page-subtitle">Manajemen data seluruh pengguna sistem</p>
        </div>
        <div class="page-toolbar-right">
            <a href="import_siswa.php" class="btn btn-secondary btn-sm">Import Siswa</a>
            <a href="import_guru.php"  class="btn btn-secondary btn-sm">Import Guru</a>
            <a href="add_user.php"     class="btn btn-sm">+ Tambah Pengguna</a>
        </div>
    </div>

    <div class="page-content">

        <!-- Flash messages -->
        <?php if (isset($_GET['msg']) && isset($msgs[$_GET['msg']])): $danger = strpos($_GET['msg'],'cannot')!==false; ?>
            <div class="<?php echo $danger?'alert-danger':'alert-success'; ?> alert" style="margin-bottom:16px;">
                <?php echo $msgs[$_GET['msg']]; ?>
            </div>
        <?php endif; ?>
        <?php if (isset($_SESSION['flash'])): ?>
            <div class="alert alert-success"><?php echo $_SESSION['flash']; unset($_SESSION['flash']); ?></div>
        <?php endif; ?>

        <div class="page-section">

            <!-- Danger zone -->
            <div class="danger-zone" style="margin-bottom:16px;">
                <span class="danger-zone-label">⚠ Hapus Massal</span>
                <form method="POST" id="bulkDeleteForm" onsubmit="return confirmBulkDelete()" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin:0;">
                    <input type="hidden" name="action" value="delete_all">
                    <select name="role_to_delete" id="bulkRole" class="filter-select" onchange="toggleClassFilter()" style="width:auto;">
                        <option value="">-- Pilih Role --</option>
                        <option value="siswa">Semua Siswa</option>
                        <option value="guru">Semua Guru</option>
                    </select>
                    <select name="class_to_delete" id="bulkClass" class="filter-select" style="width:auto;display:none;">
                        <option value="">-- Semua Kelas --</option>
                        <?php foreach ($classes as $c): ?><option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option><?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-danger btn-sm">Hapus Massal</button>
                </form>
            </div>

            <!-- Filter bar -->
            <form method="GET" class="filter-bar" style="margin-bottom:16px;">
                <input type="text" name="search" class="filter-input" placeholder="Cari nama, NIP, NIS…" value="<?php echo htmlspecialchars($search_query); ?>">
                <select name="role" class="filter-select">
                    <option value="">Semua Role</option>
                    <?php foreach (['guru'=>'Guru','siswa'=>'Siswa','admin'=>'Admin','osis'=>'OSIS','kepsek'=>'Kepala Sekolah','wakasek'=>'Wakil Kepsek','bk'=>'Guru BK'] as $val=>$lbl): ?>
                    <option value="<?php echo $val; ?>" <?php echo $filter_role===$val?'selected':''; ?>><?php echo $lbl; ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="gender" class="filter-select">
                    <option value="">Gender</option>
                    <option value="L" <?php echo $filter_gender==='L'?'selected':''; ?>>Laki-laki</option>
                    <option value="P" <?php echo $filter_gender==='P'?'selected':''; ?>>Perempuan</option>
                </select>
                <select name="class_id" class="filter-select">
                    <option value="">Kelas</option>
                    <?php foreach ($classes as $c): ?><option value="<?php echo $c['id']; ?>" <?php echo $filter_class==$c['id']?'selected':''; ?>><?php echo htmlspecialchars($c['name']); ?></option><?php endforeach; ?>
                </select>
                <select name="subject_id" class="filter-select">
                    <option value="">Mapel</option>
                    <?php foreach ($subjects as $s): ?><option value="<?php echo $s['id']; ?>" <?php echo $filter_subject==$s['id']?'selected':''; ?>><?php echo htmlspecialchars($s['name']); ?></option><?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-sm">Filter</button>
                <?php if ($search_query||$filter_role||$filter_class||$filter_gender||$filter_subject): ?>
                <a href="manage_users.php" class="btn btn-secondary btn-sm">Reset</a>
                <?php endif; ?>
            </form>

            <!-- Table -->
            <div class="page-table-wrap">
                <table class="page-table">
                    <thead>
                        <tr>
                            <th class="num-col">No</th>
                            <th>Pengguna</th>
                            <th>Password</th>
                            <th>Info</th>
                            <th>Role</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php $n=1; foreach ($users as $u): ?>
                    <tr>
                        <td class="num-col"><?php echo $n++; ?></td>
                        <td>
                            <div class="user-info">
                                <?php if ($u['photo_path']): ?>
                                    <img src="/<?php echo htmlspecialchars($u['photo_path']); ?>" class="user-avatar" alt="">
                                <?php else: ?>
                                    <div class="user-avatar-placeholder">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <div style="font-weight:600;color:var(--text-primary);"><?php echo htmlspecialchars($u['full_name']); ?></div>
                                    <div style="font-size:0.75rem;color:var(--text-muted);">@<?php echo htmlspecialchars($u['username']); ?></div>
                                    <?php if ($u['nip']): ?><div style="font-size:0.75rem;color:var(--text-muted);">NIP: <?php echo htmlspecialchars($u['nip']); ?></div>
                                    <?php elseif ($u['nis']): ?><div style="font-size:0.75rem;color:var(--text-muted);">NIS: <?php echo htmlspecialchars($u['nis']); ?></div><?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="pwd-mask" style="font-family:monospace;color:var(--text-muted);">••••••••</span>
                            <span class="pwd-text" style="display:none;font-family:monospace;font-size:0.75rem;background:var(--bg-muted);padding:2px 6px;border-radius:4px;"><?php echo htmlspecialchars($u['password']); ?></span>
                            <button type="button" class="sub-btn" style="margin-left:4px;" onclick="togglePwd(this)">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </td>
                        <td>
                            <div style="font-size:0.8125rem;color:var(--text-secondary);">
                                <?php if ($u['gender']): ?><div><?php echo $u['gender']==='L'?'Laki-laki':'Perempuan'; ?></div><?php endif; ?>
                                <?php if ($u['role']==='siswa' && $u['class_name']): ?>
                                    <span class="badge-class"><?php echo htmlspecialchars($u['class_name']); ?></span>
                                <?php elseif ($u['role']==='guru' && $u['subject_name']): ?>
                                    <span class="badge-subj"><?php echo htmlspecialchars($u['subject_name']); ?></span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <span class="role-badge role-<?php echo $u['role']; ?>"><?php echo $u['role']; ?></span>
                            <?php if ($u['status']==='graduated'): ?><div style="font-size:0.6875rem;color:var(--text-muted);margin-top:3px;font-weight:600;">TAMAT</div><?php endif; ?>
                            <?php if ($u['status']==='suspended'): ?><div style="font-size:0.6875rem;color:var(--danger);margin-top:3px;font-weight:600;">SUSPEND</div><?php endif; ?>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <a href="add_user.php?edit=<?php echo $u['id']; ?>" class="sub-btn">Edit</a>
                                <?php if ($u['username']!=='admin'): ?>
                                <a href="manage_users.php?delete=<?php echo $u['id']; ?>" class="sub-btn danger" onclick="return confirm('Hapus pengguna ini?')">Hapus</a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($users)): ?>
                    <tr><td colspan="6"><div class="empty-state"><div class="empty-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></div><h4>Tidak ada pengguna ditemukan</h4><p>Coba ubah filter pencarian Anda</p></div></td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="table-footer">
                <span><?php echo count($users); ?> pengguna ditemukan</span>
            </div>

        </div>
    </div>

</main>
</div>

<script>
function toggleClassFilter() {
    const role = document.getElementById('bulkRole').value;
    const cl   = document.getElementById('bulkClass');
    cl.style.display = role==='siswa' ? 'inline-block' : 'none';
    if (role!=='siswa') cl.value='';
}
function confirmBulkDelete() {
    const role = document.getElementById('bulkRole').value;
    if (!role) { alert('Pilih role terlebih dahulu!'); return false; }
    return confirm('Yakin hapus SEMUA ' + role + '? Tindakan ini tidak dapat dibatalkan!');
}
function togglePwd(btn) {
    const mask = btn.previousElementSibling.previousElementSibling;
    const text = btn.previousElementSibling;
    const show = text.style.display==='none';
    text.style.display = show?'inline':'none';
    mask.style.display = show?'none':'inline';
}
</script>
</body>
</html>
