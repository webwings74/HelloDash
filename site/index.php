<?php
// Volledig-scherm bordweergave - geen pincode nodig (alleen lezen), geen
// sitechrome (header/nav), bedoeld om op een los beeldscherm te draaien.
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/board.php';

$current = latest_message($pdo);
$theme = board_theme($pdo);
?>
<!doctype html>
<html lang="nl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>HelloDash</title>
<link rel="stylesheet" href="style.css?v=<?= (int) @filemtime(__DIR__ . '/style.css') ?>">
</head>
<body class="board-page board-theme-<?= htmlspecialchars($theme, ENT_QUOTES, 'UTF-8') ?>">
<?= render_grid(board_blank_grid(), false, board_font($pdo), $theme) ?>
<script>
window.INITIAL_ID = <?= (int) $current['id'] ?>;
// Het bord start leeg en display.js laat het huidige bericht daarna
// binnenflappen, zodat de animatie ook bij laden/verversen te zien is.
window.INITIAL_GRID = <?= json_encode($current['grid']) ?>;
window.ALLOWED_EMOJI = <?= json_encode(ALLOWED_EMOJI) ?>;
window.ALLOWED_CHARS = <?= json_encode(ALLOWED_CHARS) ?>;
window.GRID_ROWS = <?= GRID_ROWS ?>;
window.GRID_COLS = <?= GRID_COLS ?>;
</script>
<script src="display.js?v=<?= (int) @filemtime(__DIR__ . '/display.js') ?>" defer></script>
</body>
</html>
