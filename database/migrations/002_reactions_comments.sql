UPDATE filters
SET params_schema = '{"level": {"type": "float", "min": 0.0, "max": 3.0, "default": 1.0}}'
WHERE name = 'Saturation';