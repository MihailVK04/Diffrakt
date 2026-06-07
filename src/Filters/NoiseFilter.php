<?php

declare(strict_types=1);

namespace Diffrakt\Filters;

use GdImage;

class NoiseFilter implements FilterInterface {
    public function apply(GdImage &$image, array $params): void {
        $width = imagesx($image);
        $height = imagesy($image);
        
        $intensity = isset($params['intensity']) ? max(1, min(100, (int)$params['intensity'])) : 10;
        $noisePixels = (int) (($width * $height) * ($intensity / 100));

        // ОПТИМИЗАЦИЯ: Алокираме цветовете ВЕДНЪЖ, извън цикъла
        $black = imagecolorallocate($image, 0, 0, 0);
        $white = imagecolorallocate($image, 255, 255, 255);

        for ($i = 0; $i < $noisePixels; $i++) {
            $x = rand(0, $width - 1);
            $y = rand(0, $height - 1);
            $color = rand(0, 1) === 1 ? $white : $black;
            imagesetpixel($image, $x, $y, $color);
        }
    }
}