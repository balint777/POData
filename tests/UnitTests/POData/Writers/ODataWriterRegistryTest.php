<?php

namespace UnitTests\POData\Writers;

use POData\Common\Version;
use POData\Common\Url;
use POData\Common\MimeTypes;
use POData\Writers\ODataWriterRegistry;
use POData\UriProcessor\RequestDescription;
use POData\Writers\IODataWriter;

use UnitTests\BaseUnitTestCase;


class ODataWriterRegistryTest extends BaseUnitTestCase {

	protected IODataWriter $mockWriter1;

	protected IODataWriter $mockWriter2;

	public function testConstructor()
	{
		$registry = new ODataWriterRegistry();
		//the registry should start empty, so there should be no matches
		$this->assertNull($registry->getWriter(Version::v1(), MimeTypes::MIME_APPLICATION_JSON));
	}


	public function testRegister()
	{
		$registry = new ODataWriterRegistry();

		$registry->register($this->mockWriter1);
		$registry->register($this->mockWriter2);

		$this->mockWriter2->method('canHandle')->with(Version::v1(), MimeTypes::MIME_APPLICATION_ATOM)
			->willReturn(true);

		$this->assertEquals($this->mockWriter2, $registry->getWriter(Version::v2(), MimeTypes::MIME_APPLICATION_ATOM));

		$registry->reset();
		$this->assertNull($registry->getWriter(Version::v1(), MimeTypes::MIME_APPLICATION_ATOM));

		//now clear it, should be no matches
		$registry->reset();
		$this->assertNull( $registry->getWriter(Version::v2(), MimeTypes::MIME_APPLICATION_ATOM));
	}

}
