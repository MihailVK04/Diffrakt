<?php
declare(strict_types=1);
namespace Diffrakt\Filters;
use GdImage;

class ContrastFilter implements FilterInterface {
    public function apply(GdImage &$image, array $params): void {
        // ВАЖНО: В GD библиотеката контрастът е обърнат! 
        // Отрицателните стойности увеличават контраста, а положителните го намаляват.
        // За да е логично за потребителя (положително = повече контраст), слагаме минус.
        $level = isset($params['level']) ? -(int)$params['level'] : -20;
        imagefilter($image, IMG_FILTER_CONTRAST, $level);
    }
}