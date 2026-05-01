<?php
// src/guru/kelas.php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guru') {
    header("Location: ../../login.php");
    exit;
}

$teacher_id = $_SESSION['user_id'];
$success = "";
$error = "";

// Handle Create Class
// Handle Create Class
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_class'])) {
    $class_id = $_POST['class_id'] ?? null;
    $subject_id = $_POST['subject_id'] ?? null;
    $class_name_custom = trim($_POST['class_name_custom'] ?? '');
    
    $special_grade_type = $_POST['special_grade_type'] ?? 'regular';
    $is_special_class = ($special_grade_type !== 'regular') ? 1 : 0;
    $special_grade_level = ($is_special_class && $special_grade_type !== 'lintas') ? $special_grade_type : null;

    if (empty($subject_id) || empty($class_name_custom)) {
        $_SESSION['flash'] = ['type' => 'error', 'message' => "Mata pelajaran dan nama kelas harus diisi."];
    }
    elseif ($is_special_class == 0 && empty($class_id)) {
        $_SESSION['flash'] = ['type' => 'error', 'message' => "Target kelas sekolah harus dipilih untuk Kelas Reguler."];
    }
    else {
        try {
            // Fetch subject name
            $stmt = $pdo->prepare("SELECT name FROM subjects WHERE id = ?");
            $stmt->execute([$subject_id]);
            $subject_name = $stmt->fetchColumn();

            // Generate Folder Name: YYYY-MM-DD ClassName
            $date_prefix = date('Y-m-d');
            $safe_name = preg_replace('/[^A-Za-z0-9_\-]/', '_', $class_name_custom);
            $folder_name = $date_prefix . ' ' . $safe_name;

            // Create Directory
            $base_dir = "../../public/uploads/classes/" . $folder_name;
            if (!file_exists($base_dir)) {
                mkdir($base_dir, 0777, true);
                mkdir($base_dir . "/materi", 0777, true);
                mkdir($base_dir . "/tugas", 0777, true);
            }

            $class_id_val = $is_special_class ? null : $class_id;

            $stmt = $pdo->prepare("INSERT INTO teacher_classes (teacher_id, class_id, name, subject, subject_id, folder_name, is_special_class, special_grade_level) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$teacher_id, $class_id_val, $class_name_custom, $subject_name, $subject_id, $folder_name, $is_special_class, $special_grade_level]);
            $_SESSION['flash'] = ['type' => 'success', 'message' => "Kelas berhasil dibuat!"];
        }
        catch (PDOException $e) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => "Gagal membuat kelas: " . $e->getMessage()];
        }
    }
    header("Location: kelas.php");
    exit;
}

// Handle Delete Class
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_class'])) {
    $class_id = intval($_POST['delete_class']);
    try {
        $stmt = $pdo->prepare("DELETE FROM teacher_classes WHERE id = ? AND teacher_id = ?");
        $stmt->execute([$class_id, $teacher_id]);
        $_SESSION['flash'] = ['type' => 'success', 'message' => "Kelas berhasil dihapus!"];
    } catch (PDOException $e) {
        $_SESSION['flash'] = ['type' => 'error', 'message' => "Gagal menghapus kelas karena ada data terkait."];
    }
    header("Location: kelas.php");
    exit;
}

// Fetch Teacher's Classes with Grade Level
$stmt = $pdo->prepare("
    SELECT tc.*, c.name as school_class_name, 
    COALESCE(c.grade_level, tc.special_grade_level) as computed_grade_level,
    (SELECT COUNT(*) FROM class_members cm WHERE cm.teacher_class_id = tc.id) as special_student_count,
    (SELECT COUNT(*) FROM users u WHERE u.class_id = tc.class_id AND u.role = 'siswa') as regular_student_count
    FROM teacher_classes tc
    LEFT JOIN classes c ON tc.class_id = c.id
    WHERE tc.teacher_id = ?
    ORDER BY COALESCE(c.grade_level, tc.special_grade_level, 99) ASC, tc.created_at DESC
");
$stmt->execute([$teacher_id]);
$all_my_classes = $stmt->fetchAll();

// Group by Grade Level
$grouped_classes = [
    '10' => [],
    '11' => [],
    '12' => [],
    'Others' => [] // For any class without strict 10/11/12
];

foreach ($all_my_classes as $class) {
    if (in_array($class['computed_grade_level'], ['10', '11', '12'])) {
        $grouped_classes[$class['computed_grade_level']][] = $class;
    }
    else {
        // Includes lintas kelas (special_grade_level = null)
        $grouped_classes['Others'][] = $class;
    }
}

// Fetch All School Classes for Dropdown
$stmt = $pdo->query("SELECT * FROM classes ORDER BY grade_level, LENGTH(name), name");
$all_classes = $stmt->fetchAll();

// Fetch All Subjects for Dropdown
$stmt = $pdo->query("SELECT * FROM subjects ORDER BY name ASC");
$all_subjects = $stmt->fetchAll();

// Helper for Subject Colors
function getSubjectStyle($subjectName)
{
    // Define a palette of nice gradients
    $gradients = [
        'blue' => 'linear-gradient(135deg, #3b82f6 0%, #2563eb 100%)',
        'indigo' => 'linear-gradient(135deg, #6366f1 0%, #4f46e5 100%)',
        'purple' => 'linear-gradient(135deg, #a855f7 0%, #9333ea 100%)',
        'pink' => 'linear-gradient(135deg, #ec4899 0%, #db2777 100%)',
        'orange' => 'linear-gradient(135deg, #f97316 0%, #ea580c 100%)',
        'teal' => 'linear-gradient(135deg, #14b8a6 0%, #0d9488 100%)',
        'green' => 'linear-gradient(135deg, #22c55e 0%, #16a34a 100%)',
        'red' => 'linear-gradient(135deg, #ef4444 0%, #dc2626 100%)',
        'cyan' => 'linear-gradient(135deg, #06b6d4 0%, #0891b2 100%)',
    ];

    // Map common subjects to specific colors
    $subjectLower = strtolower($subjectName);
    if (strpos($subjectLower, 'matematika') !== false)
        return $gradients['blue'];
    if (strpos($subjectLower, 'biologi') !== false)
        return $gradients['green'];
    if (strpos($subjectLower, 'fisika') !== false)
        return $gradients['indigo'];
    if (strpos($subjectLower, 'kimia') !== false)
        return $gradients['purple'];
    if (strpos($subjectLower, 'sejarah') !== false)
        return $gradients['orange'];
    if (strpos($subjectLower, 'bahasa') !== false)
        return $gradients['teal'];
    if (strpos($subjectLower, 'inggris') !== false)
        return $gradients['pink'];
    if (strpos($subjectLower, 'ekonomi') !== false)
        return $gradients['cyan'];
    if (strpos($subjectLower, 'geografi') !== false)
        return $gradients['teal'];
    if (strpos($subjectLower, 'sosiologi') !== false)
        return $gradients['orange'];
    if (strpos($subjectLower, 'pk') !== false)
        return $gradients['red'];

    // Fallback: Use hash to pick a color deterministically
    $keys = array_keys($gradients);
    $hash = crc32($subjectName);
    $index = abs($hash) % count($keys);
    return $gradients[$keys[$index]];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta charset="UTF-8">
    <title>Kelas Saya - Guru</title>
    <link rel="stylesheet" href="/public/assets/css/style.css">

</head>
<body class="admin-full-layout">

<div class="app-container">
    <?php include '../templates/sidebar.php'; ?>
    
    <main class="main-content">
        <div class="page-toolbar">
            <div class="page-toolbar-left">
                <h1 class="page-title">Kelas Saya</h1>
                <p class="page-subtitle">Kelola kelas, materi, dan tugas untuk setiap mata pelajaran</p>
            </div>
            <div class="page-toolbar-right">
                <input type="text" id="classSearchInput" class="filter-input" placeholder="Cari kelas atau mapel..." style="width:220px;">
                <button onclick="document.getElementById('addClassModal').style.display='block'" class="btn btn-sm">+ Buat Kelas Baru</button>
            </div>
        </div>
        <div class="page-content">
        <div class="page-section">

        <?php
if (isset($_SESSION['flash'])) {
    $flash = $_SESSION['flash'];
    $bg = '#f0fdf4';
    $color = '#166534';
    $border = '#bbf7d0'; // Success default

    if ($flash['type'] == 'error') {
        $bg = '#fef2f2';
        $color = '#991b1b';
        $border = '#fecaca';
    }

    echo "<div style='background:$bg; color:$color; padding:16px; border-radius:12px; margin-bottom:24px; border:1px solid $border; display:flex; align-items:center; gap:10px;'>
                    " . ($flash['type'] == 'error' ? "<svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' style='display:inline-block; vertical-align:middle; line-height:1;'><path d=\"m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z\"/><line x1=\"12\" y1=\"9\" x2=\"12\" y2=\"13\"/><line x1=\"12\" y1=\"17\" x2=\"12.01\" y2=\"17\"/></svg> " : "<svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' style='display:inline-block; vertical-align:middle; line-height:1;'><polyline points=\"20 6 9 17 4 12\"/></svg> ") . htmlspecialchars($flash['message']) . "
                  </div>";
    unset($_SESSION['flash']);
}
?>

        <?php if (empty($all_my_classes)): ?>
            <div style="text-align: center; padding: 5rem 2rem; background: white; border-radius: 24px; border: 2px dashed #cbd5e1; max-width: 600px; margin: 40px auto;">
                <div style="font-size: 4rem; margin-bottom: 1.5rem; opacity: 0.8; display: flex; justify-content: center;"><svg width='1em' height='1em' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' style='display:inline-block; vertical-align:middle; line-height:1;'><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg></div>
                <h3 style="color: #1e293b; font-size: 1.5rem; font-weight: 700; margin-bottom: 0.75rem;">Belum ada kelas aktif</h3>
                <p style="color: #64748b; margin-bottom: 2rem; font-size: 1rem; line-height: 1.6;">Selamat datang! Mulai perjalanan mengajar Anda dengan membuat kelas pertama. Tambahkan materi dan tugas dengan mudah.</p>
                <button onclick="document.getElementById('addClassModal').style.display='block'" class="btn btn-secondary">
                    <svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' style='display:inline-block; vertical-align:middle; line-height:1;'><path d='M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2c.71-.84.7-2.13-.09-2.91a2.18 2.18 0 0 0-2.91-.09z'/><path d='m12 15-3-3a22 22 0 0 1 2-3.95A12.88 12.88 0 0 1 22 2c0 2.72-.78 7.5-6 11a22.35 22.35 0 0 1-4 2z'/><path d='M9 12H4s.55-3.03 2-4c1.62-1.08 5 0 5 0'/><path d='M12 15v5s3.03-.55 4-2c1.08-1.62 0-5 0-5'/></svg> Buat Kelas Pertama
                </button>
            </div>
        <?php
else: ?>
            
            <div id="searchResultsArea">
            <?php
    // Display logic loops through grades 10, 11, 12, and 'Others'
    $display_grades = ['10' => 'Kelas 10', '11' => 'Kelas 11', '12' => 'Kelas 12', 'Others' => 'Kelas Lainnya'];
?>

            <?php foreach ($display_grades as $key => $label): ?>
                <?php if (!empty($grouped_classes[$key])): ?>
                    <div class="grade-section" data-grade-section>
                        <div class="grade-title">
                            <span class="grade-badge"><?php echo $key === 'Others' ? 'Lainnya' : $key; ?></span>
                            <?php echo $label; ?>
                        </div>
                        <div class="class-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 16px;">
                            <?php foreach ($grouped_classes[$key] as $class):
                                $bgStyle = getSubjectStyle($class['subject']);
                            ?>
                            <div class="class-card class-row-search" 
                                data-name="<?php echo strtolower(htmlspecialchars($class['name'])); ?>"
                                data-subject="<?php echo strtolower(htmlspecialchars($class['subject'])); ?>"
                                style="background: #fff; border: 1px solid var(--border); border-radius: var(--radius-xl); padding: 20px; display: flex; flex-direction: column; gap: 16px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
                                
                                <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                    <div>
                                        <span class="table-subject-badge" style="background: <?php echo $bgStyle; ?>; margin-bottom: 8px;">
                                            <?php echo htmlspecialchars($class['subject']); ?>
                                        </span>
                                        <div style="font-weight: 800; color: #1e293b; font-size: 1.25rem; margin-top: 4px;"><?php echo htmlspecialchars($class['name']); ?></div>
                                    </div>
                                    <?php if($class['is_special_class']): ?>
                                        <span style="font-size: 0.7rem; background: #fbbf24; color: #78350f; padding: 3px 8px; border-radius: 6px; font-weight: 700;">KHUSUS</span>
                                    <?php endif; ?>
                                </div>

                                <div style="color: #64748b; font-size: 0.9rem;">
                                    <?php echo $class['is_special_class'] ? 'Lintas Kelas' : htmlspecialchars($class['school_class_name']); ?>
                                </div>

                                <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--border); padding-top: 12px; font-size: 0.85rem;">
                                    <div>
                                        <span style="font-weight: 700; color: #334155;">
                                            <?php echo $class['is_special_class'] ? $class['special_student_count'] : $class['regular_student_count']; ?>
                                        </span> Siswa
                                    </div>
                                    <div style="color: #94a3b8;">
                                        <?php echo date('d M Y', strtotime($class['created_at'])); ?>
                                    </div>
                                </div>

                                <div style="display: flex; gap: 8px;">
                                    <a href="view_class.php?id=<?php echo $class['id']; ?>" class="btn btn-primary" style="flex: 1; text-align: center; font-weight: 700; padding: 8px 16px; border-radius: 8px;">
                                        Masuk Kelas &rarr;
                                    </a>
                                    <form method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus kelas ini? Semua materi dan tugas di dalamnya mungkin akan ikut terhapus.');" style="margin: 0;">
                                        <input type="hidden" name="delete_class" value="<?php echo $class['id']; ?>">
                                        <button type="submit" class="btn" style="background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; padding: 8px 12px; border-radius: 8px; cursor: pointer;" title="Hapus Kelas">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                        </button>
                                    </form>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php
        endif; ?>
            <?php
    endforeach; ?>
            </div>

            <!-- No Results Message -->
            <div id="noResultsMsg" style="display: none; text-align: center; padding: 4rem 1rem; color: #64748b;">
                <p style="font-size: 3rem; margin-bottom: 10px;"><svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' style='display:inline-block; vertical-align:middle; line-height:1;'><circle cx='11' cy='11' r='8'/><line x1='21' y1='21' x2='16.65' y2='16.65'/></svg></p>
                <p style="font-size: 1.1rem; font-weight: 500;">Tidak ditemukan kelas yang cocok.</p> 
            </div>

        <?php
endif; ?>
            </div><!-- end page-section -->
        </div><!-- end page-content -->


        <!-- Add Class Modal -->
        <div id="addClassModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="document.getElementById('addClassModal').style.display='none'">&times;</span>
                <h2 style="margin-bottom: 0.5rem; font-size: 1.5rem; font-weight: 800; color: #1e293b;">Buat Kelas Baru</h2>
                <p style="color: #64748b; margin-bottom: 2rem;">Isi formulir di bawah ini untuk menambahkan kelas.</p>
                
                <form method="POST">
                    <input type="hidden" name="create_class" value="1">
                    
                    <div class="form-group">
                        <label>Mata Pelajaran</label>
                        <select name="subject_id" required id="guruSubjectSelect" onchange="autoFillClassName()">
                            <option value="">-- Pilih Mata Pelajaran --</option>
                            <?php foreach ($all_subjects as $s): ?>
                                <option value="<?php echo $s['id']; ?>" data-name="<?php echo htmlspecialchars($s['name']); ?>"><?php echo htmlspecialchars($s['name']); ?></option>
                            <?php
endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group" style="background: #f8fafc; padding: 15px; border-radius: 10px; border: 1px solid #e2e8f0; margin-bottom: 20px;">
                        <label style="font-weight: 700; color: #4338ca; margin-bottom: 8px; display: block;">Target Jenjang / Tipe Kelas</label>
                        <select name="special_grade_type" id="specialGradeType" onchange="toggleSpecialClass()" style="width: 100%; border-color: #cbd5e1; padding: 10px;">
                            <option value="regular">Kelas Reguler (Satu Kelas Fisik Sekolah)</option>
                            <option value="10">Kelas Khusus - Jenjang X (Lintas Kelas X)</option>
                            <option value="11">Kelas Khusus - Jenjang XI (Lintas Kelas XI)</option>
                            <option value="12">Kelas Khusus - Jenjang XII (Lintas Kelas XII)</option>
                            <option value="lintas">Kelas Khusus - Lintas Semua Jenjang</option>
                        </select>
                        <p style="margin: 8px 0 0 0; font-size: 0.85rem; color: #64748b; line-height: 1.4;">Pilih "Kelas Reguler" untuk mengajar satu kelas fisik (contoh: X-1). Pilih "Kelas Khusus" untuk kelas gabungan dari berbagai kelas fisik (contoh: Agama Kristen Kelas X).</p>
                    </div>

                    <div class="form-group" id="regularClassGroup">
                        <label>Target Kelas Sekolah <span style="color: #ef4444;">*</span></label>
                        <select name="class_id" id="guruClassSelect" onchange="autoFillClassName()">
                            <option value="">-- Pilih Kelas --</option>
                            <?php foreach ($all_classes as $c): ?>
                                <option value="<?php echo $c['id']; ?>" data-name="<?php echo htmlspecialchars($c['name']); ?>"><?php echo htmlspecialchars($c['name']) . " (Kelas " . $c['grade_level'] . ")"; ?></option>
                            <?php
endforeach; ?>
                        </select>
                        <small style="color: #94a3b8; display: block; margin-top: 5px;">Kelas fisik/resmi yang terdaftar di sekolah.</small>
                    </div>

                    <div class="form-group">
                        <label>Nama Kelas (Custom)</label>
                        <input type="text" name="class_name_custom" id="guruCustomName" placeholder="Otomatis terisi, atau ketik manual" required>
                        <small style="color: #94a3b8; display: block; margin-top: 5px;">Nama ini akan muncul di dashboard Anda.</small>
                    </div>

                    <div style="display: flex; gap: 12px; margin-top: 2.5rem;">
                        <button type="button" class="btn btn-secondary" onclick="document.getElementById('addClassModal').style.display='none'" style="flex: 1; justify-content: center; background: #f1f5f9; color: #475569; border: none;">Batal</button>
                        <button type="submit" class="btn" style="flex: 2; justify-content: center; box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);">&#10024; Buat Kelas</button>
                    </div>
                </form>
            </div>
        </div>

    </main>
</div>

<script>
function toggleSpecialClass() {
    const specialType = document.getElementById('specialGradeType').value;
    const isSpecial = specialType !== 'regular';
    const regularGroup = document.getElementById('regularClassGroup');
    const classSelect = document.getElementById('guruClassSelect');
    const customName = document.getElementById('guruCustomName');
    
    if (isSpecial) {
        regularGroup.style.display = 'none';
        classSelect.required = false;
        classSelect.value = ''; // Reset selection
        
        // Auto-fill fallback based on subject alone
        const subjectSelect = document.getElementById('guruSubjectSelect');
        const subjectOpt = subjectSelect.options[subjectSelect.selectedIndex];
        const subjectName = subjectOpt && subjectOpt.dataset.name ? subjectOpt.dataset.name : '';
        
        let gradeLabel = '';
        if (specialType === '10') gradeLabel = 'Kelas X';
        else if (specialType === '11') gradeLabel = 'Kelas XI';
        else if (specialType === '12') gradeLabel = 'Kelas XII';
        else if (specialType === 'lintas') gradeLabel = 'Lintas Kelas';

        if (subjectName) {
            customName.value = subjectName + ' - ' + gradeLabel;
        }
    } else {
        regularGroup.style.display = 'block';
        classSelect.required = true;
        autoFillClassName(); // Re-trigger normal autofill
    }
}

function autoFillClassName() {
    if (document.getElementById('specialGradeType').value !== 'regular') return;

    const subjectSelect = document.getElementById('guruSubjectSelect');
    const classSelect = document.getElementById('guruClassSelect');
    const customName = document.getElementById('guruCustomName');

    const subjectOpt = subjectSelect.options[subjectSelect.selectedIndex];
    const classOpt = classSelect.options[classSelect.selectedIndex];

    const subjectName = subjectOpt && subjectOpt.dataset.name ? subjectOpt.dataset.name : '';
    const className = classOpt && classOpt.dataset.name ? classOpt.dataset.name : '';

    if (subjectName && className) {
        customName.value = subjectName + ' - ' + className;
    }
}

// Search Feature Script
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('classSearchInput');
    if (!searchInput) return;

    searchInput.addEventListener('input', function() {
        const query = this.value.toLowerCase().trim();
        const gradeSections = document.querySelectorAll('.grade-section');
        let hasGlobalResults = false;

        gradeSections.forEach(section => {
            const cards = section.querySelectorAll('.class-row-search');
            let hasSectionResults = false;

            cards.forEach(card => {
                const name = card.dataset.name || '';
                const subject = card.dataset.subject || '';
                
                if (name.includes(query) || subject.includes(query)) {
                    card.style.display = 'table-row'; // Restore display
                    hasSectionResults = true;
                    hasGlobalResults = true;
                } else {
                    card.style.display = 'none';
                }
            });

            // Toggle Section Visibility
            if (hasSectionResults) {
                section.style.display = 'block';
            } else {
                section.style.display = 'none';
            }
        });

        // Show/Hide "No Results" Message
        const noResultsMsg = document.getElementById('noResultsMsg');
        if (hasGlobalResults) {
            noResultsMsg.style.display = 'none';
        } else {
            noResultsMsg.style.display = 'block';
        }
    });

    // Input focus styling
    searchInput.addEventListener('focus', function() {
        this.style.borderColor = '#6366f1';
        this.style.boxShadow = '0 0 0 3px rgba(99, 102, 241, 0.1)';
    });
    searchInput.addEventListener('blur', function() {
        this.style.borderColor = '#e2e8f0';
        this.style.boxShadow = '0 1px 2px rgba(0,0,0,0.05)';
    });
});
</script>

</body>
</html>



