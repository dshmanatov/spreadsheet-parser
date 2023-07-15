<?php
declare(strict_types=1);

namespace ImageSpark\SpreadsheetParser;

use Illuminate\Support\Carbon;
use ImageSpark\SpreadsheetParser\Attributes\Column;
use ImageSpark\SpreadsheetParser\Attributes\NoHeader;

/**
 * См. пример использования ниже
 *
 * - Можно не указывать индекс если столбцы идут подряд;
 * - Можно референсить столбцы по числовому индексу (zero based), e.g. 2: "date1";
 * - Можно (но не нужно) референсить столбцы со строковым индексом (как в Excel),
 *   регистр символов при этом игнорируется. E.g. "b": "rank".
 *
 * Также можно использовать bool флаг `mandatory`. Если этот флаг присутствует на одном или
 * нескольких столбцах, то строка будет обрабатываться только в том случае, если все столбцы
 * с этим флагом имеют "непустые" значения.
 *
 * @NoHeader(
 *  columns={
 *      "mail",
 *      2: "date1",
 *      "b": "rank",
 *  },
 *  messages={
 *      "required": "The required `:attribute` is missing",
 *  },
 * )
 */
class Example
{
    /**
     * @Column(
     *      rule="required|email",
     *      messages={
     *          "email": "The email address is required"
     *      }
     * )
     * @var string
     */
    public string $mail;

    /**
     * @Column(
     *      mandatory=true,
     *      rule="integer"
     * )
     * @var integer
     */
    public int $rank;

    /**
     * @Column(rule="nullable|date")
     *
     * @var Carbon|null
     */
    public ?Carbon $date1;
}