<?php

declare(strict_types=1);
include_once __DIR__ . '/stubs/Validator.php';
class GeofencyValidationTest extends TestCaseSymconValidation
{
    public function testValidateGeofency(): void
    {
        $this->validateLibrary(__DIR__ . '/..');
    }
    public function testValidateGeofencyModule(): void
    {
        $this->validateModule(__DIR__ . '/../Geofency');
    }
}