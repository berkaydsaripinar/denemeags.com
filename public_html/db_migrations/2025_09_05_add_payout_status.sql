ALTER TABLE satis_loglari
ADD COLUMN yazar_odeme_durumu ENUM('beklemede', 'odendi') NOT NULL DEFAULT 'beklemede',
ADD COLUMN yazar_odeme_tarihi DATETIME NULL;

CREATE INDEX idx_satis_loglari_yazar_odeme
    ON satis_loglari (yazar_id, yazar_odeme_durumu, tarih);
