<?php

declare(strict_types=1);

namespace AtelliTech\Hyperf\Utils\Core\Validation;

use Hyperf\Validation\Contract\ValidatorFactoryInterface;
use Hyperf\Validation\Validator;
use InvalidArgumentException;
use RuntimeException;

/**
 * Base validator class for application-level validation logic.
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

    public function __construct(protected ValidatorFactoryInterface $factory)
    {
        $rules = $this->rules();
        if (empty($rules)) {
            throw new RuntimeException('Rules(' . static::class . ') cannot be empty.');
        }

        $this->fields = array_keys($rules);
    }

    /**
     * Magic getter for validated attributes.
     */
    public function __get(string $name): mixed
    {
        if (! $this->validated) {
            throw new RuntimeException('Data not validated yet.');
        }

        if (! array_key_exists($name, $this->attributes)) {
            if (in_array($name, $this->fields, true)) {
                return null;
            }

            throw new InvalidArgumentException(
                sprintf('%s: undefined property "%s"', static::class, $name)
            );
        }

        return $this->attributes[$name];
    }

    /**
     * Load input data into the validator.
     *
     * @param array<string, mixed> $data
     */
    public function load(array $data): static
    {
        $unknownKeys = array_diff(array_keys($data), $this->fields);
        if (! empty($unknownKeys)) {
            throw new RuntimeException(sprintf(
                '[%s] Unknown keys: %s',
                static::class,
                implode(', ', $unknownKeys)
            ));
        }

        $this->attributes = $data;
        $this->validated = false;

        return $this;
    }

    /**
     * Validate the loaded data.
     *
     * @throws ValidationException
     */
    public function validate(): bool
    {
        /** @var Validator $validator */
        $validator = $this->factory->make($this->attributes, $this->rules(), $this->messages());

        if ($validator->fails()) {
            $errors = [];

            foreach ($this->fields as $field) {
                if ($validator->errors()->has($field)) {
                    $errors[$field] = $validator->errors()->get($field);
                }
            }

            if (empty($errors)) {
                $errors['_'] = $validator->errors()->all();
            }

            throw new ValidationException($errors);
        }

        $this->validated = true;
        return true;
    }

    /**
     * Return all validated attributes.
     *
     * @return array<string, mixed>
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
     * Validation rules.
     *
     * @return array<string, mixed>
     */
    abstract protected function rules(): array;
}
