<?php
declare(strict_types=1);

namespace ImageSpark\SpreadsheetParser;

use Doctrine\Common\Annotations\AnnotationException;
use Doctrine\Common\Annotations\AnnotationReader;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use ImageSpark\SpreadsheetParser\Attributes\{Column, Header, NoHeader};
use ImageSpark\SpreadsheetParser\Casters\DateCaster;
use ImageSpark\SpreadsheetParser\Contracts\{CasterInterface, ParserInterface};
use ImageSpark\SpreadsheetParser\Exceptions\{ParserException, ValidationException};
use ReflectionClass;
use ReflectionProperty;

class Parser implements ParserInterface
{
    /**
     * Дата кастеры, которые могут использоваться
     *
     * @var string[]
     */
    private const DATA_CASTERS = [
        Carbon::class => DateCaster::class,
    ];

    /**
     * Data caster registry
     *
     * Хранит инстансы кастеров.
     * Ключ - строковое представление типа (integer/string/etc)
     * Значение - инстанс кастера CasterInterface
     *
     * @var CasterInterface[]
     */
    private array $casterRegistry = [];

    private string $mappedClass;

    /**
     * Header
     *
     * @var Header
     */
    private Header $header;

    /**
     * Columns
     *
     * @var Col[]
     */
    private array $columns;

    /**
     * Properties of the class
     *
     * @var ReflectionProperty[]
     */
    private array $properties;

    /**
     * Индексы столбцов в виде $propName => $index
     *
     * @var array
     */
    private array $indexes = [];

    /**
     * Индексы столбцов, которые должны присутствовать в строке, чтобы она считалась "не пустой"
     *
     * @var int[]
     */
    private array $mandatoryColumns = [];

    /**
     * Правила валидации
     *
     * Использует имя столбца в качестве ключа
     *
     * @var array
     */
    private array $rules = [];

    /**
     * Сообщения об ошибках валидации
     *
     * @var array
     */
    private array $messages = [];

    /**
     * Конструктор
     *
     * @param string    $mappedClass
     */
    public function __construct(string $mappedClass)
    {
        $this->mappedClass = $mappedClass;

        $reader = new AnnotationReader;
        $reflectionClass = new ReflectionClass($mappedClass);

        // Порядок вызовов ниже имеет значение!!!
        $this
            ->registerCasters()
            ->assembleHeader($reader, $reflectionClass)
            ->assembleProperties($reader, $reflectionClass)
            ->assembleValidation();
    }

    public function validateAll(iterable $rows): void
    {
        foreach ($this->filterRows($rows) as $rowIndex => $row) {
            $this->validateRow($row, $rowIndex);
        }
    }

    public function parse(iterable $rows): \Generator
    {
        foreach ($this->filterRows($rows) as $rowIndex => $row) {
            // содержит массив с именами пропсов в виде ключей и валидированными значениями
            $validatedRow = $this->validateRow($row, $rowIndex);

            yield $rowIndex => $this->parseValidatedRow($validatedRow, $rowIndex);
        }
    }

    /**
     * Валидирует строку.
     *
     * На входе получает "сырую" строку с числовыми индексами. В случае успешного прохождения
     * валидации возвращает ассоциативный массив с именами пропсов в виде ключей.
     *
     * @param array $row
     * @param int   $rowIndex
     * @return array
     * @throws ValidationException
     */
    private function validateRow(array $row, int $rowIndex): array
    {
        // Преобразуем $row в ассоциативный массив, используя имена пропсов
        $mappedRow = $this->convertIndexedRowToHavePropsNamesAsKeys($row);

        $validator = Validator::make($mappedRow, $this->rules, $this->messages);

        if ($validator->fails()) {
            $errorMsg = $validator->messages()->toJson(JSON_UNESCAPED_UNICODE);
            throw new ValidationException($errorMsg, $rowIndex);
        }

        return $validator->getData();
    }

    /**
     * Преобразовывает исходный "сырой" массив с числовыми ключами в ассоциативный, используя имена пропсов
     *
     * @param array $row
     * @return $row
     */
    private function convertIndexedRowToHavePropsNamesAsKeys(array $row): array
    {
        $mappedRow = [];
        foreach ($this->columns as $name => $column) {
            $columnIndex = $this->indexes[$name];

            $mappedRow[$name] = $row[$columnIndex] ?? null;
        }

        return $mappedRow;
    }

    /**
     * Исключает строки, которые не требуют обработки
     *
     * @param iterable $rows
     * @return \Generator|mixed[]
     */
    private function filterRows(iterable $rows): \Generator
    {
        foreach ($rows as $rowIndex => $row) {
            // пропускаем Header rows
            if ($rowIndex < $this->header->getRows()) {
                continue;
            }

            // Не обрабатываем строки, которые не содержат всех обязательных значений
            if (!$this->allMandatoryColumnsPresent($row)) {
                continue;
            }

            yield $rowIndex => $row;
        }
    }

    /**
     * Return `true` if the current row should not be processed
     *
     * We skip it when:
     * - there's at least one empty 'mandatory' column value
     * - this is a header row
     *
     * @param array   $row
     * @param integer $rowIndex
     * @return boolean
     */
    private function shouldSkipRow(int $rowIndex, array $row): bool
    {
        return ($rowIndex < $this->header->getRows())
            || (!$this->allMandatoryColumnsPresent($row));
    }

    /**
     * Создает инстансы кастеров
     *
     * @return self
     */
    private function registerCasters(): self
    {
        foreach (self::DATA_CASTERS as $type => $casterClass) {
            $this->casterRegistry[$type] = new $casterClass;
        }

        return $this;
    }

    /**
     * Читаем заголовок
     *
     * @param  AnnotationReader $reader
     * @param  ReflectionClass $reflectionClass
     * @return self
     * @throws ParserException
     */
    private function assembleHeader(AnnotationReader $reader, ReflectionClass $reflectionClass): self
    {
        try {
            $header = $this->getHeaderAnnotation($reader, $reflectionClass);
        } catch (AnnotationException $e) {
            throw new ParserException(
                $e->getMessage()
            );
        }

        if ($header === null) {
            throw new ParserException("Missing @Header or @NoHeader annotation");
        }

        $this->header = $header;

        return $this;
    }

    /**
     * Подготавливаем пропсы.
     *
     * @param AnnotationReader $reader
     * @param ReflectionClass $reflectionClass
     * @return self
     * @throws ParserException
     */
    private function assembleProperties(AnnotationReader $reader, ReflectionClass $reflectionClass): self
    {
        $props = $reflectionClass->getProperties();
        foreach ($props as $prop) {
            try {
                $annotations = $reader->getPropertyAnnotations($prop);
            } catch (AnnotationException $e) {
                throw new ParserException($e->getMessage());
            }

            $annotationFound = false;
            foreach ($annotations as $annotation) {
                if ($annotation instanceof Column) {
                    $propName = $prop->getName();

                    if ($annotationFound) {
                        throw new ParserException("There is more than one @Column per property `{$propName}`");
                    }

                    $this->columns[$propName] = $annotation;
                    $this->properties[$propName] = $prop;

                    $colIndex = $this->header->getColumnIndex($propName);
                    $this->indexes[$propName] = $colIndex;
                    if ($annotation->isMandatory()) {
                        $this->mandatoryColumns[$propName] = $colIndex;
                    }

                    $annotationFound = true;
                }
            }
        }

        if (empty($this->columns)) {
            throw new ParserException("No @Column annotations found");
        }

        // Удостоверимся, что число пропсов совпадает с числом столбцов в Header
        if (sizeof($this->header->getColumns()) !== sizeof($this->columns)) {
            throw new ParserException("@Header columns count doesn't match the @Column count");
        }

        return $this;
    }

    private function assembleValidation(): self
    {
        // Сообщения валидации из Header
        $this->messages = $this->header->getMessages();

        // Правила валидации для строк
        foreach ($this->columns as $name => $column) {
            // rules
            $rule = $column->getRule();

            if ($rule !== null) {
                $this->rules[$name] = $rule;
            }

            // messages (для Col, еще могут быть заданы global messages в Header)
            $messages = $column->getMessages();
            if ($messages) {
                foreach ($messages as $k => $message) {
                    $this->messages["{$name}.{$k}"] = $message;
                }
            }
        }

        return $this;
    }

    /**
     * Возвращает annotation для заголовка
     *
     * @param  AnnotationReader $reader
     * @param  ReflectionClass  $reflectionClass
     * @return Header|null
     */
    private function getHeaderAnnotation(AnnotationReader $reader, ReflectionClass $reflectionClass): ?Header
    {
        $header = $reader->getClassAnnotation($reflectionClass, Header::class, );

        // Если Header не задан, проверяем NoHeader
        $header ??= $reader->getClassAnnotation($reflectionClass, NoHeader::class);

        return $header;
    }

    /**
     * Парсив валидированного массива, уже использующего имена пропсов в виде ключей
     *
     * @param  array  $row
     * @param  int    $rowIndex
     * @return object
     */
    private function parseValidatedRow(array $row, int $rowIndex): object
    {
        $obj = new $this->mappedClass;

        foreach ($this->properties as $name => $prop) {
            // Получаем тип свойства
            $propType = $prop->getType();
            if ($propType === null) {
                // No type defined for the mapped class
                throw new ParserException("No type definition for `{$name}` property");
            }

            $propTypeName = $prop->getType()->getName();
            $format = $this->columns[$name]->getFormat();
            $value = $row[$name] ?: null;
            $value = $this->castValueToPropType($value, $propTypeName, $format);

            // Записываем значение property в объект
            $prop->setAccessible(true);
            $prop->setValue($obj, $value);
        }

        return $obj;
    }

    /**
     * Преобразовывает строковое значение из spreadsheet в определенный тип
     *
     * @param mixed       $value
     * @param string      $type
     * @param string|null $format
     * @return mixed
     */
    private function castValueToPropType($value, string $type, ?string $format = null)
    {
        // Не обрабатываем null значения
        if ($value === null) {
            return $value;
        }

        if (isset($this->casterRegistry[$type])) {
            return $this->casterRegistry[$type]->cast($value, $format);
        }

        return $value;
    }

    /**
     * Возвращает true, если строка "не пустая", т.е. все обязательные столбцы имеют непустые значения
     *
     * @param  array $row
     * @return boolean
     */
    private function allMandatoryColumnsPresent(array $row): bool
    {
        foreach ($this->mandatoryColumns as $index) {
            if (empty($row[$index])) {
                return false;
            }
        }

        return true;
    }
}