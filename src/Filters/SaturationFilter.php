<?php
declare(strict_types=1);
namespace Diffrakt\Filters;
use GdImage;

class SaturationFilter implements FilterInterface {
    public function apply(GdImage &$image, array $params): void {
        $level = isset($params['level']) ? (int)$params['level'] : 0;
        
        // Тъй като GD няма нативен saturation, ще имплементираме само десатурация (намаляване на цвета),
        // което е изключително бързо чрез смесване с черно-бяло копие.
        if ($level < 0) {
            $opacity = abs($level);
            $opacity = max(0, min(100, $opacity));
            
            $width = imagesx($image);
            $height = imagesy($image);
            
            $bwImage = imagecreatetruecolor($width, $height);
            imagecopy($bwImage, $image, 0, 0, 0, 0, $width, $height);
            imagefilter($bwImage, IMG_FILTER_GRAYSCALE);
            
            // Смесваме оригинала с черно-бялото копие според избрания процент
            imagecopymerge($image, $bwImage, 0, 0, 0, 0, $width, $height, $opacity);
            imagedestroy($bwImage); 
        }
        // Забележка: Увеличаването на сатурацията над 0 изисква HSL пикселна манипулация,
        // което би претоварило PHP сървъра, затова се ограничаваме до десатурация.
    }
}