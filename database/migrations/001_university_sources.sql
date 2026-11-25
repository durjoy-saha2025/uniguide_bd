ALTER TABLE universities
    ADD COLUMN source_id BIGINT UNSIGNED NULL AFTER cover_image_url,
    ADD CONSTRAINT fk_universities_source FOREIGN KEY (source_id)
        REFERENCES sources (id) ON DELETE RESTRICT;
