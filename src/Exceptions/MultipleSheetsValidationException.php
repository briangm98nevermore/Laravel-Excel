<?php

namespace Maatwebsite\Excel\Exceptions;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\MessageBag;
use Maatwebsite\Excel\Validators\ValidationException;

class MultipleSheetsValidationException extends ValidationException
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
     * @return void
     */
    public function __construct(array $sheetErrors)
    {
        $this->sheetErrors = $sheetErrors;

        // Create a dummy validator for parent constructor
        $validator = \Illuminate\Support\Facades\Validator::make(
            ['dummy' => 'value'],
            ['dummy' => 'sometimes']
        );

        // Generate custom message
        $message = $this->generateErrorMessage();

        parent::__construct($validator, $message);

        // Set the actual errors using reflection
        $this->setErrors($this->formatErrors($sheetErrors));
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
     * Get the sheet validation errors.
     *
     * @return array
     */
    public function getSheetErrors(): array
    {
        return $this->sheetErrors;
    }

    /**
     * Format the sheet errors into a single message bag.
     *
     * @param  array  $sheetErrors
     * @return MessageBag
     */
    protected function formatErrors(array $sheetErrors): MessageBag
    {
        $errors = new MessageBag;

        foreach ($sheetErrors as $sheetName => $sheetError) {
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
     * Set the errors using reflection.
     *
     * @param  MessageBag  $errors
     * @return void
     */
    protected function setErrors(MessageBag $errors): void
    {
        $reflector = new \ReflectionClass(parent::class);
        $property = $reflector->getProperty('errors');
        $property->setAccessible(true);
        $property->setValue($this, $errors);
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
}
