<?php

declare(strict_types=1);

namespace Diffrakt\Filters;

use GdImage;

interface FilterInterface {
    /**
     * Прилага филтъра директно върху изображението в паметта.
     * * @param GdImage &$image Изображението (подава се по референция &, за да може филтърът да подменя обекта)
     * @param array $params Допълнителни параметри (напр. ниво на яркост, контраст)
     */
    public function apply(GdImage &$image, array $params): void;
}