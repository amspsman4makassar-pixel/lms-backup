<?php
// src/pimpinan/siswa_list.php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['kepsek', 'wakasek'])) {
    header("Location: ../../login.php");
    exit;
}

$search_query = $_GET['search'] ?? '';
$filter_class = $_GET['class_id'] ?? '';

try {
    $classes = $pdo->query("SELECT * FROM classes ORDER BY grade_level, LENGTH(name), name")->fetchAll();
}
catch (PDOException $e) {
    $classes = [];
}

$sql = "SELECT users.*, classes.name as class_name 
        FROM users 
        LEFT JOIN classes ON users.class_id = classes.id 
        WHERE users.role = 'siswa' ";

$params = [];

if ($search_query) {
    $sql .= " AND (users.full_name LIKE ? OR users.username LIKE ? OR users.nis LIKE ?) ";
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
}

if ($filter_class) {
    $sql .= " AND users.class_id = ? ";
    $params[] = $filter_class;
}

$sql .= " ORDER BY classes.grade_level ASC, classes.name ASC, users.full_name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$siswaList = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta charset="UTF-8">
    <title>Direktori Siswa - Pimpinan</title>
    <link rel="stylesheet" href="/public/assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .role-badge { padding: 4px 10px; border-radius: 20px; font-weight: 700; font-size: 0.75rem; text-transform: uppercase; }


        .user-info { display: flex; align-items: center; gap: 1rem; }
        .user-avatar-placeholder { width: 40px; height: 40px; border-radius: 50%; background: #f1f5f9; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; }
        .user-avatar { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; }
    </style>
</head>
<body style="background-color: #f8fafc;">

<div class="app-container">
    <?php include '../templates/sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Page Toolbar -->
        <div class="page-toolbar">
            <div class="page-toolbar-left">
                <h1 class="page-title">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:inline-block; vertical-align:middle; line-height:1; margin-right: 6px;"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
                    Direktori Siswa
                </h1>
                <p class="page-subtitle">Pantau data seluruh siswa di sekolah berdasarkan kelas.</p>
            </div>
        </div>
        
        <div class="page-content">
            <div class="page-section">
                <form method="GET" style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <input type="text" name="search" placeholder="Cari nama, NIS, username..." value="<?php echo htmlspecialchars($search_query); ?>" style="flex: 2; min-width: 200px; padding: 0.75rem 1rem; border: 1px solid #e2e8f0; border-radius: 8px; font-family: inherit;">
                    
                    <select name="class_id" style="flex: 1; min-width: 150px; padding: 0.75rem 1rem; border: 1px solid #e2e8f0; border-radius: 8px; font-family: inherit; background: white;">
                        <option value="">Semua Kelas</option>
                        <?php foreach ($classes as $c): ?>
                            <option value="<?php echo $c['id']; ?>" <?php echo $filter_class == $c['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($c['name']); ?>
                            </option>
                        <?php
endforeach; ?>
                    </select>

                    <button type="submit" style="background: #0ea5e9; color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 8px; font-weight: 600; cursor: pointer;">Filter</button>
                    <!-- Reset Button -->
                    <?php if ($search_query || $filter_class): ?>
                        <a href="siswa_list.php" style="background: #f1f5f9; color: #475569; text-decoration: none; padding: 0.75rem 1.5rem; border-radius: 8px; font-weight: 600; display: flex; align-items: center; justify-content: center;">Reset</a>
                    <?php
endif; ?>
                </form>
            </div>

            <div class="page-table-wrap">
                <table class="page-table">
                    <thead>
                        <tr>
                            <th>Siswa</th>
                            <th>Kelas</th>
                            <th>Gender & Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($siswaList)): ?>
                            <tr>
                                <td colspan="3" style="text-align: center; color: #64748b; padding: 2rem;">Tidak ada data siswa ditemukan.</td>
                            </tr>
                        <?php
else: ?>
                            <?php foreach ($siswaList as $u): ?>
                            <tr>
                                <td>
                                    <div class="user-info">
                                        <?php if ($u['photo_path']): ?>
                                            <img src="/<?php echo htmlspecialchars($u['photo_path']); ?>" class="user-avatar">
                                        <?php
        else: ?>
                                            <div class="user-avatar-placeholder"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block; vertical-align:middle; line-height:1;"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></div>
                                        <?php
        endif; ?>
                                        <div>
                                            <strong style="color: #0f172a; font-size: 0.95rem;"><?php echo htmlspecialchars($u['full_name']); ?></strong><br>
                                            <span style="color: #64748b; font-size: 0.8rem;">@<?php echo htmlspecialchars($u['username']); ?></span>
                                            <?php if ($u['nis']): ?>
                                                <div style="font-size: 0.75rem; color: #475569;">NIS: <?php echo htmlspecialchars($u['nis']); ?></div>
                                            <?php
        endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($u['class_name']): ?>
                                        <span style="background: #f1f5f9; color: #334155; padding: 4px 10px; border-radius: 6px; font-size: 0.85rem; font-weight: 500; display: inline-block;">
                                            Kelas <?php echo htmlspecialchars($u['class_name']); ?>
                                        </span>
                                    <?php
        else: ?>
                                        <span style="color: #94a3b8; font-style: italic; font-size: 0.85rem;">Belum diatur</span>
                                    <?php
        endif; ?>
                                </td>
                                <td>
                                    <?php if ($u['gender'])
            echo($u['gender'] == 'L' ? '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block; vertical-align:middle; line-height:1;"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg> Laki-laki' : '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block; vertical-align:middle; line-height:1;"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg> Perempuan') . '<br>'; ?>
                                    <div style="margin-top: 6px;">
                                        <?php if ($u['status'] == 'active'): ?>
                                            <span style="color: #10b981; font-size: 0.8rem; font-weight: 600;"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block; vertical-align:middle; line-height:1;"><polyline points="20 6 9 17 4 12"/></svg> AKTIF</span>
                                        <?php
        elseif ($u['status'] == 'graduated'): ?>
                                            <span style="color: #64748b; font-size: 0.8rem; font-weight: 600;"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block; vertical-align:middle; line-height:1;"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg> TAMAT</span>
                                        <?php
        elseif ($u['status'] == 'suspended'): ?>
                                            <span style="color: #ef4444; font-size: 0.8rem; font-weight: 600;"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block; vertical-align:middle; line-height:1;"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg> SUSPEND</span>
                                        <?php
        else: ?>
                                            <span style="color: #64748b; font-size: 0.8rem; font-weight: 600; text-transform: uppercase;"><?php echo htmlspecialchars($u['status']); ?></span>
                                        <?php
        endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php
    endforeach; ?>
                        <?php
endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div style="margin-top: 1rem; font-size: 0.85rem; color: #64748b; text-align: right;">
                Total Siswa: <?php echo count($siswaList); ?>
            </div>
            </div>
        </div>
    </main>
</div>

</body>
</html>


