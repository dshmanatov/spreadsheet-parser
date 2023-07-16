<?php
namespace Tests;

use Illuminate\Container\Container;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\ValidFixture;

use Illuminate\Translation\Translator;
use Illuminate\Translation\ArrayLoader;
use ImageSpark\SpreadsheetParser\Parser;
use Illuminate\Validation\Factory as ValidatorFactory;
use ImageSpark\SpreadsheetParser\Contracts\ParserInterface;
use ImageSpark\SpreadsheetParser\Exceptions\ParserException;

class ParserTest extends TestCase
{
    public function setUp(): void
    {
        $app = new Container();

        $translator = new Translator(new ArrayLoader(), 'en_EN');
        $validatorFactory = new ValidatorFactory($translator);

        $app->bind('validator', static fn() => $validatorFactory);

        Container::setInstance($app);
        Facade::setFacadeApplication($app);
    }

    public function tearDown(): void
    {
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        Container::setInstance(null);
    }

    public function testParseClassWithoutHeaderAttribute()
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessageMatches('/Missing @Header/');

        new Parser(\stdClass::class);
    }

    public function testValidFixtureWithNoMandatoryValues()
    {
        $data = [
            [
                'dummy@example.com',
                '',
                '',
                'some string',
            ],
            [
                'other@mail.com',
                null,
                '10/31/1977',
                'another string',
            ],
        ];

        $parser = $this->getParser(ValidFixture::class);
        $result = iterator_to_array($parser->parse($data));

        $this->assertEmpty($result);
    }

    public function testValidFixtureWithDataArray()
    {
        $data = [
            [
                'dummy@example.com',
                1,
                '',
                8513 => 'some string', // LOL column name
                16871 => null, // XXX column name
            ],
            [
                'other@mail.com',
                2,
                '10/31/1977',
                8513 => 'another string', // LOL column name
                16871 => 12.34, // XXX column name
            ],
        ];

        $parser = $this->getParser(ValidFixture::class);
        $result = iterator_to_array($parser->parse($data));

        /** @var ValidFixture */
        $resultItem1 = $result[0];
        /** @var ValidFixture */
        $resultItem2 = $result[1];

        $this->assertSame($resultItem1->email, 'dummy@example.com');
        $this->assertSame($resultItem1->int, 1);
        $this->assertNull($resultItem1->date);
        $this->assertSame($resultItem1->string, 'some string');

        $this->assertSame($resultItem2->email, 'other@mail.com');
        $this->assertSame($resultItem2->int, 2);
        $this->assertInstanceOf(Carbon::class, $resultItem2->date);
        $this->assertSame($resultItem2->date->toDateString(), '1977-10-31');
        $this->assertSame($resultItem2->string, 'another string');
    }

    private function getParser(string $class): ParserInterface
    {
        $parser = new Parser($class);

        return $parser;
    }
}
