<?php
declare(strict_types=1);

/**
 * MenuRepository - Verwaltung der Speisekarte
 * 
 * Laedt das Menue aus einer JSON-Datei und bietet Zugriff auf
 * Kategorien und einzelne Artikel.
 */

final class MenuRepository
{
    private static string $menuPath = __DIR__ . '/../../storage/data/menu.json';

    /**
     * Laedt die komplette Speisekarte aus der JSON-Datei.
     */
    public static function load(): array
    {
        if (!is_file(self::$menuPath)) {
            return ['categories' => []];
        }

        $json = file_get_contents(self::$menuPath);
        if ($json === false) {
            return ['categories' => []];
        }

        $data = json_decode($json, true);
        return is_array($data) ? $data : ['categories' => []];
    }

    /**
     * Sucht einen Artikel anhand seiner ID.
     */
    public static function getItem(string $itemId): ?array
    {
        $menu = self::load();
        foreach ($menu['categories'] ?? [] as $category) {
            foreach ($category['items'] ?? [] as $item) {
                if ($item['id'] === $itemId) {
                    return $item;
                }
            }
        }
        return null;
    }

    /**
     * Prueft, ob ein Artikel verfuegbar ist.
     */
    public static function isAvailable(string $itemId): bool
    {
        $item = self::getItem($itemId);
        return $item !== null && ($item['available'] ?? false);
    }

    /**
     * Findet eine Kategorie anhand ihrer ID.
     */
    public static function getCategoryById(string $categoryId): ?array
    {
        $menu = self::load();
        foreach ($menu['categories'] ?? [] as $category) {
            if ($category['id'] === $categoryId) {
                return $category;
            }
        }
        return null;
    }
}
