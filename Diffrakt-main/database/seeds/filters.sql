INSERT INTO filters (id, name, type, owner_id, is_public, params_schema) VALUES
(1,  'Blur',       'atomic', NULL, 1, '{"intensity": {"type": "int",   "min": 1,    "max": 50,  "default": 1}}'),
(2,  'Grayscale',  'atomic', NULL, 1, NULL),
(3,  'Sepia',      'atomic', NULL, 1, '{"intensity": {"type": "float", "min": 0.0,  "max": 1.0, "default": 1.0}}'),
(4,  'Brightness', 'atomic', NULL, 1, '{"level":     {"type": "int",   "min": -255, "max": 255, "default": 20}}'),
(5,  'Contrast',   'atomic', NULL, 1, '{"level":     {"type": "int",   "min": -255, "max": 255, "default": 20}}'),
(6,  'Saturation', 'atomic', NULL, 1, '{"level": {"type": "float", "min": -100, "max": 0, "default": -50}}'),
(7,  'HueRotate',  'atomic', NULL, 1, '{"angle":     {"type": "float", "min": 0.0,  "max": 360, "default": 90}}'),
(8,  'Vignette',   'atomic', NULL, 1, '{"strength":  {"type": "float", "min": 0.0,  "max": 1.0, "default": 0.5}}'),
(9,  'Noise',      'atomic', NULL, 1, '{"intensity": {"type": "int",   "min": 1,    "max": 100, "default": 10}}'),
(10, 'EdgeDetect', 'atomic', NULL, 1, NULL);