<?php
// src/guru/view_attendance.php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guru') {
    header("Location: ../../login.php");
    exit;
}

$teacher_id = $_SESSION['user_id'];
$class_id = $_GET['class_id'] ?? 0;


// Fetch Teacher Class Details
$stmt = $pdo->prepare("
    SELECT tc.*, c.name as school_class_name 
    FROM teacher_classes tc
    JOIN classes c ON tc.class_id = c.id
    WHERE tc.id = ? AND tc.teacher_id = ?
");
$stmt->execute([$class_id, $teacher_id]);
$class = $stmt->fetch();

if (!$class) {
    die("Kelas tidak ditemukan atau Anda tidak memiliki akses.");
}

// Fetch Students
$stmt = $pdo->prepare("SELECT id, full_name, username, gender, nis FROM users WHERE class_id = ? AND role = 'siswa' ORDER BY nis ASC, full_name ASC");
$stmt->execute([$class['class_id']]);
$students = $stmt->fetchAll();

// Fetch all attendance assignments for this class
$stmt = $pdo->prepare("SELECT * FROM assignments WHERE teacher_class_id = ? AND assignment_type = 'absensi' AND teacher_id = ? ORDER BY meeting_number ASC");
$stmt->execute([$class_id, $teacher_id]);
$attendances = $stmt->fetchAll();

// Selected meeting filter
$selected_meeting = $_GET['meeting'] ?? 'all';

// Deduplicate assignments by meeting_number (keep latest ID)
$unique_attendances = [];
foreach ($attendances as $att) {
    $m_num = intval($att['meeting_number']);
    if (!isset($unique_attendances[$m_num]) || $att['id'] > $unique_attendances[$m_num]['id']) {
        $unique_attendances[$m_num] = $att;
    }
}
$attendances = $unique_attendances;

// Build attendance data
$attendance_data = [];
foreach ($attendances as $att) {
    // Modified: Fetch submitted_at as well
    $sub_stmt = $pdo->prepare("SELECT student_id, status, submitted_at FROM submissions WHERE assignment_id = ?");
    $sub_stmt->execute([$att['id']]);
    // FETCH_KEY_PAIR only works for 2 columns. We have 3 now.
    // Let's refetch differently.
    $raw_subs = $sub_stmt->fetchAll(PDO::FETCH_ASSOC);

    $submissions = [];
    $submission_times = [];
    foreach ($raw_subs as $rs) {
        $submissions[$rs['student_id']] = $rs['status'];
        $submission_times[$rs['student_id']] = $rs['submitted_at'];
    }

    $num = $att['meeting_number']; // e.g. 1
    $attendance_data[intval($num)]['info'] = $att;

    foreach ($students as $s) {
        $status = $submissions[$s['id']] ?? null;
        $time = $submission_times[$s['id']] ?? null;

        $attendance_data[intval($num)]['students'][] = [
            'student_name' => $s['full_name'],
            'student_id' => $s['id'],
            'username' => $s['username'],
            'nis' => $s['nis'],
            'status' => $status,
            'time' => $time
        ];
    }
}
ksort($attendance_data);

// Filter logic
$display_data = [];
$show_all = true;

if ($selected_meeting !== 'all') {
    $show_all = false;
    if (isset($attendance_data[intval($selected_meeting)])) {
        $filtered[intval($selected_meeting)] = $attendance_data[intval($selected_meeting)];
    }
    else {
        $filtered = [];
    }
    $display_data = $filtered;
}
else {
    $display_data = $attendance_data;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta charset="UTF-8">
    <title>Absensi - <?php echo htmlspecialchars($class['name']); ?></title>
    <link rel="stylesheet" href="/public/assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    
</head>
<body>

<div class="app-container">
    <?php include '../templates/sidebar.php'; ?>
    
    <main class="main-content">

        <div class="page-toolbar">
            <div class="page-toolbar-left">
                <h1 class="page-title">Data Absensi</h1>
                <p class="page-subtitle">Rekap dan riwayat kehadiran siswa</p>
            </div>
        </div>

        <div class="att-content">

            <!-- Print Header (only visible when printing) -->
            <div class="print-header">
                <h2>Rekap Absensi - <?php echo htmlspecialchars($class['name']); ?></h2>
                <p><?php echo htmlspecialchars($class['subject']); ?> &middot; <?php echo htmlspecialchars($class['school_class_name']); ?></p>
                <p>Dicetak: <?php echo date('d M Y H:i'); ?></p>
            </div>

            <div class="att-panel no-print att-filter-panel">
                <div class="filter-bar">
                    <label for="meetingFilter">Filter Pertemuan:</label>
                    <div class="filter-actions">
                        <select id="meetingFilter" onchange="window.location.href='view_attendance.php?class_id=<?php echo (int) $class_id; ?>&meeting='+this.value">
                            <option value="all" <?php echo $selected_meeting === 'all' ? 'selected' : ''; ?>>Semua Pertemuan</option>
                            <?php for ($i = 1; $i <= 16; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php echo $selected_meeting == $i ? 'selected' : ''; ?>>
                                Pertemuan <?php echo $i; ?>
                                <?php echo isset($attendance_data[$i]) ? '' : '(belum ada)'; ?>
                            </option>
                            <?php endfor; ?>
                        </select>
                        <a href="export_attendance.php?class_id=<?php echo (int) $class_id; ?>" class="btn-print" style="background:#d1fae5; color:#065f46; text-decoration:none; margin-left:0;">
                            &#128202; Export Excel
                        </a>
                        <button type="button" class="btn-print" onclick="window.print()" style="margin-left:0;"><svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' style='display:inline-block; vertical-align:middle; line-height:1;'><polyline points='6 9 6 2 18 2 18 9'/><path d='M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2'/><rect x='6' y='14' width='12' height='8'/></svg> Cetak / Print</button>
                    </div>
                </div>
            </div>

            <?php if (empty($display_data)): ?>
                <div class="att-panel">
                    <div style="text-align:center; padding:3rem; color:#94a3b8;">
                        <p style="font-size:2.5rem; margin-bottom:8px;"><svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' style='display:inline-block; vertical-align:middle; line-height:1;'><path d='M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2'/><rect x='8' y='2' width='8' height='4' rx='1' ry='1'/></svg></p>
                        <p>Belum ada data absensi untuk pertemuan ini.</p>
                    </div>
                </div>
            <?php
else: ?>

                <?php foreach ($display_data as $meeting_num => $data):
        // Calculate Counts
        $counts = ['hadir' => 0, 'sakit' => 0, 'izin' => 0, 'alpha' => 0, 'terlambat' => 0];
        foreach ($data['students'] as $student) {
            $st = $student['status'];
            if ($st && isset($counts[$st])) {
                $counts[$st]++;
            }
        }
        $total_students = count($students);
?>
                <div class="att-panel">
                    <div class="meeting-title">Pertemuan <?php echo $meeting_num; ?></div>
                    <div class="meeting-meta">
                        Tanggal: <?php echo date('d M Y', strtotime($data['info']['deadline'])); ?>
                    </div>

                    <div class="summary-row">
                        <div class="summary-card">
                            <div class="num" style="color:#166534;"><?php echo $counts['hadir']; ?></div>
                            <div class="lbl">Hadir</div>
                        </div>
                        <div class="summary-card">
                            <div class="num" style="color:#991b1b;"><?php echo $counts['sakit']; ?></div>
                            <div class="lbl">Sakit</div>
                        </div>
                        <div class="summary-card">
                            <div class="num" style="color:#854d0e;"><?php echo $counts['izin']; ?></div>
                            <div class="lbl">Izin</div>
                        </div>
                        <div class="summary-card">
                            <div class="num" style="color:#64748b;"><?php echo $counts['alpha']; ?></div>
                            <div class="lbl">Alpha</div>
                        </div>
                        <div class="summary-card">
                            <div class="num" style="color:#9a3412;"><?php echo $counts['terlambat']; ?></div>
                            <div class="lbl">Terlambat</div>
                        </div>
                        <div class="summary-card">
                            <div class="num" style="color:#1e293b;"><?php echo $total_students; ?></div>
                            <div class="lbl">Total Siswa</div>
                        </div>
                    </div>

                    <!-- Table -->
                    <div class="page-table-wrap">
                    <table class="page-table">
                        <thead>
                            <tr>
                                <th class="num-col">No</th>
                                <th class="name-col">Nama Siswa</th>
                                <th>NIS</th>
                                <th>Status Kehadiran</th>
                                <th>Waktu Absen</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($data['students'] as $i => $s): ?>
                            <tr>
                                <td class="num-col"><?php echo $i + 1; ?></td>
                                <td class="name-col"><?php echo htmlspecialchars($s['student_name']); ?></td>
                                <td style="color:#64748b;"><?php echo htmlspecialchars($s['nis'] ?? '-'); ?></td>
                                <td>
                                    <?php
            $status = $s['status'];
            if ($status === 'hadir'): ?>
                                        <span class="status-badge st-hadir"><svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' style='display:inline-block; vertical-align:middle; line-height:1;'><polyline points='20 6 9 17 4 12'/></svg> Hadir</span>
                                    <?php
            elseif ($status === 'sakit'): ?>
                                        <span class="status-badge st-sakit"><svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' style='display:inline-block; vertical-align:middle; line-height:1;'><circle cx='12' cy='12' r='10'/><path d='M8 14s1.5 2 4 2 4-2 4-2'/><line x1='9' y1='9' x2='9.01' y2='9'/><line x1='15' y1='9' x2='15.01' y2='9'/></svg> Sakit</span>
                                    <?php
            elseif ($status === 'izin'): ?>
                                        <span class="status-badge st-izin"><svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' style='display:inline-block; vertical-align:middle; line-height:1;'><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg> Izin</span>
                                    <?php
            elseif ($status === 'alpha'): ?>
                                        <span class="status-badge st-alpha"><svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' style='display:inline-block; vertical-align:middle; line-height:1;'><circle cx='12' cy='12' r='10'/><path d='M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3'/><line x1='12' y1='17' x2='12.01' y2='17'/></svg> Alpha</span>
                                    <?php
            elseif ($status === 'terlambat'): ?>
                                        <span class="status-badge st-terlambat">Ã¢ÂÂ° Terlambat</span>
                                    <?php
            else: ?>
                                        <span class="status-badge st-none">-</span>
                                    <?php
            endif; ?>
                                </td>
                                <td style="color:#64748b; font-size:0.8rem; font-family:monospace;">
                                    <?php echo $s['time'] ? date('H:i', strtotime($s['time'])) : '-'; ?>
                                </td>
                            </tr>
                            <?php
        endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                </div>

                <?php
    endforeach; ?>

            <?php
endif; ?>

        </div>
    </main>
</div>

</body>
</html>


