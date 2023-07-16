<?php
declare(strict_types=1);

namespace ImageSpark\SpreadsheetParser;

use Illuminate\Contracts\Container\Container;
use ImageSpark\SpreadsheetParser\Contracts\ParserInterface;

class Factory
{
    /**
     * The IoC container instance.
     *
     * @var Container
     */
    protected $container;

    public function __construct(Container $container = null)
    {
        $this->container = $container;
    }

    public function make(string $mappedClass): ParserInterface
    {
        return new Parser($mappedClass);
    }
}