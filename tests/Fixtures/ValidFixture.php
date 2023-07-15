<?php
declare(strict_types=1);

namespace Tests\Fixtures;

use Illuminate\Support\Carbon;
use ImageSpark\SpreadsheetParser\Attributes\Column;
use ImageSpark\SpreadsheetParser\Attributes\NoHeader;

/**
 * @NoHeader(
 *  columns={
 *      "email",
 *      "1": "int",
 *      "c": "date",
 *      3: "string",
 *  },
 *  messages={
 *      "required": "The required `:attribute` is missing",
 *  },
 * )
 */
class ValidFixture
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
    public string $email;

    /**
     * @Column(
     *      mandatory=true,
     *      rule="integer"
     * )
     * @var integer
     */
    public int $int;

    /**
     * @Column(rule="nullable|date")
     *
     * @var Carbon|null
     */
    public ?Carbon $date;

    /**
     * @Column(rule="required")
     * @var string
     */
    public string $string;
}