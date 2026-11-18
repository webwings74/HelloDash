(function () {
    'use strict';
    var ROWS = window.GRID_ROWS || 6, COLS = window.GRID_COLS || 24;
    var boardEl = document.getElementById('board-grid');
    var form = document.getElementById('board-form');
    var gridInput = document.getElementById('grid-input');
    var tiles = Array.prototype.slice.call(boardEl.querySelectorAll('.tile'));
    var selected = null;

    function tileAt(row, col) {
        return boardEl.querySelector('.tile[data-row="' + row + '"][data-col="' + col + '"]');
    }

    function select(tile) {
        if (selected) selected.classList.remove('selected');
        selected = tile;
        if (selected) {
            selected.classList.add('selected');
            selected.focus();
        }
    }

    function setTile(tile, token, className, label) {
        tile.dataset.token = token;
        tile.className = className + (tile === selected ? ' selected' : '');
        tile.textContent = label;
    }

    // Leest de huidige inhoud (token/klassen/label) van een hele regel, zonder
    // de 'selected'-klasse mee te nemen - die hangt af van de positie, niet
    // van de inhoud, en wordt in writeRow() opnieuw per tegel bepaald.
    function readRow(row) {
        var cells = [];
        for (var c = 0; c < COLS; c++) {
            var tile = tileAt(row, c);
            var classes = Array.prototype.filter.call(tile.classList, function (cls) { return cls !== 'selected'; });
            cells.push({ token: tile.dataset.token || '', className: classes.join(' '), label: tile.textContent });
        }
        return cells;
    }

    function writeRow(row, cells) {
        for (var c = 0; c < COLS; c++) {
            setTile(tileAt(row, c), cells[c].token, cells[c].className, cells[c].label);
        }
    }

    // "Links": het meest linkse teken komt aan de rechterkant terug en de
    // rest schuift een plek op naar links. "Rechts" is precies het omgekeerde.
    function shiftRow(row, direction) {
        var cells = readRow(row);
        if (direction === 'left') {
            cells.push(cells.shift());
        } else {
            cells.unshift(cells.pop());
        }
        writeRow(row, cells);
    }

    function advance() {
        if (!selected) return;
        var row = parseInt(selected.dataset.row, 10);
        var col = parseInt(selected.dataset.col, 10) + 1;
        if (col >= COLS) { col = 0; row = (row + 1) % ROWS; }
        select(tileAt(row, col));
    }

    function back() {
        if (!selected) return;
        var row = parseInt(selected.dataset.row, 10);
        var col = parseInt(selected.dataset.col, 10) - 1;
        if (col < 0) { col = COLS - 1; row = (row - 1 + ROWS) % ROWS; }
        select(tileAt(row, col));
    }

    tiles.forEach(function (tile) {
        tile.addEventListener('click', function () { select(tile); });
    });

    document.addEventListener('keydown', function (e) {
        if (!selected) return;

        if (e.key === 'Backspace') {
            setTile(selected, '', 'tile tile-blank', '');
            back();
            e.preventDefault();
            return;
        }
        if (e.key === 'ArrowRight') { advance(); e.preventDefault(); return; }
        if (e.key === 'ArrowLeft') { back(); e.preventDefault(); return; }
        if (e.key === 'ArrowDown') {
            select(tileAt((parseInt(selected.dataset.row, 10) + 1) % ROWS, parseInt(selected.dataset.col, 10)));
            e.preventDefault();
            return;
        }
        if (e.key === 'ArrowUp') {
            select(tileAt((parseInt(selected.dataset.row, 10) - 1 + ROWS) % ROWS, parseInt(selected.dataset.col, 10)));
            e.preventDefault();
            return;
        }
        if (e.key.length === 1) {
            var ch = e.key.toUpperCase();
            if (window.ALLOWED_CHARS.indexOf(ch) !== -1) {
                setTile(selected, ch, 'tile tile-char', ch);
                advance();
            }
            e.preventDefault();
        }
    });

    // Kleur- en emoticon-knoppen in het palet delen data-token/data-class.
    Array.prototype.forEach.call(document.querySelectorAll('#palette-colors [data-token], #palette-emoji [data-token]'), function (btn) {
        btn.addEventListener('click', function () {
            if (!selected) return;
            setTile(selected, btn.dataset.token, btn.dataset.class, btn.textContent);
            advance();
        });
    });

    // Op-scherm toetsenbord, opgebouwd uit dezelfde tekenset als de server.
    var keysEl = document.getElementById('palette-keys');
    window.ALLOWED_CHARS.split('').forEach(function (ch) {
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'palette-key';
        btn.textContent = ch === ' ' ? '␣' : ch;
        btn.addEventListener('click', function () {
            if (!selected) return;
            setTile(selected, ch, 'tile tile-char', ch);
            advance();
        });
        keysEl.appendChild(btn);
    });

    document.getElementById('shift-left').addEventListener('click', function () {
        if (!selected) return;
        shiftRow(parseInt(selected.dataset.row, 10), 'left');
    });

    document.getElementById('shift-right').addEventListener('click', function () {
        if (!selected) return;
        shiftRow(parseInt(selected.dataset.row, 10), 'right');
    });

    document.getElementById('clear-cell').addEventListener('click', function () {
        if (!selected) return;
        setTile(selected, '', 'tile tile-blank', '');
    });

    document.getElementById('clear-board').addEventListener('click', function () {
        if (!window.confirm('Hele bord wissen?')) return;
        tiles.forEach(function (tile) { setTile(tile, '', 'tile tile-blank', ''); });
    });

    select(tileAt(0, 0));

    // Lettertype- en themakeuze: direct voorvertonen op het bord in de
    // editor (opslaan gebeurt pas bij het versturen van het formulier).
    var boardEl = document.getElementById('board-grid');
    function previewSetting(selectId, prefix) {
        var sel = document.getElementById(selectId);
        if (!sel || !boardEl) return;
        sel.addEventListener('change', function () {
            Array.prototype.slice.call(boardEl.classList).forEach(function (cls) {
                if (cls.indexOf(prefix) === 0) boardEl.classList.remove(cls);
            });
            boardEl.classList.add(prefix + sel.value);
        });
    }
    previewSetting('font-select', 'font-');
    previewSetting('theme-select', 'board-theme-');

    form.addEventListener('submit', function () {
        var grid = [];
        for (var r = 0; r < ROWS; r++) {
            var row = [];
            for (var c = 0; c < COLS; c++) {
                row.push(tileAt(r, c).dataset.token || '');
            }
            grid.push(row);
        }
        gridInput.value = JSON.stringify(grid);
    });
})();
