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

/**
 * Schema represents the database schema information that is DBMS specific.
 */
abstract class Schema
{
    // The following are the supported abstract column data types.
    public const TYPE_PK = 'pk';

    public const TYPE_UPK = 'upk';

    public const TYPE_BIGPK = 'bigpk';

    public const TYPE_UBIGPK = 'ubigpk';

    public const TYPE_CHAR = 'char';

    public const TYPE_STRING = 'string';

    public const TYPE_TEXT = 'text';

    public const TYPE_TINYINT = 'tinyint';

    public const TYPE_SMALLINT = 'smallint';

    public const TYPE_INTEGER = 'integer';

    public const TYPE_BIGINT = 'bigint';

    public const TYPE_FLOAT = 'float';

    public const TYPE_DOUBLE = 'double';

    public const TYPE_DECIMAL = 'decimal';

    public const TYPE_DATETIME = 'datetime';

    public const TYPE_TIMESTAMP = 'timestamp';

    public const TYPE_TIME = 'time';

    public const TYPE_DATE = 'date';

    public const TYPE_BINARY = 'binary';

    public const TYPE_BOOLEAN = 'boolean';

    public const TYPE_MONEY = 'money';

    public const TYPE_JSON = 'json';

    /**
     * Extracts the PHP type from abstract DB type.
     * @param SchemaColumn $column the column schema information
     * @return string PHP type name
     */
    protected function getColumnPhpType(SchemaColumn $column)
    {
        static $typeMap = [
            // abstract type => php type
            self::TYPE_TINYINT => 'integer',
            self::TYPE_SMALLINT => 'integer',
            self::TYPE_INTEGER => 'integer',
            self::TYPE_BIGINT => 'integer',
            self::TYPE_BOOLEAN => 'boolean',
            self::TYPE_FLOAT => 'double',
            self::TYPE_DOUBLE => 'double',
            self::TYPE_BINARY => 'resource',
            self::TYPE_JSON => 'array',
        ];
        if (isset($typeMap[$column->type])) {
            if ($column->type === 'bigint') {
                return PHP_INT_SIZE === 8 && ! $column->unsigned ? 'integer' : 'string';
            }
            if ($column->type === 'integer') {
                return PHP_INT_SIZE === 4 && $column->unsigned ? 'string' : 'integer';
            }

            return $typeMap[$column->type];
        }

        return 'string';
    }
}
