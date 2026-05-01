<?php
// src/admin/manage_master_classes.php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../login.php");
    exit;
}

$success = "";
$error = "";

// Handle Create Class
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_master_class'])) {
    $grade_level = intval($_POST['grade_level']);
    $class_name = trim($_POST['class_name']);
    $major = trim($_POST['major']);

    if (empty($grade_level) || empty($class_name)) {
        $error = "Tingkat Kelas dan Nama Kelas harus diisi.";
    }
    else {
        try {
            // Check for duplicate
            $check = $pdo->prepare("SELECT COUNT(*) FROM classes WHERE name = ? AND grade_level = ?");
            $check->execute([$class_name, $grade_level]);
            if ($check->fetchColumn() > 0) {
                $_SESSION['flash'] = ['type' => 'error', 'message' => "Kelas ini sudah ada."];
            }
            else {
                $stmt = $pdo->prepare("INSERT INTO classes (name, grade_level, major) VALUES (?, ?, ?)");
                $stmt->execute([$class_name, $grade_level, empty($major) ? null : $major]);
                $_SESSION['flash'] = ['type' => 'success', 'message' => "Master Kelas berhasil ditambahkan!"];
            }
        }
        catch (PDOException $e) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => "Gagal menambahkan kelas: " . $e->getMessage()];
        }
    }
    header("Location: manage_master_classes.php");
    exit;
}

// Handle Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_master_class'])) {
    $class_id = intval($_POST['class_id']);
    try {
        // We delete the class.
        // Also it might be best to clean up teacher_classes? The schema probably doesn't cascade.
        // But for safety let's just delete from classes.
        $stmt = $pdo->prepare("DELETE FROM classes WHERE id = ?");
        $stmt->execute([$class_id]);
        $_SESSION['flash'] = ['type' => 'success', 'message' => "Master Kelas berhasil dihapus."];
    }
    catch (PDOException $e) {
        $_SESSION['flash'] = ['type' => 'error', 'message' => "Gagal menghapus: Kelas masih digunakan oleh data lain."];
    }
    header("Location: manage_master_classes.php");
    exit;
}

// Handle Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_master_class'])) {
    $class_id = intval($_POST['class_id']);
    $grade_level = intval($_POST['grade_level']);
    $class_name = trim($_POST['class_name']);
    $major = trim($_POST['major']);

    if (empty($grade_level) || empty($class_name)) {
        $error = "Tingkat Kelas dan Nama Kelas harus diisi.";
    }
    else {
        try {
            $stmt = $pdo->prepare("UPDATE classes SET name = ?, grade_level = ?, major = ? WHERE id = ?");
            $stmt->execute([$class_name, $grade_level, empty($major) ? null : $major, $class_id]);
            $_SESSION['flash'] = ['type' => 'success', 'message' => "Master Kelas berhasil diperbarui!"];
        }
        catch (PDOException $e) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => "Gagal mengedit kelas: " . $e->getMessage()];
        }
    }
    header("Location: manage_master_classes.php");
    exit;
}

// Fetch all classes
$classes = $pdo->query("SELECT * FROM classes ORDER BY grade_level ASC, LENGTH(name) ASC, name ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta charset="UTF-8">
    <title>Kelola Master Kelas - Admin</title>
    <link rel="stylesheet" href="/public/assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>

    <div class="app-container">
    <?php include '../templates/sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Unified Hero Header -->
        <div class="page-toolbar">
            <div class="page-toolbar-left">
                <h1 class="page-title">Master Kelas</h1>
                <p class="page-subtitle">Kelola data master kelas dan tingkat sekolah</p>
            </div>
        </div>
        <div class="page-content">
            <div class="page-actions">
                <button onclick="document.getElementById('createClassModal').showModal()" class="btn btn-primary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Tambah Master Kelas
                </button>
            </div>

            <div class="page-section">
                <div class="panel-header">
                    <h3 class="panel-title">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
                        Daftar Master Kelas
                    </h3>
                    <span style="background:#dbeafe;color:#1e40af;padding:3px 12px;border-radius:20px;font-size:0.75rem;font-weight:700;">Total: <?= count($classes) ?></span>
                </div>

                <?php if (isset($_SESSION['flash'])): $flash = $_SESSION['flash']; unset($_SESSION['flash']); ?>
                    <div class="<?= $flash['type'] === 'error' ? 'flash-error' : 'flash-success' ?>">
                        <?= $flash['type'] === 'error'
                            ? '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>'
                            : '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>'; ?>
                        <?= htmlspecialchars($flash['message']) ?>
                    </div>
                <?php endif; ?>

                <?php if (empty($classes)): ?>
                    <div class="empty-state">
                        <div class="empty-icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg></div>
                        <h4>Belum ada master kelas</h4>
                        <p>Klik tombol di atas untuk menambahkan kelas.</p>
                    </div>
                <?php else: ?>
                    <div class="page-table-wrap" style="border:none;border-radius:0;margin-bottom:0;">
                        <table class="page-table">
                            <thead><tr><th>Jenjang</th><th>Nama Kelas</th><th>Jurusan</th><th>Aksi</th></tr></thead>
                            <tbody>
                                <?php foreach ($classes as $c): ?>
                                <tr>
                                    <td><span class="badge-jam">Kelas <?= htmlspecialchars($c['grade_level']) ?></span></td>
                                    <td style="font-weight:600;color:#0f172a;"><?= htmlspecialchars($c['name']) ?></td>
                                    <td><?= !empty($c['major']) ? '<span class="badge-subj">'.htmlspecialchars($c['major']).'</span>' : '<span style="color:#94a3b8">&mdash;</span>' ?></td>
                                    <td>
                                        <div style="display:flex;gap:6px;">
                                            <button type="button" class="sub-btn" onclick="openEditModal(<?= $c['id'] ?>, <?= $c['grade_level'] ?>, '<?= htmlspecialchars(addslashes($c['name'])) ?>', '<?= htmlspecialchars(addslashes($c['major'] ?? '')) ?>')">Edit</button>
                                            <form method="POST" onsubmit="return confirm('Yakin hapus?');" style="margin:0;">
                                                <input type="hidden" name="delete_master_class" value="1">
                                                <input type="hidden" name="class_id" value="<?= $c['id'] ?>">
                                                <button type="submit" class="sub-btn danger">Hapus</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<!-- Modal for Create Class -->
<dialog id="createClassModal">
    <div class="modal-header"><h3>Buat Master Kelas</h3><button class="modal-close" onclick="document.getElementById('createClassModal').close()">&times;</button></div>
    <div class="modal-body">
        <form method="POST">
            <input type="hidden" name="create_master_class" value="1">

            <div class="form-group">
                <label>Jenjang Kelas (Angka)</label>
                <select name="grade_level" required class="filter-select" style="width: 100%;">
                    <option value="">-- Pilih Jenjang --</option>
                    <option value="10">Kelas 10 (X)</option>
                    <option value="11">Kelas 11 (XI)</option>
                    <option value="12">Kelas 12 (XII)</option>
                </select>
            </div>

            <div class="form-group">
                <label>Nama Kelas (Contoh: X-1, XI IPA 1)</label>
                <input type="text" name="class_name" required placeholder="Contoh: X-1" class="filter-input" style="width: 100%;">
            </div>

            <div class="form-group">
                <label>Jurusan (Opsional)</label>
                <input type="text" name="major" placeholder="Contoh: MIPA, IPS, Bahasa" class="filter-input" style="width: 100%;">
            </div>

            <div class="modal-footer" style="padding:1rem 0 0;border:none;">
                <button type="button" onclick="document.getElementById('createClassModal').close()" class="btn btn-ghost">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan</button>
            </div>
        </form>
    </div>
</dialog>

<!-- Modal for Edit Class -->
<dialog id="editClassModal">
    <div class="modal-header"><h3>Edit Master Kelas</h3><button class="modal-close" onclick="document.getElementById('editClassModal').close()">&times;</button></div>
    <div class="modal-body">
        <form method="POST">
            <input type="hidden" name="edit_master_class" value="1">
            <input type="hidden" name="class_id" id="edit_class_id">

            <div class="form-group">
                <label>Jenjang Kelas (Angka)</label>
                <select name="grade_level" id="edit_grade_level" required class="filter-select" style="width: 100%;">
                    <option value="">-- Pilih Jenjang --</option>
                    <option value="10">Kelas 10 (X)</option>
                    <option value="11">Kelas 11 (XI)</option>
                    <option value="12">Kelas 12 (XII)</option>
                </select>
            </div>

            <div class="form-group">
                <label>Nama Kelas</label>
                <input type="text" name="class_name" id="edit_class_name" required placeholder="Contoh: X-1" class="filter-input" style="width: 100%;">
            </div>

            <div class="form-group">
                <label>Jurusan (Opsional)</label>
                <input type="text" name="major" id="edit_major" placeholder="Contoh: MIPA, IPS, Bahasa" class="filter-input" style="width: 100%;">
            </div>

            <div class="modal-footer" style="padding:1rem 0 0;border:none;">
                <button type="button" onclick="document.getElementById('editClassModal').close()" class="btn btn-ghost">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</dialog>

<script>
function openEditModal(id, grade, name, major) {
    document.getElementById('edit_class_id').value = id;
    document.getElementById('edit_grade_level').value = grade;
    document.getElementById('edit_class_name').value = name;
    document.getElementById('edit_major').value = major;
    document.getElementById('editClassModal').showModal();
}
</script>

</body>
</html>


