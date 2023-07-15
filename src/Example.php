<?php
declare(strict_types=1);

namespace ImageSpark\SpreadsheetParser;

use ImageSpark\SpreadsheetParser\Annotations\Col;
use ImageSpark\SpreadsheetParser\Annotations\NoHeader;

// todo - проверить нуллабельность столбцов

/**
 * @NoHeader(
 *  columns={
 *      "mail",
 *      "rank"
 *  },
 *  messages={
 *      "required": "Глобальное сообщение об отсутствии required столбца",
 *  }
 * )
 */
class Example
{
    /**
     * @Col(rule="required|email", messages={
     *      "email": "Ты чо не ввел емайл, балда?!"
     * })
     * @var string
     */
    public string $mail;

    /**
     * @Col(rule="required|integer")
     * @var integer
     */
    public ?int $rank;
}