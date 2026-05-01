<?php
// src/admin/manage_classes.php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../login.php");
    exit;
}

$success = "";
$error = "";

// Handle Create Class for Teacher
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_class'])) {
    $teacher_id = intval($_POST['teacher_id']);
    $class_id = intval($_POST['class_id']);
    $subject_id = intval($_POST['subject_id']);
    $class_name_custom = trim($_POST['class_name_custom']);

    if (empty($teacher_id) || empty($class_id) || empty($subject_id) || empty($class_name_custom)) {
        $error = "Semua field harus diisi.";
    }
    else {
        try {
            // Check for duplicate
            $check = $pdo->prepare("SELECT COUNT(*) FROM teacher_classes WHERE teacher_id = ? AND class_id = ? AND subject = (SELECT name FROM subjects WHERE id = ?)");
            $check->execute([$teacher_id, $class_id, $subject_id]);
            if ($check->fetchColumn() > 0) {
                $error = "Kelas ini sudah ada untuk guru tersebut.";
            }
            else {
                // Fetch subject name
                $stmt = $pdo->prepare("SELECT name FROM subjects WHERE id = ?");
                $stmt->execute([$subject_id]);
                $subject_name = $stmt->fetchColumn();

                // Generate Folder Name: YYYY-MM-DD ClassName
                $date_prefix = date('Y-m-d');
                $safe_title = preg_replace('/[^A-Za-z0-9_\-]/', '_', $class_name_custom);
                $folder_name = $date_prefix . ' ' . $safe_title;

                // Create Directory
                $base_dir = "../../public/uploads/classes/" . $folder_name;
                if (!file_exists($base_dir)) {
                    mkdir($base_dir, 0777, true);
                    mkdir($base_dir . "/materi", 0777, true);
                    mkdir($base_dir . "/tugas", 0777, true);
                }

                // Added subject_id and folder_name to the insert
                $stmt = $pdo->prepare("INSERT INTO teacher_classes (teacher_id, class_id, name, subject, subject_id, folder_name) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$teacher_id, $class_id, $class_name_custom, $subject_name, $subject_id, $folder_name]);
                $_SESSION['flash'] = ['type' => 'success', 'message' => "Kelas berhasil dibuat untuk guru!"];
            }
        }
        catch (PDOException $e) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => "Gagal membuat kelas: " . $e->getMessage()];
        }
    }
    header("Location: manage_classes.php");
    exit;
}

// Handle Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_class'])) {
    $tc_id = intval($_POST['tc_id']);
    try {
        $stmt = $pdo->prepare("DELETE FROM teacher_classes WHERE id = ?");
        $stmt->execute([$tc_id]);
        $_SESSION['flash'] = ['type' => 'success', 'message' => "Kelas berhasil dihapus."];
    }
    catch (PDOException $e) {
        $_SESSION['flash'] = ['type' => 'error', 'message' => "Gagal menghapus: " . $e->getMessage()];
    }
    header("Location: manage_classes.php");
    exit;
}

// Handle Edit Class
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_class'])) {
    $tc_id = intval($_POST['tc_id']);
    $teacher_id = intval($_POST['teacher_id']);
    $class_id_val = intval($_POST['class_id']);
    $subject_id = intval($_POST['subject_id']);
    $class_name_custom = trim($_POST['class_name_custom']);

    if (empty($teacher_id) || empty($class_id_val) || empty($subject_id) || empty($class_name_custom)) {
        $error = "Semua field harus diisi.";
    }
    else {
        try {
            // Fetch subject name
            $stmt = $pdo->prepare("SELECT name FROM subjects WHERE id = ?");
            $stmt->execute([$subject_id]);
            $subject_name = $stmt->fetchColumn();

            $stmt = $pdo->prepare("UPDATE teacher_classes SET teacher_id = ?, class_id = ?, name = ?, subject = ? WHERE id = ?");
            $stmt->execute([$teacher_id, $class_id_val, $class_name_custom, $subject_name, $tc_id]);
            $_SESSION['flash'] = ['type' => 'success', 'message' => "Kelas berhasil diperbarui!"];
        }
        catch (PDOException $e) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => "Gagal mengedit kelas: " . $e->getMessage()];
        }
    }
    header("Location: manage_classes.php");
    exit;
}

// Fetch all teachers
$teachers = $pdo->query("SELECT id, full_name FROM users WHERE role='guru' ORDER BY full_name ASC")->fetchAll();

// Fetch all classes
$classes = $pdo->query("SELECT id, name, grade_level FROM classes ORDER BY grade_level, LENGTH(name), name")->fetchAll();

// Fetch all subjects
$subjects = $pdo->query("SELECT id, name FROM subjects ORDER BY name ASC")->fetchAll();

// Fetch all teacher_classes with joins
$all_tc = $pdo->query("
    SELECT tc.id, tc.teacher_id, tc.class_id, tc.name as custom_name, tc.subject, tc.created_at, tc.is_special_class, tc.special_grade_level,
           u.full_name as teacher_name,
           c.name as class_name, c.grade_level
    FROM teacher_classes tc
    JOIN users u ON tc.teacher_id = u.id
    LEFT JOIN classes c ON tc.class_id = c.id
    ORDER BY u.full_name ASC, COALESCE(c.grade_level, tc.special_grade_level) ASC, LENGTH(c.name) ASC, c.name ASC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta charset="UTF-8">
    <title>Kelola Kelas - Admin</title>
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
                <h1 class="page-title">Kelola Kelas</h1>
                <p class="page-subtitle">Buat dan atur kelas yang dapat digunakan oleh guru</p>
            </div>
        </div>
            <a href="promote_class.php" class="btn btn-glass">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
                Kenaikan Kelas
            </a>
            <div class="hero-deco hero-deco-tr"></div>
        <div class="page-content">

            <div class="page-actions">
                <button onclick="document.getElementById('createClassModal').showModal()" class="btn btn-primary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Tambah Kelas Baru
                </button>
            </div>

            <div class="page-section">
                <div class="panel-header">
                    <h3 class="panel-title">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg>
                        Daftar Kelas Guru
                    </h3>
                    <div class="panel-meta">
                        <span style="background:#dbeafe;color:#1e40af;padding:3px 12px;border-radius:20px;font-size:0.75rem;font-weight:700;">Kelas: <?= count($all_tc) ?></span>
                        <span style="background:#dcfce7;color:#166534;padding:3px 12px;border-radius:20px;font-size:0.75rem;font-weight:700;">Guru: <?= count($teachers) ?></span>
                    </div>
                </div>

                <?php if (isset($_SESSION['flash'])): $flash = $_SESSION['flash']; unset($_SESSION['flash']); ?>
                    <div class="<?= $flash['type'] === 'error' ? 'flash-error' : 'flash-success' ?>">
                        <?= $flash['type'] === 'error'
                            ? '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>'
                            : '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>'; ?>
                        <?= htmlspecialchars($flash['message']) ?>
                    </div>
                <?php endif; ?>

                <?php if (empty($all_tc)): ?>
                    <div class="empty-state">
                        <div class="empty-icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg></div>
                        <h4>Belum ada kelas yang dibuat</h4>
                        <p>Klik "Tambah Kelas Baru" untuk mulai membuat kelas guru.</p>
                    </div>
                <?php else: ?>
                    <div class="page-table-wrap" style="border:none;border-radius:0;margin-bottom:0;">
                        <table class="page-table">
                            <thead>
                                <tr>
                                    <th>Guru</th>
                                    <th>Nama Kelas</th>
                                    <th>Kelas</th>
                                    <th>Mata Pelajaran</th>
                                    <th>Tanggal</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($all_tc as $tc): ?>
                                <tr>
                                    <td style="font-weight:600;color:#0f172a;"><?= htmlspecialchars($tc['teacher_name']) ?></td>
                                    <td>
                                        <?= htmlspecialchars($tc['custom_name']) ?>
                                        <?php if (!empty($tc['is_special_class'])): ?>
                                            <span style="font-size:0.72rem;background:#fef3c7;color:#92400e;padding:2px 6px;border-radius:4px;margin-left:5px;border:1px solid #fde68a;">Kelas Khusus</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($tc['is_special_class'])): ?>
                                            <span class="badge-subj">Lintas Kelas <?= htmlspecialchars($tc['special_grade_level'] ?? '') ?></span>
                                        <?php else: ?>
                                            <span class="badge-class"><?= htmlspecialchars($tc['class_name'] ?? '') ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge-subj"><?= htmlspecialchars($tc['subject']) ?></span></td>
                                    <td style="color:#94a3b8;font-size:0.83rem;"><?= date('d M Y', strtotime($tc['created_at'])) ?></td>
                                    <td>
                                        <div style="display:flex;gap:6px;">
                                            <?php if (empty($tc['is_special_class'])): ?>
                                                <button type="button" class="sub-btn" onclick="openEditModal(<?= $tc['id'] ?>, <?= $tc['teacher_id'] ?? 0 ?>, <?= $tc['class_id'] ?? 0 ?>, '<?= htmlspecialchars($tc['subject'], ENT_QUOTES) ?>', '<?= htmlspecialchars($tc['custom_name'], ENT_QUOTES) ?>')">Edit</button>
                                            <?php endif; ?>
                                            <form method="POST" onsubmit="return confirm('Yakin hapus kelas ini?');" style="margin:0;">
                                                <input type="hidden" name="delete_class" value="1">
                                                <input type="hidden" name="tc_id" value="<?= $tc['id'] ?>">
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
    <div class="modal-header">
        <h3>Buat Kelas Baru</h3>
        <button class="modal-close" onclick="document.getElementById('createClassModal').close()">&times;</button>
    </div>
    <div class="modal-body">
        <form method="POST">
            <input type="hidden" name="create_class" value="1">

            <div class="form-group">
                <label>Guru Tujuan</label>
                <select name="teacher_id" required class="filter-select" style="width: 100%;">
                    <option value="">-- Pilih Guru --</option>
                    <?php foreach ($teachers as $t): ?>
                        <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['full_name']); ?></option>
                    <?php
endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Mata Pelajaran</label>
                <select name="subject_id" required id="subjectSelect" onchange="autoFillName()" class="filter-select" style="width: 100%;">
                    <option value="">-- Pilih Mata Pelajaran --</option>
                    <?php foreach ($subjects as $s): ?>
                        <option value="<?php echo $s['id']; ?>" data-name="<?php echo htmlspecialchars($s['name']); ?>"><?php echo htmlspecialchars($s['name']); ?></option>
                    <?php
endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Kelas</label>
                <select name="class_id" required id="classSelect" onchange="autoFillName()" class="filter-select" style="width: 100%;">
                    <option value="">-- Pilih Kelas --</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?php echo $c['id']; ?>" data-name="<?php echo htmlspecialchars($c['name']); ?>">Kelas <?php echo htmlspecialchars($c['name']); ?></option>
                    <?php
endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Nama Kelas (Custom)</label>
                <input type="text" name="class_name_custom" id="customName" required placeholder="Auto-generated atau ketik manual" class="filter-input" style="width: 100%;">
            </div>

            <div class="modal-footer" style="padding:1rem 0 0;border:none;">
                <button type="button" onclick="document.getElementById('createClassModal').close()" class="btn btn-ghost">Batal</button>
                <button type="submit" class="btn btn-primary">Buat Kelas</button>
            </div>
        </form>
    </div>
</dialog>

<!-- Modal for Edit Class -->
<dialog id="editClassModal">
    <div class="modal-header">
        <h3>Edit Kelas</h3>
        <button class="modal-close" onclick="document.getElementById('editClassModal').close()">&times;</button>
    </div>
    <div class="modal-body">
        <form method="POST">
            <input type="hidden" name="edit_class" value="1">
            <input type="hidden" name="tc_id" id="edit_tc_id">

            <div class="form-group">
                <label>Guru Tujuan</label>
                <select name="teacher_id" required id="edit_teacher_id" class="filter-select" style="width: 100%;">
                    <option value="">-- Pilih Guru --</option>
                    <?php foreach ($teachers as $t): ?>
                        <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['full_name']); ?></option>
                    <?php
endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Mata Pelajaran</label>
                <select name="subject_id" required id="edit_subject_id" onchange="editAutoFillName()" class="filter-select" style="width: 100%;">
                    <option value="">-- Pilih Mata Pelajaran --</option>
                    <?php foreach ($subjects as $s): ?>
                        <option value="<?php echo $s['id']; ?>" data-name="<?php echo htmlspecialchars($s['name']); ?>"><?php echo htmlspecialchars($s['name']); ?></option>
                    <?php
endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Kelas</label>
                <select name="class_id" required id="edit_class_id" onchange="editAutoFillName()" class="filter-select" style="width: 100%;">
                    <option value="">-- Pilih Kelas --</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?php echo $c['id']; ?>" data-name="<?php echo htmlspecialchars($c['name']); ?>">Kelas <?php echo htmlspecialchars($c['name']); ?></option>
                    <?php
endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Nama Kelas (Custom)</label>
                <input type="text" name="class_name_custom" id="edit_customName" required placeholder="Nama kelas" class="filter-input" style="width: 100%;">
            </div>

            <div class="modal-footer" style="padding:1rem 0 0;border:none;">
                <button type="button" onclick="document.getElementById('editClassModal').close()" class="btn btn-ghost">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</dialog>


<script>
function autoFillName() {
    const subjectSelect = document.getElementById('subjectSelect');
    const classSelect = document.getElementById('classSelect');
    const customName = document.getElementById('customName');

    const subjectOption = subjectSelect.options[subjectSelect.selectedIndex];
    const classOption = classSelect.options[classSelect.selectedIndex];

    const subjectName = subjectOption && subjectOption.dataset.name ? subjectOption.dataset.name : '';
    const className = classOption && classOption.dataset.name ? classOption.dataset.name : '';

    if (subjectName && className) {
        customName.value = subjectName + ' - ' + className;
    }
}

function editAutoFillName() {
    const subjectSelect = document.getElementById('edit_subject_id');
    const classSelect = document.getElementById('edit_class_id');
    const customName = document.getElementById('edit_customName');

    const subjectOption = subjectSelect.options[subjectSelect.selectedIndex];
    const classOption = classSelect.options[classSelect.selectedIndex];

    const subjectName = subjectOption && subjectOption.dataset.name ? subjectOption.dataset.name : '';
    const className = classOption && classOption.dataset.name ? classOption.dataset.name : '';

    if (subjectName && className) {
        customName.value = subjectName + ' - ' + className;
    }
}

function openEditModal(tcId, teacherId, classId, subjectName, customName) {
    document.getElementById('edit_tc_id').value = tcId;
    document.getElementById('edit_teacher_id').value = teacherId;
    document.getElementById('edit_class_id').value = classId;
    document.getElementById('edit_customName').value = customName;

    // Find and select the subject by name
    const subjectSelect = document.getElementById('edit_subject_id');
    for (let i = 0; i < subjectSelect.options.length; i++) {
        if (subjectSelect.options[i].dataset.name === subjectName) {
            subjectSelect.selectedIndex = i;
            break;
        }
    }

    document.getElementById('editClassModal').showModal();
}
</script>

</body>
</html>


