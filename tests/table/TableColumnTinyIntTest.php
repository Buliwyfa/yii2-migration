<?php declare(strict_types=1);

namespace bizley\tests\table;

use bizley\migration\table\TableColumnTinyInt;

class TableColumnTinyIntTest extends TableColumnTestCase
{
    public function testDefinitionSpecific(): void
    {
        $column = new TableColumnTinyInt(['size' => 1]);
        $this->assertEquals('$this->tinyInteger(1)', $column->renderDefinition($this->getTable(false)));
    }

    public function testDefinitionGeneral(): void
    {
        $column = new TableColumnTinyInt(['size' => 10]);
        $this->assertEquals('$this->tinyInteger()', $column->renderDefinition($this->getTable()));
    }
}
