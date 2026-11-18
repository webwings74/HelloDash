#!/usr/bin/env python3
"""HelloDash vanaf de command line: bericht plaatsen, bord bekijken en
lettertype/thema wijzigen via de API van de site (site/api.php).

Alleen de standaardbibliotheek, dus geen pip install nodig.

Instellingen: config.py naast dit script (zie config-example.py) met
API_URL en API_KEY, of de omgevingsvariabelen HELLODASH_URL en
HELLODASH_KEY (die gaan voor).

Voorbeelden:
    python3 hellodash.py "Hallo wereld"
    python3 hellodash.py "Goedemorgen|Koffie staat klaar"     # | = nieuwe regel
    python3 hellodash.py "{red}{red} Brandalarm test {red}{red}"
    python3 hellodash.py "Fijn weekend 🎉" --left --top
    python3 hellodash.py '<Links|=Midden|>Rechts'            # uitlijning per regel
    python3 hellodash.py 'Fijne {dag}!|{datumlang}'          # ingebouwde placeholders
    python3 hellodash.py 'Buiten {temp} graden' --var temp=12  # eigen variabele
    python3 hellodash.py --show                              # huidig bord tonen
    python3 hellodash.py --font doto --theme light           # alleen instellingen
    python3 hellodash.py "Test" --dry-run                    # alleen voorvertoning
    python3 hellodash.py --list                              # fonts/thema's/kleuren/emoji
"""

import argparse
import datetime
import json
import os
import re
import sys
import textwrap
import unicodedata
import urllib.error
import urllib.request

# Kleuren voor de terminalweergave (--show / --dry-run); dezelfde waarden
# als de CSS-variabelen in site/style.css.
TERM_COLORS = {
    'red': (192, 57, 43), 'orange': (217, 117, 43), 'yellow': (212, 179, 42),
    'green': (58, 125, 68), 'blue': (47, 95, 168), 'purple': (106, 61, 154),
    'white': (233, 231, 223), 'black': (21, 22, 26),
}
TILE_BG = (27, 29, 32)
TILE_FG = (244, 241, 232)

# Tekens die vaak in gewone tekst staan maar niet op het bord bestaan.
REPLACEMENTS = {
    '‘': "'", '’': "'", '‚': "'", '“': '"', '”': '"', '„': '"',
    '–': '-', '—': '-', '…': '...', '€': 'EUR', '×': 'X', 'ß': 'SS',
}


def fail(message, code=1):
    print('Fout: ' + message, file=sys.stderr)
    sys.exit(code)


# --- Configuratie & API --------------------------------------------------

def load_settings():
    url = os.environ.get('HELLODASH_URL')
    key = os.environ.get('HELLODASH_KEY')
    if not (url and key):
        sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
        try:
            import config  # noqa: E402 - config.py naast dit script
        except ImportError:
            fail('config.py ontbreekt. Kopieer config-example.py naar config.py en vul API_URL en API_KEY in.')
        url = url or getattr(config, 'API_URL', '')
        key = key or getattr(config, 'API_KEY', '')
    if not url or not key:
        fail('API_URL en API_KEY moeten ingevuld zijn (config.py of HELLODASH_URL/HELLODASH_KEY).')
    # Mag de map van de site zijn of direct api.php.
    if not url.endswith('.php'):
        url = url.rstrip('/') + '/api.php'
    return url, key


def api_request(url, key, payload=None):
    data = None
    headers = {'X-API-Key': key, 'Accept': 'application/json', 'User-Agent': 'hellodash-cli'}
    if payload is not None:
        data = json.dumps(payload).encode('utf-8')
        headers['Content-Type'] = 'application/json'
    req = urllib.request.Request(url, data=data, headers=headers, method='POST' if data else 'GET')
    try:
        with urllib.request.urlopen(req, timeout=20) as res:
            return json.loads(res.read().decode('utf-8'))
    except urllib.error.HTTPError as e:
        try:
            msg = json.loads(e.read().decode('utf-8')).get('error', '')
        except ValueError:
            msg = ''
        fail('API gaf HTTP {}{}'.format(e.code, ': ' + msg if msg else ' (bestaat api.php op de site?)'))
    except urllib.error.URLError as e:
        fail('kan {} niet bereiken: {}'.format(url, e.reason))
    except ValueError:
        fail('API gaf geen geldige JSON terug (klopt API_URL?).')


# --- Placeholders -------------------------------------------------------

DAGEN = ['maandag', 'dinsdag', 'woensdag', 'donderdag', 'vrijdag', 'zaterdag', 'zondag']
MAANDEN = ['januari', 'februari', 'maart', 'april', 'mei', 'juni', 'juli',
           'augustus', 'september', 'oktober', 'november', 'december']


def builtin_vars(now=None):
    """Ingebouwde placeholders, op het moment van plaatsen ingevuld."""
    now = now or datetime.datetime.now()
    return {
        'datum': now.strftime('%d-%m-%Y'),             # 14-11-2026
        'datumlang': '{} {} {}'.format(now.day, MAANDEN[now.month - 1], now.year),  # 14 november 2026
        'tijd': now.strftime('%H:%M'),                 # 12:30
        'dag': DAGEN[now.weekday()],                   # zaterdag
        'maand': MAANDEN[now.month - 1],               # november
        'jaar': str(now.year),                         # 2026
        'week': str(now.isocalendar()[1]),             # 46
    }


def parse_vars(pairs, colors):
    """--var naam=waarde (herhaalbaar) -> dict. Namen zijn hoofdletter-
    ongevoelig; kleurnamen zijn gereserveerd voor kleurtegels."""
    result = {}
    for pair in pairs or []:
        name, sep, value = pair.partition('=')
        name = name.strip().lower()
        if not sep or not re.fullmatch(r'[a-z0-9_]+', name):
            fail('--var verwacht naam=waarde (naam: letters, cijfers, _), niet "{}".'.format(pair))
        if name in colors:
            fail('"{}" is een kleurnaam en kan geen variabele zijn.'.format(name))
        result[name] = value
    return result


def fill_placeholders(text, variables, colors):
    """Vervangt {naam} door de waarde; {kleur} blijft staan voor
    tokenize_line. Een onbekende naam is een fout (anders zou hij stil
    verdwijnen, want { en } bestaan niet op het bord)."""
    unknown = set()

    def repl(m):
        name = m.group(1).lower()
        if name in colors:
            return m.group(0)
        if name in variables:
            return variables[name]
        unknown.add(m.group(1))
        return m.group(0)

    filled = re.sub(r'\{([A-Za-z0-9_]+)\}', repl, text)
    if unknown:
        fail('onbekende placeholder(s): {}. Geef ze mee met --var naam=waarde, of kies uit: {}.'.format(
            ', '.join('{' + u + '}' for u in sorted(unknown)),
            ', '.join('{' + k + '}' for k in sorted(variables))))
    return filled


# --- Tekst -> grid -------------------------------------------------------

def normalize_text(text):
    """Hoofdletters, accenten eraf (é -> E), typografische tekens vervangen."""
    out = []
    for ch in text:
        ch = REPLACEMENTS.get(ch, ch)
        if len(ch) == 1 and ord(ch) < 0x2000:
            decomposed = unicodedata.normalize('NFKD', ch)
            ch = ''.join(c for c in decomposed if not unicodedata.combining(c)) or ch
        out.append(ch.upper() if ch.isascii() else ch)
    return ''.join(out)


def tokenize_line(line, info, warnings):
    """Zet één tekstregel om in tokens: tekens, {kleur} en emoji. Een spatie
    wordt '' (lege tegel)."""
    chars = info['chars']
    colors = info['colors']
    # Emoji ook herkennen zonder variatieteken (U+FE0F), bv. ❤ naast ❤️.
    emoji = {}
    for e in info['emoji']:
        emoji[e] = e
        emoji.setdefault(e.replace('️', ''), e)
    emoji_keys = sorted(emoji, key=len, reverse=True)

    tokens = []
    i = 0
    while i < len(line):
        if line[i] == '{':
            end = line.find('}', i)
            name = line[i + 1:end].lower() if end != -1 else ''
            if name in colors:
                tokens.append('COLOR:' + name)
                i = end + 1
                continue
        match = next((k for k in emoji_keys if line.startswith(k, i)), None)
        if match:
            tokens.append(emoji[match])
            i += len(match)
            continue
        ch = line[i]
        if ch == ' ':
            tokens.append('')
        elif ch in chars:
            tokens.append(ch)
        elif ch != '️':
            warnings.add(ch)
        i += 1
    return tokens


def wrap_tokens(tokens, width):
    """Woordafbreking op tegelniveau: woorden zijn reeksen niet-lege tokens."""
    words, word = [], []
    for t in tokens:
        if t == '':
            if word:
                words.append(word)
                word = []
        else:
            word.append(t)
    if word:
        words.append(word)

    lines, cur = [], []
    for w in words:
        while len(w) > width:  # te lang woord: hard afbreken
            if cur:
                lines.append(cur)
                cur = []
            lines.append(w[:width])
            w = w[width:]
        needed = len(w) + (1 if cur else 0)
        if len(cur) + needed > width:
            lines.append(cur)
            cur = []
        cur = cur + ([''] if cur else []) + w
    if cur:
        lines.append(cur)
    return lines


# Uitlijning per regel: een van deze tekens aan het begin van een regel gaat
# voor --left/--right/standaard. Geen van deze tekens bestaat op het bord, dus
# ze botsen niet met de tekst zelf. \l \c \r werken ook.
LINE_ALIGN = [('\\l', 'left'), ('\\c', 'center'), ('\\r', 'right'),
              ('<', 'left'), ('=', 'center'), ('>', 'right')]


def split_line_align(raw, default):
    raw = raw.strip()
    for marker, align in LINE_ALIGN:
        if raw.lower().startswith(marker):
            return raw[len(marker):].strip(), align
    return raw, default


def text_to_grid(text, info, align='center', valign='middle', wrap=True):
    rows, cols = info['rows'], info['cols']
    warnings = set()
    lines = []  # (tokens, uitlijning)
    for raw in text.replace('\\n', '|').replace('\n', '|').split('|'):
        raw, line_align = split_line_align(raw, align)
        tokens = tokenize_line(normalize_text(raw), info, warnings)
        if wrap:
            lines.extend((t, line_align) for t in (wrap_tokens(tokens, cols) or [[]]))
        else:
            lines.append((tokens[:cols], line_align))
    if warnings:
        print('Let op: weggelaten (niet op het bord): ' + ' '.join(sorted(warnings)), file=sys.stderr)
    if len(lines) > rows:
        fail('tekst past niet: {} regels nodig, het bord heeft er {}.'.format(len(lines), rows))

    grid = [[''] * cols for _ in range(rows)]
    top = {'top': 0, 'middle': (rows - len(lines)) // 2, 'bottom': rows - len(lines)}[valign]
    for n, (line, line_align) in enumerate(lines):
        left = {'left': 0, 'center': (cols - len(line)) // 2, 'right': cols - len(line)}[line_align]
        for c, token in enumerate(line):
            grid[top + n][left + c] = token
    return grid


# --- Weergave in de terminal ---------------------------------------------

def print_grid(grid, emoji):
    color = sys.stdout.isatty() and os.environ.get('NO_COLOR') is None

    def cell(token):
        if token.startswith('COLOR:'):
            rgb = TERM_COLORS.get(token[6:], TILE_BG)
            return ('\x1b[48;2;{};{};{}m   \x1b[0m'.format(*rgb)) if color else '[{}]'.format(token[6].upper())
        if token in emoji:
            return (' ' + token) if color else ' ' + token
        label = ' {} '.format(token or ' ')
        if color:
            return '\x1b[48;2;{};{};{}m\x1b[38;2;{};{};{}m\x1b[1m{}\x1b[0m'.format(*TILE_BG, *TILE_FG, label)
        return label

    sep = ' ' if color else ''
    for row in grid:
        print(sep.join(cell(t) for t in row))


# --- Main ----------------------------------------------------------------

def main():
    parser = argparse.ArgumentParser(
        description='Bericht op HelloDash zetten, of het bord bekijken.',
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog=textwrap.dedent('''\
            Tekst: | of \\n = nieuwe regel, {kleurnaam} = kleurtegel (bv. {red}),
            een regel die begint met < = > (of \\l \\c \\r) wordt links, gecentreerd
            of rechts uitgelijnd, los van --left/--right,
            {naam} = placeholder: ingebouwd {datum} {datumlang} {tijd} {dag} {maand}
            {jaar} {week}, of eigen waarden met --var naam=waarde,
            emoji uit de vaste lijst worden herkend (zie --list). Kleine letters
            en accenten worden omgezet (é -> E); andere tekens vallen weg.'''))
    parser.add_argument('text', nargs='*', help='het bericht (meerdere woorden mag zonder aanhalingstekens)')
    parser.add_argument('--left', dest='align', action='store_const', const='left', default='center', help='links uitlijnen (standaard gecentreerd)')
    parser.add_argument('--right', dest='align', action='store_const', const='right', help='rechts uitlijnen')
    parser.add_argument('--top', dest='valign', action='store_const', const='top', default='middle', help='bovenaan (standaard verticaal gecentreerd)')
    parser.add_argument('--bottom', dest='valign', action='store_const', const='bottom', help='onderaan')
    parser.add_argument('--no-wrap', action='store_true', help='niet afbreken; te lange regels worden afgekapt')
    parser.add_argument('--font', help='lettertype van het bord (zie --list)')
    parser.add_argument('--theme', help='thema van het bord: dark, light of auto')
    parser.add_argument('--clear', action='store_true', help='bord leegmaken')
    parser.add_argument('--file', help='JSON-bestand met een volledig grid (lijst van regels met tokens)')
    parser.add_argument('--show', action='store_true', help='huidig bord tonen')
    parser.add_argument('--list', action='store_true', help='lettertypes, thema\'s, kleuren en emoji tonen')
    parser.add_argument('--var', action='append', metavar='NAAM=WAARDE', help='waarde voor placeholder {NAAM} in de tekst (herhaalbaar)')
    parser.add_argument('--dry-run', action='store_true', help='alleen tonen wat er geplaatst zou worden')
    args = parser.parse_args()

    url, key = load_settings()
    info = api_request(url, key)

    if args.list:
        print('Lettertypes: ' + ', '.join('{} ({})'.format(k, v) for k, v in info['fonts'].items()))
        print('Thema\'s:     ' + ', '.join(info['themes']))
        print('Kleuren:     ' + ', '.join('{' + c + '}' for c in info['colors']))
        print('Emoji:       ' + ' '.join(info['emoji']))
        print('Tekens:      ' + info['chars'].replace(' ', ''))
        print('Bord:        {} x {}, nu: font={}, thema={}'.format(info['cols'], info['rows'], info['font'], info['theme']))
        return

    payload = {}
    sources = sum(bool(x) for x in (args.text, args.clear, args.file))
    if sources > 1:
        fail('kies één van: tekst, --clear of --file.')
    if args.text:
        variables = builtin_vars()
        variables.update(parse_vars(args.var, info['colors']))  # eigen --var gaat voor
        text = fill_placeholders(' '.join(args.text), variables, info['colors'])
        payload['grid'] = text_to_grid(text, info, args.align, args.valign, not args.no_wrap)
    elif args.clear:
        payload['grid'] = [[''] * info['cols'] for _ in range(info['rows'])]
    elif args.file:
        with open(args.file, encoding='utf-8') as fh:
            payload['grid'] = json.load(fh)
    if args.font:
        if args.font not in info['fonts']:
            fail('onbekend lettertype "{}". Kies uit: {}'.format(args.font, ', '.join(info['fonts'])))
        payload['font'] = args.font
    if args.theme:
        if args.theme not in info['themes']:
            fail('onbekend thema "{}". Kies uit: {}'.format(args.theme, ', '.join(info['themes'])))
        payload['theme'] = args.theme

    if not payload:
        if args.show or not sys.argv[1:]:
            print('Huidig bericht (#{}, font={}, thema={}):'.format(info['id'], info['font'], info['theme']))
            print_grid(info['grid'], set(info['emoji']))
            return
        fail('niets te doen; zie --help.')

    if 'grid' in payload and (args.dry_run or args.show):
        print_grid(payload['grid'], set(info['emoji']))
    if args.dry_run:
        rest = {k: v for k, v in payload.items() if k != 'grid'}
        if rest:
            print('Instellingen: ' + json.dumps(rest))
        print('(dry-run: niets verstuurd)')
        return

    result = api_request(url, key, payload)
    parts = []
    if result.get('id'):
        parts.append('bericht #{} geplaatst'.format(result['id']))
    if 'font' in payload or 'theme' in payload:
        parts.append('font={}, thema={}'.format(result.get('font'), result.get('theme')))
    print('OK: ' + '; '.join(parts))


if __name__ == '__main__':
    main()
