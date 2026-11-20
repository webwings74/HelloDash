# HelloDash

Een "beeldscherm/dashboard"-website die eruitziet als een (mechanisch)
Vestaboard-bord (www.vestaboard.com): een raster van 24 tekens breed x
standaard 6 regels (instelbaar, zie `BOARD_ROWS` hieronder), waarop steeds
één actueel bericht wordt getoond. Elke tegel is een letter/cijfer, een effen
kleurvlak, of een van een beperkt aantal emoticons. Geen framework of
Composer — gewone PHP-bestanden.

Berichten worden nu geplaatst via een pincode-beveiligde webpagina
(`bericht.php`). Er is bewust nog geen API-endpoint; die komt later en
schrijft dan naar dezelfde `messages`-tabel, dus daar hoeft nu niets voor
voorbereid te worden.

## Mapstructuur

- `site/` — de daadwerkelijke website. Deze map is zelfstandig en is dus de
  map die naar de webserver gaat.
- `hellodash.py` — command-line tool om berichten te plaatsen via de API
  (zie "Command line" hieronder); `config-example.py` is het sjabloon voor
  zijn `config.py`. Draait op je eigen computer, hoort niet op de webserver.
- `hellodash.sql` — maakt de tabellen `messages` en `settings` aan. Opnieuw importeren is veilig (`CREATE TABLE IF NOT EXISTS`); doe dat ook na een update die een nieuwe tabel toevoegt.

### Bestanden in `site/`

| Bestand | Doel |
|---|---|
| `config.php` | Databaseverbindingsgegevens, de pincode, `BOARD_ROWS` (aantal regels, standaard 6) en `API_KEY` (voor `api.php`). Staat niet in git: kopieer `config-example.php` naar `config.php` en vul de echte gegevens in |
| `db.php` | Maakt de PDO-verbinding (`$pdo`) op basis van `config.php`; DSN-charset `utf8mb4` (er staan emoticons in het bord) |
| `pin.php` | Pincodebeveiliging: elke wijziging (POST) wordt pas uitgevoerd na de juiste pincode; geladen via `helpers.php` |
| `helpers.php` | Hulpfuncties: `h()` (escapen voor HTML), `redirect()`, laadt `pin.php` |
| `header.php` / `nav.php` | Gedeeld titelblok + menubalk (Dashboard, Bericht plaatsen, Lettertypes) met licht/donker-themaknop. Alleen gebruikt door `bericht.php` en de pincodepagina — `index.php` heeft bewust geen sitechrome |
| `style.css` | Opmaak: kleurvariabelen met licht/donker-thema voor de site, plus het bord/tegel-ontwerp |
| `fonts/` | Lettertypes voor de tegels, allemaal zelf gehost (geen internetverbinding nodig tijdens het draaien). Van Google Fonts met OFL-licentie (`fonts/OFL-*.txt`): Fjalla One (standaard), Oswald, Bebas Neue, Anton, Space Mono, Saira Condensed, Orbitron en Doto. Daarnaast Split-Flap TV en Split-Flap TV BlackLine (`.ttf` en `.otf`), die geen OFL-licentie hebben. Zie "Lettertype" hieronder |
| `board.php` | Kern van het bord: afmetingen (24x6), toegestane tekens/kleuren/emoticons, bordinstellingen (`BOARD_FONTS`, `BOARD_THEMES`, `board_font()`, `board_theme()`, `board_save_setting()`), `validate_grid()`, `render_grid()`, `latest_message()` — gedeeld door de editor en de weergave |
| `bericht.php` | Pincode-beveiligde editor: klik een tegel, typ een teken of kies een kleur/emoticon uit het palet; slaat een nieuw bericht op. Bevat ook de keuze voor lettertype en thema van het bord |
| `editor.js` | Interactie voor de editor: celselectie (klik of pijltjestoetsen), toetsenbord, palet, en de knoppen "Regel ⟵"/"Regel ⟶" om de regel van de geselecteerde tegel rond te schuiven (handig om tekst te centreren) |
| `fonts.php` | Proefpagina met zo'n 35 kandidaat-lettertypes van Google Fonts op bordtegels, met eigen proeftekst en thema. Aanvinken + "Selectie kopiëren" levert een lijstje op om toe te voegen. Laadt de fonts rechtstreeks van Google, dus heeft (anders dan het bord) internet nodig |
| `index.php` | Publieke, read-only volledig-scherm weergave van het huidige bericht |
| `api.php` | JSON-API voor `hellodash.py`: `GET` geeft het huidige bericht en de instellingen, `POST` plaatst een bericht en/of wijzigt lettertype/thema. Vereist de header `X-API-Key`; uitgeschakeld zolang `API_KEY` in `config.php` ontbreekt. Zie "Command line" hieronder |
| `check.php` | JSON-polling-endpoint (`{id, grid, font, theme}`) dat `index.php` elke paar seconden bevraagt |
| `display.js` | Pollt `check.php`. Verandert een tegel van letter/cijfer/leesteken, dan flikkert hij razendsnel door alle tussenliggende tekens van het toetsenbord (zoals een echt flap-wiel, dat altijd maar één kant op draait) en landt met een kanteling; bij een kleur- of emoticon-wijziging blijft het bij die ene kanteling. Bij het laden of verversen van de pagina start het bord leeg en flapt het huidige bericht er op dezelfde manier in (`index.php` geeft het mee als `INITIAL_GRID`; `INTRO_DELAY_MS` in `display.js` is de pauze vooraf). Speelt bij elke stap een kort, zelf gesynthetiseerd "tik"-geluid af (Web Audio API, geen geluidsbestand nodig) — zie "Geluid" hieronder |

## Bericht

Een bericht is een raster van `BOARD_ROWS` regels (standaard 6, instelbaar in
`config.php`) x 24 tekens, opgeslagen als JSON in `messages.grid_json`. De rij
met het hoogste `id` is altijd het huidige (enige) bericht — geen wachtrij,
geen `active`-vlag. Elke tegel is:

- een leeg veld,
- precies één teken (hoofdletter, cijfer of leesteken),
- een effen kleurvlak (`COLOR:<naam>`, uit een vaste lijst van 8 kleuren), of
- een van de 16 vaste emoticons.

**`BOARD_ROWS` aanpassen:** het aantal kolommen (24) staat vast, maar een
gewoon beeldscherm past vaak meer dan 6 regels. Zet `BOARD_ROWS` in
`config.php` op het gewenste aantal. Let op: een al opgeslagen bericht met
een ander aantal regels dan de nieuwe instelling wordt dan als ongeldig
gezien (`validate_grid()` controleert het exacte aantal regels) en het bord
toont tijdelijk leeg, totdat je opnieuw een bericht opslaat met het nieuwe
aantal regels.

Alle validatie gebeurt server-side in `board.php` vóór het opslaan.

## Lettertype

Het lettertype van de tegels is één instelling voor het hele bord (niet per
bericht), opgeslagen in de `settings`-tabel (`name = 'font'`). Kiezen gaat in
`bericht.php`: de keuzelijst toont het direct op het bord in de editor, en
wordt opgeslagen met "Plaatsen op dashboard" (bericht + instellingen) of
"Alleen instellingen opslaan" (alleen lettertype en thema; niet-geplaatste
wijzigingen aan het bericht gaan dan verloren). `index.php` neemt een nieuw
lettertype bij de volgende poll over, zonder flap-animatie.

Ontbreekt de `settings`-tabel of de instelling, dan valt alles terug op
Fjalla One. De tabel wordt bij het eerste opslaan van een lettertype
automatisch aangemaakt (`board_ensure_settings_table()`), dus opnieuw
importeren van `hellodash.sql` is daarvoor niet nodig.

Een lettertype toevoegen: zet het `.ttf`-bestand en de licentie in `fonts/`,
voeg een `@font-face` en een `.font-<sleutel> .tile-char`-blok toe in
`style.css`, en zet `'<sleutel>' => '<Naam>'` in `BOARD_FONTS` (`board.php`).
`font-size-adjust: cap-height 0.834` maakt de hoofdletters van elk font even
hoog als die van Fjalla One; met `--font-pad-top`/`--font-pad-bottom` (in
`em`) zet je de letters verticaal recht in de tegel.

**Split-Flap TV** (en de variant **BlackLine**, met een dubbele lijn over de
kaart) werkt anders dan de andere fonts: elk teken is een complete flap-kaart
in de letterkleur, met de letter erin uitgespaard. Op het bord verschijnen
dus kaartjes op de tegels (licht bij het donkere thema, zwart bij het
lichte); lege tegels blijven gewone tegels. `style.css` zet daarom de
tegel-padding uit en gebruikt een kleinere `font-size-adjust`. De
fontbestanden bevatten geen copyright- of licentiegegevens en er zit geen
licentiebestand bij: controleer de licentie bij de bron van het font
voordat je het publiek gebruikt.

## Thema scherm

Net als het lettertype is het kleurthema van het bord één instelling in de
`settings`-tabel (`name = 'theme'`), te kiezen in `bericht.php` naast het
lettertype:

- **Donker** (standaard): zwarte tegels met witte letters.
- **Licht**: witte tegels met zwarte letters op een lichte achtergrond.
- **Automatisch**: volgt de licht/donker-instelling van het apparaat waarop
  `index.php` draait.

Dit staat los van de licht/donker-knop rechtsboven op de site zelf; die
geldt alleen voor de pagina's met menubalk, per browser. De kleuren staan
als CSS-variabelen in `style.css` bij `.board-theme-light`.

## Geluid

`index.php` speelt bij elke flap-stap een kort "tik"-geluid af, gesynthetiseerd
in `display.js` met de Web Audio API (gefilterde ruisburst, geen los
geluidsbestand). Aan/uit en volume staan als `SOUND_ENABLED`/`CLACK_VOLUME`
bovenaan dat bestand.

**Let op:** browsers blokkeren geluid totdat er één keer ergens op de pagina
is geklikt/getikt (autoplay-beleid). `display.js` ontgrendelt dit automatisch
bij de eerste interactie, maar op een écht onbemand scherm gebeurt die
interactie misschien nooit. Oplossingen:

- Eén keer het scherm aanraken/aanklikken na het opstarten (werkt daarna de
  rest van de sessie).
- Chromium/Chrome in kiosk-modus starten met de vlag
  `--autoplay-policy=no-user-gesture-required`, zodat geluid meteen is
  toegestaan.

## Scherm aan houden

`index.php` vraagt de browser om het scherm aan te houden (Screen Wake Lock
API, in `display.js`; uit te zetten met `KEEP_SCREEN_ON`), zodat het
beeldscherm niet in slaapstand gaat zolang het bord in beeld is. Voorwaarden:

- De site moet via **HTTPS** draaien (of `localhost`); via gewoon `http://`
  staat de browser het niet toe.
- Het tabblad moet zichtbaar zijn. Na wisselen van tabblad of venster wordt
  het slot automatisch opnieuw aangevraagd.
- Ondersteund door Chrome/Edge, Safari 16.4+ en Firefox 126+. Sommige
  browsers geven het pas na één klik/tik op de pagina.

Dit voorkomt alleen dat het *scherm* uitgaat door inactiviteit; een
ingestelde vaste slaaptijd van de computer zelf of een schermbeveiliging met
wachtwoord kan nog steeds ingrijpen.

## Command line (`hellodash.py`)

`hellodash.py` (in deze map, níet in `site/`) plaatst berichten en wijzigt
lettertype/thema vanaf de command line, via de JSON-API `site/api.php`.
Rechtstreeks met de database verbinden kan niet: die accepteert alleen
verbindingen van de webserver zelf. Alleen de standaardbibliotheek van
Python, geen pip install nodig.

Eenmalig instellen:

1. Zet in `site/config.php` een `API_KEY` (minimaal 16 tekens, zie
   `config-example.php`) en upload `config.php` en `api.php`. Zonder
   `API_KEY` is de API uitgeschakeld.
2. Kopieer `config-example.py` naar `config.py` en vul `API_URL` (de map
   van de site) en dezelfde `API_KEY` in. `config.py` staat in `.gitignore`.
   In plaats daarvan kan ook `HELLODASH_URL`/`HELLODASH_KEY` als
   omgevingsvariabele.

Gebruik:

```bash
python3 hellodash.py "Hallo wereld"                     # gecentreerd, automatisch afgebroken
python3 hellodash.py "Goedemorgen|Koffie staat klaar"   # | = nieuwe regel
python3 hellodash.py "{red}{red} Alarm {red}{red}"      # {kleur} = kleurtegel
python3 hellodash.py "Fijn weekend 🎉" --left --top     # ook --right, --bottom
python3 hellodash.py '<Links|=Midden|>Rechts'           # uitlijning per regel (ook \l \c \r)
python3 hellodash.py 'Fijne {dag}!|{datumlang}'         # ingebouwde placeholders
python3 hellodash.py 'Buiten {temp} graden' --var temp=12   # eigen variabele
python3 hellodash.py --font doto --theme light          # alleen instellingen
python3 hellodash.py --show                             # huidig bord in de terminal
python3 hellodash.py "Test" --dry-run                   # voorvertoning, niets versturen
python3 hellodash.py --clear                            # bord leeg
python3 hellodash.py --file bericht.json                # volledig grid uit JSON
python3 hellodash.py --list                             # fonts, thema's, kleuren, emoji
```

Een regel die begint met `<`, `=` of `>` (of `\l`, `\c`, `\r`) wordt links,
gecentreerd of rechts uitgelijnd, los van `--left`/`--right` voor de rest.
Gebruik in zsh enkele aanhalingstekens (`'...'`): `<`/`>` zijn anders
omleidingen en `!` binnen dubbele aanhalingstekens is geschiedenis-expansie.

Placeholders `{naam}` worden bij het plaatsen ingevuld. Ingebouwd: `{datum}`
(14-11-2026), `{datumlang}` (14 november 2026), `{tijd}` (12:30), `{dag}`
(zaterdag), `{maand}`, `{jaar}` en `{week}` (ISO-weeknummer). Eigen waarden
met `--var naam=waarde` (herhaalbaar; gaat voor een ingebouwde met dezelfde
naam). Kleurnamen zoals `{red}` blijven kleurtegels. Een onbekende
placeholder geeft een foutmelding in plaats van stil te verdwijnen.

Kleine letters en accenten worden omgezet (é → E, “ ” → "); tekens die niet
op het bord bestaan vallen weg met een waarschuwing. Past de tekst niet in
het aantal regels, dan stopt het script met een foutmelding.

`api.php`: `GET` geeft het huidige bericht, de instellingen en alle
toegestane tekens/kleuren/emoji/fonts/thema's; `POST` met JSON
`{"grid": [...], "font": "...", "theme": "..."}` (elk optioneel, minstens
één). Elke aanvraag vereist de header `X-API-Key`.

## Lokaal draaien

```
cp site/config-example.php site/config.php   # eenmalig, daarna invullen
cd site
php -S localhost:8000
```

Open `http://localhost:8000/bericht.php` om een bericht te maken, en
`http://localhost:8000/index.php` voor de weergave.

## Pincode

`pin.php` vangt elk
POST-verzoek af en vraagt om de pincode uit `config.php` (`PIN_CODE`,
`PIN_TIMEOUT`). Na 5 foute pogingen vanaf hetzelfde IP-adres is wijzigen 15
minuten geblokkeerd. Lezen (`index.php`, `check.php`) blijft voor iedereen
open, zonder pincode.

## Hosting

Draait op gewone PHP-webhosting met MariaDB/MySQL. Staan site en database
bij dezelfde provider, dan is `DB_HOST` meestal `localhost`. Verhuist de site
ooit naar bijvoorbeeld een Synology (Web Station), dan draaien app en
database ook daar op dezelfde machine — zie de commentaarregels boven de
`DB_*`-constanten in `config-example.php`.

Veel hostingpakketten hebben maar één database per account, die je dan deelt
met andere sites. Dat kan prima: HelloDash gebruikt alleen de tabellen
`messages` en `settings`. Zorg wel dat geen ander project in dezelfde
database tabellen met die namen gebruikt.

## Schema

- `messages`: `id` (PK, auto-increment), `grid_json` (JSON van het
  `BOARD_ROWS`x24 raster als tekst), `created_at`. Zie `hellodash.sql`, dat
  ook een voorbeeldbericht ("HELLODASH", 6 regels) invoegt zolang de tabel
  nog leeg is.
- `settings`: `name` (PK) en `value`, voor de bordinstellingen `font` en
  `theme`.
