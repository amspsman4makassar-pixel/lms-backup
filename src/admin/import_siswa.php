<?php
// src/admin/import_users.php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../login.php");
    exit;
}

$error = "";
$success = "";
$import_report = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    if ($_FILES['file']['error'] === 0) {
        $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));

        $filename_upper = strtoupper($_FILES['file']['name']);
        $expected_grade = null;
        // Check for XII first to avoid XI matching XII
        if (preg_match('/\bXII\b/', $filename_upper)) {
            $expected_grade = 'XII';
        } elseif (preg_match('/\bXI\b/', $filename_upper)) {
            $expected_grade = 'XI';
        } elseif (preg_match('/\bX\b/', $filename_upper)) {
            $expected_grade = 'X';
        }

        $rows = [];

        // 1. Check Extension
        if ($ext === 'csv') {
            $file = fopen($_FILES['file']['tmp_name'], 'r');
            while (($data = fgetcsv($file, 1000, ",")) !== FALSE) {
                $rows[] = $data;
            }
            fclose($file);
        }
        elseif ($ext === 'xlsx' || $ext === 'xls') {
            require_once '../../src/lib/SimpleXLSX.php';
            if ($xlsx = Shuchkin\SimpleXLSX::parse($_FILES['file']['tmp_name'])) {
                // Fetch Classes for mapping
                $class_map = [];
                $class_map_stripped = [];
                try {
                    $classes_raw = $pdo->query("SELECT id, name FROM classes")->fetchAll(PDO::FETCH_KEY_PAIR);
                    foreach ($classes_raw as $id => $name) {
                        $clean_name = strtolower(trim($name));
                        $class_map[$clean_name] = $id;
                        $class_map_stripped[str_replace([' ', '.', '-'], '', $clean_name)] = $id;
                    }
                }
                catch (PDOException $e) {
                    $error = "Gagal memuat data referensi kelas: " . $e->getMessage();
                }

                if (empty($error)) {
                    // Check format by looking at the first cell of the first sheet
                    $first_sheet_first_row = $xlsx->rows(0)[0] ?? [];
                    $is_absensi_format = false;

                    if (isset($first_sheet_first_row[0]) && strpos(strtoupper($first_sheet_first_row[0]), 'PEMERINTAH PROVINSI SULAWESI SELATAN') !== false) {
                        $is_absensi_format = true;
                    }

                    if ($is_absensi_format) {
                        $success_count = 0;
                        $fail_count = 0;
                        $i = 0;

                        while ($xlsx->worksheet($i) !== false) {
                            $sheet_rows = $xlsx->rows($i);
                            if ($sheet_rows) {
                                $current_class_id = null;
                                $start_reading_students = false;

                                foreach ($sheet_rows as $row_idx => $data) {
                                    if (empty(implode('', $data)))
                                        continue; // skip entirely empty rows

                                    // Check if row contains KELAS info
                                    $col0 = trim((string)($data[0] ?? ''));

                                    if (strpos(strtoupper($col0), 'KELAS') !== false) {
                                        // Reset current class ID before matching, to avoid bleeding students into the previous class
                                        $current_class_id = null;

                                        // It usually looks like "KELAS   : X 1"
                                        $parts = explode(':', $col0);
                                        if (count($parts) > 1) {
                                            $class_name = trim($parts[1]);
                                        }
                                        else {
                                            // Maybe it's in the next columns
                                            $class_name = trim((string)($data[2] ?? ''));
                                            if (empty($class_name))
                                                $class_name = trim((string)($data[3] ?? ''));
                                        }

                                        if ($class_name) {
                                            $lookup = strtolower($class_name);

                                            // Validate grade level from filename if present
                                            $skip_this_class = false;
                                            if ($expected_grade) {
                                                $parsed_grade = null;
                                                $upper_lookup = strtoupper($lookup);
                                                // Check for XII first to avoid XI matching XII
                                                if (strpos($upper_lookup, 'XII') !== false) {
                                                    $parsed_grade = 'XII';
                                                } elseif (strpos($upper_lookup, 'XI') !== false) {
                                                    $parsed_grade = 'XI';
                                                } elseif (strpos($upper_lookup, 'X') !== false) {
                                                    $parsed_grade = 'X';
                                                }
                                                
                                                if ($parsed_grade && $parsed_grade !== $expected_grade) {
                                                    $skip_this_class = true;
                                                    $import_report[] = "Sheet " . ($i + 1) . ": Melewati Kelas '$class_name' karena jenjang tidak sesuai dengan nama file ($expected_grade).";
                                                }
                                            }

                                            if ($skip_this_class) {
                                                continue;
                                            }

                                            // Handle cases like "X 1" -> "X-1" or "X.1"
                                            $lookup_dash = str_replace([' ', '.'], '-', $lookup);
                                            $lookup_dot = str_replace([' ', '-'], '.', $lookup);

                                            if (isset($class_map[$lookup])) {
                                                $current_class_id = $class_map[$lookup];
                                            }
                                            elseif (isset($class_map[$lookup_dash])) {
                                                $current_class_id = $class_map[$lookup_dash];
                                            }
                                            elseif (isset($class_map[$lookup_dot])) {
                                                $current_class_id = $class_map[$lookup_dot];
                                            }
                                            else {
                                                $clean = str_replace('kelas ', '', $lookup);
                                                $clean_dash = str_replace([' ', '.'], '-', $clean);
                                                $clean_stripped = str_replace([' ', '.', '-'], '', $clean);

                                                if (isset($class_map[$clean])) {
                                                    $current_class_id = $class_map[$clean];
                                                }
                                                elseif (isset($class_map[$clean_dash])) {
                                                    $current_class_id = $class_map[$clean_dash];
                                                }
                                                elseif (isset($class_map_stripped[$clean_stripped])) {
                                                    $current_class_id = $class_map_stripped[$clean_stripped];
                                                }
                                                else {
                                                    $import_report[] = "Sheet " . ($i + 1) . ": Kelas '$class_name' tidak ditemukan di database.";
                                                }
                                            }
                                        }
                                        continue;
                                    }

                                    if (strtoupper($col0) === 'NOMOR' || strtoupper($col0) === 'URUT' || strtoupper($col0) === 'URT.') {
                                        // Next rows will be students ONLY if the class is valid and not skipped
                                        if (!empty($current_class_id)) {
                                            $start_reading_students = true;
                                        } else {
                                            $start_reading_students = false;
                                        }
                                        continue;
                                    }

                                    if ($start_reading_students) {
                                        $urut = trim((string)($data[0] ?? ''));
                                        if (empty($urut))
                                            $urut = trim((string)($data[1] ?? '')); // fallback if shifted
                                        if (!is_numeric($urut) && !empty($urut)) {
                                            // Berhenti membaca siswa untuk tabel ini, tapi lanjut scan ke bawah jika ada tabel kelas lain di sheet yang sama
                                            $start_reading_students = false;
                                            continue;
                                        }
                                        if (empty($urut))
                                            continue;

                                        $nisn = isset($data[2]) ? trim((string)$data[2]) : '';
                                        if (ctype_digit($nisn) && strlen($nisn) > 0 && strlen($nisn) < 10) {
                                            $nisn = str_pad($nisn, 10, "0", STR_PAD_LEFT);
                                        }
                                        $full_name = isset($data[3]) ? trim((string)$data[3]) : '';
                                        $raw_gender = isset($data[4]) ? strtoupper(trim((string)$data[4])) : '';
                                        $gender = null;
                                        if ($raw_gender === 'L' || $raw_gender === 'P') {
                                            $gender = $raw_gender;
                                        }

                                        if (empty($nisn) || empty($full_name)) {
                                            $import_report[] = "Sheet " . ($i + 1) . " Baris " . ($row_idx + 1) . ": NISN atau Nama kosong.";
                                            $fail_count++;
                                            continue;
                                        }

                                        if (empty($current_class_id)) {
                                            $import_report[] = "Sheet " . ($i + 1) . " Baris " . ($row_idx + 1) . ": Gagal import '$full_name' karena Kelas tidak dikenali / tidak ditemukan di Database.";
                                            $fail_count++;
                                            continue;
                                        }

                                        if ($nisn === '#REF!' || $nisn === '#VALUE!') {
                                            $import_report[] = "Sheet " . ($i + 1) . " Baris " . ($row_idx + 1) . ": NISN error dari rumus Excel ($nisn). Pastikan paste as values.";
                                            $fail_count++;
                                            continue;
                                        }

                                        $username = $nisn;
                                        // Default password requested by user
                                        $password = 'smanegeri4makassar'; // plain text
                                        $role = 'siswa';
                                        $status = 'active';

                                        // Cek apakah user sudah ada
                                        $stmt_check = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                                        $stmt_check->execute([$username]);

                                        if ($stmt_check->rowCount() > 0) {
                                            $import_report[] = "Sheet " . ($i + 1) . " Baris " . ($row_idx + 1) . ": Siswa dengan NISN $nisn sudah ada.";
                                            $fail_count++;
                                            continue;
                                        }

                                        try {
                                            $stmt_insert = $pdo->prepare("INSERT INTO users (username, password, full_name, role, status, class_id, gender, nis) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                                            // Make sure to use plain text password!
                                            $stmt_insert->execute([$username, $password, $full_name, $role, $status, $current_class_id, $gender, $nisn]);
                                            $success_count++;
                                        }
                                        catch (PDOException $e) {
                                            $import_report[] = "Sheet " . ($i + 1) . ": Error DB - " . $e->getMessage();
                                            $fail_count++;
                                        }
                                    }
                                }
                            }
                            $i++;
                        }
                        $success = "Import Format Absensi selesai! Sukses: $success_count, Gagal: $fail_count";
                        // Prevent the legacy block from running by emptying rows
                        $rows = [];
                    }
                    else {
                        // Legacy generic format
                        $rows = $xlsx->rows();
                    }
                }
            }
            else {
                $error = "Gagal parsing Excel file.";
            }
        }
        else {
            $error = "Hanya file CSV atau Excel (.xlsx, .xls) yang diperbolehkan.";
        }

        // Fetch Classes and Subjects for mapping (legacy generic)
        if (!empty($rows) && empty($error)) {
            try {
                $classes_raw = $pdo->query("SELECT id, name FROM classes")->fetchAll(PDO::FETCH_KEY_PAIR); // ID => Name
                $class_map = [];
                foreach ($classes_raw as $id => $name) {
                    $class_map[strtolower(trim($name))] = $id;
                }

                $subjects_raw = $pdo->query("SELECT id, name FROM subjects")->fetchAll(PDO::FETCH_KEY_PAIR);
                $subject_map = [];
                foreach ($subjects_raw as $id => $name) {
                    $subject_map[strtolower(trim($name))] = $id;
                }
            }
            catch (PDOException $e) {
                $error = "Gagal memuat data referensi: " . $e->getMessage();
            }
        }

        // Legacy Generic Format Logic
        if (empty($error) && !empty($rows)) {
            $row_count = 0;
            $success_count = 0;
            $fail_count = 0;

            foreach ($rows as $data) {
                $row_count++;

                $col_count = count($data);

                // Skip header if first row has "password" or "username"
                if ($row_count == 1 && (strtolower($data[0]) == 'password' || strtolower($data[0]) == 'username' || strtolower($data[0]) == 'identity'))
                    continue;

                if ($col_count < 4) { // Minimum required cols
                    $import_report[] = "Baris $row_count: Data tidak lengkap (min 4 kolom utama).";
                    $fail_count++;
                    continue;
                }

                $identity = trim($data[0]);
                $password = trim($data[1]);
                $full_name = trim($data[2]);
                $role = 'siswa'; // Force role to siswa

                // Optional / Contextual fields
                $status = isset($data[4]) ? strtolower(trim($data[4])) : 'active';
                $class_subject_name = isset($data[5]) ? trim($data[5]) : '';
                $gender = isset($data[6]) ? strtoupper(trim($data[6])) : null;
                $address = isset($data[7]) ? trim($data[7]) : null;

                // 1. Identity Logic
                $username = $identity;
                $nip = null;
                $nis = $identity;

                // 2. Class Logic
                $class_id = null;
                $subject_id = null;

                if (!empty($class_subject_name)) {
                    $lookup = strtolower($class_subject_name);
                    // Try exact match first, then "Kelas X" format
                    if (isset($class_map[$lookup])) {
                        $class_id = $class_map[$lookup];
                    }
                    else {
                        // Try removing "Kelas " prefix if user included it
                        $clean = str_replace('kelas ', '', $lookup);
                        if (isset($class_map[$clean])) {
                            $class_id = $class_map[$clean];
                        }
                        else {
                            $import_report[] = "Baris $row_count warning: Kelas '$class_subject_name' tidak ditemukan.";
                        }
                    }
                }

                if (empty($username) || empty($password)) {
                    $import_report[] = "Baris $row_count: Identity atau Password kosong.";
                    $fail_count++;
                    continue;
                }

                try {
                    // Reverted back to plain text
                    $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, role, gender, status, nip, nis, class_id, subject_id, address) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$username, $password, $full_name, $role, $gender, $status, $nip, $nis, $class_id, $subject_id, $address]);
                    $success_count++;
                }
                catch (PDOException $e) {
                    $fail_count++;
                    if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                        $import_report[] = "Baris $row_count: Duplicate username '$username'.";
                    }
                    else {
                        $import_report[] = "Baris $row_count: Error DB - " . $e->getMessage();
                    }
                }
            }
            $success = "Import selesai! Sukses: $success_count, Gagal: $fail_count";
        }
    }
    else {
        $error = "Terjadi kesalahan upload file.";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta charset="UTF-8">
    <title>Import Siswa - Admin</title>
    <link rel="stylesheet" href="/public/assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>

<div class="app-container">
    <?php include '../templates/sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Unified Hero Header -->
        <div class="dashboard-hero">
            <div style="position: relative; z-index: 2;">
                <h1 style="color: white; margin-bottom: 0.5rem;"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block; vertical-align:middle; line-height:1;"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg> Import Siswa</h1>
                <p style="color: rgba(255,255,255,0.8);">Upload file CSV atau Excel untuk mendaftarkan Siswa secara massal.</p>
            </div>
            <div style="position: absolute; right: 40px; top: 50%; transform: translateY(-50%); z-index: 10;">
                <a href="manage_users.php" class="btn" style="background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.3); backdrop-filter: blur(5px);">&larr; Kembali ke Daftar</a>
            </div>
            <!-- Decorative circle -->
            <div style="position: absolute; right: -50px; top: -50px; width: 200px; height: 200px; background: rgba(255,255,255,0.1); border-radius: 50%;"></div>
        </div>

        <div class="content-overlap">
            <div class="card" style="max-width: 600px; margin: 0 auto;">
            <?php if ($success): ?>
                <div class="badge badge-success" style="display:block; padding: 1rem; margin-bottom: 1rem; text-align:center;">
                    <?php echo $success; ?>
                </div>
            <?php
endif; ?>
            
            <?php if ($error): ?>
                <div class="badge badge-danger" style="display:block; padding: 1rem; margin-bottom: 1rem; text-align:center;">
                    <?php echo $error; ?>
                </div>
            <?php
endif; ?>

            <?php if (!empty($import_report)): ?>
                <div style="background: #fffbeb; border: 1px solid #fcd34d; padding: 15px; border-radius: 8px; margin-bottom: 20px; font-size: 0.85rem; color: #92400e;">
                    <strong>Laporan Error:</strong>
                    <ul style="margin: 5px 0 0 15px; padding: 0;">
                        <?php foreach ($import_report as $rep): ?>
                            <li><?php echo htmlspecialchars($rep); ?></li>
                        <?php
    endforeach; ?>
                    </ul>
                </div>
            <?php
endif; ?>
            
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Pilih File Excel / CSV</label>
                    <input type="file" name="file" accept=".csv, .xlsx, .xls" required>
                    <p style="margin-top: 10px; font-size: 0.85rem; color: #64748b;">
                        Fitur Baru: <strong>Support Lengkap (8 Kolom)</strong><br>
                        Format Kolom (Urutan):<br>
                        <code>Identity (NIS) | Password | Nama Lengkap | Role (Abaikan) | Status | Kelas | Gender | Alamat</code>
                    </p>
                </div>

                <div class="form-group">
                    <label>Contoh Isi Tabel:</label>
                    <div style="overflow-x: auto;">
                    <div class="page-table-wrap">
                    <table class="page-table">
                        <thead>
                            <tr>
                                <th>Identity</th>
                                <th>Password</th>
                                <th>Name</th>
                                <th>Role (Diabaikan)</th>
                                <th>Status</th>
                                <th>Kelas</th>
                                <th>Gender</th>
                                <th>Alamat</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>2023001</td>
                                <td>smanegeri4makassar</td>
                                <td>Budi S</td>
                                <td>siswa</td>
                                <td>active</td>
                                <td>X-1</td>
                                <td>L</td>
                                <td>Jl. Mawar</td>
                            </tr>
                        </tbody>
                    </table>
                    </div>
                </div>

                <button type="submit" class="btn" style="width: 100%;">Upload & Import</button>
            </form>
            </div>
        </div>
    </main>
</div>

</body>
</html>


