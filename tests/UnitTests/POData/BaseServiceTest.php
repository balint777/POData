<?php


namespace UnitTests\POData\Common;

use PHPUnit\Framework\TestCase;

use Doctrine\Common\Annotations\Annotation\Target;
use POData\BaseService;
use POData\Common\Url;
use POData\Configuration\ProtocolVersion;
use POData\UriProcessor\RequestDescription;
use POData\UriProcessor\UriProcessor;
use POData\OperationContext\ServiceHost;
use POData\Configuration\ServiceConfiguration;
use POData\Providers\Metadata\IMetadataProvider;

use POData\Writers\ODataWriterRegistry;
use UnitTests\BaseUnitTestCase;
use POData\Writers\Json\JsonODataV1Writer;
use POData\Writers\Atom\AtomODataWriter;
use POData\Common\Version;


class BaseServiceTest extends BaseUnitTestCase {

	protected RequestDescription $mockRequest;

	protected UriProcessor $mockUriProcessor;

	protected ODataWriterRegistry $mockRegistry;

	protected IMetadataProvider $mockMetaProvider;

	protected ServiceHost $mockHost;

	public function testRegisterWritersV1()
	{
		$service = $this->getMockBuilder(BaseService::class)->onlyMethods(['initialize','getODataWriterRegistry','getConfiguration','getQueryProvider','getMetadataProvider','getStreamProviderX'])->getMock();

		$service->setHost($this->mockHost);

		//TODO: have to do this since the registry & config is actually only instantiated during a handleRequest
		//will change this once that request pipeline is cleaned up
		$service->method('getODataWriterRegistry')->willReturn($this->mockRegistry);
		$fakeConfig = new ServiceConfiguration($this->mockMetaProvider);
		$fakeConfig->setMaxDataServiceVersion(ProtocolVersion::V1);
		$service->method('getConfiguration')->willReturn($fakeConfig);
		
		//fake the service url
		$fakeUrl = "http://host/service.svc/Collection";
		$this->mockHost->method('getAbsoluteServiceUri')->willReturn(new Url($fakeUrl));

		//only 2 writers for v1
		$this->mockRegistry->expects($this->exactly(2))->method('register')->with($this->logicalOr($this->isInstanceOf(JsonODataV1Writer::class),$this->isInstanceOf(AtomODataWriter::class)));
		$service->registerWriters();
		$this->mockRegistry->expects($this->exactly(0))->method('register')->with($this->anything()); //nothing should be registered at first
	}

	public function testRegisterWritersV2()
	{
		$service = $this->getMockBuilder(BaseService::class)->onlyMethods(['initialize','getODataWriterRegistry','getConfiguration','getQueryProvider','getMetadataProvider','getStreamProviderX'])->getMock();


		$service->setHost($this->mockHost);

		//TODO: have to do this since the registry & config is actually only instantiated during a handleRequest
		//will change this once that request pipeline is cleaned up
		$service->method('getODataWriterRegistry')->willReturn($this->mockRegistry);
		$fakeConfig = new ServiceConfiguration($this->mockMetaProvider);
		$fakeConfig->setMaxDataServiceVersion(ProtocolVersion::V2);
		$service->method('getConfiguration')->willReturn($fakeConfig);

		//fake the service url
		$fakeUrl = "http://host/service.svc/Collection";
		$this->mockHost->method('getAbsoluteServiceUri')->willReturn(new Url($fakeUrl));
		
		//only 2 writers for v1
		$this->mockRegistry->expects($this->exactly(3))->method('register')->with($this->logicalOr(
			$this->isInstanceOf('\POData\Writers\Atom\AtomODataWriter'),
			$this->isInstanceOf('\POData\Writers\Json\JsonODataV1Writer'),
			$this->isInstanceOf('\POData\Writers\Json\JsonODataV2Writer')
		));

		$service->registerWriters();
		
		$this->mockRegistry->expects($this->exactly(0))->method('register')->with($this->anything()); //nothing should be registered at first

	}

	public function testRegisterWritersV3()
	{
		$service = $this->getMockBuilder(BaseService::class)->onlyMethods(['initialize','getODataWriterRegistry','getConfiguration','getQueryProvider','getMetadataProvider','getStreamProviderX'])->getMock();

		$service->setHost($this->mockHost);

		//TODO: have to do this since the registry & config is actually only instantiated during a handleRequest
		//will change this once that request pipeline is cleaned up
		$service->method('getODataWriterRegistry')->willReturn($this->mockRegistry);
		$fakeConfig = new ServiceConfiguration($this->mockMetaProvider);
		$fakeConfig->setMaxDataServiceVersion(ProtocolVersion::V3);
		$service->method('getConfiguration')->willReturn($fakeConfig);

		//fake the service url
		$fakeUrl = "http://host/service.svc/Collection";
		$this->mockHost->method('getAbsoluteServiceUri')->willReturn(new Url($fakeUrl));

		
		
		//only 2 writers for v1
		$this->mockRegistry->expects($this->exactly(6))->method('register')->with($this->logicalOr(
			$this->isInstanceOf('\POData\Writers\Atom\AtomODataWriter'),
			$this->isInstanceOf('\POData\Writers\Json\JsonODataV1Writer'),
			$this->isInstanceOf('\POData\Writers\Json\JsonODataV2Writer'),
			$this->isInstanceOf('\POData\Writers\Json\JsonLightODataWriter')
		));
		
		$service->registerWriters();
		
		$this->mockRegistry->expects($this->exactly(0))->method('register')->with($this->anything()); //nothing should be registered at first

	}



}