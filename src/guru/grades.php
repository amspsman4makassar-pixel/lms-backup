<?php
// src/guru/grades.php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guru') {
    header("Location: ../../login.php");
    exit;
}

$teacher_id = $_SESSION['user_id'];

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
        $grouped_classes['Others'][] = $class;
    }
}

// Helper for Subject Colors
function getSubjectStyle($subjectName)
{
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

    $subjectLower = strtolower($subjectName);
    if (strpos($subjectLower, 'matematika') !== false) return $gradients['blue'];
    if (strpos($subjectLower, 'biologi') !== false) return $gradients['green'];
    if (strpos($subjectLower, 'fisika') !== false) return $gradients['indigo'];
    if (strpos($subjectLower, 'kimia') !== false) return $gradients['purple'];
    if (strpos($subjectLower, 'sejarah') !== false) return $gradients['orange'];
    if (strpos($subjectLower, 'bahasa') !== false) return $gradients['teal'];
    if (strpos($subjectLower, 'inggris') !== false) return $gradients['pink'];
    if (strpos($subjectLower, 'ekonomi') !== false) return $gradients['cyan'];
    if (strpos($subjectLower, 'geografi') !== false) return $gradients['teal'];
    if (strpos($subjectLower, 'sosiologi') !== false) return $gradients['orange'];
    if (strpos($subjectLower, 'pk') !== false) return $gradients['red'];

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
    <title>Input Nilai Siswa</title>
    <link rel="stylesheet" href="/public/assets/css/style.css">

</head>
<body class="admin-full-layout">

<div class="app-container">
    <?php include '../templates/sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Dashboard Hero -->
        <div class="page-toolbar">
            <div class="page-toolbar-left">
                <h1 class="page-title">Input Nilai</h1>
                <p class="page-subtitle">Kelola dan input nilai siswa per kelas dan mata pelajaran</p>
            </div>
        </div>
        <div class="page-content">
            <div class="page-section">

        <?php if (empty($all_my_classes)): ?>
            <div style="text-align: center; padding: 5rem 2rem; background: white; border-radius: 24px; border: 2px dashed #cbd5e1; max-width: 600px; margin: 40px auto;">
                <div style="font-size: 4rem; opacity: 0.8; margin-bottom: 1.5rem; color: #94a3b8; display: flex; justify-content: center;"><svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg></div>
                <h3 style="color: #1e293b; font-size: 1.5rem; font-weight: 700; margin-bottom: 0.75rem;">Belum ada kelas aktif</h3>
                <p style="color: #64748b;">Anda harus membuat kelas terlebih dahulu di menu "Kelas & Materi" sebelum menginput nilai.</p>
                <a href="kelas.php" class="btn" style="display: inline-block; margin-top: 1rem; text-decoration: none;">Ke Menu Kelas</a>
            </div>
        <?php else: ?>
            <div id="searchResultsArea">
            <?php
            $display_grades = ['10' => 'Kelas 10', '11' => 'Kelas 11', '12' => 'Kelas 12', 'Others' => 'Kelas Lainnya'];
            foreach ($display_grades as $key => $label): 
                if (!empty($grouped_classes[$key])): 
            ?>
                    <div class="grade-section" data-grade-section>
                        <div class="grade-title">
                            <span class="grade-badge"><?php echo $key === 'Others' ? 'Lainnya' : $key; ?></span>
                            <?php echo $label; ?>
                        </div>
                        <div class="page-table-wrap">
                            <table class="page-table">
                                <thead>
                                    <tr>
                                        <th>Mata Pelajaran</th>
                                        <th>Nama Kelas</th>
                                        <th>Jenis Kelas</th>
                                        <th>Siswa Terdaftar</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                            <?php foreach ($grouped_classes[$key] as $class):
                                $bgStyle = getSubjectStyle($class['subject']);
                            ?>
                                    <tr class="class-row-search" 
                                        data-name="<?php echo strtolower(htmlspecialchars($class['name'])); ?>"
                                        data-subject="<?php echo strtolower(htmlspecialchars($class['subject'])); ?>">
                                        <td>
                                            <span class="table-subject-badge" style="background: <?php echo $bgStyle; ?>;">
                                                <?php echo htmlspecialchars($class['subject']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div style="font-weight: 700; color: #1e293b;"><?php echo htmlspecialchars($class['name']); ?></div>
                                            <?php if($class['is_special_class']): ?>
                                                <span style="font-size: 0.75rem; background: #fbbf24; color: #78350f; padding: 2px 6px; border-radius: 4px; display: inline-block; margin-top: 4px;">Kelas Khusus</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="color: #64748b;">
                                            <?php echo $class['is_special_class'] ? 'Lintas Kelas' : htmlspecialchars($class['school_class_name']); ?>
                                        </td>
                                        <td>
                                            <span style="font-weight: 600; color: #334155;">
                                                <?php echo $class['is_special_class'] ? $class['special_student_count'] : $class['regular_student_count']; ?>
                                            </span> Siswa
                                        </td>
                                        <td>
                                            <a href="grades_input.php?class_id=<?php echo $class['id']; ?>" class="btn-action">
                                                Input Nilai &rarr;
                                            </a>
                                        </td>
                                    </tr>
                            <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
            </div>

            <!-- No Results Message -->
            <div id="noResultsMsg" style="display: none; text-align: center; padding: 4rem 1rem; color: #64748b;">
                <p style="font-size: 3rem; margin-bottom: 10px;"><svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg></p>
                <p style="font-size: 1.1rem; font-weight: 500;">Tidak ditemukan kelas yang cocok.</p> 
            </div>
        <?php endif; ?>

            </div> <!-- End page-section -->
        </div>
    </main>
</div>

<script>
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
                    card.style.display = 'table-row';
                    hasSectionResults = true;
                    hasGlobalResults = true;
                } else {
                    card.style.display = 'none';
                }
            });

            if (hasSectionResults) {
                section.style.display = 'block';
            } else {
                section.style.display = 'none';
            }
        });

        const noResultsMsg = document.getElementById('noResultsMsg');
        if (hasGlobalResults) {
            noResultsMsg.style.display = 'none';
        } else {
            noResultsMsg.style.display = 'block';
        }
    });
});
</script>
</body>
</html>




