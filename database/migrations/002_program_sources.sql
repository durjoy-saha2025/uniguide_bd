ALTER TABLE programs
    ADD COLUMN source_id BIGINT UNSIGNED NULL AFTER description,
    ADD CONSTRAINT fk_programs_source FOREIGN KEY (source_id)
        REFERENCES sources (id) ON DELETE RESTRICT;
