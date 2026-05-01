<?php
// src/guru/export_grades_print.php
// Printable / Save-as-PDF view for submission grades.

session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guru') {
    header("Location: ../../login.php");
    exit;
}

$assignment_id = intval($_GET['assignment_id'] ?? 0);
$teacher_id    = $_SESSION['user_id'];

// Verify ownership
$stmt = $pdo->prepare("SELECT * FROM assignments WHERE id = ? AND teacher_id = ?");
$stmt->execute([$assignment_id, $teacher_id]);
$assignment = $stmt->fetch();

if (!$assignment) {
    die("Tugas tidak ditemukan atau Anda tidak memiliki akses.");
}

// Fetch class names
$stmtC = $pdo->prepare("
    SELECT GROUP_CONCAT(classes.name ORDER BY classes.name SEPARATOR ', ') as class_names
    FROM assignment_classes
    JOIN classes ON assignment_classes.class_id = classes.id
    WHERE assignment_classes.assignment_id = ?
");
$stmtC->execute([$assignment_id]);
$class_names = $stmtC->fetchColumn() ?: '-';

// Fetch submissions
$stmt = $pdo->prepare("
    SELECT s.*, u.full_name, u.username
    FROM submissions s
    JOIN users u ON s.student_id = u.id
    WHERE s.assignment_id = ?
    ORDER BY u.full_name ASC
");
$stmt->execute([$assignment_id]);
$submissions = $stmt->fetchAll();

// Stats
$grade_values = array_filter(array_column($submissions, 'grade'), fn($g) => $g !== null);
$avg_grade    = count($grade_values) ? round(array_sum($grade_values) / count($grade_values), 1) : '-';
$graded_count = count($grade_values);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta charset="UTF-8">
    <title>Rekap Nilai &mdash; <?php echo htmlspecialchars($assignment['title']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 13px;
            color: #1e293b;
            background: #f8fafc;
            padding: 20px;
        }

        /* â”€â”€ Print controls (hidden when printing) â”€â”€ */
        .print-controls {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-bottom: 20px;
        }
        .btn-print {
            background: #4f46e5;
            color: #fff;
            border: none;
            padding: 9px 20px;
            border-radius: 8px;
            font-size: 0.88rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-family: inherit;
        }
        .btn-close {
            background: #f1f5f9;
            color: #475569;
            border: none;
            padding: 9px 20px;
            border-radius: 8px;
            font-size: 0.88rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-family: inherit;
        }

        /* â”€â”€ Document â”€â”€ */
        .document {
            background: #fff;
            max-width: 760px;
            margin: 0 auto;
            padding: 36px 40px;
            border-radius: 12px;
            box-shadow: 0 2px 16px rgba(0,0,0,0.06);
        }

        /* â”€â”€ Header â”€â”€ */
        .doc-header {
            border-bottom: 2px solid #4f46e5;
            padding-bottom: 16px;
            margin-bottom: 20px;
        }
        .school-name {
            font-size: 0.78rem;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 4px;
        }
        .doc-title {
            font-size: 1.3rem;
            font-weight: 800;
            color: #1e293b;
        }
        .doc-subtitle {
            font-size: 0.85rem;
            color: #64748b;
            margin-top: 2px;
        }

        /* â”€â”€ Meta info grid â”€â”€ */
        .meta-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 6px 24px;
            margin-bottom: 22px;
            padding: 14px 16px;
            background: #f8fafc;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }
        .meta-item { display: flex; gap: 6px; font-size: 0.82rem; }
        .meta-label { color: #64748b; min-width: 80px; flex-shrink: 0; }
        .meta-value { color: #1e293b; font-weight: 600; }

        /* â”€â”€ Stats row â”€â”€ */
        .stats-row {
            display: flex;
            gap: 12px;
            margin-bottom: 20px;
        }
        .stat-box {
            flex: 1;
            text-align: center;
            padding: 10px 8px;
            border-radius: 8px;
            background: #eef2ff;
        }
        .stat-box .stat-num { font-size: 1.3rem; font-weight: 800; color: #4f46e5; }
        .stat-box .stat-lbl { font-size: 0.72rem; color: #6366f1; font-weight: 600; margin-top: 2px; }
        .stat-box.green { background: #dcfce7; }
        .stat-box.green .stat-num { color: #16a34a; }
        .stat-box.green .stat-lbl { color: #15803d; }
        .stat-box.amber { background: #fef3c7; }
        .stat-box.amber .stat-num { color: #d97706; }
        .stat-box.amber .stat-lbl { color: #b45309; }

        /* â”€â”€ Table â”€â”€ */
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.82rem;
        }
        thead th {
            background: #4f46e5;
            color: #fff;
            padding: 9px 10px;
            text-align: left;
            font-weight: 700;
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        thead th:first-child { border-radius: 6px 0 0 0; }
        thead th:last-child  { border-radius: 0 6px 0 0; text-align: center; }
        tbody tr:nth-child(even) { background: #f8fafc; }
        tbody tr:hover { background: #eef2ff; }
        tbody td {
            padding: 9px 10px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }
        td:first-child { text-align: center; color: #94a3b8; font-weight: 600; }
        td:last-child  { text-align: center; }

        .badge-nilai {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 20px;
            font-weight: 700;
            font-size: 0.88rem;
        }
        .badge-belum { background: #fee2e2; color: #991b1b; }
        .badge-rendah { background: #fef3c7; color: #92400e; }
        .badge-sedang { background: #dbeafe; color: #1d4ed8; }
        .badge-tinggi { background: #dcfce7; color: #15803d; }

        .tanggal-cell { font-size: 0.78rem; color: #475569; }
        .terlambat-badge {
            display: inline-block;
            background: #fee2e2;
            color: #991b1b;
            font-size: 0.65rem;
            font-weight: 700;
            padding: 1px 5px;
            border-radius: 3px;
            margin-left: 4px;
            vertical-align: middle;
        }

        /* â”€â”€ Footer â”€â”€ */
        .doc-footer {
            margin-top: 28px;
            padding-top: 14px;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
        }
        .doc-footer .generated {
            font-size: 0.72rem;
            color: #94a3b8;
        }
        .signature-box {
            text-align: center;
            font-size: 0.78rem;
            color: #475569;
        }
        .signature-line {
            width: 160px;
            border-bottom: 1px solid #cbd5e1;
            margin: 40px auto 6px;
        }

        /* â”€â”€ Print styles â”€â”€ */
        @media print {
            body { background: #fff; padding: 0; }
            .print-controls { display: none !important; }
            .document {
                box-shadow: none;
                border-radius: 0;
                padding: 20px 24px;
                max-width: 100%;
            }
            tbody tr:hover { background: transparent; }
            @page {
                margin: 1.2cm;
                size: A4;
            }
        }
    </style>
</head>
<body>

<!-- Print / Close buttons -->
<div class="print-controls">
    <a href="view_submissions.php?assignment_id=<?php echo $assignment_id; ?>" class="btn-close">
        &times; Tutup
    </a>
    <button onclick="window.print()" class="btn-print">
        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
        Print / Simpan sebagai PDF
    </button>
</div>

<!-- Document -->
<div class="document">

    <!-- Header -->
    <div class="doc-header">
        <div class="school-name">Rekap Nilai Tugas</div>
        <div class="doc-title"><?php echo htmlspecialchars($assignment['title']); ?></div>
        <div class="doc-subtitle">Kelas: <?php echo htmlspecialchars($class_names); ?></div>
    </div>

    <!-- Meta Info -->
    <div class="meta-grid">
        <div class="meta-item">
            <span class="meta-label">Nama Tugas</span>
            <span class="meta-value"><?php echo htmlspecialchars($assignment['title']); ?></span>
        </div>
        <div class="meta-item">
            <span class="meta-label">Kelas</span>
            <span class="meta-value"><?php echo htmlspecialchars($class_names); ?></span>
        </div>
        <div class="meta-item">
            <span class="meta-label">Deadline</span>
            <span class="meta-value"><?php echo date('d M Y, H:i', strtotime($assignment['deadline'])); ?></span>
        </div>
        <div class="meta-item">
            <span class="meta-label">Dicetak pada</span>
            <span class="meta-value"><?php echo date('d M Y, H:i'); ?></span>
        </div>
    </div>

    <!-- Stats -->
    <div class="stats-row">
        <div class="stat-box">
            <div class="stat-num"><?php echo count($submissions); ?></div>
            <div class="stat-lbl">Total Mengumpulkan</div>
        </div>
        <div class="stat-box green">
            <div class="stat-num"><?php echo $graded_count; ?></div>
            <div class="stat-lbl">Sudah Dinilai</div>
        </div>
        <div class="stat-box amber">
            <div class="stat-num"><?php echo count($submissions) - $graded_count; ?></div>
            <div class="stat-lbl">Belum Dinilai</div>
        </div>
        <div class="stat-box">
            <div class="stat-num"><?php echo $avg_grade; ?></div>
            <div class="stat-lbl">Rata-rata Nilai</div>
        </div>
    </div>

    <!-- Table -->
    <table>
        <thead>
            <tr>
                <th style="width:40px;">No.</th>
                <th>Nama Siswa</th>
                <th>Tanggal Kumpul</th>
                <th style="width:90px; text-align:center;">Nilai</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($submissions)): ?>
            <tr>
                <td colspan="4" style="text-align:center; padding:20px; color:#94a3b8;">
                    Belum ada siswa yang mengumpulkan.
                </td>
            </tr>
        <?php else: ?>
            <?php $no = 1; foreach ($submissions as $sub):
                $is_late = ($sub['status'] === 'terlambat') ||
                           (!empty($sub['submitted_at']) && strtotime($sub['submitted_at']) > strtotime($assignment['deadline']));
                $tanggal = !empty($sub['submitted_at']) ? date('d M Y, H:i', strtotime($sub['submitted_at'])) : '-';
                $grade   = $sub['grade'];

                if ($grade === null) {
                    $badge_class = 'badge-belum';
                    $grade_text  = '-';
                } elseif ($grade < 60) {
                    $badge_class = 'badge-rendah';
                    $grade_text  = $grade;
                } elseif ($grade < 75) {
                    $badge_class = 'badge-sedang';
                    $grade_text  = $grade;
                } else {
                    $badge_class = 'badge-tinggi';
                    $grade_text  = $grade;
                }
            ?>
            <tr>
                <td><?php echo $no++; ?></td>
                <td><?php echo htmlspecialchars($sub['full_name']); ?></td>
                <td class="tanggal-cell">
                    <?php echo $tanggal; ?>
                    <?php if ($is_late): ?>
                        <span class="terlambat-badge">Terlambat</span>
                    <?php endif; ?>
                </td>
                <td>
                    <span class="badge-nilai <?php echo $badge_class; ?>"><?php echo $grade_text; ?></span>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>

    <!-- Footer -->
    <div class="doc-footer">
        <div class="generated">
            Dicetak oleh sistem LMS SMA Negeri 4 Makassar<br>
            <?php echo date('d M Y \p\u\k\u\l H:i'); ?>
        </div>
        <div class="signature-box">
            <div class="signature-line"></div>
            Guru Pengampu
        </div>
    </div>

</div>

<script>
// Auto-trigger print dialog on page load
window.addEventListener('load', function() {
    // Small delay to let fonts render properly
    setTimeout(function() { window.print(); }, 500);
});
</script>

</body>
</html>

