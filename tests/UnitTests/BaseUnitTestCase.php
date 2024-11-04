<?php

namespace UnitTests;

use Phockito;

use ReflectionClass;
use ReflectionNamedType;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class BaseUnitTestCase extends TestCase
{
	public function setUp(): void
	{
		Phockito::include_hamcrest();
		$this->generateMocksAndSpies();
	}


	/**
 * Reflects on the current instance for any properties prefixed with "mock" or "spy".
 * Generates a mock or spy instance based on the prefix and assigns it to the property.
 */
public function generateMocksAndSpies(): void
{
    $class = new ReflectionClass($this);

    // Iterate over all properties in the class
    foreach ($class->getProperties() as $property) {
        $propertyName = $property->getName();

        // Skip if the property name doesn't start with "mock" or "spy"
        if (!(str_starts_with($propertyName, 'mock') || str_starts_with($propertyName, 'spy'))) {
            continue;
        }

        // Ignore PHPUnit's `mockObjects` property
        if ($propertyName === "mockObjects") {
            continue;
        }

        // Ensure the property has a type hint
        $type = $property->getType();
        if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
            throw new InvalidArgumentException("Property '$propertyName' must have a class type hint.");
        }

        // Get the class name from the type hint
        $classType = $type->getName();

        // Create the mock or spy based on the prefix
        $mock = str_starts_with($propertyName, 'spy')
            ? Phockito::spy($classType)
            : Phockito::mock($classType);

        // Make the property accessible and set its value
        $property->setAccessible(true);
        $property->setValue($this, $mock);
    }
}
}