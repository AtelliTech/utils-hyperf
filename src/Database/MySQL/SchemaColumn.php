<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @see     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace AtelliTech\Hyperf\Utils\Database\MySQL;

use BackedEnum;
use PDO;

/**
 * This class represents a column in a database schema.
 */
class SchemaColumn
{
    public string $name;

    public string $dbType;

    public string $type;

    public ?string $collation;

    public bool $allowNull = false;

    public bool $isPrimaryKey = false;

    public bool $autoIncrement = false;

    /**
     * @var null|mixed
     */
    public mixed $defaultValue;

    public string $extra;

    public string $privileges;

    public ?string $comment;

    public string $phpType;

    public bool $unsigned = false;

    /**
     * @var null|array<int, array<int, string>|string>
     */
    public ?array $enumValues;

    public ?int $size;

    public ?int $scale;

    public ?int $precision;

    /**
     * Determines the PDO type for the given PHP data value.
     * @param mixed $data the data whose PDO type is to be determined
     * @return int the PDO type
     * @see https://www.php.net/manual/en/pdo.constants.php
     */
    public function getPdoType($data)
    {
        static $typeMap = [
            // php type => PDO type
            'boolean' => PDO::PARAM_BOOL,
            'integer' => PDO::PARAM_INT,
            'string' => PDO::PARAM_STR,
            'resource' => PDO::PARAM_LOB,
            'NULL' => PDO::PARAM_NULL,
        ];
        $type = gettype($data);

        return isset($typeMap[$type]) ? $typeMap[$type] : PDO::PARAM_STR;
    }

    /**
     * Converts the input value according to $phpType after retrieval from the database.
     *
     * @param mixed $value the value to be typecasted
     * @return mixed the typecasted value
     */
    public function phpTypecast($value)
    {
        return $this->typecast($value);
    }

    /**
     * Typecasts a value to the appropriate PHP type.
     *
     * @param mixed $value the value to be typecasted
     * @return mixed the typecasted value
     */
    protected function typecast($value)
    {
        if (
            $value === ''
            && ! in_array(
                $this->type,
                [
                    Schema::TYPE_TEXT,
                    Schema::TYPE_STRING,
                    Schema::TYPE_BINARY,
                    Schema::TYPE_CHAR,
                ],
                true
            )
        ) {
            return null;
        }
        if (
            $value === null
            || gettype($value) === $this->phpType
        ) {
            return $value;
        }
        if (
            is_array($value)
            && count($value) === 2
            && isset($value[1])
            && in_array($value[1], $this->getPdoParamTypes(), true)
        ) {
            return new class($value[0], $value[1]) {
                public function __construct(public mixed $value, public int $type)
                {
                }
            };
        }
        switch ($this->phpType) {
            case 'resource':
            case 'string':
                if (is_resource($value)) {
                    return $value;
                }
                if (is_float($value)) {
                    // ensure type cast always has . as decimal separator in all locales
                    return str_replace(',', '.', (string) $value);
                }
                if (
                    is_numeric($value)
                ) {
                    // https://github.com/yiisoft/yii2/issues/14663
                    return $value;
                }
                if (PHP_VERSION_ID >= 80100 && is_object($value) && $value instanceof BackedEnum) {
                    return (string) $value->value;
                }
                return (string) $value;
            case 'integer':
                if (PHP_VERSION_ID >= 80100 && is_object($value) && $value instanceof BackedEnum) {
                    return (int) $value->value;
                }
                return (int) $value;
            case 'boolean':
                // treating a 0 bit value as false too
                // https://github.com/yiisoft/yii2/issues/9006
                return (bool) $value && $value !== "\0" && strtolower($value) !== 'false';
            case 'double':
                return (float) $value;
        }
        return $value;
    }

    /**
     * @return int[] array of numbers that represent possible PDO parameter types
     */
    private function getPdoParamTypes()
    {
        return [PDO::PARAM_BOOL, PDO::PARAM_INT, PDO::PARAM_STR, PDO::PARAM_LOB, PDO::PARAM_NULL, PDO::PARAM_STMT];
    }
}
