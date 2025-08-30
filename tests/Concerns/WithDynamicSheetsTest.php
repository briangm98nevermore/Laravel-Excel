<?php

namespace Maatwebsite\Excel\Tests\Concerns;

use Maatwebsite\Excel\Concerns\WithDynamicSheets;
use Maatwebsite\Excel\Tests\TestCase;
use Maatwebsite\Excel\Exceptions\MultipleSheetsValidationException;

class WithDynamicSheetsTest extends TestCase
{
    use WithDynamicSheets;

    /**
     * @test
     */
    public function test_it_can_set_and_get_sheet_names()
    {
        $sheetNames = ['Sheet1', 'Sheet2', 'Sheet3'];
        $this->setSheetNames($sheetNames);

        $this->assertEquals($sheetNames, $this->getSheetNames());
    }

    /**
     * @test
     */
    public function test_it_can_set_and_get_sheets_to_skip()
    {
        $sheetsToSkip = ['Metadata', 'Temp'];
        $this->setSheetsToSkip($sheetsToSkip);

        $this->assertEquals($sheetsToSkip, $this->getSheetsToSkip());
    }

    /**
     * @test
     */
    public function test_it_can_process_dynamic_sheets_with_exclusion()
    {
        $this->setSheetNames(['Sheet1', 'Sheet2', 'Metadata', 'Data_1']);
        $this->setSheetsToSkip(['Metadata']);

        $sheets = $this->processDynamicSheets(function ($sheetName) {
            return new class($sheetName) {
                public $name;
                public function __construct($name)
                {
                    $this->name = $name;
                }
            };
        }, false);

        $this->assertCount(3, $sheets);
        $this->assertArrayHasKey('Sheet1', $sheets);
        $this->assertArrayHasKey('Sheet2', $sheets);
        $this->assertArrayHasKey('Data_1', $sheets);
        $this->assertArrayNotHasKey('Metadata', $sheets);
    }

    /**
     * @test
     */
    public function test_it_throws_exception_when_no_sheet_names_available()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('No sheet names available');

        $this->processDynamicSheets(function ($sheetName) {
            return new class {};
        }, false);
    }

    /**
     * @test
     */
    public function test_it_throws_exception_when_no_sheets_to_process()
    {
        $this->setSheetNames(['Metadata', 'Temp']);
        $this->setSheetsToSkip(['Metadata', 'Temp']);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('No sheets to process');

        $this->processDynamicSheets(function ($sheetName) {
            return new class {};
        }, false);
    }

    /**
     * @test
     */
    public function test_it_can_validate_sheet_names_patterns()
    {
        $this->setSheetNames(['Data_2024', 'Data_2023', 'Summary']);

        $this->validateSheetNames(['/^Data_/', '/^Summary$/']);

        $this->assertTrue(true); // No exception thrown
    }

    /**
     * @test
     */
    public function test_it_throws_exception_when_sheet_pattern_not_found()
    {
        $this->setSheetNames(['Data_2024', 'Data_2023']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No sheet found matching pattern');

        $this->validateSheetNames(['/^Summary$/']);
    }

    /**
     * @test
     */
    public function test_it_can_add_and_retrieve_validation_errors()
    {
        $this->addValidationError('Sheet1', ['field1' => ['Error 1']]);
        $this->addValidationError('Sheet2', ['field2' => ['Error 2']]);

        $errors = $this->getValidationErrors();

        $this->assertCount(2, $errors);
        $this->assertArrayHasKey('Sheet1', $errors);
        $this->assertArrayHasKey('Sheet2', $errors);
        $this->assertTrue($this->hasValidationErrors());
    }

    /**
     * @test
     */
    public function test_it_can_clear_validation_errors()
    {
        $this->addValidationError('Sheet1', ['field1' => ['Error 1']]);
        $this->clearValidationErrors();

        $this->assertFalse($this->hasValidationErrors());
        $this->assertEmpty($this->getValidationErrors());
    }

    /**
     * @test
     */
    public function test_it_throws_multiple_sheets_validation_exception()
    {

        $this->setSheetNames(['Sheet1', 'Sheet2']);

        $this->addValidationError('Sheet1', ['email' => ['Invalid email']]);
        $this->addValidationError('Sheet2', ['price' => ['Must be numeric']]);

        $this->expectException(MultipleSheetsValidationException::class);
        $this->expectExceptionMessage('2 error(s) across 2 sheet(s) failed validation');

        throw new MultipleSheetsValidationException($this->getValidationErrors());
    }

    /**
     * @test
     */
    public function test_it_can_retrieve_sheet_errors_from_exception()
    {
        $sheetErrors = [
            'Sheet1' => ['email' => ['Invalid email']],
            'Sheet2' => ['price' => ['Must be numeric']]
        ];

        $exception = new MultipleSheetsValidationException($sheetErrors);

        $this->assertEquals($sheetErrors, $exception->getSheetErrors());
        $this->assertCount(2, $exception->getSheetErrors());
    }
}
