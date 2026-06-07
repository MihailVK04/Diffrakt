<?php
declare(strict_types=1);
namespace Diffrakt\Filters;
use GdImage;

class NoiseFilter implements FilterInterface {
    public function apply(GdImage &$image, array $params): void {
        $width = imagesx($image);
        $height = imagesy($image);
        
        // Колко точки шум да генерираме
        $intensity = isset($params['intensity']) ? max(1, min(100, (int)$params['intensity'])) : 10;
        $noisePixels = (int) (($width * $height) * ($intensity / 100));

        for ($i = 0; $i < $noisePixels; $i++) {
            $x = rand(0, $width - 1);
            $y = rand(0, $height - 1);
            // Генерираме произволен цвят (черен или бял) за класически шум
            $colorValue = rand(0, 1) === 1 ? 255 : 0;
            $color = imagecolorallocate($image, $colorValue, $colorValue, $colorValue);
            imagesetpixel($image, $x, $y, $color);
        }
    }
}