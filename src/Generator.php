<?php declare(strict_types=1);

namespace bizley\migration;

use bizley\migration\table\TableColumn;
use bizley\migration\table\TableColumnFactory;
use bizley\migration\table\TableForeignKey;
use bizley\migration\table\TableIndex;
use bizley\migration\table\TablePrimaryKey;
use bizley\migration\table\TableStructure;
use Yii;
use yii\base\Component;
use yii\base\InvalidConfigException;
use yii\base\NotSupportedException;
use yii\base\View;
use yii\db\Connection;
use yii\db\TableSchema;
use yii\helpers\ArrayHelper;
use yii\helpers\FileHelper;

/**
 * Class Generator
 * @package bizley\migration
 *
 * @property TableSchema $tableSchema
 * @property array $structure
 * @property TableStructure $table
 * @property-read string|null $normalizedNamespace
 */
class Generator extends Component
{
    /**
     * @var Connection DB connection.
     */
    public $db;

    /**
     * @var string Table name to be generated (before prefix).
     */
    public $tableName;

    /**
     * @var string Migration class name.
     */
    public $className;

    /**
     * @var View View used in controller.
     */
    public $view;

    /**
     * @var bool Table prefix flag.
     */
    public $useTablePrefix = true;

    /**
     * @var string File template.
     */
    public $templateFile;

    /**
     * @var string File update template.
     */
    public $templateFileUpdate;

    /**
     * @var string Migration namespace.
     */
    public $namespace;

    /**
     * @var bool Whether to use general column schema instead of database specific.
     */
    public $generalSchema = true;

    /**
     * @var string|null
     * @since 3.0.4
     */
    public $tableOptionsInit;

    /**
     * @var string|null
     * @since 3.0.4
     */
    public $tableOptions;

    /**
     * Checks if DB connection is passed.
     * @throws InvalidConfigException
     */
    public function init(): void
    {
        parent::init();
        if (!($this->db instanceof Connection)) {
            throw new InvalidConfigException("Parameter 'db' must be an instance of yii\\db\\Connection!");
        }
    }

    protected $_tableSchema;

    /**
     * Returns table schema.
     * @return TableSchema|null
     */
    public function getTableSchema(): ?TableSchema
    {
        if ($this->_tableSchema === null) {
            $this->_tableSchema = $this->db->getTableSchema($this->tableName);
        }
        return $this->_tableSchema;
    }

    /**
     * Returns table primary key.
     * @return TablePrimaryKey
     */
    protected function getTablePrimaryKey(): TablePrimaryKey
    {
        $data = [];

        /* @var $constraint \yii\db\Constraint */
        $constraint = $this->db->schema->getTablePrimaryKey($this->tableName, true);
        if ($constraint) {
            $data = [
                'columns' => $constraint->columnNames,
                'name' => $constraint->name,
            ];
        }

        return new TablePrimaryKey($data);
    }

    /**
     * Returns columns structure.
     * @param TableIndex[] $indexes
     * @return TableColumn[]
     * @throws InvalidConfigException
     */
    protected function getTableColumns(array $indexes = []): array
    {
        $columns = [];
        if ($this->tableSchema instanceof TableSchema) {
            $indexData = !empty($indexes) ? $indexes : $this->getTableIndexes();
            foreach ($this->tableSchema->columns as $column) {
                $isUnique = false;
                foreach ($indexData as $index) {
                    if ($index->unique && $index->columns[0] === $column->name && \count($index->columns) === 1) {
                        $isUnique = true;
                        break;
                    }
                }
                $columns[$column->name] = TableColumnFactory::build([
                    'name' => $column->name,
                    'type' => $column->type,
                    'size' => $column->size,
                    'precision' => $column->precision,
                    'scale' => $column->scale,
                    'isNotNull' => $column->allowNull ? null : true,
                    'isUnique' => $isUnique,
                    'check' => null,
                    'default' => $column->defaultValue,
                    'isPrimaryKey' => $column->isPrimaryKey,
                    'autoIncrement' => $column->autoIncrement,
                    'isUnsigned' => $column->unsigned,
                    'comment' => $column->comment,
                ]);
            }
        }
        return $columns;
    }

    /**
     * Returns foreign keys structure.
     * @return TableForeignKey[]
     */
    protected function getTableForeignKeys(): array
    {
        $data = [];

        $fks = $this->db->schema->getTableForeignKeys($this->tableName, true);
        /* @var $fk \yii\db\ForeignKeyConstraint */
        foreach ($fks as $fk) {
            $data[$fk->name] = new TableForeignKey([
                'name' => $fk->name,
                'columns' => $fk->columnNames,
                'refTable' => $fk->foreignTableName,
                'refColumns' => $fk->foreignColumnNames,
                'onDelete' => $fk->onDelete,
                'onUpdate' => $fk->onUpdate,
            ]);
        }

        return $data;
    }

    /**
     * Returns table indexes.
     * @return TableIndex[]
     * @since 2.2.2
     */
    protected function getTableIndexes(): array
    {
        $data = [];

        $idxs = $this->db->schema->getTableIndexes($this->tableName, true);
        /* @var $idx \yii\db\IndexConstraint */
        foreach ($idxs as $idx) {
            if (!$idx->isPrimary) {
                $data[$idx->name] = new TableIndex([
                    'name' => $idx->name,
                    'unique' => $idx->isUnique,
                    'columns' => $idx->columnNames
                ]);
            }
        }

        return $data;
    }

    private $_table;

    /**
     * Returns table data
     * @return TableStructure
     * @throws InvalidConfigException
     */
    public function getTable(): TableStructure
    {
        if ($this->_table === null) {
            $indexes = $this->getTableIndexes();
            $this->_table = new TableStructure([
                'name' => $this->tableName,
                'schema' => \get_class($this->db->schema),
                'generalSchema' => $this->generalSchema,
                'usePrefix' => $this->useTablePrefix,
                'dbPrefix' => $this->db->tablePrefix,
                'primaryKey' => $this->getTablePrimaryKey(),
                'columns' => $this->getTableColumns($indexes),
                'foreignKeys' => $this->getTableForeignKeys(),
                'indexes' => $indexes,
                'tableOptionsInit' => $this->tableOptionsInit,
                'tableOptions' => $this->tableOptions,
            ]);
        }
        return $this->_table;
    }

    /**
     * Returns normalized namespace.
     * @return null|string
     */
    public function getNormalizedNamespace(): ?string
    {
        return !empty($this->namespace) ? FileHelper::normalizePath($this->namespace, '\\') : null;
    }

    /**
     * Generates migration content or echoes exception message.
     * @return string
     */
    public function generateMigration(): string
    {
        return $this->view->renderFile(Yii::getAlias($this->templateFile), [
            'table' => $this->table,
            'className' => $this->className,
            'namespace' => $this->normalizedNamespace
        ]);
    }
}
