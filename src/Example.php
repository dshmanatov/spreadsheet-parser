<?php
declare(strict_types=1);

namespace ImageSpark\SpreadsheetParser;

use Illuminate\Support\Carbon;
use ImageSpark\SpreadsheetParser\Annotations\Col;
use ImageSpark\SpreadsheetParser\Annotations\NoHeader;

// todo - проверить нуллабельность столбцов

/**
 * @NoHeader(
 *  columns={
 *      "mail",
 *      "rank",
 *      "date1",
 *  },
 *  messages={
 *      "required": "Глобальное сообщение об отсутствии required столбца",
 *  }
 * )
 */
class Example
{
    /**
     * @Col(rule="email", messages={
     *      "email": "Ты чо не ввел емайл, балда?!"
     * })
     * @var string
     */
    public string $mail;

    /**
     * @Col(rule="integer")
     * @var integer
     */
    public ?int $rank;

    /**
     * @Col(rule="nullable|date")
     *
     * @var Carbon|null
     */
    public ?Carbon $date1;
}