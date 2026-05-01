<?php
// src/admin/add_user.php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../login.php");
    exit;
}

$edit_mode = false;
$edit_user = null;
$error = "";
$success = "";

// Fetch Classes & Subjects for Dropdowns
try {
    $classes = $pdo->query("SELECT * FROM classes ORDER BY grade_level, LENGTH(name), name")->fetchAll();
    $subjects = $pdo->query("SELECT * FROM subjects ORDER BY name")->fetchAll();
}
catch (PDOException $e) {
    die("Error Database: " . $e->getMessage());
}

// Handle Edit Fetch
if (isset($_GET['edit'])) {
    $edit_mode = true;
    $id = $_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $edit_user = $stmt->fetch();
    if (!$edit_user) {
        header("Location: manage_users.php");
        exit;
    }
}

// Handle Create/Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Sanitize & Basic Validation
    $username = trim($_POST['username'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $role = $_POST['role'] ?? '';
    $gender = $_POST['gender'] ?? null;
    $address = $_POST['address'] ?? null;
    $status = $_POST['status'] ?? 'active';

    // Auto-map NIP/NIS from Username (Identity Number)
    $nip = (in_array($role, ['guru', 'admin', 'kepsek', 'wakasek', 'bk'], true)) ? $username : null;
    $nis = ($role == 'siswa') ? $username : null;

    // Optional fields
    $class_id = ($_POST['role'] == 'siswa' && !empty($_POST['class_id'])) ? $_POST['class_id'] : null;
    $subject_id = ($_POST['role'] == 'guru' && !empty($_POST['subject_id'])) ? $_POST['subject_id'] : null;

    // File Upload Handler
    $photo_path = null;
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['photo']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (in_array($ext, $allowed)) {
            $new_name = time() . '_' . rand(1000, 9999) . '.' . $ext;
            $upload_dir = '../../public/uploads/profiles/';
            if (!file_exists($upload_dir))
                mkdir($upload_dir, 0777, true);

            if (move_uploaded_file($_FILES['photo']['tmp_name'], $upload_dir . $new_name)) {
                $photo_path = 'public/uploads/profiles/' . $new_name;
            }
        }
    }

    // 2. Strict Empty Check
    if (empty($username) || empty($full_name) || empty($role)) {
        $error = "<svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' style='display:inline-block; vertical-align:middle; line-height:1;'><line x1='18' y1='6' x2='6' y2='18'/><line x1='6' y1='6' x2='18' y2='18'/></svg> Harap isi bidang wajib: Username, Nama Lengkap, dan Role.";
    }
    elseif ($role == 'siswa' && empty($class_id) && $status == 'active') {
        $error = "<svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' style='display:inline-block; vertical-align:middle; line-height:1;'><path d='m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z'/><line x1='12' y1='9' x2='12' y2='13'/><line x1='12' y1='17' x2='12.01' y2='17'/></svg> Peringatan: Anda memilih role SISWA tetapi belum memilih KELAS. Mohon pilih kelas.";
    }
    elseif ($role == 'guru' && empty($subject_id)) {
        $error = "<svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' style='display:inline-block; vertical-align:middle; line-height:1;'><path d='m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z'/><line x1='12' y1='9' x2='12' y2='13'/><line x1='12' y1='17' x2='12.01' y2='17'/></svg> Peringatan: Data GURU sebaiknya memiliki Mata Pelajaran.";
    }
    else {
        try {
            if ($edit_mode) {
                // UPDATE
                $id = $_POST['user_id'];
                $password = $_POST['password'];

                $sql = "UPDATE users SET username=?, full_name=?, role=?, gender=?, address=?, status=?, class_id=?, subject_id=?, nip=?, nis=?";
                $params = [$username, $full_name, $role, $gender, $address, $status, $class_id, $subject_id, $nip, $nis];

                if (!empty($password)) {
                    $sql .= ", password=?, password_changed=0";
                    $params[] = password_hash($password, PASSWORD_DEFAULT);
                }

                if ($photo_path) {
                    $sql .= ", photo_path=?";
                    $params[] = $photo_path;
                }

                $sql .= " WHERE id=?";
                $params[] = $id;

                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $_SESSION['flash'] = "<svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' style='display:inline-block; vertical-align:middle; line-height:1;'><polyline points='20 6 9 17 4 12'/></svg> User berhasil diupdate!";
                header("Location: manage_users.php");
                exit;

            }
            else {
                // CREATE
                $password = $_POST['password'] ?? '';

                if (empty($password)) {
                    $error = "<svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' style='display:inline-block; vertical-align:middle; line-height:1;'><line x1='18' y1='6' x2='6' y2='18'/><line x1='6' y1='6' x2='18' y2='18'/></svg> Password wajib diisi untuk user baru.";
                }
                else {
                    $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, role, gender, address, status, class_id, subject_id, photo_path, nip, nis) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$username, $password, $full_name, $role, $gender, $address, $status, $class_id, $subject_id, $photo_path, $nip, $nis]);
                    $_SESSION['flash'] = "<svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' style='display:inline-block; vertical-align:middle; line-height:1;'><polyline points='20 6 9 17 4 12'/></svg> User berhasil ditambahkan!";
                    header("Location: manage_users.php");
                    exit;
                }
            }
        }
        catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                $error = "<svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' style='display:inline-block; vertical-align:middle; line-height:1;'><line x1='18' y1='6' x2='6' y2='18'/><line x1='6' y1='6' x2='18' y2='18'/></svg> Username '$username' sudah terdaftar. Gunakan yang lain.";
            }
            else {
                $error = "<svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' style='display:inline-block; vertical-align:middle; line-height:1;'><line x1='18' y1='6' x2='6' y2='18'/><line x1='6' y1='6' x2='18' y2='18'/></svg> Terjadi kesalahan sistem: " . $e->getMessage();
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
    <title><?php echo $edit_mode ? "Edit Pengguna" : "Tambah Pengguna Baru"; ?> - Admin</title>
    <link rel="stylesheet" href="/public/assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>
        function toggleFields() {
            const role = document.getElementById('roleSelect').value;
            const classGroup = document.getElementById('classGroup');
            const subjectGroup = document.getElementById('subjectGroup');
            const statusOptActive = document.getElementById('statusOptActive');
            const statusOptGraduated = document.getElementById('statusOptGraduated');
            
            // Label Identity
            const identityLabel = document.getElementById('identityLabel');
            const identityInput = document.getElementById('identityInput');

            if (role === 'siswa') {
                classGroup.style.display = 'block';
                subjectGroup.style.display = 'none';
                identityLabel.innerHTML = 'NIS (Nomor Induk Siswa) <span style="color:var(--danger)">*</span>';
                identityInput.placeholder = 'Masukkan NIS';
                
                if(statusOptActive) statusOptActive.text = "Aktif / Naik Kelas";
                if(statusOptGraduated) statusOptGraduated.style.display = 'block';
            } else if (role === 'guru') {
                classGroup.style.display = 'none';
                subjectGroup.style.display = 'block';
                identityLabel.innerHTML = 'NIP (Nomor Induk Pegawai) <span style="color:var(--danger)">*</span>';
                identityInput.placeholder = 'Masukkan NIP';

                if(statusOptActive) statusOptActive.text = "Aktif"; 
                if(statusOptGraduated) statusOptGraduated.style.display = 'none'; 
            } else if (role === 'admin' || role === 'kepsek' || role === 'wakasek' || role === 'bk') {
                classGroup.style.display = 'none';
                subjectGroup.style.display = 'none';
                identityLabel.innerHTML = 'NIP / Username <span style="color:var(--danger)">*</span>';
                identityInput.placeholder = 'Masukkan NIP atau Username';

                if(statusOptActive) statusOptActive.text = "Aktif";
                if(statusOptGraduated) statusOptGraduated.style.display = 'none';
            } else {
                classGroup.style.display = 'none';
                subjectGroup.style.display = 'none';
                identityLabel.innerHTML = 'Username / ID <span style="color:var(--danger)">*</span>';
                identityInput.placeholder = 'Masukkan Username';

                if(statusOptActive) statusOptActive.text = "Aktif";
                if(statusOptGraduated) statusOptGraduated.style.display = 'none';
            }
        }
    </script>
</head>
<body>

<div class="app-container">
    <?php include '../templates/sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Unified Hero Header -->
        <div class="dashboard-hero">
            <div style="position: relative; z-index: 2;">
                <h1 style="color: white; margin-bottom: 0.5rem;"><?php echo $edit_mode ? "<svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' style='display:inline-block; vertical-align:middle; line-height:1;'><path d='M12 20h9'/><path d='M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z'/></svg> Edit Pengguna" : "<svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' style='display:inline-block; vertical-align:middle; line-height:1;'><line x1='12' y1='5' x2='12' y2='19'/><line x1='5' y1='12' x2='19' y2='12'/></svg> Tambah Pengguna Baru"; ?></h1>
                <p style="color: rgba(255,255,255,0.8);"><?php echo $edit_mode ? "Perbarui data pengguna yang sudah terdaftar." : "Daftarkan pengguna baru ke dalam sistem."; ?></p>
            </div>
            <div style="position: absolute; right: 40px; top: 50%; transform: translateY(-50%); z-index: 10;">
                <a href="manage_users.php" class="btn" style="background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.3); backdrop-filter: blur(5px);">&larr; Kembali ke Daftar</a>
            </div>
            <!-- Decorative circle -->
            <div style="position: absolute; right: -50px; top: -50px; width: 200px; height: 200px; background: rgba(255,255,255,0.1); border-radius: 50%;"></div>
        </div>

        <div class="content-overlap">
            <div class="card" style="max-width: 800px; margin: 0 auto;">
            <?php if (isset($error) && $error)
    echo "<div class='badge badge-danger' style='display:block; padding: 1rem; margin-bottom: 1rem; text-align:center;'>$error</div>"; ?>
            
            <form method="POST" enctype="multipart/form-data">
                <?php if ($edit_mode): ?>
                    <input type="hidden" name="user_id" value="<?php echo $edit_user['id']; ?>">
                    
                    <!-- Show Current Photo -->
                    <div style="text-align: center; margin-bottom: 1.5rem;">
                        <?php if ($edit_user['photo_path']): ?>
                            <img src="/<?php echo htmlspecialchars($edit_user['photo_path']); ?>" style="width: 100px; height: 100px; border-radius: 50%; object-fit: cover; border: 3px solid var(--border); margin-bottom: 10px;">
                            <p style="font-size: 0.8rem; color: var(--text-muted);">Foto Saat Ini</p>
                        <?php
    endif; ?>
                    </div>
                <?php
endif; ?>

                <div class="form-group">
                    <label>Foto Profil</label>
                    <input type="file" name="photo">
                    <small style="color: var(--text-muted);">Format: JPG, PNG. Kosongkan jika tidak ingin mengubah.</small>
                </div>

                <!-- 1. Username/Identity (NIP/NIS) -->
                <div class="form-group">
                    <label id="identityLabel">Username / NIP / NIS <span style="color:var(--danger)">*</span></label>
                    <input type="text" name="username" id="identityInput" value="<?php echo $edit_mode ? $edit_user['username'] : ''; ?>" required>
                    <small style="color: var(--text-muted); font-size: 0.75rem;">Username akan otomatis disamakan dengan NIP/NIS.</small>
                </div>
                
                <!-- 2. Password -->
                <div class="form-group" style="position: relative;">
                    <label>Password <span style="color:var(--danger)">*</span> <small style="font-weight:normal; color:var(--text-muted);">(Default: smanegeri4makassar)</small></label>
                    <input type="password" id="passwordInput" name="password" placeholder="Password User" value="<?php echo $edit_mode ? '' : 'smanegeri4makassar'; ?>" <?php echo $edit_mode ? '' : 'required'; ?> style="padding-right: 40px;">
                    <button type="button" onclick="togglePasswordInput('passwordInput', this)" style="position: absolute; right: 10px; top: 35px; background: none; border: none; cursor: pointer; color: #64748b; padding: 0;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                    <?php if ($edit_mode): ?>
                    <small style="color: var(--text-muted); font-size: 0.75rem;">Biarkan kosong jika tidak ingin mengubah password.</small>
                    <?php
endif; ?>
                </div>

                <!-- 3. Full Name -->
                <div class="form-group">
                    <label>Nama Lengkap <span style="color:var(--danger)">*</span></label>
                    <input type="text" name="full_name" value="<?php echo $edit_mode ? $edit_user['full_name'] : ''; ?>" required>
                </div>

                <!-- 4. Role -->
                <div class="form-group">
                    <label>Role</label>
                    <select name="role" id="roleSelect" onchange="toggleFields()" required>
                        <option value="guru" <?php echo($edit_mode && $edit_user['role'] == 'guru') ? 'selected' : ''; ?>>Guru</option>
                        <option value="siswa" <?php echo($edit_mode && $edit_user['role'] == 'siswa') ? 'selected' : ''; ?>>Siswa</option>
                        <option value="osis" <?php echo($edit_mode && $edit_user['role'] == 'osis') ? 'selected' : ''; ?>>OSIS</option>
                        <option value="kepsek" <?php echo($edit_mode && $edit_user['role'] == 'kepsek') ? 'selected' : ''; ?>>Kepala Sekolah</option>
                        <option value="wakasek" <?php echo($edit_mode && $edit_user['role'] == 'wakasek') ? 'selected' : ''; ?>>Wakil Kepala Sekolah</option>
                        <option value="admin" <?php echo($edit_mode && $edit_user['role'] == 'admin') ? 'selected' : ''; ?>>Administrator</option>
                        <option value="bk" <?php echo($edit_mode && $edit_user['role'] == 'bk') ? 'selected' : ''; ?>>Guru BK</option>
                    </select>
                </div>

                <!-- 5. Status -->
                <div class="form-group">
                     <label>Status Akun</label>
                     <select name="status" id="statusSelect">
                         <option id="statusOptActive" value="active" <?php echo($edit_mode && $edit_user['status'] == 'active') ? 'selected' : ''; ?>>Aktif / Naik Kelas</option>
                         <option id="statusOptGraduated" value="graduated" <?php echo($edit_mode && $edit_user['status'] == 'graduated') ? 'selected' : ''; ?>>Tamat / Lulus</option>
                         <option value="suspended" <?php echo($edit_mode && $edit_user['status'] == 'suspended') ? 'selected' : ''; ?>>Suspended</option>
                     </select>
                </div>

                <!-- 6. Dynamic Fields (Class/Subject) -->
                <div class="form-group" id="classGroup" style="display:none;">
                    <label>Kelas Siswa</label>
                    <select name="class_id">
                        <option value="">-- Pilih Kelas --</option>
                        <?php foreach ($classes as $c): ?>
                            <option value="<?php echo $c['id']; ?>" <?php echo($edit_mode && $edit_user['class_id'] == $c['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($c['name']); ?>
                            </option>
                        <?php
endforeach; ?>
                    </select>
                </div>

                <div class="form-group" id="subjectGroup" style="display:none;">
                    <label>Mata Pelajaran Guru</label>
                    <select name="subject_id">
                        <option value="">-- Pilih Mapel --</option>
                        <?php foreach ($subjects as $s): ?>
                            <option value="<?php echo $s['id']; ?>" <?php echo($edit_mode && $edit_user['subject_id'] == $s['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($s['name']); ?>
                            </option>
                        <?php
endforeach; ?>
                    </select>
                </div>
                
                <!-- 7. Gender & Address -->
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
                    <div class="form-group">
                        <label>Gender</label>
                        <select name="gender">
                            <option value="">-</option>
                            <option value="L" <?php echo($edit_mode && $edit_user['gender'] == 'L') ? 'selected' : ''; ?>>Laki-laki</option>
                            <option value="P" <?php echo($edit_mode && $edit_user['gender'] == 'P') ? 'selected' : ''; ?>>Perempuan</option>
                        </select>
                    </div>
                     <div class="form-group">
                        <label>Alamat</label>
                        <input type="text" name="address" value="<?php echo $edit_mode ? $edit_user['address'] : ''; ?>">
                    </div>
                </div>

                <button type="submit" class="btn" style="width: 100%; margin-top: 20px;"><?php echo $edit_mode ? "Simpan Perubahan" : "Buat User"; ?></button>
            </form>
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

 
 

