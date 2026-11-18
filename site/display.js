(function () {
    'use strict';
    var ROWS = window.GRID_ROWS || 6, COLS = window.GRID_COLS || 24;
    var POLL_MS = 4000;
    var FLIP_MS = 600; // duur van de laatste, "echte" flip - zie .tile.flipping in style.css
    var STEP_MS = 90;  // duur per tussenstap bij het doorlopen van het "alfabet"
    var INTRO_DELAY_MS = 500; // pauze na het laden voordat het bericht binnenflapt
    var boardEl = document.getElementById('board-grid');
    var lastId = window.INITIAL_ID || 0;
    var emojiSet = {};
    (window.ALLOWED_EMOJI || []).forEach(function (e) { emojiSet[e] = true; });
    var sequence = (window.ALLOWED_CHARS || '').split('');
    var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    var SOUND_ENABLED = true;
    var CLACK_VOLUME = 0.35; // 0 = stil, 1 = luidst
    var audioCtx = null;

    function getAudioCtx() {
        if (!SOUND_ENABLED) return null;
        if (!audioCtx) {
            var Ctx = window.AudioContext || window.webkitAudioContext;
            if (!Ctx) return null;
            audioCtx = new Ctx();
        }
        return audioCtx;
    }

    // Browsers staan geluid pas toe na een eerste gebruikersinteractie met de
    // pagina. Op een onbemand beeldscherm gebeurt dat misschien nooit - dan
    // blijft het stil totdat je dat omzeilt (zie README.md: Chromium-kiosk-
    // vlag of één keer aanraken van het scherm).
    ['click', 'touchstart', 'keydown'].forEach(function (evt) {
        document.addEventListener(evt, function () {
            var ctx = getAudioCtx();
            if (ctx && ctx.state === 'suspended') ctx.resume();
        }, { passive: true });
    });

    // Korte, gefilterde ruisburst met snelle uitklinktijd - de "tik" van een
    // mechanische flap die langsklapt. Geen los geluidsbestand nodig.
    function playClack() {
        var ctx = getAudioCtx();
        if (!ctx || ctx.state !== 'running') return;

        var now = ctx.currentTime;
        var duration = 0.03;
        var bufferSize = Math.floor(ctx.sampleRate * duration);
        var buffer = ctx.createBuffer(1, bufferSize, ctx.sampleRate);
        var data = buffer.getChannelData(0);
        for (var i = 0; i < bufferSize; i++) {
            data[i] = (Math.random() * 2 - 1) * (1 - i / bufferSize);
        }

        var noise = ctx.createBufferSource();
        noise.buffer = buffer;

        var bandpass = ctx.createBiquadFilter();
        bandpass.type = 'bandpass';
        bandpass.frequency.value = 2200;
        bandpass.Q.value = 1.2;

        var gain = ctx.createGain();
        gain.gain.setValueAtTime(CLACK_VOLUME, now);
        gain.gain.exponentialRampToValueAtTime(0.001, now + duration);

        noise.connect(bandpass).connect(gain).connect(ctx.destination);
        noise.start(now);
        noise.stop(now + duration);
    }

    function tileAt(row, col) {
        return boardEl.querySelector('.tile[data-row="' + row + '"][data-col="' + col + '"]');
    }

    function classesFor(token) {
        if (token === '') return 'tile tile-blank';
        if (token.indexOf('COLOR:') === 0) return 'tile tile-color-' + token.slice(6);
        if (emojiSet[token]) return 'tile tile-emoji';
        return 'tile tile-char';
    }

    function labelFor(token) {
        return token.indexOf('COLOR:') === 0 ? '' : token;
    }

    // Positie van een teken in de vaste tekenreeks (hetzelfde "toetsenbord"
    // als onder het bord op bericht.php). Een lege tegel telt als een
    // spatie: dat is de dichtstbijzijnde fysieke flap-positie.
    function sequenceIndex(token) {
        return sequence.indexOf(token === '' ? ' ' : token);
    }

    // Bouwt het pad van tussenliggende tekens van oud naar nieuw, steeds één
    // kant op (zoals een echt flap-wiel nooit achteruit draait). Geeft null
    // terug zodra oud of nieuw geen teken uit de reeks is (kleur, emoticon)
    // - dan valt updateTile terug op één enkele flip.
    function buildPath(oldToken, newToken) {
        var oldIdx = sequenceIndex(oldToken);
        var newIdx = sequenceIndex(newToken);
        if (oldIdx === -1 || newIdx === -1 || sequence.length === 0) return null;
        var steps = [];
        var i = oldIdx;
        while (i !== newIdx) {
            i = (i + 1) % sequence.length;
            steps.push(i === newIdx ? newToken : sequence[i]);
        }
        if (steps.length === 0) steps.push(newToken);
        return steps;
    }

    // De "echte" landing: dezelfde kanteling-met-schaduw als voorheen.
    function settleTile(tile, token) {
        if (reduceMotion) {
            tile.dataset.token = token;
            tile.className = classesFor(token);
            tile.textContent = labelFor(token);
            return;
        }
        tile.classList.add('flipping');
        setTimeout(function () {
            tile.dataset.token = token;
            tile.className = classesFor(token) + ' flipping';
            tile.textContent = labelFor(token);
            playClack();
        }, FLIP_MS / 2);
        setTimeout(function () {
            tile.classList.remove('flipping');
        }, FLIP_MS);
    }

    // Loopt rap door de tussenliggende tekens (alleen tekst, geen kantel-
    // animatie - bij dat tempo leest het al als "doorbladeren") en laat de
    // laatste stap met settleTile() landen.
    function runPath(tile, steps, index) {
        if (index >= steps.length - 1) {
            settleTile(tile, steps[steps.length - 1]);
            return;
        }
        var token = steps[index];
        tile.dataset.token = token;
        tile.textContent = labelFor(token);
        playClack();
        setTimeout(function () { runPath(tile, steps, index + 1); }, STEP_MS);
    }

    function updateTile(tile, newToken) {
        var oldToken = tile.dataset.token || '';
        var path = reduceMotion ? null : buildPath(oldToken, newToken);
        if (path) {
            runPath(tile, path, 0);
        } else {
            settleTile(tile, newToken);
        }
    }

    function applyGrid(grid) {
        for (var r = 0; r < ROWS; r++) {
            for (var c = 0; c < COLS; c++) {
                var token = (grid[r] && grid[r][c]) || '';
                var tile = tileAt(r, c);
                if (!tile || tile.dataset.token === token) continue;
                updateTile(tile, token);
            }
        }
    }

    // Lettertype en thema zijn bordinstellingen, los van het bericht: wissel
    // alleen de klasse <prefix><waarde> (zie style.css), zonder animatie.
    function swapClass(el, prefix, value) {
        if (!/^[a-z0-9-]+$/.test(value) || el.classList.contains(prefix + value)) return;
        Array.prototype.slice.call(el.classList).forEach(function (cls) {
            if (cls.indexOf(prefix) === 0) el.classList.remove(cls);
        });
        el.classList.add(prefix + value);
    }

    function poll() {
        fetch('check.php', { cache: 'no-store' })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.font) swapClass(boardEl, 'font-', data.font);
                if (data.theme) {
                    swapClass(boardEl, 'board-theme-', data.theme);
                    swapClass(document.body, 'board-theme-', data.theme);
                }
                if (data.id !== lastId) {
                    lastId = data.id;
                    applyGrid(data.grid);
                }
            })
            .catch(function () {});
    }

    // Intro: index.php levert een leeg bord; laat het huidige bericht er na
    // het laden in flappen, net als bij een nieuw bericht. Eerst wachten op
    // de lettertypes, zodat de animatie niet halverwege van font wisselt.
    function intro() {
        setTimeout(function () { applyGrid(window.INITIAL_GRID || []); }, INTRO_DELAY_MS);
    }
    // Een leeg bord bevat geen tekst, dus de browser zou het lettertype pas
    // laden als de eerste letters verschijnen. Een onzichtbare proeftegel
    // vertelt welk font actief is; dat laden we expliciet vóór de intro.
    var probe = document.createElement('div');
    probe.className = 'tile tile-char';
    probe.setAttribute('aria-hidden', 'true');
    probe.style.cssText = 'position:absolute;visibility:hidden;pointer-events:none;';
    probe.textContent = 'A';
    boardEl.appendChild(probe);
    var probeStyle = getComputedStyle(probe);
    var fontSpec = probeStyle.fontWeight + ' 1em ' + probeStyle.fontFamily;
    boardEl.removeChild(probe);
    if (document.fonts && document.fonts.load) {
        // Hooguit 3 s wachten: een traag of ontbrekend font mag de intro niet tegenhouden.
        Promise.race([
            document.fonts.load(fontSpec),
            new Promise(function (resolve) { setTimeout(resolve, 3000); }),
        ]).then(intro, intro);
    } else {
        intro();
    }

    // Scherm aan houden (Screen Wake Lock API), zodat het beeldscherm niet in
    // slaapstand gaat zolang het bord zichtbaar is - zoals YouTube tijdens
    // het afspelen. Werkt alleen via HTTPS (of localhost). De browser laat
    // het slot los zodra het tabblad onzichtbaar wordt; daarom vragen we het
    // opnieuw aan als de pagina weer zichtbaar is. Sommige browsers geven het
    // pas na een eerste klik/tik, dus dan proberen we het daar ook nog eens.
    var KEEP_SCREEN_ON = true;
    var wakeLock = null;

    function requestWakeLock() {
        if (!KEEP_SCREEN_ON || !('wakeLock' in navigator) || wakeLock || document.visibilityState !== 'visible') return;
        navigator.wakeLock.request('screen').then(function (lock) {
            wakeLock = lock;
            lock.addEventListener('release', function () { wakeLock = null; });
        }).catch(function () {});
    }

    document.addEventListener('visibilitychange', requestWakeLock);
    ['click', 'touchstart', 'keydown'].forEach(function (evt) {
        document.addEventListener(evt, requestWakeLock, { passive: true });
    });
    requestWakeLock();

    setInterval(poll, POLL_MS);
})();
