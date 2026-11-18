<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/board.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['form_action'] ?? '';
    $font = (string) ($_POST['font'] ?? '');
    $theme = (string) ($_POST['theme'] ?? '');
    $settingsValid = isset(BOARD_FONTS[$font], BOARD_THEMES[$theme]);

    // "Alleen instellingen opslaan": lettertype/thema wijzigen zonder nieuw bericht.
    if ($action === 'settings' && $settingsValid) {
        board_save_setting($pdo, 'font', $font, BOARD_FONTS);
        board_save_setting($pdo, 'theme', $theme, BOARD_THEMES);
        redirect('bericht.php?msg=settings');
    }

    // "Plaatsen op dashboard": bericht opslaan én de gekozen instellingen.
    if ($action === 'save') {
        $decoded = json_decode($_POST['grid'] ?? '', true);
        if (validate_grid($decoded) && $settingsValid) {
            $stmt = $pdo->prepare('INSERT INTO messages (grid_json) VALUES (?)');
            $stmt->execute([json_encode($decoded)]);
            board_save_setting($pdo, 'font', $font, BOARD_FONTS);
            board_save_setting($pdo, 'theme', $theme, BOARD_THEMES);
            redirect('bericht.php?msg=saved');
        }
    }
    redirect('bericht.php?error=invalid');
}

$current = latest_message($pdo);
$grid = $current['grid'];
$font = board_font($pdo);
$theme = board_theme($pdo);

$flashLabels = [
    'saved' => 'Bericht geplaatst op het dashboard.',
    'settings' => 'Lettertype en thema van het bord bijgewerkt.',
];
?>
<!doctype html>
<html lang="nl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>HelloDash - Bericht plaatsen</title>
<link rel="stylesheet" href="style.css?v=<?= (int) @filemtime(__DIR__ . '/style.css') ?>">
</head>
<body>
<?php require __DIR__ . '/header.php'; ?>
<?php require __DIR__ . '/nav.php'; ?>
<h2>Bericht plaatsen</h2>

<?php if (isset($_GET['msg'], $flashLabels[$_GET['msg']])): ?>
    <p class="flash flash-ok"><?= h($flashLabels[$_GET['msg']]) ?></p>
<?php endif; ?>
<?php if (($_GET['error'] ?? '') === 'invalid'): ?>
    <p class="flash flash-error">Het bericht kon niet worden opgeslagen (ongeldige invoer).</p>
<?php endif; ?>

<p class="board-hint">Klik een tegel, typ dan een teken of kies een kleur/emoticon uit het palet. Na elke keuze springt de selectie automatisch naar de volgende tegel. Met "Regel ⟵"/"Regel ⟶" schuift de hele regel van de geselecteerde tegel rond (het teken dat van de rand afvalt komt aan de andere kant weer terug) — handig om tekst te centreren.</p>

<div class="board-wrap">
<?= render_grid($grid, true, $font, $theme) ?>
</div>

<div class="palette" id="palette">
    <div class="palette-group" id="palette-keys"></div>
    <div class="palette-group" id="palette-colors">
        <?php foreach (ALLOWED_COLORS as $color): ?>
            <button type="button" class="palette-swatch tile-color-<?= h($color) ?>" data-token="COLOR:<?= h($color) ?>" data-class="tile tile-color-<?= h($color) ?>" title="<?= h($color) ?>"></button>
        <?php endforeach; ?>
    </div>
    <div class="palette-group" id="palette-emoji">
        <?php foreach (ALLOWED_EMOJI as $emoji): ?>
            <button type="button" class="palette-key palette-emoji-key" data-token="<?= h($emoji) ?>" data-class="tile tile-emoji"><?= h($emoji) ?></button>
        <?php endforeach; ?>
    </div>
    <div class="palette-group">
        <button type="button" class="palette-action" id="shift-left" title="Schuift de regel van de geselecteerde tegel naar links">Regel ⟵</button>
        <button type="button" class="palette-action" id="shift-right" title="Schuift de regel van de geselecteerde tegel naar rechts">Regel ⟶</button>
    </div>
    <div class="palette-group">
        <button type="button" class="palette-action" id="clear-cell">Leeg</button>
        <button type="button" class="palette-action" id="clear-board">Bord wissen</button>
    </div>
</div>

<form method="post" id="board-form">
    <input type="hidden" name="grid" id="grid-input">
    <label class="font-picker">Lettertype
        <select name="font" id="font-select">
            <?php foreach (BOARD_FONTS as $key => $label): ?>
                <option value="<?= h($key) ?>"<?= $key === $font ? ' selected' : '' ?>><?= h($label) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>Thema scherm
        <select name="theme" id="theme-select">
            <?php foreach (BOARD_THEMES as $key => $label): ?>
                <option value="<?= h($key) ?>"<?= $key === $theme ? ' selected' : '' ?>><?= h($label) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <button type="submit" name="form_action" value="save">Plaatsen op dashboard</button>
    <button type="submit" name="form_action" value="settings" title="Wijzigt alleen lettertype en thema op het dashboard; niet-geplaatste wijzigingen aan het bericht gaan verloren">Alleen instellingen opslaan</button>
</form>

<script>
window.ALLOWED_CHARS = <?= json_encode(ALLOWED_CHARS) ?>;
window.GRID_ROWS = <?= GRID_ROWS ?>;
window.GRID_COLS = <?= GRID_COLS ?>;
</script>
<script src="editor.js?v=<?= (int) @filemtime(__DIR__ . '/editor.js') ?>" defer></script>
</body>
</html>
