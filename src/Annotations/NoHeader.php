<?php
declare(strict_types=1);

namespace ImageSpark\SpreadsheetParser\Annotations;

/**
 * @Annotation
 * @NamedArgumentConstructor
 * @Target("CLASS")
 */
class NoHeader extends Header
{
    /**
     * Конструктор
     *
     * @param array $fields - маппинги столбцов
     */
    public function __construct(array $columns)
    {
        // Отсутствует header. Используем 0 строк, но используем маппинги
        parent::__construct($columns, 0);
    }
}
