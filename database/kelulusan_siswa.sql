-- Tabel data kelulusan siswa
-- Jalankan sekali di database sman4_lms

CREATE TABLE IF NOT EXISTS `kelulusan_siswa` (
  `id`                INT(11) NOT NULL AUTO_INCREMENT,
  `nisn`              VARCHAR(20)  NOT NULL,
  `nama_siswa`        VARCHAR(255) NOT NULL,
  `kelas`             VARCHAR(100) DEFAULT NULL,
  `status_kelulusan`  ENUM('lulus','tidak_lulus') NOT NULL DEFAULT 'lulus',
  `tahun_kelulusan`   VARCHAR(10)  DEFAULT NULL,
  `catatan`           TEXT         DEFAULT NULL,
  `created_at`        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_nisn` (`nisn`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
