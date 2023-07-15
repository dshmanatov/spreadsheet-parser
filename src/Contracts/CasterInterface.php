<?php
declare(strict_types=1);

namespace ImageSpark\SpreadsheetParser\Contracts;

interface CasterInterface
{
    /**
     * Преобразовывает строковое значение в необходимый тип
     *
     * @param string $value
     * @param string|null $format
     * @return mixed
     */
    public function cast(string $value, ?string $format = null);
}