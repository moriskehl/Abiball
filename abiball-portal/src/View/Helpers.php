<?php
declare(strict_types=1);

// src/View/Helpers.php
function e(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
