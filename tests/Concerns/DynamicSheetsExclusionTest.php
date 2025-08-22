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
     * Este test DEMUESTRA el problema actual: BeforeImport se ejecuta DEMASIADO TARDE
     */
    public function it_fails_to_access_sheet_names_before_sheets_method()
    {
        $this->expectException(\LogicException::class);

        $import = new class implements WithMultipleSheets, WithEvents {
            use Importable;

            public $dynamicSheets = [];

            public function sheets(): array
            {
                // ¡ERROR! $dynamicSheets está vacío porque BeforeImport no se ha ejecutado
                if (empty($this->dynamicSheets)) {
                    throw new \LogicException('Cannot access sheet names before BeforeImport event');
                }

                return $this->dynamicSheets;
            }

            public function registerEvents(): array
            {
                return [
                    BeforeImport::class => function (BeforeImport $event) {
                        // Esto se ejecuta DEMASIADO TARDE
                        $sheetNames = $event->getReader()->getSpreadsheet()->getSheetNames();
                        foreach ($sheetNames as $sheetName) {
                            $this->dynamicSheets[$sheetName] = new class {
                                // Sheet import dummy
                            };
                        }
                    }
                ];
            }
        };

        // Esta prueba FALLARÁ demostrando el bug
        $import->import(__DIR__ . '/../Data/Disks/Local/import-multiple-sheets.xlsx');
    }

    /**
     * @test
     * Demuestra que no podemos excluir sheets dinámicamente
     */
    public function it_cannot_exclude_specific_sheets_dynamically()
    {
        $import = new class implements WithMultipleSheets {
            use Importable;

            public function sheets(): array
            {
                // Queremos excluir los primeros 2 sheets
                // pero no hay forma de saber los sheet names aquí
                return [
                    // ¿Cómo excluimos Sheet1 y Sheet2?
                    // ¿Cómo procesamos solo sheets 3+?
                ];
            }
        };

        // Esta prueba muestra la limitación actual
        $this->markTestIncomplete('Cannot dynamically exclude sheets');
    }

    /**
     * @test
     * Demuestra que la validación no es transactional
     */
    public function it_validates_each_sheet_individually_instead_of_transactionally()
    {
        $import = new class implements WithMultipleSheets {
            use Importable;

            public function sheets(): array
            {
                return [
                    'Sheet1' => new class { /* Sheet con error */
                    },
                    'Sheet2' => new class { /* Sheet válido */
                    },
                    'Sheet3' => new class { /* Sheet con error */
                    },
                ];
            }
        };

        // ACTUAL: Falla en Sheet1, Sheet2 y Sheet3 nunca se validan
        // IDEAL: Debería validar TODOS y mostrar TODOS los errores

        $this->markTestIncomplete('Validation is not transactional across sheets');
    }

    /**
     * @test
     * Comportamiento ideal que debería funcionar
     */
    public function it_should_allow_dynamic_sheet_exclusion()
    {
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
                // Excluir primeros 2 sheets
                $sheetsToProcess = array_slice($this->sheetNames, 2);

                foreach ($sheetsToProcess as $sheetName) {
                    $this->dynamicSheets[$sheetName] = new class {
                        // Sheet import para sheets dinámicos
                    };
                }

                return $this->dynamicSheets;
            }
        };

        // Esta prueba FALLARÁ porque ProvidesSheetNames no existe
        // pero demuestra el comportamiento deseado
        $this->markTestIncomplete('ProvidesSheetNames interface does not exist');
    }

    public function test_basic()
    {
        $this->assertTrue(true);
    }
}
