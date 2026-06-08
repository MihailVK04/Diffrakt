<?php

declare(strict_types=1);

namespace Diffrakt\Filters;

use GdImage;

class VignetteFilter implements FilterInterface {
    public function apply(GdImage &$image, array $params): void {
        $strength = isset($params['strength']) ? max(0.0, min(1.0, (float)$params['strength'])) : 0.5;
        if ($strength === 0.0) return;

        $width = imagesx($image);
        $height = imagesy($image);

        // Намираме центъра
        $cx = $width / 2;
        $cy = $height / 2;
        
        // Максималната дистанция от центъра до ъгъла
        $maxDist = sqrt(($cx * $cx) + ($cy * $cy));

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $rgb = imagecolorat($image, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;

                // Разстояние от текущия пиксел до центъра
                $dx = $x - $cx;
                $dy = $y - $cy;
                $dist = sqrt(($dx * $dx) + ($dy * $dy));

                // Изчисляваме затъмняването (квадратичното падане стои най-естествено за винетка)
                $ratio = $dist / $maxDist;
                $darkenFactor = 1.0 - (pow($ratio, 2) * $strength);
                $darkenFactor = max(0.0, min(1.0, $darkenFactor));

                $newR = (int)($r * $darkenFactor);
                $newG = (int)($g * $darkenFactor);
                $newB = (int)($b * $darkenFactor);

                $color = imagecolorallocate($image, $newR, $newG, $newB);
                imagesetpixel($image, $x, $y, $color);
            }
        }
    }
}