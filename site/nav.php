<?php
/**
 * Shared menu bar, included right under header.php (not on index.php).
 * Requires h() from helpers.php to already be loaded.
 */
$__navCurrent = basename($_SERVER['SCRIPT_NAME']);
$__navLinks = [
    'index.php' => 'Dashboard',
    'bericht.php' => 'Bericht plaatsen',
    'fonts.php' => 'Lettertypes',
];
?>
<nav class="site-nav">
    <div class="nav-row">
        <?php foreach ($__navLinks as $file => $label): ?>
            <a href="<?= h($file) ?>" class="<?= $__navCurrent === $file ? 'active' : '' ?>"><?= h($label) ?></a>
        <?php endforeach; ?>
    </div>
</nav>
