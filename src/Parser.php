<?php
namespace ImageSpark\SpreadsheetParser;

use Doctrine\Common\Annotations\AnnotationException;
use ImageSpark\SpreadsheetParser\Exceptions\ParserException;
use ReflectionClass;
use ReflectionProperty;
use ImageSpark\SpreadsheetParser\Annotations\Col;
use ImageSpark\SpreadsheetParser\Annotations\Header;
use ImageSpark\SpreadsheetParser\Annotations\NoHeader;
use Doctrine\Common\Annotations\AnnotationReader;

class Parser
{
    private array $data;
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
     * Конструктор
     *
     * @param array  $data
     * @param string $mappedClass
     */
    public function __construct(array $data, string $mappedClass)
    {
        $this->data = $data;
        $this->mappedClass = $mappedClass;

        $reader = new AnnotationReader;
        $reflectionClass = new ReflectionClass($mappedClass);

        // Порядок вызовов ниже важен
        $this->assembleHeader($reader, $reflectionClass);
        $this->assembleProperties($reader, $reflectionClass);
    }

    /**
     * Йелдит объекты, полученные из spreadsheet
     *
     * @return \Generator
     */
    public function parseRows(): \Generator
    {
        foreach ($this->data as $rowIndex => $row)
        {
            // пропускаем Header rows
            if ($rowIndex < $this->header->getRows()) {
                continue;
            }

            // Не обрабатываем строки, которые не содержат всех обязательных значений
            if (!$this->allMandatoryColumnsPresent($row)) {
                continue;
            }

            yield $rowIndex => $this->parseRow($row);
        }
    }

    /**
     * Читаем заголовок
     *
     * @param  AnnotationReader $reader
     * @param  ReflectionClass $reflectionClass
     * @return void
     * @throws ParserException
     */
    private function assembleHeader(AnnotationReader $reader, ReflectionClass $reflectionClass): void
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
    }

    /**
     * Подготавливаем пропсы.
     *
     * @param AnnotationReader $reader
     * @param ReflectionClass $reflectionClass
     * @return void
     * @throws ParserException
     */
    private function assembleProperties(AnnotationReader $reader, ReflectionClass $reflectionClass): void
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
     * Парсив raw массив строки
     *
     * @param  array  $row
     * @return object
     */
    private function parseRow(array $row): object
    {
        $obj = new $this->mappedClass;

        foreach ($this->columns as $name => $column) {
            $columnIndex = $this->indexes[$name];

            $prop = $this->properties[$name];

            // Записываем значение property в объект
            $prop->setAccessible(true);
            $prop->setValue($obj, $row[$columnIndex] ?? null);
        }

        return $obj;
    }

    private function validateRow(array $row)
    {
        // validator(...)
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
