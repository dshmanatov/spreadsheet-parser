<?php
namespace Tests;

use Illuminate\Support\Carbon;
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
    public function testParseClassWithoutHeaderAttribute()
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessageMatches('/Missing @Header/');

        new Parser(\stdClass::class);
    }

    public function testValidFixtureWithDataArray()
    {
        $data = [
            [
                'dummy@example.com',
                1,
                '',
                'some string',
            ],
            [
                'other@mail.com',
                2,
                '10/31/1977',
                'another string',
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
        $translator = new Translator(new ArrayLoader(), 'ru_RU');
        $validatorFactory = new ValidatorFactory($translator);

        $parser = new Parser($class);
        $parser->setValidatorFactory($validatorFactory);

        return $parser;
    }
}
