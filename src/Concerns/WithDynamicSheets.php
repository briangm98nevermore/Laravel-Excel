<?php

namespace Maatwebsite\Excel\Concerns;

use Maatwebsite\Excel\Validators\ValidationException;
use Maatwebsite\Excel\Exceptions\MultipleSheetsValidationException;
use Illuminate\Support\Collection;

trait WithDynamicSheets
{
    /**
     * Sheet names from the Excel file.
     */
    protected array $sheetNames = [];

    /**
     * Sheets to skip during processing.
     */
    protected array $sheetsToSkip = [];

    /**
     * Validation errors across all sheets.
     */
    protected array $validationErrors = [];

    /**
     * Whether to validate all sheets before importing.
     */
    protected bool $validateAllSheetsFirst = true;

    /**
     * Set the sheet names from the event.
     */
    public function setSheetNames(array $sheetNames): void
    {
        $this->sheetNames = $sheetNames;
    }

    /**
     * Get the sheet names.
     */
    public function getSheetNames(): array
    {
        return $this->sheetNames;
    }

    /**
     * Set sheets to skip.
     */
    public function setSheetsToSkip(array $sheetsToSkip): void
    {
        $this->sheetsToSkip = $sheetsToSkip;
    }

    /**
     * Get sheets to skip.
     */
    public function getSheetsToSkip(): array
    {
        return $this->sheetsToSkip;
    }

    /**
     * Enable/disable validation of all sheets before import.
     */
    public function setValidateAllSheetsFirst(bool $validateFirst): void
    {
        $this->validateAllSheetsFirst = $validateFirst;
    }

    /**
     * Add validation error for a specific sheet.
     */
    public function addValidationError(string $sheetName, array $errors): void
    {
        if (!isset($this->validationErrors[$sheetName])) {
            $this->validationErrors[$sheetName] = [];
        }

        $this->validationErrors[$sheetName] = array_merge(
            $this->validationErrors[$sheetName],
            $errors
        );
    }

    /**
     * Get all validation errors.
     */
    public function getValidationErrors(): array
    {
        return $this->validationErrors;
    }

    /**
     * Check if there are any validation errors.
     */
    public function hasValidationErrors(): bool
    {
        return !empty($this->validationErrors);
    }

    /**
     * Clear all validation errors.
     */
    public function clearValidationErrors(): void
    {
        $this->validationErrors = [];
    }

    /**
     * Process sheets dynamically with advanced features.
     */
    public function processDynamicSheets(callable $importCreator, ?bool $validateFirst = null): array
    {
        $validateFirst = $validateFirst ?? $this->validateAllSheetsFirst;

        if (empty($this->sheetNames)) {
            throw new \LogicException('No sheet names available. Did you register the BeforeImport event?');
        }

        $sheetsToProcess = array_diff($this->sheetNames, $this->sheetsToSkip);

        if (empty($sheetsToProcess)) {
            throw new \LogicException('No sheets to process after applying filters.');
        }

        $sheets = [];

        // First pass: validate all sheets if enabled
        if ($validateFirst) {
            $this->clearValidationErrors();

            foreach ($sheetsToProcess as $sheetName) {
                try {
                    $sheetImport = $importCreator($sheetName);

                    // If the sheet import has validation rules, validate it
                    if (method_exists($sheetImport, 'rules') || method_exists($sheetImport, 'withValidator')) {
                        // Create a fake row to trigger validation
                        $fakeData = [['test' => 'data']];

                        if (method_exists($sheetImport, 'withValidator')) {
                            $validator = \Illuminate\Support\Facades\Validator::make(
                                ['rows' => $fakeData],
                                method_exists($sheetImport, 'rules') ? $sheetImport->rules() : []
                            );

                            $sheetImport->withValidator($validator);
                            $validator->validate();
                        } elseif (method_exists($sheetImport, 'rules')) {
                            \Illuminate\Support\Facades\Validator::make(
                                ['rows' => $fakeData],
                                $sheetImport->rules()
                            )->validate();
                        }
                    }
                } catch (ValidationException $e) {
                    $this->addValidationError($sheetName, $e->errors());
                } catch (\Exception $e) {
                    $this->addValidationError($sheetName, ['general' => $e->getMessage()]);
                }
            }

            // If there are validation errors, throw them all together
            if ($this->hasValidationErrors()) {
                throw new MultipleSheetsValidationException($this->validationErrors);
            }
        }

        // Second pass: create imports for processing
        foreach ($sheetsToProcess as $sheetName) {
            $sheets[$sheetName] = $importCreator($sheetName);
        }

        return $sheets;
    }

    /**
     * Validate sheet names against required patterns.
     */
    public function validateSheetNames(array $requiredPatterns = []): void
    {
        foreach ($requiredPatterns as $pattern) {
            $found = false;
            foreach ($this->sheetNames as $sheetName) {
                if (preg_match($pattern, $sheetName)) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                throw new \RuntimeException("No sheet found matching pattern: {$pattern}");
            }
        }
    }

    /**
     * Get sheets that match a pattern.
     */
    public function getSheetsMatchingPattern(string $pattern): array
    {
        return array_filter($this->sheetNames, function ($sheetName) use ($pattern) {
            return preg_match($pattern, $sheetName);
        });
    }

    /**
     * Get sheets that don't match a pattern.
     */
    public function getSheetsNotMatchingPattern(string $pattern): array
    {
        return array_filter($this->sheetNames, function ($sheetName) use ($pattern) {
            return !preg_match($pattern, $sheetName);
        });
    }

    /**
     * Get sheet names as a collection for easier manipulation.
     */
    public function getSheetNamesCollection(): Collection
    {
        return Collection::make($this->sheetNames);
    }
}
