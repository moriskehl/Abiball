<?php
declare(strict_types=1);

// src/Repository/MenuRepository.php

final class MenuRepository
{
    private static string $menuPath = __DIR__ . '/../../storage/data/menu.json';

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

    public static function isAvailable(string $itemId): bool
    {
        $item = self::getItem($itemId);
        return $item !== null && ($item['available'] ?? false);
    }

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
