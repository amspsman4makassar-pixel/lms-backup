<?php
session_start();
require_once 'config/database.php';

$result     = null;
$not_found  = false;
$input_raw  = '';
$error_msg  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['nisn'])) {
    $input_raw = trim($_POST['kode_siswa'] ?? $_GET['nisn'] ?? '');
    $cleaned = preg_replace('/\s+/', '', $input_raw);
    $nisn    = '';

    if (stripos($cleaned, 'smapat') === 0) {
        $nisn = substr($cleaned, 6);
    } else {
        $error_msg = "Format kode salah. Gunakan awalan Smapat diikuti NISN (Contoh: Smapat0071234567).";
    }

    if ($nisn !== '' && empty($error_msg)) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM kelulusan_siswa WHERE nisn = ? LIMIT 1");
            $stmt->execute([$nisn]);
            $row = $stmt->fetch();

            if ($row) {
                $result = $row;
            } else {
                $not_found = true;
            }
        } catch (PDOException $e) {
            $error_msg = "Terjadi kesalahan koneksi database.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengumuman Kelulusan - SMAN 4 Makassar</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            background-color: #f1f5f9;
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            position: relative;
        }
        
        body::before {
            content: "";
            position: fixed;
            bottom: -20px;
            right: 2%;
            width: 450px;
            height: 600px;
            background-image: url('/public/assets/images/kepsek.png');
            background-repeat: no-repeat;
            background-position: right bottom;
            background-size: contain;
            opacity: 1; /* Foto sepenuhnya jelas (tidak transparan) */
            pointer-events: none;
            z-index: 0;
        }
        
        *, *::before, *::after { box-sizing: inherit; }
        
        .header-top {
            background: #fff;
            padding: 1.25rem 5%;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            z-index: 1;
        }
        .header-top .logo {
            font-weight: 700;
            color: #1e293b;
            text-decoration: none;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .back-link {
            color: #64748b;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
        }
        .back-link:hover { color: #0f172a; }
        
        .main-content {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 3rem 1rem;
            position: relative;
            z-index: 1;
        }
        
        .announce-box {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            width: 100%;
            max-width: 520px;
            overflow: hidden;
            border: 1px solid #e2e8f0;
            position: relative;
        }
        
        .announce-header {
            background: #1e293b;
            padding: 2.5rem 2rem;
            text-align: center;
            color: #fff;
            position: relative;
            z-index: 1;
        }
        .announce-header h1 {
            font-size: 1.35rem;
            font-weight: 600;
            margin: 0;
            letter-spacing: 0.5px;
        }
        .announce-header p {
            color: #94a3b8;
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }
        
        .announce-body {
            padding: 2.5rem 2rem;
            position: relative;
            z-index: 1;
        }
        
        .form-group { margin-bottom: 1.5rem; }
        .form-label {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            color: #334155;
            margin-bottom: 0.5rem;
        }
        .input-group {
            display: flex;
            align-items: center;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            overflow: hidden;
            background: #f8fafc;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .input-group:focus-within {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        .input-control {
            flex: 1;
            padding: 0.75rem 1rem;
            border: none;
            outline: none;
            font-size: 1rem;
            background: transparent;
            color: #0f172a;
        }
        .input-control:focus {
            background: #fff;
        }
        
        .btn-submit {
            width: 100%;
            padding: 0.85rem;
            background: #2563eb;
            color: #fff;
            border: none;
            border-radius: 6px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-submit:hover { background: #1d4ed8; }
        
        /* Surat Kelulusan Style */
        .surat-result {
            padding: 3rem 2.5rem;
            text-align: center;
            position: relative;
            z-index: 1;
        }
        .status-box {
            display: inline-block;
            padding: 0.5rem 1.5rem;
            border-radius: 4px;
            font-weight: 700;
            font-size: 1.35rem;
            letter-spacing: 3px;
            margin-bottom: 1.5rem;
            border: 2px solid;
        }
        .status-lulus {
            color: #059669;
            border-color: #059669;
            background: #ecfdf5;
        }
        .status-gagal {
            color: #dc2626;
            border-color: #dc2626;
            background: #fef2f2;
        }
        
        .student-details {
            text-align: left;
            margin-bottom: 2rem;
            background: #f8fafc;
            padding: 1.5rem;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
        }
        .detail-row {
            display: flex;
            margin-bottom: 0.7rem;
            font-size: 0.95rem;
        }
        .detail-row:last-child { margin-bottom: 0; }
        .detail-label {
            width: 120px;
            color: #64748b;
            font-weight: 500;
        }
        .detail-value {
            flex: 1;
            font-weight: 600;
            color: #1e293b;
        }
        
        .official-text {
            font-size: 0.9rem;
            line-height: 1.6;
            color: #334155;
            text-align: justify;
        }
        
        .print-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 2rem;
            padding: 0.6rem 1.2rem;
            background: #fff;
            border: 1px solid #cbd5e1;
            color: #475569;
            font-size: 0.85rem;
            font-weight: 600;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
        }
        .print-btn:hover { background: #f8fafc; }
        
        .error-msg {
            color: #dc2626;
            font-size: 0.85rem;
            margin-top: 0.5rem;
            display: block;
        }
        
        footer {
            text-align: center;
            padding: 1.5rem;
            color: #64748b;
            font-size: 0.8rem;
            position: relative;
            z-index: 1;
        }
        
        @media (max-width: 768px) {
            body::before {
                width: 200px;
                height: 300px;
                right: -10px;
                bottom: 0;
            }
            .header-top {
                flex-direction: column;
                gap: 0.8rem;
                padding: 1rem;
            }
            .main-content {
                padding: 1.5rem 1rem;
            }
            .surat-result {
                padding: 2rem 1.5rem;
            }
        }
        
        @media print {
            body { background: #fff; }
            body::before { opacity: 0.1; } /* Muncul tipis saat print */
            .header-top, footer, .print-btn { display: none; }
            .main-content { padding: 0; align-items: flex-start; }
            .announce-box { border: none; box-shadow: none; max-width: 100%; margin: 0; }
            .surat-result { padding: 0; text-align: left; }
            .student-details { border: none; background: transparent; padding: 1rem 0; }
            .status-box { display: block; text-align: center; margin: 2rem auto; max-width: 300px; }
            .official-text { text-align: justify; }
        }
    </style>
</head>
<body>

<header class="header-top">
    <a href="index.php" class="logo">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:#2563eb;"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
        SMAN 4 Makassar
    </a>
    <a href="index.php" class="back-link">Kembali ke Beranda</a>
</header>

<div class="main-content">
    <div class="announce-box">
        <?php if ($result): ?>
            <?php $is_lulus = $result['status_kelulusan'] === 'lulus'; ?>
            <div class="surat-result">
                <div style="text-align: center; margin-bottom: 2.5rem;">
                    <h2 style="font-size: 1.15rem; color: #1e293b; margin: 0; letter-spacing: 1px;">PENGUMUMAN KELULUSAN</h2>
                    <p style="font-size: 0.9rem; color: #64748b; margin: 0.35rem 0 0;">Tahun Pelajaran <?= htmlspecialchars($result['tahun_kelulusan'] ?? date('Y')) ?></p>
                    <hr style="margin-top: 1.5rem; border: none; border-top: 2px solid #e2e8f0; width: 60%; margin-left: auto; margin-right: auto;">
                </div>
                
                <div class="student-details">
                    <div class="detail-row">
                        <div class="detail-label">Nomor Induk</div>
                        <div class="detail-value">: <?= htmlspecialchars($result['nisn']) ?></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Nama Lengkap</div>
                        <div class="detail-value">: <?= htmlspecialchars($result['nama_siswa']) ?></div>
                    </div>
                    <?php if (!empty($result['kelas'])): ?>
                    <div class="detail-row">
                        <div class="detail-label">Kelas</div>
                        <div class="detail-value">: <?= htmlspecialchars($result['kelas']) ?></div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="official-text">
                    <p style="margin-bottom: 2rem;">Berdasarkan kriteria kelulusan peserta didik dari satuan pendidikan dan hasil rapat pleno dewan pendidik SMA Negeri 4 Makassar, maka siswa yang namanya tercantum di atas dinyatakan:</p>
                </div>
                
                <div style="text-align: center;">
                    <div class="status-box <?= $is_lulus ? 'status-lulus' : 'status-gagal' ?>">
                        <?= $is_lulus ? 'L U L U S' : 'T I D A K &nbsp; L U L U S' ?>
                    </div>
                </div>
                
                <?php if (!empty($result['catatan'])): ?>
                <div style="margin-top: 1rem; font-size: 0.85rem; color: #475569; text-align: left; border-left: 3px solid #cbd5e1; padding-left: 1rem;">
                    <strong>Catatan Pihak Sekolah:</strong> <?= htmlspecialchars($result['catatan']) ?>
                </div>
                <?php endif; ?>
                
                <div style="display: flex; justify-content: center; gap: 0.5rem; margin-top: 2rem;">
                    <button onclick="window.print()" class="print-btn">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                        Cetak / Simpan PDF
                    </button>
                    <a href="cek_kelulusan.php" class="print-btn" style="background: #f8fafc;">Periksa Data Lain</a>
                </div>
            </div>

        <?php else: ?>
            
            <div class="announce-header">
                <h1>Sistem Informasi Kelulusan</h1>
                <p>Silakan masukkan nomor registrasi Anda</p>
            </div>
            
            <div class="announce-body">
                <form method="POST" action="cek_kelulusan.php">
                    <div class="form-group">
                        <label class="form-label" for="kode_siswa">Nomor Registrasi Kelulusan</label>
                        <div class="input-group">
                            <input type="text" id="kode_siswa" name="kode_siswa" class="input-control" 
                                   placeholder="Contoh: Smapat00--++----" 
                                   value="<?= htmlspecialchars($input_raw) ?>"
                                   required autofocus autocomplete="off">
                        </div>
                        <p style="margin-top: 0.6rem; font-size: 0.85rem; color: #64748b; line-height: 1.4;">
                            <span style="color:#2563eb; font-weight:600; display:inline-flex; align-items:center; gap:0.25rem;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                                Format:
                            </span> Gabungan kata <strong>Smapat</strong> dan <strong>NISN</strong>.<br>
                            Contoh: Jika NISN Anda 00--++----, ketik <strong>Smapat00--++----</strong>
                        </p>
                        <?php if ($error_msg): ?>
                            <span class="error-msg"><?= $error_msg ?></span>
                        <?php endif; ?>
                        
                        <?php if ($not_found): ?>
                            <div style="margin-top: 1rem; padding: 0.75rem 1rem; background: #fffbeb; border: 1px solid #fde68a; border-radius: 6px; color: #b45309; font-size: 0.85rem; line-height: 1.5;">
                                <strong>Data tidak ditemukan.</strong><br>Pastikan kode yang dimasukkan sudah benar. Jika merasa terdapat kekeliruan, silakan hubungi bagian Tata Usaha sekolah untuk validasi data.
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <button type="submit" class="btn-submit">Tampilkan Hasil</button>
                </form>
            </div>
            
        <?php endif; ?>
    </div>
</div>

<footer>
    &copy; <?= date('Y') ?> SMA Negeri 4 Makassar. Hak Cipta Dilindungi.
</footer>

</body>
</html>
