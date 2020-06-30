<?php

namespace UnitTests;

use Phockito;

use PhpDocReader\PhpDocReader;
use ReflectionClass;
use PHPUnit\Framework\TestCase;

class BaseUnitTestCase extends TestCase
{
	public function setUp()
	{
		Phockito::include_hamcrest();
		$this->generateMocksAndSpies();
	}


	/**
	 * Reflects on the current instance for any members prefixed with the name mock or spy
	 * For each found it reflects on the php doc comment for the type and then generates a mock or spy instance
	 * and sets the member to that instance.
	 */
	public function generateMocksAndSpies()
	{
		$parser = new PhpDocReader();

		$class = new ReflectionClass($this);

		//Find every member that begins with "mock" or "spy"
		foreach ($class->getProperties() as $property) {

			if (strpos($property->name, 'mock') === 0 || strpos($property->name, 'spy') === 0) {
				if($property->name == "mockObjects"){
					//This is inherited from PHPUnit_Framework_TestCase and we can't mock it
					continue;
				}

				$classType = $parser->getPropertyType( $property);

				//Create the mock and assign it to the member
				if($property->name[0] === 's'){
					$mock = Phockito::spy($classType);
				}
				else{
					$mock = Phockito::mock($classType);
				}

				$property->setAccessible(true);
				$property->setValue( $this, $mock );

			}

		}

	}
}