// src/Exceptions/MultipleSheetsValidationException.php
<?php

namespace Maatwebsite\Excel\Exceptions;

use Exception;
use Illuminate\Support\MessageBag;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use Throwable;

class MultipleSheetsValidationException extends Exception implements Arrayable, JsonSerializable, LaravelExcelException
{
    /**
     * The validation errors for each sheet.
     *
     * @var array
     */
    protected $sheetErrors;

    /**
     * Create a new exception instance.
     *
     * @param  array  $sheetErrors
     * @param  string|null  $message
     * @param  int  $code
     * @param  Throwable|null  $previous
     * @return void
     */
    public function __construct(array $sheetErrors, ?string $message = null, int $code = 0, ?Throwable $previous = null)
    {
        $this->sheetErrors = $sheetErrors;

        $message = $message ?: $this->generateErrorMessage();

        parent::__construct($message, $code, $previous);
    }

    /**
     * Get the sheet validation errors.
     *
     * @return array
     */
    public function getSheetErrors(): array
    {
        return $this->sheetErrors;
    }

    /**
     * Get all errors as a single message bag.
     *
     * @return MessageBag
     */
    public function errors(): MessageBag
    {
        $errors = new MessageBag;

        foreach ($this->sheetErrors as $sheetName => $sheetError) {
            foreach ($sheetError as $field => $messages) {
                if (is_array($messages)) {
                    foreach ($messages as $message) {
                        $errors->add("{$sheetName}.{$field}", $message);
                    }
                } else {
                    $errors->add("{$sheetName}.{$field}", $messages);
                }
            }
        }

        return $errors;
    }

    /**
     * Generate the error message.
     */
    protected function generateErrorMessage(): string
    {
        $sheetCount = count($this->sheetErrors);
        $errorCount = 0;

        foreach ($this->sheetErrors as $errors) {
            $errorCount += count($errors);
        }

        return "{$errorCount} error(s) across {$sheetCount} sheet(s) failed validation.";
    }

    /**
     * Get the exception's context information.
     *
     * @return array
     */
    public function context(): array
    {
        return ['sheet_errors' => $this->sheetErrors];
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray(): array
    {
        return $this->sheetErrors;
    }

    /**
     * Convert the object into something JSON serializable.
     *
     * @return array
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Convert the exception to a string representation.
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->getMessage() . "\n" . json_encode($this->sheetErrors, JSON_PRETTY_PRINT);
    }
}
