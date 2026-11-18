<?php
/**
 * JSON-API voor HelloDash, bedoeld voor scripts zoals de CLI
 * (../hellodash.py). Schrijft naar dezelfde messages- en settings-tabellen
 * als bericht.php.
 *
 * Beveiliging: elke aanvraag moet de header "X-API-Key: <API_KEY>" meesturen,
 * met API_KEY uit config.php (minimaal 16 tekens). Zonder API_KEY is de API
 * uitgeschakeld. Bewust een eigen header en niet "Authorization": die wordt
 * door veel gedeelde hosting (Apache/CGI) weggefilterd.
 *
 * Bewust GEEN helpers.php: die laadt pin.php, dat bij elke POST om de
 * pincode vraagt (HTML-pagina) - de API-sleutel vervangt hier de pincode.
 *
 * GET  api.php  -> huidig bericht + instellingen + wat er is toegestaan
 * POST api.php  -> JSON {"grid": [[...]], "font": "...", "theme": "..."};
 *                  alle drie optioneel, maar minstens één. Een grid wordt een
 *                  nieuw bericht; font/theme wijzigen de bordinstellingen.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/board.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function api_out(int $status, array $data): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!defined('API_KEY') || strlen((string) API_KEY) < 16) {
    api_out(503, ['error' => 'API uitgeschakeld: zet API_KEY (minimaal 16 tekens) in config.php.']);
}

$key = (string) ($_SERVER['HTTP_X_API_KEY'] ?? '');
if ($key === '' || !hash_equals((string) API_KEY, $key)) {
    sleep(1); // raden afremmen
    api_out(401, ['error' => 'Ongeldige of ontbrekende API-sleutel (header X-API-Key).']);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $current = latest_message($pdo);
    api_out(200, [
        'id' => $current['id'],
        'grid' => $current['grid'],
        'font' => board_font($pdo),
        'theme' => board_theme($pdo),
        'rows' => GRID_ROWS,
        'cols' => GRID_COLS,
        'chars' => ALLOWED_CHARS,
        'colors' => ALLOWED_COLORS,
        'emoji' => ALLOWED_EMOJI,
        'fonts' => BOARD_FONTS,
        'themes' => BOARD_THEMES,
    ]);
}

if ($method !== 'POST') {
    header('Allow: GET, POST');
    api_out(405, ['error' => 'Alleen GET en POST.']);
}

$body = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($body)) {
    api_out(400, ['error' => 'Body moet een JSON-object zijn.']);
}

$hasGrid = array_key_exists('grid', $body);
$hasFont = array_key_exists('font', $body);
$hasTheme = array_key_exists('theme', $body);
if (!$hasGrid && !$hasFont && !$hasTheme) {
    api_out(400, ['error' => 'Geef minstens één van: grid, font, theme.']);
}

// Eerst alles valideren, pas daarna opslaan - zodat een fout in één veld
// niet half doorgevoerd wordt.
if ($hasGrid && !validate_grid($body['grid'])) {
    api_out(422, ['error' => 'Ongeldig grid: verwacht ' . GRID_ROWS . ' regels x ' . GRID_COLS . ' tegels met toegestane tokens.']);
}
if ($hasFont && !(is_string($body['font']) && isset(BOARD_FONTS[$body['font']]))) {
    api_out(422, ['error' => 'Onbekend lettertype. Kies uit: ' . implode(', ', array_keys(BOARD_FONTS)) . '.']);
}
if ($hasTheme && !(is_string($body['theme']) && isset(BOARD_THEMES[$body['theme']]))) {
    api_out(422, ['error' => 'Onbekend thema. Kies uit: ' . implode(', ', array_keys(BOARD_THEMES)) . '.']);
}

if ($hasFont) {
    board_save_setting($pdo, 'font', $body['font'], BOARD_FONTS);
}
if ($hasTheme) {
    board_save_setting($pdo, 'theme', $body['theme'], BOARD_THEMES);
}
$id = null;
if ($hasGrid) {
    $stmt = $pdo->prepare('INSERT INTO messages (grid_json) VALUES (?)');
    $stmt->execute([json_encode($body['grid'])]);
    $id = (int) $pdo->lastInsertId();
}

api_out(200, ['ok' => true, 'id' => $id, 'font' => board_font($pdo), 'theme' => board_theme($pdo)]);
