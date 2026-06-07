<?php

declare(strict_types=1);

namespace Diffrakt\Filters;

use GdImage;

class SepiaFilter implements FilterInterface {
    public function apply(GdImage &$image, array $params): void {
        $intensity = isset($params['intensity']) ? max(0.0, min(1.0, (float)$params['intensity'])) : 1.0;
        if ($intensity === 0.0) return;

        $width = imagesx($image);
        $height = imagesy($image);

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                // Вземаме текущия пиксел и го разбиваме на R, G, B
                $rgb = imagecolorat($image, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;

                // Стандартна формула за Сепия
                $tr = ($r * 0.393) + ($g * 0.769) + ($b * 0.189);
                $tg = ($r * 0.349) + ($g * 0.686) + ($b * 0.168);
                $tb = ($r * 0.272) + ($g * 0.534) + ($b * 0.131);

                // Прилагаме интензитета (интерполация между оригинала и сепията)
                $newR = (int)($r + ($tr - $r) * $intensity);
                $newG = (int)($g + ($tg - $g) * $intensity);
                $newB = (int)($b + ($tb - $b) * $intensity);

                // Clamping (ограничаване до 255)
                $newR = min(255, max(0, $newR));
                $newG = min(255, max(0, $newG));
                $newB = min(255, max(0, $newB));

                $color = imagecolorallocate($image, $newR, $newG, $newB);
                imagesetpixel($image, $x, $y, $color);
            }
        }
    }
}