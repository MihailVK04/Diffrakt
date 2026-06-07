<?php

declare(strict_types=1);

namespace Diffrakt\Filters;

use GdImage;

class VignetteFilter implements FilterInterface {
    public function apply(GdImage &$image, array $params): void {
        $width  = imagesx($image);
        $height = imagesy($image);
        
        $overlay = imagecreatetruecolor($width, $height);
        imagesavealpha($overlay, true);
        
        imagealphablending($overlay, false); 
        
        $black = imagecolorallocatealpha($overlay, 0, 0, 0, 0);
        imagefill($overlay, 0, 0, $black);
        
        $cx = (int)($width / 2);
        $cy = (int)($height / 2);
        
        for ($r = 100; $r >= 0; $r -= 2) {
            $alpha = (int)(127 * (1 - ($r / 100)));
            $color = imagecolorallocatealpha($overlay, 0, 0, 0, $alpha);
            
            $ellipseW = (int)($width  * ($r / 100));
            $ellipseH = (int)($height * ($r / 100));
            
            imagefilledellipse($overlay, $cx, $cy, $ellipseW, $ellipseH, $color);
        }
        
        imagealphablending($image, true);
        imagecopy($image, $overlay, 0, 0, 0, 0, $width, $height);
        

    }
}