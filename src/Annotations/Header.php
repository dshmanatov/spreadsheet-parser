<?php
declare(strict_types=1);

namespace ImageSpark\SpreadsheetParser\Annotations;

use ImageSpark\SpreadsheetParser\Exceptions\ParserException;

/**
 * @Annotation
 * @NamedArgumentConstructor
 * @Target("CLASS")
 */
class Header
{
    /**
     * Количество строк, занимаемых заголовком
     *
     * @var integer
     */
    private int $rows;

    /**
     * Маппинг столбцов.
     *
     * Массив, где:
     * - ключ, это индекс столбца
     * - значение, это имя пропса
     *
     * @var array
     */
    private array $columns;

    /**
     * Глобальные сообщения валидации, т.е. не привязанные к столбцам
     *
     * @var array
     */
    private array $messages;

    /**
     * Конструктор.
     *
     * Обязательно требуется передавать число строк, занимаемых заголовком.
     * Если заголовок отсутствует, то необходимо использовать NoHeader.
     *
     * @see NoHeader
     *
     * @param array   $columns  - маппинги столбцов (index -> имя пропса)
     * @param integer $rows     - кол-во строк в заголовке
     *
     */
    public function __construct(array $columns, int $rows = 1, array $messages = [])
    {
        if (empty($columns)) {
            throw new ParserException("At least one column must be specified in the header");
        }

        $this->columns = $columns;
        $this->rows = $rows;
        $this->messages = $messages;
    }

    /**
     * Геттер числа строк в заголовке
     *
     * @param string|int $index
     * @return integer
     */
    public function getRows(): int
    {
        return $this->rows;
    }

    /**
     * Возвращает индекс поля по имени.
     *
     * В $columns ключи могут быть как числовыми, так и строковыми в формате названий столбцов в XLS файлах.
     *
     * @param  string $name - имя столбца (свойства)
     * @return int
     */
    public function getColumnIndex(string $name): int
    {
        // преобразуем в $name => $index
        $columns = array_flip($this->columns);
        $index = $columns[$name] ?? null;

        if ($index === null) {
            throw new ParserException("The column name `{$name}` is not specified in the header columns");
        }

        if (is_string($index)) {
            if (empty($index)) {
                throw new ParserException("Empty string index for column `{$name}`");
            }

            // Если индекс начинается с символа, преобразуем в int
            if (ctype_alnum(substr($index, 0, 1))) {
                $index = $this->excelColumnNameToNumber($index);
            }
        }

        return (int) $index;
    }

    /**
     * Возвращает массив с индексами полей
     *
     * @return array
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /**
     * Возвращает массив с "глобальными" сообщениями валидации
     *
     * @return array
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * Возвращает индекс столбца в виде числа из строки вида "A1" (Excel format)
     *
     * @param string $name
     * @return integer
     */
    private function excelColumnNameToNumber(string $name): int
    {
        $value = 0;

        foreach (explode('', $name) as $char) {
            $value = $value * 26 + (ord($char) - ord('A') + 1);
        }

        return $value;
    }
}