# spreadsheet-parser

This library's only purpose is to easily convert table/spreadsheet data into POPO on the fly.

It does not include the spreadsheet reader itself, you can use any 3rd party reader, e.g. Box/Spout.

Since this parser is targeted towards PHP 7.x, it utilizes doctrine/annotations package to parse annotations.
This library can be easily adapted for use with PHP 8.x and its attributes support.

The work is still in progress, and not everything is quite perfect yet.

## Usage

### POPO example:
```php
declare(strict_types=1);

namespace ImageSpark\SpreadsheetParser;

use Illuminate\Support\Carbon;
use ImageSpark\SpreadsheetParser\Attributes\Column;
use ImageSpark\SpreadsheetParser\Attributes\NoHeader;

/**
 * POPO example
 *
 * Let's say you need to read data from a spreadsheet and extract it to a usable object.
 * To achieve that just add the annotations as shown below.
 *
 * The `Header` or `NoHeader` annotations must include the `columns` array (its key are optional). This array
 * contains property names which will be populated after parsing. The properties access modifiers can be public, protected or private.
 * As of the `columns` array keys:
 * - for sequential columns the keys can be omitted;
 * - you can use alphanumeric indexes instead of numeric ones (as used in Excel), the character case is ignored;
 *
 * You can also define "global" validation messages in the `messages` array as shown below.
 *
 * If you use a `Header` annotation you can pass the `rows` setting, which indicates how many rows the header spans.
 * If you use a `NoHeader` annotation it uses the `rows` setting set to 0, meaning that it'll start processing from the very first row.
 *
 * Note that this parser converts empty strings to `null` before processing.
 *
 * See how the columns are managed below.
 *
 * @NoHeader(
 *  columns={
 *      "mail",
 *      2: "date1",
 *      "b": "rank",
 *  },
 *  messages={
 *      "required": "The required `:attribute` column value is missing",
 *  },
 * )
 */
class Example
{
    /**
     * You can pass validation rules & messages for every column.
     * @see Col
     *
     * @Column(rule="required|email", messages={
     *      "email": "Email is required"
     * })
     * @var string
     */
    public string $mail;

    /**
     * You can also set the `mandatory` boolean flag to true. This flag can be used to skip rows, where a row is treated as "empty" if any of
     * `mandatory` columns is empty, thus the whole row will be skipped and not processed.
     *
     * For example, this only column is marked as `mandatory`. It means that if this field is empty in the source data the whole row will be skipped.
     * @Column(mandatory=true, rule="integer")
     * @var integer
     */
    public ?int $rank;

    /**
     * @Column(rule="nullable|date")
     *
     * @var Carbon|null
     */
    public ?Carbon $date1;
}
```

### Sample usage
```php
<?php
use Illuminate\Translation\Translator;
use Illuminate\Translation\ArrayLoader;
use ImageSpark\SpreadsheetParser\Exceptions\ValidationException;
use ImageSpark\SpreadsheetParser\Parser;
use ImageSpark\SpreadsheetParser\Example;
use Illuminate\Validation\Factory as ValidatorFactory;

require "./vendor/autoload.php";

$data = [
    [
        '',
        123,
        '10/1/1977',
    ],
    [
        'something@somewhere.org',
        '2',
    ],
    [
        'other@mail.com',
        '4',
    ]
];

// since it accepts iterable, you can also pass an iterable
$data = (static fn() => yield from $data)();

$translator = new Translator(new ArrayLoader(), 'en_EN');
$validatorFactory = new ValidatorFactory($translator);

$parser = new Parser(Example::class);
$parser->setValidatorFactory($validatorFactory);

print_r(iterator_to_array($parser->parse($data)));

exit;
```

### Output
```
Array
(
    [0] => ImageSpark\SpreadsheetParser\Example Object
        (
            [mail] =>
            [rank] => 123
            [date1] => Illuminate\Support\Carbon Object...
        )

    [1] => ImageSpark\SpreadsheetParser\Example Object
        (
            [mail] => something@somewhere.org
            [rank] => 2
            [date1] =>
        )

    [2] => ImageSpark\SpreadsheetParser\Example Object
        (
            [mail] => other@mail.com
            [rank] => 4
            [date1] =>
        )

)
```
