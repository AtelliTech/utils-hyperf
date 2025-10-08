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

use Hyperf\Database\ConnectionInterface;
use stdClass;

/**
 * This is a schema reader for MySQL/MariaDB compatible database.
 */
class SchemaReader extends Schema
{
    /**
     * @var array<string, mixed> mapping from physical column types (keys) to abstract column types (values)
     */
    protected $typeMap = [
        'tinyint' => self::TYPE_TINYINT,
        'bool' => self::TYPE_TINYINT,
        'boolean' => self::TYPE_TINYINT,
        'bit' => self::TYPE_INTEGER,
        'smallint' => self::TYPE_SMALLINT,
        'mediumint' => self::TYPE_INTEGER,
        'int' => self::TYPE_INTEGER,
        'integer' => self::TYPE_INTEGER,
        'bigint' => self::TYPE_BIGINT,
        'float' => self::TYPE_FLOAT,
        'double' => self::TYPE_DOUBLE,
        'double precision' => self::TYPE_DOUBLE,
        'real' => self::TYPE_FLOAT,
        'decimal' => self::TYPE_DECIMAL,
        'numeric' => self::TYPE_DECIMAL,
        'dec' => self::TYPE_DECIMAL,
        'fixed' => self::TYPE_DECIMAL,
        'tinytext' => self::TYPE_TEXT,
        'mediumtext' => self::TYPE_TEXT,
        'longtext' => self::TYPE_TEXT,
        'longblob' => self::TYPE_BINARY,
        'blob' => self::TYPE_BINARY,
        'text' => self::TYPE_TEXT,
        'varchar' => self::TYPE_STRING,
        'string' => self::TYPE_STRING,
        'char' => self::TYPE_CHAR,
        'datetime' => self::TYPE_DATETIME,
        'year' => self::TYPE_DATE,
        'date' => self::TYPE_DATE,
        'time' => self::TYPE_TIME,
        'timestamp' => self::TYPE_TIMESTAMP,
        'enum' => self::TYPE_STRING,
        'set' => self::TYPE_STRING,
        'binary' => self::TYPE_BINARY,
        'varbinary' => self::TYPE_BINARY,
        'json' => self::TYPE_JSON,
    ];

    /**
     * Constructor.
     *
     * @param ConnectionInterface $db the DB connection instance
     */
    public function __construct(private ConnectionInterface $db)
    {
    }

    /**
     * Get table columns.
     *
     * @param string $table Table name
     * @return SchemaColumn[]
     */
    public function getTableColumns(string $table): array
    {
        $sql = "SHOW FULL COLUMNS FROM `{$table}`";
        $rows = $this->db->select($sql);
        $columns = [];
        foreach ($rows as $row) {
            $columns[] = $this->loadColumnSchema((array) $row);
        }

        return $columns;
    }

    /**
     * load column schema from stdClass object.
     *
     * @param array<string, mixed> $info
     */
    protected function loadColumnSchema(array $info): SchemaColumn
    {
        $column = new SchemaColumn();
        $column->name = $info['Field'];
        $column->allowNull = $info['Null'] === 'YES';
        $column->isPrimaryKey = strpos($info['Key'], 'PRI') !== false;
        $column->autoIncrement = stripos($info['Extra'], 'auto_increment') !== false;
        $column->comment = $info['Comment'];

        $column->dbType = $info['Type'];
        $column->unsigned = stripos($column->dbType, 'unsigned') !== false;

        $column->type = self::TYPE_STRING;
        if (preg_match('/^(\w+)(?:\(([^\)]+)\))?/', $column->dbType, $matches)) {
            $type = strtolower($matches[1]);
            if (isset($this->typeMap[$type])) {
                $column->type = $this->typeMap[$type];
            }
            if (! empty($matches[2])) {
                if ($type === 'enum') {
                    preg_match_all("/'[^']*'/", $matches[2], $values);
                    foreach ($values[0] as $i => $value) {
                        $values[$i] = trim($value, "'");
                    }
                    $column->enumValues = $values;
                } else {
                    $values = explode(',', $matches[2]);
                    $column->size = $column->precision = (int) $values[0];
                    if (isset($values[1])) {
                        $column->scale = (int) $values[1];
                    }
                    if ($column->size === 1 && $type === 'bit') {
                        $column->type = 'boolean';
                    } elseif ($type === 'bit') {
                        if ($column->size > 32) {
                            $column->type = 'bigint';
                        } elseif ($column->size === 32) {
                            $column->type = 'integer';
                        }
                    }
                }
            }
        }

        $column->phpType = $this->getColumnPhpType($column);

        if (! $column->isPrimaryKey) {
            /**
             * When displayed in the INFORMATION_SCHEMA.COLUMNS table, a default CURRENT TIMESTAMP is displayed
             * as CURRENT_TIMESTAMP up until MariaDB 10.2.2, and as current_timestamp() from MariaDB 10.2.3.
             *
             * See details here: https://mariadb.com/kb/en/library/now/#description
             */
            if (
                in_array($column->type, ['timestamp', 'datetime', 'date', 'time'])
                && isset($info['Default'])
                && preg_match('/^current_timestamp(?:\(([0-9]*)\))?$/i', $info['Default'], $matches)
            ) {
                $column->defaultValue = 'CURRENT_TIMESTAMP' . (! empty($matches[1]) ? '(' . $matches[1] . ')' : '');
            } elseif (isset($type) && $type === 'bit') {
                $column->defaultValue = bindec(trim(isset($info['Default']) ? $info['Default'] : '', 'b\''));
            } else {
                $column->defaultValue = $column->phpTypecast($info['Default']);
            }
        }

        return $column;
    }
}
