<?php
declare(strict_types=1);

namespace ImageSpark\SpreadsheetParser;

use ImageSpark\SpreadsheetParser\Annotations\Col;
use ImageSpark\SpreadsheetParser\Annotations\Header;

// todo - проверить нуллабельность столбцов

/**
 * @Header(
 *  rows=2,
 *  columns={
 *      "email",
 *      "rank",
 *      3: "third",
 *  }
 * )
 */
class Example
{
    /**
     * @Col
     * @var string
     */
    public ?string $email;

    /**
     * @Col(rule="required|integer")
     * @var integer
     */
    public ?int $rank;

    /**
     * @Col
     * @var string
     */
    public ?string $third;
}