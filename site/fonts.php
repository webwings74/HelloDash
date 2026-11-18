<?php
// Lettertype-proefpagina: toont kandidaat-lettertypes van Google Fonts op
// een rijtje bordtegels, met een eigen proeftekst. Alleen bedoeld om te
// kiezen welke lettertypes erbij moeten; de selectie kopieer je en geef je
// door. Echt toevoegen gaat daarna zoals in de README ("Lettertype") staat:
// zelf hosten in fonts/ en opnemen in BOARD_FONTS.
//
// Let op: deze pagina laadt de lettertypes rechtstreeks van Google Fonts,
// dus hij heeft (anders dan het bord zelf) wél internet nodig.
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/board.php';

// [naam op Google Fonts, gewicht (null = het enige/standaard gewicht), soort]
$candidates = [
    // Smal / condensed
    ['Barlow Condensed', 600, 'Smal'],
    ['Big Shoulders Display', 700, 'Smal'],
    ['League Gothic', null, 'Smal'],
    ['Antonio', 600, 'Smal'],
    ['Pathway Gothic One', null, 'Smal'],
    ['Saira Condensed', 600, 'Smal'],
    ['Sofia Sans Condensed', 700, 'Smal'],
    ['Roboto Condensed', 700, 'Smal'],
    ['Teko', 500, 'Smal'],
    ['Staatliches', null, 'Smal'],
    ['Six Caps', null, 'Smal'],
    // Stevig / display
    ['Archivo Black', null, 'Stevig'],
    ['Russo One', null, 'Stevig'],
    ['Bungee', null, 'Stevig'],
    ['Squada One', null, 'Stevig'],
    ['Black Ops One', null, 'Stevig'],
    ['Alfa Slab One', null, 'Stevig'],
    ['Righteous', null, 'Stevig'],
    ['Kanit', 600, 'Stevig'],
    ['Rubik', 700, 'Stevig'],
    // Technisch / futuristisch
    ['Rajdhani', 700, 'Technisch'],
    ['Chakra Petch', 600, 'Technisch'],
    ['Orbitron', 700, 'Technisch'],
    ['Michroma', null, 'Technisch'],
    ['Audiowide', null, 'Technisch'],
    // Monospace
    ['Share Tech Mono', null, 'Monospace'],
    ['JetBrains Mono', 700, 'Monospace'],
    ['IBM Plex Mono', 600, 'Monospace'],
    ['Roboto Mono', 700, 'Monospace'],
    ['Overpass Mono', 700, 'Monospace'],
    ['Major Mono Display', null, 'Monospace'],
    // Pixel / matrix (lijkt op een led- of puntmatrixbord)
    ['VT323', null, 'Pixel'],
    ['Silkscreen', null, 'Pixel'],
    ['DotGothic16', null, 'Pixel'],
    ['Doto', 800, 'Pixel'],
    ['Press Start 2P', null, 'Pixel'],
];

$installed = array_values(BOARD_FONTS);
?>
<!doctype html>
<html lang="nl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>HelloDash - Lettertypes</title>
<link rel="stylesheet" href="style.css?v=<?= (int) @filemtime(__DIR__ . '/style.css') ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<?php foreach ($candidates as [$name, $weight]): ?>
<?php // Eén link per lettertype: een ongeldig gewicht breekt dan alleen dat ene font, niet de hele lijst. ?>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=<?= h(str_replace(' ', '+', $name)) ?><?= $weight ? ':wght@' . (int) $weight : '' ?>&amp;display=swap">
<?php endforeach; ?>
<style>
.font-tools { display: flex; flex-wrap: wrap; gap: 0.8rem; align-items: flex-end; margin: 1rem 0; }
.font-tools input[type="text"] { min-width: 16rem; text-transform: uppercase; }
.font-list { display: flex; flex-direction: column; gap: 0.9rem; padding-bottom: 5rem; }
.font-group { margin: 1.2rem 0 0; color: var(--muted); font-size: 0.95rem; text-transform: uppercase; letter-spacing: 0.08em; }
.font-card { display: flex; flex-direction: column; gap: 0.35rem; }
.font-card label { flex-direction: row; align-items: center; gap: 0.5rem; font-weight: 600; cursor: pointer; }
.font-card .installed { font-weight: 400; color: var(--muted); font-size: 0.85rem; }
.font-card .board-grid { aspect-ratio: auto; grid-template-rows: 2.6rem; width: 100%; padding: 0.35rem; }
.font-card .tile { font-size: 1.6rem; }
.font-card.chosen .board-grid { outline: 3px solid var(--accent); outline-offset: 2px; }
.font-selection {
    position: sticky; bottom: 0; display: flex; flex-wrap: wrap; gap: 0.6rem; align-items: center;
    padding: 0.7rem 0.9rem; background: var(--box-bg); border: 1px solid var(--border); border-radius: 6px;
}
.font-selection span { flex: 1 1 14rem; }
@media (max-width: 640px) {
    .font-card .board-grid { grid-template-rows: 1.8rem; gap: 2px; }
    .font-card .tile { font-size: 1rem; }
}
</style>
</head>
<body>
<?php require __DIR__ . '/header.php'; ?>
<?php require __DIR__ . '/nav.php'; ?>
<h2>Lettertypes bekijken</h2>
<p class="board-hint">Kandidaat-lettertypes van Google Fonts op bordtegels. Vink aan wat je wilt toevoegen en kopieer de selectie. De hoogte van de letters is al gelijkgetrokken; de precieze verticale positie wordt pas afgesteld als een lettertype echt wordt toegevoegd.</p>

<div class="font-tools">
    <label>Proeftekst
        <input type="text" id="sample" maxlength="24" value="HELLO DASH 0123!">
    </label>
    <label>Thema
        <select id="preview-theme">
            <?php foreach (BOARD_THEMES as $key => $label): ?>
                <option value="<?= h($key) ?>"><?= h($label) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
</div>

<div class="font-list" id="font-list">
    <?php $group = null; foreach ($candidates as [$name, $weight, $kind]): ?>
        <?php if ($kind !== $group): $group = $kind; ?>
            <h3 class="font-group"><?= h($kind) ?></h3>
        <?php endif; ?>
        <div class="font-card" data-font="<?= h($name) ?>">
            <label>
                <input type="checkbox" value="<?= h($name) ?>">
                <?= h($name) ?>
                <?php if (in_array($name, $installed, true)): ?><span class="installed">(al op het bord)</span><?php endif; ?>
            </label>
            <div class="board-grid board-theme-dark" style="--tile-font:'<?= h($name) ?>';--tile-font-weight:<?= (int) ($weight ?? 400) ?>;"></div>
        </div>
    <?php endforeach; ?>
</div>

<div class="font-selection">
    <span id="selection-text">Nog niets geselecteerd.</span>
    <button type="button" id="copy-selection" disabled>Selectie kopiëren</button>
</div>

<script>
(function () {
    'use strict';
    var allowed = <?= json_encode(ALLOWED_CHARS) ?>;
    var sample = document.getElementById('sample');
    var themeSel = document.getElementById('preview-theme');
    var cards = Array.prototype.slice.call(document.querySelectorAll('.font-card'));
    var text = document.getElementById('selection-text');
    var copyBtn = document.getElementById('copy-selection');
    var STORE = 'hellodash-font-selection';

    function render() {
        var chars = sample.value.toUpperCase().split('').filter(function (c) { return allowed.indexOf(c) !== -1; });
        // Altijd 24 kolommen, net als het echte bord, zodat de tegels de
        // juiste verhouding houden; de rest wordt aangevuld met lege tegels.
        while (chars.length < <?= GRID_COLS ?>) chars.push(' ');
        cards.forEach(function (card) {
            var grid = card.querySelector('.board-grid');
            grid.style.gridTemplateColumns = 'repeat(' + chars.length + ', 1fr)';
            grid.innerHTML = chars.map(function (c) {
                var div = document.createElement('div');
                div.className = c === ' ' ? 'tile tile-blank' : 'tile tile-char';
                div.textContent = c === ' ' ? '' : c;
                return div.outerHTML;
            }).join('');
        });
    }

    function applyTheme() {
        cards.forEach(function (card) {
            var grid = card.querySelector('.board-grid');
            grid.className = 'board-grid board-theme-' + themeSel.value;
        });
    }

    function selected() {
        return cards.filter(function (card) { return card.querySelector('input').checked; })
            .map(function (card) { return card.dataset.font; });
    }

    function updateSelection() {
        var names = selected();
        cards.forEach(function (card) { card.classList.toggle('chosen', card.querySelector('input').checked); });
        text.textContent = names.length ? 'Geselecteerd (' + names.length + '): ' + names.join(', ') : 'Nog niets geselecteerd.';
        copyBtn.disabled = !names.length;
        try { localStorage.setItem(STORE, JSON.stringify(names)); } catch (e) {}
    }

    // Selectie onthouden in deze browser, zodat herladen hem niet wist.
    try {
        var saved = JSON.parse(localStorage.getItem(STORE) || '[]');
        cards.forEach(function (card) {
            if (saved.indexOf(card.dataset.font) !== -1) card.querySelector('input').checked = true;
        });
    } catch (e) {}

    copyBtn.addEventListener('click', function () {
        var value = 'Voeg deze lettertypes toe aan HelloDash: ' + selected().join(', ');
        function done() { copyBtn.textContent = 'Gekopieerd ✓'; setTimeout(function () { copyBtn.textContent = 'Selectie kopiëren'; }, 1500); }
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(value).then(done, function () { window.prompt('Kopieer deze tekst:', value); });
        } else {
            window.prompt('Kopieer deze tekst:', value);
        }
    });

    sample.addEventListener('input', render);
    themeSel.addEventListener('change', applyTheme);
    document.getElementById('font-list').addEventListener('change', updateSelection);
    render();
    updateSelection();
})();
</script>
</body>
</html>
