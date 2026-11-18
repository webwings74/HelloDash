<?php
/**
 * Shared site title block (name + light/dark toggle), included at the top
 * of bericht.php and the PIN page. The full-screen dashboard (index.php)
 * skips this on purpose - it has no site chrome.
 *
 * The theme choice ("auto", "light" or "dark") is kept per browser in
 * localStorage; "auto" follows the device setting via CSS.
 */
?>
<script>
// Apply the saved theme before the rest of the page is drawn (no light flash).
(function () {
    try {
        var t = localStorage.getItem('theme');
        if (t === 'light' || t === 'dark') document.documentElement.dataset.theme = t;
    } catch (e) {}
})();
</script>
<header class="site-header">
    <h1>🗨️ HelloDash</h1>
    <button type="button" class="theme-toggle" id="theme-toggle" title="Thema wisselen: automatisch, licht, donker">◐ Auto</button>
</header>
<script>
(function () {
    var order = ['auto', 'light', 'dark'];
    var labels = { auto: '◐ Auto', light: '☀ Licht', dark: '☾ Donker' };
    var btn = document.getElementById('theme-toggle');
    var root = document.documentElement;
    function current() { return root.dataset.theme || 'auto'; }
    function apply(t) {
        if (t === 'auto') delete root.dataset.theme; else root.dataset.theme = t;
        btn.textContent = labels[t];
        try {
            if (t === 'auto') localStorage.removeItem('theme'); else localStorage.setItem('theme', t);
        } catch (e) {}
    }
    btn.textContent = labels[current()];
    btn.addEventListener('click', function () {
        apply(order[(order.indexOf(current()) + 1) % order.length]);
    });
})();
</script>
