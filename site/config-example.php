<?php
// Database connection settings.
//
// When this app moves to the Synology (Web Station), the app and the
// database will run on the same machine, so DB_HOST likely becomes
// 'localhost' and DB_PORT the MariaDB package's own port (check with
// `netstat -tlnp | grep mysqld` on the Synology, or the package's settings
// in DSM). The remote values below are for local development only.
define('DB_HOST', 'your-host-url'); // Mostly localhost
define('DB_PORT', 3306);
define('DB_NAME', 'your-database-name');
define('DB_USER', 'your-user-name');
define('DB_PASS', 'your-database-password');

// PIN required for every change (add/edit/delete) on the site.
// PIN_TIMEOUT: seconds the PIN stays valid after entering it; 0 = ask every time.
define('PIN_CODE', '0000');
define('PIN_TIMEOUT', 600);

// Number of rows on the board. Default is 6 (like a real Vestaboard), but a
// regular monitor often fits more rows - adjust per screen. Width stays
// fixed at 24 columns. Changing this makes any previously saved message
// invalid (wrong row count), so the board shows blank until a new one is
// saved.
define('BOARD_ROWS', 6);

// Key for the JSON API (api.php), used by the command line tool
// ../hellodash.py (sent as header X-API-Key). At least 16 characters; leave
// empty or remove to disable the API. Generate one with:
//   python3 -c "import secrets; print(secrets.token_urlsafe(32))"
define('API_KEY', '');
