<?php

declare(strict_types=1);

namespace Diffrakt\Filters;

use GdImage;

class SaturationFilter implements FilterInterface {
    public function apply(GdImage &$image, array $params): void {
        $level = isset($params['level']) ? (int)$params['level'] : 0;
        
        if ($level < 0) {
            $opacity = abs($level);
            $opacity = max(0, min(100, $opacity));
            
            $width = imagesx($image);
            $height = imagesy($image);
            
            $bwImage = imagecreatetruecolor($width, $height);
            imagecopy($bwImage, $image, 0, 0, 0, 0, $width, $height);
            imagefilter($bwImage, IMG_FILTER_GRAYSCALE);
            
            // Внимание: imagecopymerge не поддържа алфа канал при PNG.
            // В нашия архитектурен модел това е приемливо, тъй като ImageService
            // предварително поставя прозрачните изображения върху бяло платно.
            imagecopymerge($image, $bwImage, 0, 0, 0, 0, $width, $height, $opacity);
            imagedestroy($bwImage);
        }
    }
}