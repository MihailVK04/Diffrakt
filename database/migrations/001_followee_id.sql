ALTER TABLE follows RENAME COLUMN followed_id TO followee_id;
ALTER TABLE filters ADD COLUMN is_public BOOLEAN NOT NULL DEFAULT 0 AFTER type;