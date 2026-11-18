<?php
function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

// Elke wijziging (POST) op de site vereist de pincode; zie pin.php.
require_once __DIR__ . '/pin.php';
