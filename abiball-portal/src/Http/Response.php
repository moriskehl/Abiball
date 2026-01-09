<?php
declare(strict_types=1);

// src/Http/Response.php
final class Response
{
    public static function redirect(string $path): void
    {
        header('Location: ' . $path);
        exit;
    }
}
