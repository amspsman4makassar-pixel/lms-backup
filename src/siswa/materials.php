<?php
// src/siswa/materials.php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'siswa') {
    header("Location: ../../login.php");
    exit;
}

// Get Student's Class
$stmt = $pdo->prepare("SELECT class_id FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user_data = $stmt->fetch();
$class_id = $user_data['class_id'];
$subject_id = null;
$current_subject = null;

// Fetch Materials for this Class OR Global
if ($class_id) {
    $subject_id = $_GET['subject_id'] ?? null;
    $is_general = isset($_GET['general']);
    $current_subject = null;

    if ($subject_id || $is_general) {
        $condition = $is_general ? "COALESCE(m.subject_id, u.subject_id) IS NULL" : "COALESCE(m.subject_id, u.subject_id) = ?";

        $sql = "SELECT m.*, u.full_name as teacher_name, 
                COALESCE(s_mat.name, s_user.name) as subject_name 
                FROM materials m 
                JOIN users u ON m.teacher_id = u.id 
                LEFT JOIN subjects s_user ON u.subject_id = s_user.id
                LEFT JOIN subjects s_mat ON m.subject_id = s_mat.id
                WHERE (m.class_id = ? OR m.class_id IS NULL)
                AND $condition
                ORDER BY m.created_at DESC";
        $stmt = $pdo->prepare($sql);
        if ($is_general) {
            $stmt->execute([$class_id]);
            $current_subject = "Umum / Lainnya";
        } else {
            $stmt->execute([$class_id, $subject_id]);
        }
        $materials = $stmt->fetchAll();

        if ($subject_id && !$current_subject) {
            $stmt_sub = $pdo->prepare("SELECT name FROM subjects WHERE id = ?");
            $stmt_sub->execute([$subject_id]);
            $current_subject = $stmt_sub->fetchColumn();
        }
    } else {
        $sql = "SELECT 
                    COALESCE(m.subject_id, u.subject_id) as id, 
                    COALESCE(s_mat.name, s_user.name) as name, 
                    COUNT(m.id) as material_count
                FROM materials m
                JOIN users u ON m.teacher_id = u.id
                LEFT JOIN subjects s_user ON u.subject_id = s_user.id
                LEFT JOIN subjects s_mat ON m.subject_id = s_mat.id
                WHERE (m.class_id = ? OR m.class_id IS NULL)
                GROUP BY id, name
                ORDER BY name ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$class_id]);
        $subjects = $stmt->fetchAll();
        $materials = [];
    }
} else {
    $materials = [];
    $warning = "Anda belum dimasukkan ke dalam kelas. Hubungi Admin.";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Materi Pelajaran</title>
    <link rel="stylesheet" href="/public/assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .main-content { background: #f5f7fb !important; padding: 0 !important; max-width: 100% !important; }

        .page-hero {
            background: linear-gradient(135deg, #1e1b4b 0%, #312e81 50%, #4338ca 100%);
            padding: 2.5rem 3rem 4rem;
            position: relative; overflow: hidden;
        }
        .page-hero::after {
            content: '';
            position: absolute;
            width: 400px; height: 400px; top:-200px; right:-100px;
            background: radial-gradient(circle, rgba(129,140,248,.25) 0%, transparent 60%);
            border-radius: 50%; pointer-events: none;
        }
        .hero-inner { position: relative; z-index: 2; }
        .hero-inner h1 { font-size: 1.6rem; font-weight: 800; color: #fff; margin-bottom: 0.3rem; }
        .hero-sub { color: rgba(255,255,255,0.6); font-size: 0.9rem; }

        .page-content {
            position: relative;
            margin-top: -2rem;
            padding: 0 3rem 3rem;
            z-index: 10;
        }

        /* ── Breadcrumb / nav ── */
        .page-nav {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
            font-size: 0.82rem;
        }
        .page-nav a { color: #4f46e5; text-decoration: none; font-weight: 600; }
        .page-nav a:hover { text-decoration: underline; }
        .page-nav-sep { color: #cbd5e1; }
        .page-nav-cur { color: #1e293b; font-weight: 600; }

        /* ── Subject list (bukan grid card) ── */
        .subject-list { display: flex; flex-direction: column; gap: 0; }
        .subject-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px 20px;
            text-decoration: none;
            color: inherit;
            border-bottom: 1px solid #f1f5f9;
            background: #fff;
            transition: background 0.15s;
        }
        .subject-item:first-child { border-radius: 12px 12px 0 0; }
        .subject-item:last-child { border-bottom: none; border-radius: 0 0 12px 12px; }
        .subject-item:only-child { border-radius: 12px; }
        .subject-item:hover { background: #fafbff; }
        .subject-ico {
            width: 40px; height: 40px;
            border-radius: 10px;
            background: #eef2ff;
            color: #4f46e5;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .subject-info { flex: 1; min-width: 0; }
        .subject-name { font-size: 0.92rem; font-weight: 700; color: #1e293b; }
        .subject-count { font-size: 0.75rem; color: #94a3b8; margin-top: 2px; }
        .subject-arrow { color: #cbd5e1; }

        /* ── Material list rows ── */
        .material-list { display: flex; flex-direction: column; gap: 0; }
        .material-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px 20px;
            border-bottom: 1px solid #f1f5f9;
            background: #fff;
        }
        .material-item:first-child { border-radius: 12px 12px 0 0; }
        .material-item:last-child { border-bottom: none; border-radius: 0 0 12px 12px; }
        .material-item:only-child { border-radius: 12px; }
        .mat-ico {
            width: 38px; height: 38px;
            border-radius: 9px;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .mat-ico.pdf   { background: #fee2e2; color: #dc2626; }
        .mat-ico.video { background: #dbeafe; color: #2563eb; }
        .mat-ico.ppt   { background: #fef3c7; color: #d97706; }
        .mat-ico.link  { background: #d1fae5; color: #059669; }
        .mat-ico.epub  { background: #fce7f3; color: #be185d; }
        .mat-ico.doc   { background: #ede9fe; color: #7c3aed; }
        .mat-info { flex: 1; min-width: 0; }
        .mat-title { font-size: 0.88rem; font-weight: 700; color: #1e293b; margin-bottom: 2px; }
        .mat-meta  { font-size: 0.73rem; color: #94a3b8; }
        .mat-type-badge {
            font-size: 0.62rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.04em;
            padding: 2px 7px; border-radius: 4px;
            background: #f1f5f9; color: #64748b;
            margin-left: 6px;
        }
        .mat-download-btn {
            display: inline-flex; align-items: center; gap: 5px;
            background: #4f46e5; color: #fff;
            padding: 6px 14px; border-radius: 7px;
            font-size: 0.78rem; font-weight: 700;
            text-decoration: none;
            white-space: nowrap; flex-shrink: 0;
            transition: background 0.15s;
        }
        .mat-download-btn:hover { background: #4338ca; }

        .empty-state {
            text-align: center; padding: 3rem 1rem;
            color: #94a3b8; font-size: 0.88rem;
            background: #fff; border-radius: 12px;
        }
        .empty-state svg { opacity: 0.35; margin-bottom: 10px; }

        @media (max-width: 900px) {
            .page-hero { padding: 2rem 1.5rem 3.5rem; }
            .page-content { padding: 0 1.2rem 2rem; }
        }
        @media (max-width: 768px) {
            .mat-download-btn { padding: 5px 10px; font-size: 0.72rem; }
        }
    </style>
</head>
<body class="admin-full-layout">

<div class="app-container">
    <?php include '../templates/sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Hero -->
        <div class="page-hero">
            <div class="hero-inner">
                <h1>
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;vertical-align:middle;margin-right:8px;"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg>
                    Materi Pelajaran
                </h1>
                <p class="hero-sub">Akses materi yang dibagikan guru Anda.</p>
            </div>
        </div>

        <div class="page-content">

        <?php if (isset($warning))
            echo "<div style='background:#fffbeb; color:#b45309; padding:12px 16px; border-radius:8px; margin-bottom:16px; font-weight:600; border:1px solid #fcd34d; font-size:0.88rem;'>⚠ $warning</div>"; ?>

        <?php if ($subject_id || isset($_GET['general'])): ?>
            <!-- Breadcrumb -->
            <div class="page-nav">
                <a href="materials.php">Semua Mapel</a>
                <span class="page-nav-sep">›</span>
                <span class="page-nav-cur"><?php echo htmlspecialchars($current_subject ?? ''); ?></span>
            </div>
        <?php endif; ?>

        <?php if (!$subject_id && !isset($_GET['general'])): ?>
            <!-- DAFTAR MATA PELAJARAN -->
            <?php if (empty($subjects)): ?>
                <div class="empty-state">
                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg>
                    <p>Belum ada materi pelajaran untuk kelas Anda.</p>
                </div>
            <?php else: ?>
                <div class="subject-list">
                    <?php foreach ($subjects as $sub):
                        $link = $sub['id'] ? "?subject_id=" . $sub['id'] : "?general=1";
                        $name = $sub['name'] ?? "Umum / Lainnya";
                    ?>
                    <a href="<?php echo $link; ?>" class="subject-item">
                        <div class="subject-ico">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg>
                        </div>
                        <div class="subject-info">
                            <div class="subject-name"><?php echo htmlspecialchars($name); ?></div>
                            <div class="subject-count"><?php echo $sub['material_count']; ?> materi tersedia</div>
                        </div>
                        <div class="subject-arrow">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <!-- DAFTAR MATERI -->
            <?php if (empty($materials)): ?>
                <div class="empty-state">
                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg>
                    <p>Belum ada materi untuk mata pelajaran ini.</p>
                </div>
            <?php else: ?>
                <div class="material-list">
                    <?php foreach ($materials as $m):
                        $typeIcon = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>';
                        $typeClass = 'doc';
                        if ($m['type'] === 'pdf') { $typeIcon = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg>'; $typeClass = 'pdf'; }
                        elseif ($m['type'] === 'video') { $typeIcon = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2" ry="2"/></svg>'; $typeClass = 'video'; }
                        elseif ($m['type'] === 'ppt') { $typeIcon = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>'; $typeClass = 'ppt'; }
                        elseif ($m['type'] === 'epub') { $typeIcon = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>'; $typeClass = 'epub'; }
                        elseif ($m['type'] === 'link') { $typeIcon = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>'; $typeClass = 'link'; }
                        $href = $m['file_path'];
                        if ($m['type'] !== 'link' && strpos($href, 'http') !== 0) { $href = '../../' . $href; }
                        $btnLabel = ($m['type'] === 'link') ? 'Buka Link' : 'Lihat Materi';
                    ?>
                    <div class="material-item">
                        <div class="mat-ico <?php echo $typeClass; ?>"><?php echo $typeIcon; ?></div>
                        <div class="mat-info">
                            <div class="mat-title">
                                <?php echo htmlspecialchars($m['title']); ?>
                                <span class="mat-type-badge"><?php echo strtoupper(htmlspecialchars($m['type'])); ?></span>
                            </div>
                            <div class="mat-meta">
                                <?php echo htmlspecialchars($m['teacher_name']); ?> · <?php echo date('d M Y', strtotime($m['created_at'])); ?>
                                <?php if (!empty($m['description'])): ?>
                                    · <?php echo htmlspecialchars(mb_substr($m['description'], 0, 60)) . (mb_strlen($m['description']) > 60 ? '…' : ''); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <a href="<?php echo htmlspecialchars($href); ?>" target="_blank" class="mat-download-btn">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                            <?php echo $btnLabel; ?>
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        </div>
    </main>
</div>

</body>
</html>
