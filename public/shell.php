<?php
$base = rtrim(dirname($_SERVER['SCRIPT_NAME'], 2), '/\\') . '/';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diffrakt</title>
    <base href="<?= htmlspecialchars($base) ?>">
    <link rel="stylesheet" href="./assets/css/app.css">
</head>
<body>
    <nav id="nav" class="nav hidden></nav>
    <div id="app"></div>
    <script type="module" src="./assets/js/app.js"></script>
</body>
</html>