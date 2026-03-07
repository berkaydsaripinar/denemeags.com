-- Develop database bootstrap template
-- 1. Export schema/data from live DB outside the repo.
-- 2. Import into the develop DB.
-- 3. Run the cleanup statements below against the develop DB.

START TRANSACTION;

-- Keep reference data, clear operational/test-sensitive tables.
TRUNCATE TABLE pdf_filigran_loglari;
TRUNCATE TABLE video_izleme_loglari;
TRUNCATE TABLE satis_loglari;

-- If you want a clean develop exam history, also clear these.
-- TRUNCATE TABLE kullanici_katilimlari;
-- TRUNCATE TABLE kullanici_erisimleri;
-- TRUNCATE TABLE erisim_kodlari;

COMMIT;

-- Optional post-import checks
-- SELECT COUNT(*) FROM denemeler;
-- SELECT COUNT(*) FROM yazarlar;
-- SELECT COUNT(*) FROM sistem_ayarlari;
