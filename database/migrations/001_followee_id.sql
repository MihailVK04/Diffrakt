ALTER TABLE follows CHANGE COLUMN followed_id followee_id INT NOT NULL;
ALTER TABLE filters ADD COLUMN is_public BOOLEAN NOT NULL DEFAULT 0 AFTER type;
ALTER TABLE filters ADD COLUMN pipeline_id INT NULL AFTER is_public;
ALTER TABLE filters ADD CONSTRAINT fk_filters_pipeline FOREIGN KEY (pipeline_id) REFERENCES pipelines(id) ON DELETE SET NULL;
UPDATE filters SET params_schema = '{"level": {"type": "float", "min": 0, "max": 3}}' WHERE name = 'saturation';
ALTER TABLE posts ADD COLUMN is_published TINYINT(1) NOT NULL DEFAULT 0;