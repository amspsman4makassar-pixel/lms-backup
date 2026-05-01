<?php
// src/admin/promote_class.php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../login.php");
    exit;
}

$success = "";
$error = "";

// Handle Promotion Process
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['promote_students'])) {
    $from_class_id = $_POST['from_class_id'];
    $to_class_id = $_POST['to_class_id'];
    $selected_students = $_POST['students'] ?? [];

    if (empty($from_class_id) || empty($to_class_id) || empty($selected_students)) {
        $error = "Pilih kelas asal, kelas tujuan, dan minimal satu siswa.";
    }
    else {
        try {
            $pdo->beginTransaction();

            if ($to_class_id === 'graduate') {
                // Graduate Students
                $sql = "UPDATE users SET status = 'graduated', class_id = NULL WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                
                // Auto-create initial Tracer Study record
                $tracer_sql = "INSERT INTO tracer_study (user_id, kegiatan, nama_instansi, jurusan_posisi, tahun_lulus) VALUES (?, 'Belum/Tidak Bekerja', '-', '-', ?) ON DUPLICATE KEY UPDATE tahun_lulus = VALUES(tahun_lulus)";
                $tracer_stmt = $pdo->prepare($tracer_sql);
                $current_year = date('Y');

                foreach ($selected_students as $student_id) {
                    $stmt->execute([$student_id]);
                    $tracer_stmt->execute([$student_id, $current_year]);
                }
                $success = count($selected_students) . " Siswa berhasil diluluskan dan data awal tracer study telah dibuat!";
            }
            else {
                // Promote to Next Class
                $sql = "UPDATE users SET class_id = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                foreach ($selected_students as $student_id) {
                    $stmt->execute([$to_class_id, $student_id]);
                }
                $success = count($selected_students) . " Siswa berhasil dinaikkan kelas!";
            }

            $pdo->commit();
        }
        catch (Exception $e) {
            $pdo->rollBack();
            $error = "Terjadi kesalahan: " . $e->getMessage();
        }
    }
}

// Fetch Validation
$valid_classes = $pdo->query("SELECT id, name FROM classes ORDER BY grade_level, LENGTH(name), name")->fetchAll();

// Get Students if class selected
$students = [];
$selected_from = $_GET['from_class'] ?? '';
if ($selected_from) {
    $stmt = $pdo->prepare("SELECT id, full_name, nis FROM users WHERE role='siswa' AND class_id = ? AND status='active' ORDER BY full_name");
    $stmt->execute([$selected_from]);
    $students = $stmt->fetchAll();
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta charset="UTF-8">
    <title>Kenaikan Kelas - Admin</title>
    <link rel="stylesheet" href="/public/assets/css/style.css">
    <script>
        function toggleCheckboxes(source) {
            checkboxes = document.getElementsByName('students[]');
            for(var i=0, n=checkboxes.length;i<n;i++) {
                checkboxes[i].checked = source.checked;
            }
        }
    </script>
</head>
<body>

<div class="app-container">
    <?php include '../templates/sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Unified Hero Header -->
        <div class="page-toolbar">
            <div class="page-toolbar-left">
                <h1 class="page-title">Promosi Kelas</h1>
                <p class="page-subtitle">Naikkan kelas siswa ke tingkat berikutnya atau tandai sebagai tamat</p>
            </div>
        </div>
        <div class="page-toolbar-right" style="padding:0 32px 12px;">
            <a href="manage_classes.php" class="btn btn-secondary btn-sm">&larr; Kembali ke Kelola Kelas</a>
        </div>

        <div class="page-content">
            <div class="card">
            <?php if ($success): ?><div class="alert alert-success"><?php echo $success; ?></div><?php
endif; ?>
            <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php
endif; ?>

            <!-- Step 1: Select Classes -->
            <form method="GET" style="display: grid; grid-template-columns: 1fr auto; gap: 15px; align-items: end; margin-bottom: 2rem;">
                <div class="form-group" style="margin:0;">
                    <label>Pilih Kelas Asal</label>
                    <select name="from_class" required onchange="this.form.submit()" style="width: 100%; padding: 10px;">
                        <option value="">-- Pilih Kelas --</option>
                        <?php foreach ($valid_classes as $c): ?>
                            <option value="<?php echo $c['id']; ?>" <?php echo $selected_from == $c['id'] ? 'selected' : ''; ?>>
                                Kelas <?php echo htmlspecialchars($c['name']); ?>
                            </option>
                        <?php
endforeach; ?>
                    </select>
                </div>
            </form>

            <?php if ($selected_from && empty($students)): ?>
                <div style="text-align:center; padding: 2rem; color: #64748b;">
                    Tidak ada siswa aktif di kelas ini.
                </div>
            <?php
elseif ($selected_from): ?>
                
                <form method="POST">
                    <input type="hidden" name="from_class_id" value="<?php echo $selected_from; ?>">
                    <input type="hidden" name="promote_students" value="1">

                    <div class="form-group">
                        <label>Pilih Tujuan</label>
                        <select name="to_class_id" required style="width: 100%; padding: 10px; border: 2px solid #3b82f6;">
                            <option value="">-- Pilih Kelas Tujuan / Lulus --</option>
                            <option value="graduate"><svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' style='display:inline-block; vertical-align:middle; line-height:1;'><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg> LULUS (Set status tamat)</option>
                            <optgroup label="Naik ke Kelas:">
                                <?php foreach ($valid_classes as $c): ?>
                                    <?php if ($c['id'] == $selected_from)
            continue; ?>
                                    <option value="<?php echo $c['id']; ?>">Kelas <?php echo htmlspecialchars($c['name']); ?></option>
                                <?php
    endforeach; ?>
                            </optgroup>
                        </select>
                    </div>

                    <div style="margin-top: 1rem;">
                        <label style="font-weight:bold; display:block; margin-bottom:10px;">Daftar Siswa:</label>
                        <div class="page-table-wrap" style="max-height: 400px; overflow-y: auto;">
                            <table class="page-table">
                                <thead style="position: sticky; top: 0; z-index: 1;">
                                    <tr>
                                        <th style="width: 40px;">
                                            <input type="checkbox" checked onclick="toggleCheckboxes(this)">
                                        </th>
                                        <th>NIS</th>
                                        <th>Nama Siswa</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($students as $s): ?>
                                        <tr>
                                            <td>
                                                <input type="checkbox" name="students[]" value="<?php echo $s['id']; ?>" checked>
                                            </td>
                                            <td><?php echo htmlspecialchars($s['nis'] ?? '-'); ?></td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($s['full_name']); ?></strong>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <p style="font-size: 0.85rem; color: #64748b; margin-top: 5px;">* Hapus centang siswa yang <strong>tidak naik kelas</strong>.</p>
                    </div>

                    <button type="submit" class="btn" style="margin-top: 20px; width: 100%;" onclick="return confirm('Yakin ingin memproses data ini? Perubahan akan disimpan.')">
                        <svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' style='display:inline-block; vertical-align:middle; line-height:1;'><path d='M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2c.71-.84.7-2.13-.09-2.91a2.18 2.18 0 0 0-2.91-.09z'/><path d='m12 15-3-3a22 22 0 0 1 2-3.95A12.88 12.88 0 0 1 22 2c0 2.72-.78 7.5-6 11a22.35 22.35 0 0 1-4 2z'/><path d='M9 12H4s.55-3.03 2-4c1.62-1.08 5 0 5 0'/><path d='M12 15v5s3.03-.55 4-2c1.08-1.62 0-5 0-5'/></svg> Proses Kenaikan Kelas
                    </button>
                </form>

            <?php
endif; ?>

            </div>
        </div>
    </main>
</div>

</body>
</html>


