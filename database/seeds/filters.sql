-- ============================================================
-- Diffrakt — seeds/filters.sql
-- Seeds всичките 10 вградени атомарни филтъра.
--
-- ВАЖНО: ID-тата трябва да съвпадат точно с filterMap в PipelineRunner.php:
--   1=Blur  2=Grayscale  3=Sepia  4=Brightness   5=Contrast
--   6=Saturation  7=HueRotate  8=Vignette  9=Noise  10=EdgeDetect
-- ============================================================

INSERT INTO filters (id, name, type, owner_id, is_public, params_schema) VALUES
(1,  'Blur',       'atomic', NULL, 1, '{"intensity": {"type": "int",   "min": 1,    "max": 50,  "default": 1}}'),
(2,  'Grayscale',  'atomic', NULL, 1, NULL),
(3,  'Sepia',      'atomic', NULL, 1, '{"intensity": {"type": "float", "min": 0.0,  "max": 1.0, "default": 1.0}}'),
(4,  'Brightness', 'atomic', NULL, 1, '{"level":     {"type": "int",   "min": -255, "max": 255, "default": 20}}'),
(5,  'Contrast',   'atomic', NULL, 1, '{"level":     {"type": "int",   "min": -255, "max": 255, "default": 20}}'),
(6,  'Saturation', 'atomic', NULL, 1, '{"value":     {"type": "float", "min": 0.0,  "max": 3.0, "default": 1.0}}'),
(7,  'HueRotate',  'atomic', NULL, 1, '{"angle":     {"type": "float", "min": 0.0,  "max": 360, "default": 90}}'),
(8,  'Vignette',   'atomic', NULL, 1, '{"strength":  {"type": "float", "min": 0.0,  "max": 1.0, "default": 0.5}}'),
(9,  'Noise',      'atomic', NULL, 1, '{"intensity": {"type": "int",   "min": 1,    "max": 100, "default": 10}}'),
(10, 'EdgeDetect', 'atomic', NULL, 1, NULL);