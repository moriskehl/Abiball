<?php
declare(strict_types=1);

// src/Http/Response.php
final class Response
{
    public static function redirect(string $path): void
    {
        // Prevent open redirect vulnerability
        // Only allow internal paths (starting with /)
        if ($path !== '' && $path[0] !== '/') {
            // If it's not an internal path, redirect to home
            $path = '/';
        }
        
        // Prevent CRLF injection in headers
        $path = str_replace(["\r", "\n"], '', $path);
        
        header('Location: ' . $path);
        exit;
    }
}
