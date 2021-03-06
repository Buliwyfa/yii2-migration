<?php declare(strict_types=1);

namespace bizley\migration\table;

/**
 * Class TableColumnBigInt
 * @package bizley\migration\table
 */
class TableColumnBigInt extends TableColumn
{
    /**
     * Builds methods chain for column definition.
     * @param TableStructure $table
     */
    public function buildSpecificDefinition(TableStructure $table): void
    {
        if ($table->generalSchema && !$table->primaryKey->isComposite() && $this->isColumnInPK($table->primaryKey)) {
            $this->isPkPossible = false;
            $this->isNotNullPossible = false;
            $this->definition[] = 'bigPrimaryKey()';
        } else {
            $this->definition[] = 'bigInteger(' . ($table->generalSchema ? null : $this->length) . ')';
        }
    }
}
