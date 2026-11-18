<?php
/**
 * PIN protection for every change on the site.
 *
 * Included from helpers.php, so it runs on every page. On a POST request
 * (saving a new board message) the change is only carried out once the
 * right PIN has been entered. Without a valid PIN the request is
 * intercepted and a PIN page is shown that re-submits the original form
 * data together with the PIN, so the individual forms don't need to know
 * about this.
 *
 * Settings in config.php:
 *   PIN_CODE    - the PIN (string, e.g. '6163')
 *   PIN_TIMEOUT - seconds a correct PIN stays valid in this browser session;
 *                 0 = ask for the PIN on every change
 */
require_once __DIR__ . '/config.php';

const PIN_FIELD = '__pin';
const PIN_MAX_FAILURES = 5;       // wrong attempts per IP address...
const PIN_LOCKOUT_SECONDS = 900;  // ...before being locked out for 15 minutes

function pinLockFile(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    return sys_get_temp_dir() . '/hellodash_pin_' . md5($ip) . '.json';
}

function pinLockState(): array
{
    $data = @json_decode((string) @file_get_contents(pinLockFile()), true);
    if (!is_array($data) || ($data['since'] ?? 0) < time() - PIN_LOCKOUT_SECONDS) {
        return ['failures' => 0, 'since' => time()];
    }
    return $data;
}

function pinRegisterFailure(): void
{
    $state = pinLockState();
    $state['failures']++;
    @file_put_contents(pinLockFile(), json_encode($state), LOCK_EX);
}

function pinClearFailures(): void
{
    @unlink(pinLockFile());
}

// Renders $_POST as hidden inputs (including nested arrays) so the PIN page
// can re-submit the original change unchanged.
function pinHiddenInputs(array $data, string $prefix = ''): string
{
    $html = '';
    foreach ($data as $key => $value) {
        if ($prefix === '' && $key === PIN_FIELD) {
            continue;
        }
        $name = $prefix === '' ? (string) $key : $prefix . '[' . $key . ']';
        if (is_array($value)) {
            $html .= pinHiddenInputs($value, $name);
        } else {
            $html .= '<input type="hidden" name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8')
                . '" value="' . htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') . '">' . "\n";
        }
    }
    return $html;
}

function pinShowPage(string $error = ''): void
{
    http_response_code($error === '' ? 200 : 403);
    $locked = pinLockState()['failures'] >= PIN_MAX_FAILURES;
    ?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>HelloDash - Pincode</title>
    <link rel="stylesheet" href="style.css?v=<?= (int) @filemtime(__DIR__ . '/style.css') ?>">
</head>
<body>
<?php require __DIR__ . '/header.php'; ?>
<?php require __DIR__ . '/nav.php'; ?>
<main class="pin-page">
<div class="pin-box">
<h2>Pincode vereist</h2>
<?php if ($error !== ''): ?>
    <p class="flash flash-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>
<?php if (!$locked && defined('PIN_CODE')): ?>
    <p>Voer de pincode in om dit bericht te plaatsen.</p>
    <form method="post" action="<?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        <?= pinHiddenInputs($_POST) ?>
        <label>Pincode
            <input type="password" name="<?= PIN_FIELD ?>" inputmode="numeric" autocomplete="off" required autofocus>
        </label>
        <button type="submit">Bevestigen</button>
    </form>
<?php endif; ?>
<p><a href="<?= htmlspecialchars(strtok($_SERVER['REQUEST_URI'] ?? '', '?') ?: 'index.php', ENT_QUOTES, 'UTF-8') ?>">Annuleren</a></p>
</div>
</main>
</body>
</html>
    <?php
    exit;
}

function requirePinForChanges(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        return;
    }

    // Fail closed: without a configured PIN no changes are possible.
    if (!defined('PIN_CODE') || (string) PIN_CODE === '') {
        pinShowPage('Er is geen pincode ingesteld in config.php (PIN_CODE); wijzigen is niet mogelijk.');
    }

    $timeout = defined('PIN_TIMEOUT') ? (int) PIN_TIMEOUT : 0;
    if ($timeout > 0) {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        if (($_SESSION['pin_ok_until'] ?? 0) >= time()) {
            $_SESSION['pin_ok_until'] = time() + $timeout;
            unset($_POST[PIN_FIELD]);
            return;
        }
    }

    if (!isset($_POST[PIN_FIELD])) {
        pinShowPage();
    }

    if (pinLockState()['failures'] >= PIN_MAX_FAILURES) {
        pinShowPage('Te veel onjuiste pogingen. Probeer het over 15 minuten opnieuw.');
    }

    if (!hash_equals((string) PIN_CODE, trim((string) $_POST[PIN_FIELD]))) {
        pinRegisterFailure();
        sleep(1);
        pinShowPage('Onjuiste pincode.');
    }

    pinClearFailures();
    unset($_POST[PIN_FIELD]);
    if ($timeout > 0) {
        session_regenerate_id(true);
        $_SESSION['pin_ok_until'] = time() + $timeout;
    }
}

requirePinForChanges();
