<?php
// src/profile.php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$is_admin = ($role === 'admin');

$success = "";
$error = "";

$stmt = $pdo->prepare("
    SELECT users.*, classes.name as class_name, subjects.name as subject_name 
    FROM users 
    LEFT JOIN classes ON users.class_id = classes.id 
    LEFT JOIN subjects ON users.subject_id = subjects.id 
    WHERE users.id = ?
");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    header("Location: ../login.php");
    exit;
}

// Handle Profile Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password = trim($_POST['new_password'] ?? '');

    // Admins can update full name, gender, address
    // Regular users can only update password
    if ($is_admin) {
        $full_name = trim($_POST['full_name']);
        $gender = $_POST['gender'];
        $address = trim($_POST['address']);
    }
    else {
        $full_name = $user['full_name'] ?? '';
        $gender = $user['gender'] ?? '';
        $address = $user['address'] ?? '';
    }

    $photo_path = null;
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] == 0 && $is_admin) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed)) {
            $dir = "../public/uploads/profiles/";
            if (!file_exists($dir))
                mkdir($dir, 0777, true);
            $new_name = "user_" . $user_id . "_" . time() . "." . $ext;
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $dir . $new_name)) {
                $photo_path = "public/uploads/profiles/" . $new_name;
            }
        }
        else {
            $error = "Format foto tidak valid.";
        }
    }

    if (empty($error)) {
        // Build Query
        $updates = [];
        $params = [];

        if ($is_admin) {
            $updates[] = "full_name = ?";
            $params[] = $full_name;
            $updates[] = "gender = ?";
            $params[] = $gender;
            $updates[] = "address = ?";
            $params[] = $address;
        }

        if ($photo_path) {
            $updates[] = "photo_path = ?";
            $params[] = $photo_path;
        }

        if (!empty($new_password)) {
            if (!$is_admin && isset($user['password_changed']) && $user['password_changed'] == 1) {
                $error = "Anda hanya diizinkan mengganti password satu kali secara mandiri. Silakan hubungi admin jika ingin menggantinya lagi.";
            } elseif (strlen($new_password) < 6) {
                $error = "Password baru minimal 6 karakter.";
            } else {
                $updates[] = "password = ?";
                $params[] = password_hash($new_password, PASSWORD_DEFAULT);
                if (!$is_admin) {
                    $updates[] = "password_changed = 1";
                }
            }
        }

        if (!empty($updates)) {
            $sql = "UPDATE users SET " . implode(", ", $updates) . " WHERE id = ?";
            $params[] = $user_id;

            $stmt = $pdo->prepare($sql);
            if ($stmt->execute($params)) {
                $success = "Perubahan berhasil disimpan!";
                if ($is_admin) {
                    $_SESSION['full_name'] = $full_name;
                }
                error_log('[LMS profile] User ' . (int) $user_id . ' (role=' . $role . ') menyimpan perubahan profil.');
                $stmt = $pdo->prepare("
                    SELECT users.*, classes.name as class_name, subjects.name as subject_name 
                    FROM users 
                    LEFT JOIN classes ON users.class_id = classes.id 
                    LEFT JOIN subjects ON users.subject_id = subjects.id 
                    WHERE users.id = ?
                ");
                $stmt->execute([$user_id]);
                $refreshed = $stmt->fetch();
                if ($refreshed) {
                    $user = $refreshed;
                }
            }
            else {
                $error = "Gagal simpan database.";
            }
        }
        else {
            if (!$is_admin) {
                $success = "Tidak ada perubahan untuk disimpan.";
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta charset="UTF-8">
    <title>Profil Saya</title>
    <link rel="stylesheet" href="/public/assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .profile-container {
            display: flex;
            flex-direction: column;
            gap: 2.5rem;
        }
        @media (min-width: 768px) {
            .profile-container {
                flex-direction: row;
                align-items: flex-start;
            }
        }
        .profile-sidebar {
            width: 100%;
            text-align: center;
        }
        @media (min-width: 768px) {
            .profile-sidebar {
                width: 250px;
                flex-shrink: 0;
            }
        }
        .profile-main {
            flex-grow: 1;
            width: 100%;
        }
        .photo-wrapper {
            width: 160px;
            height: 160px;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            border-radius: 50%;
            padding: 8px;
            margin: 0 auto 1.5rem;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
            position: relative;
        }
        .photo-inner {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            overflow: hidden;
            background: white;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.06);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .photo-inner img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .photo-inner .placeholder {
            font-size: 4rem;
            color: #cbd5e1;
        }
        .readonly-input {
            background-color: #f8fafc !important;
            border-color: #e2e8f0 !important;
            color: #475569 !important;
            font-weight: 500;
            cursor: default !important;
            box-shadow: none !important;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr;
            gap: 15px;
        }
        @media (min-width: 600px) {
            .form-row {
                grid-template-columns: 1fr 1fr;
            }
        }
    </style>
</head>
<body class="admin-full-layout">

<div class="app-container">
    <?php include 'templates/sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Page Toolbar (Role-aware) -->
        <div class="page-toolbar" data-lms-role="<?php echo $role; ?>">
            <div class="page-toolbar-left">
                <h1 class="page-title">Profil Saya</h1>
                <p class="page-subtitle">Kelola informasi profil dan pengaturan keamanan akun Anda secara mandiri.</p>
            </div>
        </div>

        <div class="page-content">
            <div class="container-fluid">

        <div>
            <?php if (!$is_admin): ?>
                <div style="background: #eff6ff; border-left: 4px solid #3b82f6; padding: 1rem; border-radius: 8px; color: #1e40af; font-size: 0.9rem; margin-bottom: 1.5rem; max-width: 800px; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);">
                    <strong>Mode Lihat Saja:</strong><br>
                    Untuk mengubah foto atau biodata silakan hubungi <strong>Administrator Sekolah</strong>. Anda hanya dapat mengganti password sebanyak satu kali di bawah ini.
                </div>
            <?php
endif; ?>

            <div class="page-section" style="max-width: 800px; margin: 0 auto;">
                <?php if ($success)
    echo "<div class='badge badge-success' style='display:block; padding: 1rem; margin-bottom: 1.5rem; text-align:center;'>$success</div>"; ?>
                <?php if ($error)
    echo "<div class='badge badge-danger' style='display:block; padding: 1rem; margin-bottom: 1.5rem; text-align:center;'>$error</div>"; ?>

            <form method="POST" enctype="multipart/form-data" class="profile-container">
                <!-- Left Column: Photo -->
                <div class="profile-sidebar">
                    <div class="photo-wrapper">
                        <div class="photo-inner">
                            <?php if ($user['photo_path']): ?>
                                <img src="/<?php echo $user['photo_path']; ?>" alt="Foto Profil">
                            <?php
else: ?>
                                <div class="placeholder"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block; vertical-align:middle; line-height:1;"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></div>
                            <?php
endif; ?>
                        </div>
                    </div>
                    
                    <?php if ($is_admin): ?>
                    <label class="btn btn-secondary" style="cursor: pointer; display: inline-block;">
                        Ganti Foto
                        <input type="file" name="photo" style="display: none;" onchange="alert('Foto terpilih! Klik Simpan Perubahan untuk mengupload.')">
                    </label>
                    <?php
endif; ?>
                </div>

                <!-- Right Column: Details -->
                <div class="profile-main">
                    <!-- Full Name -->
                    <div class="form-group">
                        <label>Nama Lengkap</label>
                        <input type="text" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" 
                               <?php echo $is_admin ? 'required' : 'readonly class="readonly-input"'; ?>>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Username (Login)</label>
                            <input type="text" value="<?php echo htmlspecialchars($user['username']); ?>" readonly class="readonly-input">
                        </div>
                        <div class="form-group">
                            <label>Role / Akses</label>
                            <input type="text" value="<?php echo strtoupper($user['role']); ?>" readonly class="readonly-input">
                        </div>
                    </div>

                    <div class="form-row">
                        <?php if ($user['role'] == 'guru' && $user['nip']): ?>
                            <div class="form-group">
                                <label>NIP</label>
                                <input type="text" value="<?php echo htmlspecialchars($user['nip']); ?>" readonly class="readonly-input">
                            </div>
                        <?php
elseif ($user['role'] == 'siswa' && $user['nis']): ?>
                            <div class="form-group">
                                <label>NIS</label>
                                <input type="text" value="<?php echo htmlspecialchars($user['nis']); ?>" readonly class="readonly-input">
                            </div>
                        <?php
endif; ?>
                        
                        <?php if ($user['class_name']): ?>
                            <div class="form-group">
                                <label>Kelas Saat Ini</label>
                                <input type="text" value="<?php echo htmlspecialchars($user['class_name']); ?>" readonly class="readonly-input" style="color: #059669 !important; font-weight: 600;">
                            </div>
                        <?php
endif; ?>

                        <?php if ($user['subject_name']): ?>
                            <div class="form-group">
                                <label>Mata Pelajaran Diampu</label>
                                <input type="text" value="<?php echo htmlspecialchars($user['subject_name']); ?>" readonly class="readonly-input">
                            </div>
                        <?php
endif; ?>
                    </div>

                    <div class="form-group">
                        <label>Jenis Kelamin</label>
                        <?php if ($is_admin): ?>
                            <select name="gender">
                                <option value="">-- Pilih --</option>
                                <option value="L" <?php echo($user['gender'] == 'L') ? 'selected' : ''; ?>>Laki-laki</option>
                                <option value="P" <?php echo($user['gender'] == 'P') ? 'selected' : ''; ?>>Perempuan</option>
                            </select>
                        <?php
else: ?>
                             <input type="text" value="<?php echo($user['gender'] == 'L' ? 'Laki-laki' : ($user['gender'] == 'P' ? 'Perempuan' : '-')); ?>" 
                                    readonly class="readonly-input">
                        <?php
endif; ?>
                    </div>

                    <div class="form-group">
                        <label>Alamat Lengkap</label>
                        <?php if ($is_admin): ?>
                            <textarea name="address" rows="3"><?php echo htmlspecialchars($user['address']); ?></textarea>
                        <?php
else: ?>
                            <textarea name="address" rows="3" readonly class="readonly-input"><?php echo htmlspecialchars($user['address']); ?></textarea>
                        <?php
endif; ?>
                    </div>

                    <hr style="margin: 25px 0; border: 0; border-top: 2px dashed #e2e8f0;">

                    <div class="form-group" style="position: relative;">
                        <label style="color: #4f46e5; font-weight: 600;"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block; vertical-align:middle; line-height:1;"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg> Password Baru (Opsional)</label>
                        <input type="password" id="newPasswordInput" name="new_password" placeholder="Kosongkan jika tidak ingin mengganti password" style="padding-right: 40px;">
                        <button type="button" onclick="togglePasswordInput('newPasswordInput', this)" style="position: absolute; right: 10px; top: 35px; background: none; border: none; cursor: pointer; color: #64748b; padding: 0;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>

                    <div style="margin-top: 2rem;">
                        <button type="submit" class="btn" style="width: 100%; padding: 12px; font-weight: 600; font-size: 1rem;"><i class="fas fa-save"></i> Simpan Perubahan Profil</button>
                    </div>
                </div>
            </form>
        </div>
        </div>
            </div>
        </div>
    </main>
</div>

<script>
function togglePasswordInput(inputId, btn) {
    const p = document.getElementById(inputId);
    if (p.type === 'password') {
        p.type = 'text';
        btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';
    } else {
        p.type = 'password';
        btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
    }
}
</script>

</body>
</html>


