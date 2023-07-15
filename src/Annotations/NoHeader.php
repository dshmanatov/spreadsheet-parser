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
     * @param array   $columns  - маппинги столбцов (index -> имя пропса)
     * @param array   $messages - "глобальные" сообщения валидации
     */
    public function __construct(array $columns, array $messages = [])
    {
        // Отсутствует header. Используем 0 строк, но используем маппинги
        parent::__construct($columns, 0, $messages);
    }
}
