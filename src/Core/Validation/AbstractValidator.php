<?php

declare(strict_types=1);

namespace AtelliTech\Hyperf\Utils\Core\Validation;

use Hyperf\Validation\Contract\ValidatorFactoryInterface;
use Hyperf\Validation\Validator;
use InvalidArgumentException;
use RuntimeException;

/**
 * This is the base validator class.
 */
abstract class AbstractValidator
{
    protected bool $validated = false;

    /**
     * @var array<string, mixed>
     */
    protected array $attributes = [];

    /**
     * @var string[]
     */
    protected array $fields = [];

    /**
     * Constructor.
     */
    public function __construct(protected ValidatorFactoryInterface $factory)
    {
        $rules = $this->rules();
        if (empty($rules)) {
            throw new RuntimeException('Rules(' . static::class . ') cannot be empty.');
        }

        $this->fields = array_keys($rules);
    }

    /**
     * Get attribute value by key.
     *
     * @return mixed
     */
    public function __get(string $name)
    {
        if (! array_key_exists($name, $this->attributes)) {
            throw new InvalidArgumentException(
                sprintf('%s: undefined property "%s"', $name, static::class)
            );
        }

        return $this->attributes[$name];
    }

    /**
     * load data into the validator.
     *
     * @param array<string, mixed> $data
     * @throws RuntimeException
     */
    public function load(array $data): void
    {
        // check has any unknown key of data not in fields
        $unknownKeys = array_diff(array_keys($data), $this->fields);
        if (! empty($unknownKeys)) {
            throw new RuntimeException('Unknown keys: ' . implode(', ', $unknownKeys));
        }

        foreach ($this->fields as $field) {
            $this->attributes[$field] = $data[$field] ?? null;
        }

        $this->validated = false;
    }

    /**
     * Validate.
     *
     * @throws ValidationException
     */
    public function validate(): bool
    {
        /** @var Validator $validator */
        $validator = $this->factory->make($this->attributes, $this->rules(), $this->messages());

        if ($validator->fails()) {
            throw new ValidationException(
                $validator->errors()->all()
            );
        }

        $this->validated = true;
        return true;
    }

    /**
     * Get validated attributes.
     *
     * @return array<string, mixed>
     * @throws RuntimeException
     */
    public function validated(): array
    {
        if (! $this->validated) {
            throw new RuntimeException('Data not validated yet.');
        }

        return $this->attributes;
    }

    /**
     * Custom error messages.
     *
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [];
    }

    /**
     * Rule definition.
     *
     * @return array<string, mixed>
     */
    abstract protected function rules(): array;
}
