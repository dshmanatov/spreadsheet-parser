<?php
declare(strict_types=1);

namespace ImageSpark\SpreadsheetParser;

use ImageSpark\SpreadsheetParser\Contracts\ParserInterface;
use ReflectionClass;
use ReflectionProperty;
use Illuminate\Validation\Factory as ValidatorFactory;
use Doctrine\Common\Annotations\AnnotationReader;
use ImageSpark\SpreadsheetParser\Annotations\Col;
use Doctrine\Common\Annotations\AnnotationException;
use ImageSpark\SpreadsheetParser\Annotations\Header;
use ImageSpark\SpreadsheetParser\Annotations\NoHeader;
use ImageSpark\SpreadsheetParser\Exceptions\ParserException;
use ImageSpark\SpreadsheetParser\Exceptions\ValidationException;

class Parser implements ParserInterface
{
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

    private ?ValidatorFactory $validatorFactory = null;

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
            ->assembleHeader($reader, $reflectionClass)
            ->assembleProperties($reader, $reflectionClass)
            ->assembleValidation();
    }

    /**
     * Сеттер для Validation/Factory
     *
     * @param ValidatorFactory $validationFactory
     * @return self
     */
    public function setValidatorFactory(ValidatorFactory $validatorFactory): self
    {
        $this->validatorFactory = $validatorFactory;

        return $this;
    }

    public function validateAll(iterable $rows): void
    {
        foreach ($this->filterRows($rows) as $rowIndex => $row)
        {
            $this->validateRow($row, $rowIndex);
        }
    }

    public function parse(iterable $rows): \Generator
    {
        foreach ($this->filterRows($rows) as $rowIndex => $row)
        {
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
        if ($this->validatorFactory === null) {
            throw new ValidationException("No validator factory configured");
        }

        // Преобразуем $row в ассоциативный массив, используя имена пропсов
        $mappedRow = $this->convertIndexedRowToHavePropsNamesAsKeys($row);

        $validator = $this
            ->validatorFactory
            ->make($mappedRow, $this->rules, $this->messages);

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
        foreach ($props as $prop)
        {
            try {
                $annotations = $reader->getPropertyAnnotations($prop);
            } catch (AnnotationException $e) {
                throw new ParserException($e->getMessage());
            }

            $annotationFound = false;
            foreach ($annotations as $annotation) {
                if ($annotation instanceof Col) {
                    $propName = $prop->getName();

                    if ($annotationFound) {
                        throw new ParserException("There is more than one @Col per property `{$propName}`");
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
            throw new ParserException("No @Col annotations found");
        }

        // Удостоверимся, что число пропсов совпадает с числом столбцов в Header
        if (sizeof($this->header->getColumns()) !== sizeof($this->columns)) {
            throw new ParserException("@Header columns count doesn't match the @Col count");
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

        foreach ($this->columns as $name => $column) {
            $prop = $this->properties[$name];

            // Записываем значение property в объект
            $prop->setAccessible(true);
            $prop->setValue($obj, $row[$name] ?? null);
        }

        return $obj;
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
