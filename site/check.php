<?php
// Minimale JSON-polling-endpoint voor index.php ({id, grid, font, theme}): alleen lezen, geen
// pincode nodig. Vormt meteen de basis voor een latere API die berichten
// kan opvragen zonder de weergavepagina zelf te laden.
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/board.php';

header('Content-Type: application/json');

$current = latest_message($pdo);
echo json_encode(['id' => $current['id'], 'grid' => $current['grid'], 'font' => board_font($pdo), 'theme' => board_theme($pdo)]);
