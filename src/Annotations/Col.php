<?php
declare(strict_types=1);

namespace ImageSpark\SpreadsheetParser\Annotations;

/**
 * @Annotation
 * @NamedArgumentConstructor
 * @Target("PROPERTY")
 */
class Col
{
    /**
     * Правило валидации
     *
     * @var string|null
     */
    private ?string $rule;

    /**
     * Кастомные сообщения об ошибках валидации
     *
     * @var array
     */
    private array $messages;

    /**
     * Строка считается "не пустой", если все поля с этим флагом присутствуют в строке
     *
     * @var boolean
     */
    private bool $mandatory;

    /**
     * Конструктор
     *
     * @param string|null  $rule      - правило валидации
     * @param array        $messages  - опциональный массив с кастомными сообщениями об ошибке валидации
     * @param boolean      $mandatory - `true`, если поле должно присутствовать в строке, чтобы она не считалась "пустой"
     */
    public function __construct(?string $rule = null, array $messages = [], bool $mandatory = false)
    {
        $this->rule = $rule;
        $this->messages = $messages;
        $this->mandatory = $mandatory;
    }

    /**
     * Геттер правила валидации
     *
     * @return string|null
     */
    public function getRule(): ?string
    {
        return $this->rule;
    }

    /**
     * Геттер кастомных сообщений об ошибках валидации
     *
     * @return array
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * Геттер для "mandatory"
     *
     * @return boolean
     */
    public function isMandatory(): bool
    {
        return $this->mandatory;
    }
}