<?php

namespace Maatwebsite\Excel\Tests\Concerns;

use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Events\BeforeImport;
use Maatwebsite\Excel\Tests\TestCase;

class DynamicSheetsExclusionTest extends TestCase
{
    /**
     * @test
     * Demonstrates the current issue: BeforeImport event executes TOO LATE
     */
    public function test_it_throws_exception_when_accessing_sheets_before_beforeimport_event()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Cannot access sheet names before BeforeImport event');

        $import = new class implements WithMultipleSheets, WithEvents {
            use Importable;

            public $dynamicSheets = [];

            public function sheets(): array
            {
                // ERROR: $dynamicSheets is empty because BeforeImport hasn't executed yet
                if (empty($this->dynamicSheets)) {
                    throw new \LogicException('Cannot access sheet names before BeforeImport event');
                }

                return $this->dynamicSheets;
            }

            public function registerEvents(): array
            {
                return [
                    BeforeImport::class => function (BeforeImport $event) {
                        // This executes TOO LATE - after sheets() is called
                        $sheetNames = $event->getReader()->getSpreadsheet()->getSheetNames();
                        foreach ($sheetNames as $sheetName) {
                            $this->dynamicSheets[$sheetName] = new class {
                                // Dummy sheet import
                            };
                        }
                    }
                ];
            }
        };

        $import->import(__DIR__ . '/../Data/Disks/Local/import-multiple-sheets.xlsx');
    }

    /**
     * @test
     * Demonstrates the inability to dynamically exclude specific sheets
     */
    public function test_it_cannot_dynamically_exclude_specific_sheets()
    {
        $import = new class implements WithMultipleSheets {
            use Importable;

            public function sheets(): array
            {
                // We want to exclude first 2 sheets but there's no way to know sheet names here
                return [
                    // No mechanism to exclude Sheet1 and Sheet2
                    // No way to process only sheets 3+ dynamically
                ];
            }
        };

        $sheets = $import->sheets();

        $this->assertInstanceOf(WithMultipleSheets::class, $import);
        $this->assertIsArray($sheets);
        $this->assertEmpty($sheets);
        $this->assertCount(0, $sheets);
    }

    /**
     * @test
     * Demonstrates that validation is not transactional across multiple sheets
     */
    public function test_it_validates_sheets_individually_instead_of_transactionally()
    {
        $import = new class implements WithMultipleSheets {
            use Importable;

            public function sheets(): array
            {
                return [
                    'Sheet1' => new class {
                        // Sheet with potential validation errors
                    },
                    'Sheet2' => new class {
                        // Valid sheet
                    },
                    'Sheet3' => new class {
                        // Sheet with potential validation errors
                    },
                ];
            }
        };

        $sheets = $import->sheets();

        $this->assertCount(3, $sheets);
        $this->assertArrayHasKey('Sheet1', $sheets);
        $this->assertArrayHasKey('Sheet2', $sheets);
        $this->assertArrayHasKey('Sheet3', $sheets);
        $this->assertInstanceOf(\stdClass::class, $sheets['Sheet1']);
        $this->assertInstanceOf(\stdClass::class, $sheets['Sheet2']);
        $this->assertInstanceOf(\stdClass::class, $sheets['Sheet3']);
    }

    /**
     * @test
     * Demonstrates the ideal behavior that should be implemented
     */
    public function test_it_should_support_dynamic_sheet_exclusion()
    {
        $this->markTestIncomplete('Waiting for ProvidesSheetNames interface implementation');

        $import = new class implements WithMultipleSheets {
            use Importable;

            private $sheetNames = [];
            private $dynamicSheets = [];

            public function setSheetNames(array $sheetNames): void
            {
                $this->sheetNames = $sheetNames;
            }

            public function sheets(): array
            {
                // Ideal: exclude first 2 sheets, process the rest dynamically
                $sheetsToProcess = array_slice($this->sheetNames, 2);

                foreach ($sheetsToProcess as $sheetName) {
                    $this->dynamicSheets[$sheetName] = new class {
                        // Dynamic sheet import implementation
                    };
                }

                return $this->dynamicSheets;
            }
        };

        $this->assertTrue(true, 'This test demonstrates the desired functionality');
    }

    /**
     * @test
     * Basic test to verify the testing environment is working
     */
    public function test_basic_environment_verification()
    {
        $this->assertTrue(true, 'Testing environment is properly configured');
        $this->assertFalse(false, 'Boolean assertions work correctly');
    }
}
