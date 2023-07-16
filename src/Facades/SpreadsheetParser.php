<?php
declare(strict_types=1);

namespace ImageSpark\SpreadsheetParser\Facades;


use Illuminate\Support\Facades\Facade;
use ImageSpark\SpreadsheetParser\Factory;

/**
 * @method static \ImageSpark\SpreadsheetParser\Contracts\ParserInterface make(string $mappedClass)
 */
class SpreadsheetParser extends Facade
{
    protected static function getFacadeAccessor()
    {
        return Factory::class;
    }
}