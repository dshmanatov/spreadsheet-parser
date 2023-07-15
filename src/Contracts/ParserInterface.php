<?php
declare(strict_types=1);

namespace ImageSpark\SpreadsheetParser\Contracts;

use ImageSpark\SpreadsheetParser\Exceptions\ValidationException;

interface ParserInterface
{
    /**
     * Проверяет все возможные строки сразу
     *
     * @param iterable $rows
     * @return void
     * @throws ValidationException
     */
    public function validateAll(iterable $rows): void;

    /**
     * Валидирует и парсит таблицу
     *
     * @param iterable $rows
     * @return \Generator|object[]
     * @throws ValidationException
     */
    public function parse(iterable $rows): \Generator;
}


