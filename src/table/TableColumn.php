<?php declare(strict_types=1);

namespace bizley\migration\table;

use yii\base\BaseObject;
use yii\db\Expression;

/**
 * Class TableColumn
 * @package bizley\migration\table
 *
 * @property-read int|string $length
 */
class TableColumn extends BaseObject
{
    /**
     * @var string
     */
    public $name;
    /**
     * @var string
     */
    public $type;
    /**
     * @var bool|null
     */
    public $isNotNull;
    /**
     * @var int
     */
    public $size;
    /**
     * @var int
     */
    public $precision;
    /**
     * @var int
     */
    public $scale;
    /**
     * @var bool
     */
    public $isUnique = false;
    /**
     * @var bool
     */
    public $isUnsigned = false;
    /**
     * @var string
     */
    public $check;
    /**
     * @var mixed
     */
    public $default;
    /**
     * @var bool
     */
    public $isPrimaryKey;
    /**
     * @var bool
     */
    public $autoIncrement;
    /**
     * @var string
     */
    public $append;
    /**
     * @var string
     */
    public $comment;

    /**
     * List of all properties to be checked.
     * @return array
     */
    public static function properties(): array
    {
        return ['type', 'isNotNull', 'size', 'precision', 'scale', 'isUnique', 'isUnsigned', 'default', 'append', 'comment'];
    }

    /**
     * Returns length of the column.
     * @return int|string
     */
    public function getLength()
    {
        return $this->size;
    }

    /**
     * Sets length of the column.
     * @param string|int $value
     */
    public function setLength($value): void
    {
        $this->size = $value;
        $this->precision = $value;
    }

    protected function buildSpecificDefinition(TableStructure $table): void {}

    protected $definition = [];
    protected $isUnsignedPossible = true;
    protected $isNotNullPossible = true;
    protected $isPkPossible = true;

    /**
     * Builds general methods chain for column definition.
     * @param TableStructure $table
     */
    protected function buildGeneralDefinition(TableStructure $table): void
    {
        array_unshift($this->definition, '$this');

        if ($this->isUnsignedPossible && $this->isUnsigned) {
            $this->definition[] = 'unsigned()';
        }
        if ($this->isNotNullPossible && $this->isNotNull) {
            $this->definition[] = 'notNull()';
        }
        if ($this->default !== null) {
            if ($this->default instanceof Expression) {
                $this->definition[] = "defaultExpression('" . $this->escapeQuotes($this->default->expression) . "')";
            } else {
                $this->definition[] = "defaultValue('" . $this->escapeQuotes((string) $this->default) . "')";
            }
        }
        if ($this->isPkPossible && !$table->primaryKey->isComposite() && $this->isColumnInPK($table->primaryKey)) {
            $append = $this->prepareSchemaAppend($table, true, $this->autoIncrement);
            if (!empty($this->append)) {
                $append .= ' ' . $this->append;
            }
            $this->definition[] = "append('" . $this->escapeQuotes((string) $append) . "')";
        } elseif (!empty($this->append)) {
            $this->definition[] = "append('" . $this->escapeQuotes((string) $this->append) . "')";
        }
        if ($this->comment) {
            $this->definition[] = "comment('" . $this->escapeQuotes((string) $this->comment) . "')";
        }
    }

    /**
     * Renders column definition.
     * @param TableStructure $table
     * @return string
     */
    public function renderDefinition(TableStructure $table): string
    {
        $this->buildSpecificDefinition($table);
        $this->buildGeneralDefinition($table);
        return implode('->', $this->definition);
    }

    /**
     * Renders the column.
     * @param TableStructure $table
     * @param int $indent
     * @return string
     */
    public function render(TableStructure $table, int $indent = 12): string
    {
        return str_repeat(' ', $indent) . "'{$this->name}' => " . $this->renderDefinition($table) . ',';
    }

    /**
     * Checks if column is a part of primary key.
     * @param TablePrimaryKey $pk
     * @return bool
     */
    public function isColumnInPK(TablePrimaryKey $pk): bool
    {
        return \in_array($this->name, $pk->columns, true);
    }

    /**
     * Checks if information of primary key is set in append property.
     * @param string $schema
     * @return bool
     */
    public function isColumnAppendPK(string $schema): bool
    {
        if (empty($this->append)) {
            return false;
        }
        if ($schema === TableStructure::SCHEMA_MSSQL) {
            if (stripos($this->append, 'IDENTITY') !== false && stripos($this->append, 'PRIMARY KEY') !== false) {
                return true;
            }
        } elseif (stripos($this->append, 'PRIMARY KEY') !== false) {
            return true;
        }
        return false;
    }

    /**
     * Prepares append SQL based on schema.
     * @param TableStructure $table
     * @param bool $primaryKey
     * @param bool $autoIncrement
     * @return string|null
     */
    public function prepareSchemaAppend(TableStructure $table, bool $primaryKey, bool $autoIncrement): ?string
    {
        switch ($table->schema) {
            case TableStructure::SCHEMA_MSSQL:
                $append = $primaryKey ? 'IDENTITY PRIMARY KEY' : '';
                break;
            case TableStructure::SCHEMA_OCI:
            case TableStructure::SCHEMA_PGSQL:
                $append = $primaryKey ? 'PRIMARY KEY' : '';
                break;
            case TableStructure::SCHEMA_SQLITE:
                $append = trim(($primaryKey ? 'PRIMARY KEY ' : '') . ($autoIncrement ? 'AUTOINCREMENT' : ''));
                break;
            case TableStructure::SCHEMA_CUBRID:
            case TableStructure::SCHEMA_MYSQL:
            default:
                $append = trim(($autoIncrement ? 'AUTO_INCREMENT ' : '') . ($primaryKey ? 'PRIMARY KEY' : ''));
        }
        return empty($append) ? null : $append;
    }

    /**
     * Escapes single quotes.
     * @param string $value
     * @return string
     */
    public function escapeQuotes(string $value): string
    {
        return str_replace('\'', '\\\'', $value);
    }

    /**
     * Removes information of primary key in append property.
     * @param string $schema
     * @return null|string
     */
    public function removePKAppend(string $schema): ?string
    {
        if (!$this->isColumnAppendPK($schema)) {
            return null;
        }

        $uppercaseAppend = preg_replace('/\s+/', ' ', mb_strtoupper($this->append, 'UTF-8'));

        switch ($schema) {
            case TableStructure::SCHEMA_MSSQL:
                $formattedAppend = str_replace(['PRIMARY KEY', 'IDENTITY'], '', $uppercaseAppend);
                break;
            case TableStructure::SCHEMA_OCI:
            case TableStructure::SCHEMA_PGSQL:
                $formattedAppend = str_replace('PRIMARY KEY', '', $uppercaseAppend);
                break;
            case TableStructure::SCHEMA_SQLITE:
                $formattedAppend = str_replace(['PRIMARY KEY', 'AUTOINCREMENT'], '', $uppercaseAppend);
                break;
            case TableStructure::SCHEMA_CUBRID:
            case TableStructure::SCHEMA_MYSQL:
            default:
                $formattedAppend = str_replace(['PRIMARY KEY', 'AUTO_INCREMENT'], '', $uppercaseAppend);
        }
        $formattedAppend = trim($formattedAppend);

        return !empty($formattedAppend) ? $formattedAppend : null;
    }
}
