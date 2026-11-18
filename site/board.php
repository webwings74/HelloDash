<?php
/**
 * Alles rondom het bord: afmetingen, toegestane tegel-inhoud, validatie van
 * een opgeslagen/binnenkomende grid, en de HTML-rendering die zowel de
 * editor (bericht.php) als de weergave (index.php) gebruikt.
 *
 * Een tegel-token is een van:
 *   ""                 - lege tegel
 *   1 teken uit ALLOWED_CHARS (hoofdletter/cijfer/leesteken)
 *   "COLOR:<naam>"      - effen kleurvlak, <naam> uit ALLOWED_COLORS
 *   een string uit ALLOWED_EMOJI
 */

const GRID_COLS = 24;
// Uit config.php (BOARD_ROWS), met 6 als terugval als die niet is gezet.
// define() i.p.v. const: top-level const staat geen functieaanroep/ternary
// als waarde toe, define() wel.
define('GRID_ROWS', (defined('BOARD_ROWS') && (int) BOARD_ROWS > 0) ? (int) BOARD_ROWS : 6);

const ALLOWED_CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789 .,!?:;\'"-+()&/@#';

const ALLOWED_COLORS = ['red', 'orange', 'yellow', 'green', 'blue', 'purple', 'white', 'black'];

// Een beperkte, vaste set emoticons - geen volledige emoji-kiezer.
const ALLOWED_EMOJI = ['😀', '😂', '😍', '😎', '🎉', '❤️', '👍', '👎', '🔥', '⭐', '☀️', '🌙', '☔', '⚡', '🎄', '🏖️'];

// Keuzelijst lettertypes voor de tegels (één instelling voor het hele bord,
// opgeslagen in de settings-tabel). Sleutel = CSS-klasse "font-<sleutel>" op
// .board-grid (zie style.css), waarde = naam in de keuzelijst.
const BOARD_FONTS = [
    'fjalla-one' => 'Fjalla One',
    'oswald' => 'Oswald',
    'bebas-neue' => 'Bebas Neue',
    'anton' => 'Anton',
    'space-mono' => 'Space Mono',
    'saira-condensed' => 'Saira Condensed',
    'orbitron' => 'Orbitron',
    'doto' => 'Doto',
];
const DEFAULT_FONT = 'fjalla-one';

// Kleurthema van het bord (weergave in index.php en het voorbeeld in de
// editor). "auto" volgt de licht/donker-instelling van het apparaat waarop
// het bord draait. Klasse "board-theme-<sleutel>", zie style.css.
const BOARD_THEMES = [
    'dark' => 'Donker',
    'light' => 'Licht',
    'auto' => 'Automatisch (volgt het apparaat)',
];
const DEFAULT_BOARD_THEME = 'dark';

// Leest een bordinstelling uit de settings-tabel. Valt terug op $default als
// er nog niets is gekozen, de waarde niet in $allowed staat, of de
// settings-tabel nog niet bestaat.
function board_setting(PDO $pdo, string $name, array $allowed, string $default): string
{
    try {
        $stmt = $pdo->prepare('SELECT value FROM settings WHERE name = ?');
        $stmt->execute([$name]);
        $value = $stmt->fetchColumn();
    } catch (PDOException $e) {
        return $default;
    }
    return is_string($value) && isset($allowed[$value]) ? $value : $default;
}

function board_save_setting(PDO $pdo, string $name, string $value, array $allowed): bool
{
    if (!isset($allowed[$value])) {
        return false;
    }
    board_ensure_settings_table($pdo);
    $stmt = $pdo->prepare('INSERT INTO settings (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)');
    return $stmt->execute([$name, $value]);
}

function board_font(PDO $pdo): string
{
    return board_setting($pdo, 'font', BOARD_FONTS, DEFAULT_FONT);
}

function board_theme(PDO $pdo): string
{
    return board_setting($pdo, 'theme', BOARD_THEMES, DEFAULT_BOARD_THEME);
}

// Maakt de settings-tabel aan als die nog niet bestaat, zodat de site werkt
// zonder dat hellodash.sql na deze update opnieuw geïmporteerd hoeft te
// worden. Zelfde definitie als in hellodash.sql - houd die twee gelijk.
function board_ensure_settings_table(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS `settings` (
        `name` varchar(64) NOT NULL,
        `value` varchar(255) NOT NULL,
        PRIMARY KEY (`name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
      COMMENT='Bordinstellingen als sleutel/waarde, bijv. font en theme.'");
}

function board_blank_grid(): array
{
    return array_fill(0, GRID_ROWS, array_fill(0, GRID_COLS, ''));
}

function board_valid_token(string $token): bool
{
    if ($token === '') {
        return true;
    }
    if (mb_strlen($token) === 1 && str_contains(ALLOWED_CHARS, $token)) {
        return true;
    }
    if (str_starts_with($token, 'COLOR:')) {
        return in_array(substr($token, 6), ALLOWED_COLORS, true);
    }
    return in_array($token, ALLOWED_EMOJI, true);
}

// Server-side controle, los van wat de editor-UI normaal gesproken al
// afdwingt - verdediging in de diepte voordat iets wordt opgeslagen.
function validate_grid($decoded): bool
{
    if (!is_array($decoded) || count($decoded) !== GRID_ROWS) {
        return false;
    }
    foreach ($decoded as $row) {
        if (!is_array($row) || count($row) !== GRID_COLS) {
            return false;
        }
        foreach ($row as $token) {
            if (!is_string($token) || !board_valid_token($token)) {
                return false;
            }
        }
    }
    return true;
}

function board_tile_classes(string $token): string
{
    if ($token === '') {
        return 'tile tile-blank';
    }
    if (str_starts_with($token, 'COLOR:')) {
        return 'tile tile-color-' . substr($token, 6);
    }
    if (in_array($token, ALLOWED_EMOJI, true)) {
        return 'tile tile-emoji';
    }
    return 'tile tile-char';
}

function board_tile_label(string $token): string
{
    return str_starts_with($token, 'COLOR:') ? '' : $token;
}

function render_grid(array $grid, bool $editable = false, string $font = DEFAULT_FONT, string $theme = DEFAULT_BOARD_THEME): string
{
    // --grid-rows/--grid-cols: zie style.css, zodat het bord ook met een
    // ander BOARD_ROWS (config.php) correct blijft rasteren.
    $style = 'style="--grid-rows:' . GRID_ROWS . ';--grid-cols:' . GRID_COLS . ';"';
    $fontClass = 'font-' . (isset(BOARD_FONTS[$font]) ? $font : DEFAULT_FONT);
    $themeClass = 'board-theme-' . (isset(BOARD_THEMES[$theme]) ? $theme : DEFAULT_BOARD_THEME);
    $html = '<div class="board-grid ' . $fontClass . ' ' . $themeClass . '" id="board-grid" ' . $style . ($editable ? ' data-editable="1"' : '') . '>';
    foreach ($grid as $r => $row) {
        foreach ($row as $c => $token) {
            $classes = htmlspecialchars(board_tile_classes($token), ENT_QUOTES, 'UTF-8');
            $label = htmlspecialchars(board_tile_label($token), ENT_QUOTES, 'UTF-8');
            $tokenAttr = htmlspecialchars($token, ENT_QUOTES, 'UTF-8');
            $attrs = $editable ? ' tabindex="0" role="button"' : '';
            $html .= '<div class="' . $classes . '" data-row="' . $r . '" data-col="' . $c
                . '" data-token="' . $tokenAttr . '"' . $attrs . '>' . $label . '</div>';
        }
    }
    $html .= '</div>';
    return $html;
}

// Haalt het huidige (= meest recente) bericht op. Is er nog niets opgeslagen,
// dan wordt een leeg bord teruggegeven in plaats van een foutmelding.
function latest_message(PDO $pdo): array
{
    $row = $pdo->query('SELECT id, grid_json FROM messages ORDER BY id DESC LIMIT 1')->fetch();
    if (!$row) {
        return ['id' => 0, 'grid' => board_blank_grid()];
    }
    $grid = json_decode($row['grid_json'], true);
    if (!validate_grid($grid)) {
        $grid = board_blank_grid();
    }
    return ['id' => (int) $row['id'], 'grid' => $grid];
}
