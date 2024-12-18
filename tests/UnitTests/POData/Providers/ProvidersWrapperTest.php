<?php

namespace UnitTests\POData\Providers\Metadata;

use POData\Providers\Metadata\ResourceSet;
use POData\Providers\Metadata\ResourceSetWrapper;
use POData\Providers\Metadata\ResourceType;
use POData\Providers\Metadata\ResourceProperty;
use POData\Providers\Metadata\ResourceTypeKind;
use POData\Providers\ProvidersWrapper;
use POData\Configuration\ServiceConfiguration;
use POData\Configuration\EntitySetRights;
use POData\Providers\Metadata\IMetadataProvider;
use POData\Common\ODataException;
use POData\Common\Messages;
use POData\Providers\Metadata\Type\StringType;
use POData\Common\InvalidOperationException;
use POData\Providers\Metadata\ResourceAssociationSet;
use POData\Providers\Metadata\ResourceAssociationSetEnd;
use POData\Providers\Query\IQueryProvider;
use POData\Providers\Query\QueryType;
use POData\UriProcessor\QueryProcessor\ExpressionParser\FilterInfo;
use POData\Providers\Query\QueryResult;


use POData\UriProcessor\RequestDescription;
use UnitTests\BaseUnitTestCase;

class ProvidersWrapperTest extends BaseUnitTestCase
{
 
	protected IQueryProvider $mockQueryProvider;

	protected IMetadataProvider $mockMetadataProvider;

	protected ServiceConfiguration $mockServiceConfig;

	protected ResourceSet $mockResourceSet;

	protected RequestDescription $mockRequest;

	protected ResourceSet $mockResourceSet2;

	protected ResourceType $mockResourceType;

    protected ResourceType $mockResourceType2;

    protected ResourceAssociationSet $mockResourceAssociationSet;

    protected ResourceProperty $mockResourceProperty;

    protected ResourceAssociationSetEnd $mockResourceAssociationSetEnd;
 
	public function getMockedWrapper() : ProvidersWrapper
	{
        $wrapper = new ProvidersWrapper(
			$this->mockMetadataProvider,
			$this->mockQueryProvider,
			$this->mockServiceConfig
		);
		return $wrapper;
	}

    public function testGetContainerName()
    {

	    $fakeContainerName = "BigBadContainer";
        $this->mockMetadataProvider->method('getContainerName')->willReturn($fakeContainerName);
        $wrapper = $this->getMockedWrapper();
        
        // $wrapper->expects($this->once())->method('getContainerName')->willReturn($fakeContainerName);
        $wrapper->getContainerName();
        $this->assertEquals($fakeContainerName, $wrapper->getContainerName());

    }

	public function testGetContainerNameThrowsWhenNull()
	{


		$wrapper = $this->getMockedWrapper();

		try{
			$wrapper->getContainerName();
			$this->fail("Expected exception not thrown");
		} catch(ODataException $ex) {
			$this->assertEquals(Messages::providersWrapperContainerNameMustNotBeNullOrEmpty(), $ex->getMessage());
			$this->assertEquals(500, $ex->getStatusCode());
		}

	}

	public function testGetContainerNameThrowsWhenEmpty()
	{

        $this->mockMetadataProvider->method('getContainerName')->willReturn('');
		$wrapper = $this->getMockedWrapper();

		try{
			$wrapper->getContainerName();
			$this->fail("Expected exception not thrown");
		} catch(ODataException $ex) {
			$this->assertEquals(Messages::providersWrapperContainerNameMustNotBeNullOrEmpty(), $ex->getMessage());
			$this->assertEquals(500, $ex->getStatusCode());
		}

	}

	public function testGetContainerNamespace()
	{
		$fakeContainerNamespace = "BigBadNamespace";
        $this->mockMetadataProvider->method('getContainerNamespace')->willReturn($fakeContainerNamespace);
		
		$wrapper = $this->getMockedWrapper();

		$this->assertEquals($fakeContainerNamespace, $wrapper->getContainerNamespace());

	}

	public function testGetContainerNamespaceThrowsWhenNull()
	{


		$wrapper = $this->getMockedWrapper();

		try{
			$wrapper->getContainerNamespace();
			$this->fail("Expected exception not thrown");
		} catch(ODataException $ex) {
			$this->assertEquals(Messages::providersWrapperContainerNamespaceMustNotBeNullOrEmpty(), $ex->getMessage());
			$this->assertEquals(500, $ex->getStatusCode());
		}

	}

	public function testGetContainerNamespaceThrowsWhenEmpty()
	{

        $this->mockMetadataProvider->method('getContainerNamespace')->willReturn('');
		
		$wrapper = $this->getMockedWrapper();

		try{
			$wrapper->getContainerNamespace();
			$this->fail("Expected exception not thrown");
		} catch(ODataException $ex) {
			$this->assertEquals(Messages::providersWrapperContainerNamespaceMustNotBeNullOrEmpty(), $ex->getMessage());
			$this->assertEquals(500, $ex->getStatusCode());
		}

	}

	public function testResolveResourceSet()
	{
		$fakeSetName = 'SomeSet';
        $this->mockMetadataProvider->method('resolveResourceSet')->with($fakeSetName)
            ->willReturn($this->mockResourceSet);

        $this->mockResourceSet->method('getResourceType')->willReturn($this->mockResourceType);

		//Indicate the resource set is visible
        $this->mockServiceConfig->method('getEntitySetAccessRule')->with($this->mockResourceSet)
            ->willReturn(EntitySetRights::READ_SINGLE);


		$wrapper = $this->getMockedWrapper();

		$actual = $wrapper->resolveResourceSet($fakeSetName);

		$this->assertEquals(new ResourceSetWrapper($this->mockResourceSet, $this->mockServiceConfig), $actual);

		//Verify it comes from cache
		$actual2 = $wrapper->resolveResourceSet($fakeSetName);
		$this->assertSame($actual, $actual2);

	}

	public function testResolveResourceSetNotVisible()
	{
		$fakeSetName = 'SomeSet';

		$this->mockMetadataProvider->method('resolveResourceSet')->with($fakeSetName)
			->willReturn($this->mockResourceSet);


		$this->mockResourceSet->method('getResourceType')
			->willReturn($this->mockResourceType);

		//Indicate the resource set is NOT visible
		$this->mockServiceConfig->method('getEntitySetAccessRule')->with($this->mockResourceSet)
			->willReturn(EntitySetRights::NONE);

		$this->mockResourceSet->method('getName')
			->willReturn($fakeSetName);

		$wrapper = $this->getMockedWrapper();

        //make sure the metadata provider was only called once
        $this->mockMetadataProvider->expects($this->exactly(1))->method('resolveResourceSet')->with($fakeSetName);

		$this->assertNull($wrapper->resolveResourceSet($fakeSetName));

		//verify it comes from cache
		$wrapper->resolveResourceSet($fakeSetName); //call it again


	}

	public function testResolveResourceSetNonExistent()
	{
		$fakeSetName = 'SomeSet';

		$this->mockMetadataProvider->method('resolveResourceSet')->with($fakeSetName)
			->willReturn(null);

		$wrapper = $this->getMockedWrapper();

		$this->assertNull($wrapper->resolveResourceSet($fakeSetName));

	}


    public function testResolveResourceTypeNonExistent()
    {

        $fakeTypeName = 'SomeType';

        $this->mockMetadataProvider->method('resolveResourceType')->with($fakeTypeName)
            ->willReturn(null);

        $wrapper = $this->getMockedWrapper();

        $this->assertNull($wrapper->resolveResourceType($fakeTypeName));

    }


    public function testResolveResourceType()
    {

        $fakeTypeName = 'SomeType';

        $this->mockMetadataProvider->method('resolveResourceType')->with($fakeTypeName)
            ->willReturn($this->mockResourceType);

        $wrapper = $this->getMockedWrapper();

        $actual = $wrapper->resolveResourceType($fakeTypeName);

        $this->assertEquals($this->mockResourceType, $actual);

    }


    public function testGetDerivedTypesNonArrayReturnedThrows()
    {
        $fakeName = "FakeType";

        $this->mockMetadataProvider->method('getDerivedTypes')->with($this->mockResourceType)
            ->willReturn($this->mockResourceType);

        $this->mockResourceType->method('getName')
            ->willReturn($fakeName);

        $wrapper = $this->getMockedWrapper();

        try{
            $wrapper->getDerivedTypes($this->mockResourceType);
            $this->fail("Expected exception not thrown");
        } catch(InvalidOperationException $ex) {
            $this->assertEquals(Messages::metadataAssociationTypeSetInvalidGetDerivedTypesReturnType($fakeName),$ex->getMessage());

        }

    }

    public function testGetDerivedTypes()
    {
        $fakeName = "FakeType";

        $this->mockMetadataProvider->method('getDerivedTypes')->with($this->mockResourceType)
            ->willReturn(array($this->mockResourceType2));

        $this->mockResourceType->method('getName')
            ->willReturn($fakeName);

        $wrapper = $this->getMockedWrapper();

        $actual = $wrapper->getDerivedTypes($this->mockResourceType);
        $this->assertEquals(array($this->mockResourceType2), $actual);

    }

    public function testHasDerivedTypes()
    {

        $this->mockMetadataProvider->method('hasDerivedTypes')->with($this->mockResourceType)
            ->willReturn(true);

        $wrapper = $this->getMockedWrapper();

        $this->assertTrue($wrapper->hasDerivedTypes($this->mockResourceType));

    }

    public function testGetResourceAssociationSet()
    {
        $fakePropName = "Fake Prop";
        $this->mockResourceProperty->method('getName')
            ->willReturn($fakePropName);


        $this->mockResourceType->method('resolvePropertyDeclaredOnThisType')->with($fakePropName)
            ->willReturn($this->mockResourceProperty);

        $fakeTypeName = "Fake Type";
        $this->mockResourceType->method('getName')
            ->willReturn($fakeTypeName);

        $fakeSetName = "Fake Set";
        $this->mockResourceSet->method('getName')
            ->willReturn($fakeSetName);

        $this->mockResourceSet->method('getResourceType')
            ->willReturn($this->mockResourceType);

        $this->mockResourceSet2->method('getResourceType')
            ->willReturn($this->mockResourceType2);

        //Indicate the resource set is visible
        $this->mockServiceConfig->method('getEntitySetAccessRule')->with($this->mockResourceSet)
            ->willReturn(EntitySetRights::READ_SINGLE);

        //Indicate the resource set is visible
        $this->mockServiceConfig->method('getEntitySetAccessRule')->with($this->mockResourceSet2)
            ->willReturn(EntitySetRights::READ_SINGLE);

        $this->mockMetadataProvider->method('getResourceAssociationSet')->with($this->mockResourceSet, $this->mockResourceType, $this->mockResourceProperty)
            ->willReturn($this->mockResourceAssociationSet);


        $this->mockResourceAssociationSet->method('getResourceAssociationSetEnd')->with($this->mockResourceSet, $this->mockResourceType, $this->mockResourceProperty)
            ->willReturn($this->mockResourceAssociationSetEnd);

        $this->mockResourceAssociationSet->method('getRelatedResourceAssociationSetEnd')->with($this->mockResourceSet, $this->mockResourceType, $this->mockResourceProperty)
            ->willReturn($this->mockResourceAssociationSetEnd);

        $this->mockResourceAssociationSetEnd->method('getResourceSet')
            ->willReturn($this->mockResourceSet2);

        $this->mockResourceAssociationSetEnd->method('getResourceType')
            ->willReturn($this->mockResourceType2);

        $wrapper = $this->getMockedWrapper();

        $actual = $wrapper->getResourceAssociationSet($this->mockResourceSet, $this->mockResourceType, $this->mockResourceProperty);

        $this->assertEquals($this->mockResourceAssociationSet, $actual);

    }


    public function testGetResourceAssociationSetEndIsNotVisible()
    {
        $fakePropName = "Fake Prop";
        $this->mockResourceProperty->method('getName')
            ->willReturn($fakePropName);


        $this->mockResourceType->method('resolvePropertyDeclaredOnThisType')->with($fakePropName)
            ->willReturn($this->mockResourceProperty);

        $fakeTypeName = "Fake Type";
        $this->mockResourceType->method('getName')
            ->willReturn($fakeTypeName);

        $fakeSetName = "Fake Set";
        $this->mockResourceSet->method('getName')
            ->willReturn($fakeSetName);

        $this->mockResourceSet->method('getResourceType')
            ->willReturn($this->mockResourceType);

        $this->mockResourceSet2->method('getResourceType')
            ->willReturn($this->mockResourceType2);

        //Indicate the resource set is visible
        $this->mockServiceConfig->method('getEntitySetAccessRule')->with($this->mockResourceSet)
            ->willReturn(EntitySetRights::NONE);

        //Indicate the resource set is visible
        $this->mockServiceConfig->method('getEntitySetAccessRule')->with($this->mockResourceSet2)
            ->willReturn(EntitySetRights::READ_SINGLE);

        $this->mockMetadataProvider->method('getResourceAssociationSet')->with($this->mockResourceSet, $this->mockResourceType, $this->mockResourceProperty)
            ->willReturn($this->mockResourceAssociationSet);


        $this->mockResourceAssociationSet->method('getResourceAssociationSetEnd')->with($this->mockResourceSet, $this->mockResourceType, $this->mockResourceProperty)
            ->willReturn($this->mockResourceAssociationSetEnd);

        $this->mockResourceAssociationSet->method('getRelatedResourceAssociationSetEnd')->with($this->mockResourceSet, $this->mockResourceType, $this->mockResourceProperty)
            ->willReturn($this->mockResourceAssociationSetEnd);

        $this->mockResourceAssociationSetEnd->method('getResourceSet')
            ->willReturn($this->mockResourceSet2);

        $this->mockResourceAssociationSetEnd->method('getResourceType')
            ->willReturn($this->mockResourceType2);

        $wrapper = $this->getMockedWrapper();

        $actual = $wrapper->getResourceAssociationSet($this->mockResourceSet, $this->mockResourceType, $this->mockResourceProperty);

        $this->assertNull($actual);

    }

	public function testGetResourceSets()
	{
		$fakeSets = array(
			$this->mockResourceSet,
		);

		$this->mockMetadataProvider->method('getResourceSets')
			->willReturn($fakeSets);

		$this->mockResourceSet->method('getResourceType')
			->willReturn($this->mockResourceType);

		$this->mockServiceConfig->method('getEntitySetAccessRule')->with($this->mockResourceSet)
			->willReturn(EntitySetRights::READ_SINGLE);

		$wrapper = $this->getMockedWrapper();

		$actual = $wrapper->getResourceSets();


		$expected = array(
			new ResourceSetWrapper($this->mockResourceSet, $this->mockServiceConfig)
		);
		$this->assertEquals($expected, $actual);

	}

	public function testGetResourceSetsDuplicateNames()
	{
		$fakeSets = array(
			$this->mockResourceSet,
			$this->mockResourceSet,
		);

		$this->mockMetadataProvider->method('getResourceSets')
			->willReturn($fakeSets);

		$this->mockResourceSet->method('getResourceType')
			->willReturn($this->mockResourceType);

		$fakeName = "Fake Set 1";
		$this->mockResourceSet->method('getName')
			->willReturn($fakeName);

		$this->mockServiceConfig->method('getEntitySetAccessRule')->with($this->mockResourceSet)
			->willReturn(EntitySetRights::READ_SINGLE);

		$wrapper = $this->getMockedWrapper();

		try{
			$wrapper->getResourceSets();
			$this->fail('An expected ODataException for entity set repetition has not been thrown');
		} catch(ODataException $exception) {
			$this->assertEquals(Messages::providersWrapperEntitySetNameShouldBeUnique($fakeName), $exception->getMessage());
			$this->assertEquals(500, $exception->getStatusCode());
		}
	}

	public function testGetResourceSetsSecondOneIsNotVisible()
	{

        $this->mockResourceSet->method('getName')
			->willReturn("fake name 1");

		$this->mockResourceSet2->method('getName')
			->willReturn("fake name 2");

		$this->mockResourceSet->method('getResourceType')
			->willReturn($this->mockResourceType);

		$this->mockResourceSet2->method('getResourceType')
			->willReturn($this->mockResourceType2);

        $this->mockServiceConfig->method('getEntitySetAccessRule')
            ->willReturnMap([
                [$this->mockResourceSet, EntitySetRights::READ_SINGLE],   // First parameter set, with its return value
                [$this->mockResourceSet2, (int) EntitySetRights::NONE],   // Second parameter set, with its return value
            ]);

		$wrapper = $this->getMockedWrapper();

        $fakeSets = array(
			$this->mockResourceSet,
			$this->mockResourceSet2,
		);

		$this->mockMetadataProvider->method('getResourceSets')
			->willReturn($fakeSets);

		$actual = $wrapper->getResourceSets();


		$expected = array(
			new ResourceSetWrapper($this->mockResourceSet, $this->mockServiceConfig)
		);

		$this->assertEquals($expected, $actual);

	}

	public function testGetTypes()
	{
		$fakeTypes = array(
			new ResourceType(new StringType(), ResourceTypeKind::PRIMITIVE, "FakeType1" ),
		);

		$this->mockMetadataProvider->method('getTypes')
			->willReturn($fakeTypes);

		$wrapper = $this->getMockedWrapper();

		$this->assertEquals($fakeTypes, $wrapper->getTypes());

	}

	public function testGetTypesDuplicateNames()
	{
		$fakeTypes = array(
			new ResourceType(new StringType(), ResourceTypeKind::PRIMITIVE, "FakeType1" ),
			new ResourceType(new StringType(), ResourceTypeKind::PRIMITIVE, "FakeType1" ),
		);

		$this->mockMetadataProvider->method('getTypes')
			->willReturn($fakeTypes);

		$wrapper = $this->getMockedWrapper();

		try {
			$wrapper->getTypes();
			$this->fail('An expected ODataException for entity type name repetition has not been thrown');
		} catch(ODataException $exception) {
			$this->assertEquals(Messages::providersWrapperEntityTypeNameShouldBeUnique("FakeType1"), $exception->getMessage());
			$this->assertEquals(500, $exception->getStatusCode());
		}

	}

    protected FilterInfo $mockFilterInfo;

    public function testGetResourceSetJustEntities()
    {
        $orderBy = null;
        $top = 10;
        $skip = 10;


        $fakeQueryResult = new QueryResult();
        $fakeQueryResult->results = array();

        $this->mockQueryProvider->method('getResourceSet')->with(
            QueryType::ENTITIES,
            $this->mockResourceSet,
			$this->mockRequest,
	        $this->mockFilterInfo,
            $orderBy,
            $top,
            $skip
        )->willReturn($fakeQueryResult);

        $wrapper = $this->getMockedWrapper();

        $actual = $wrapper->getResourceSet(
            QueryType::ENTITIES,
            $this->mockResourceSet,
			$this->mockRequest,
            $this->mockFilterInfo,
            $orderBy,
            $top,
            $skip
        );
        $this->assertEquals($fakeQueryResult, $actual);

    }


    public function testGetResourceSetReturnsNonQueryResult()
    {
        $orderBy = null;
        $top = 10;
        $skip = 10;


        $this->mockQueryProvider->method('getResourceSet')->with(
            QueryType::ENTITIES,
            $this->mockResourceSet,
			$this->mockRequest,
            $this->mockFilterInfo,
            $orderBy,
            $top,
            $skip
        )->willReturn(null);

        $wrapper = $this->getMockedWrapper();

        try{
            $wrapper->getResourceSet(
                QueryType::ENTITIES,
                $this->mockResourceSet,
				$this->mockRequest,
                $this->mockFilterInfo,
                $orderBy,
                $top,
                $skip
            );
            $this->fail("expected exception not thrown");
        }
        catch(ODataException $ex){
            $this->assertEquals(Messages::queryProviderReturnsNonQueryResult("IQueryProvider::getResourceSet"), $ex->getMessage());
            $this->assertEquals(500, $ex->getStatusCode());
        }

    }


	public function testGetResourceSetReturnsCountWhenQueryTypeIsCountProviderDoesNotHandlePaging()
	{
		$orderBy = null;
		$top = 10;
		$skip = 10;

		$fakeQueryResult = new QueryResult();
		$fakeQueryResult->count = 123; //this is irrelevant
		$fakeQueryResult->results = null;

		//Because the provider doe NOT handle paging and this request needs a count, there must be results to calculate a count from
		$this->mockQueryProvider->method('handlesOrderedPaging')
			->willReturn(false);

		$this->mockQueryProvider->method('getResourceSet')->with(
			QueryType::COUNT,
			$this->mockResourceSet,
			$this->mockRequest,
			$this->mockFilterInfo,
			$orderBy,
			$top,
			$skip
		)->willReturn($fakeQueryResult);

		$wrapper = $this->getMockedWrapper();

		try{
			$wrapper->getResourceSet(
				QueryType::COUNT,
				$this->mockResourceSet,
				$this->mockRequest,
				$this->mockFilterInfo,
				$orderBy,
				$top,
				$skip
			);
			$this->fail("expected exception not thrown");
		}
		catch(ODataException $ex){
			$this->assertEquals(Messages::queryProviderResultsMissing("IQueryProvider::getResourceSet", QueryType::COUNT), $ex->getMessage());
			$this->assertEquals(500, $ex->getStatusCode());
		}

	}

    public function testGetResourceSetReturnsCountWhenQueryTypeIsCountProviderHandlesPaging()
    {
        $orderBy = null;
        $top = 10;
        $skip = 10;

        $fakeQueryResult = new QueryResult();
        $fakeQueryResult->count = null; //null is not numeric

	    //Because the provider handles paging and this request needs a count, the count must be numeric
	    $this->mockQueryProvider->method('handlesOrderedPaging')
		    ->willReturn(true);

        $this->mockQueryProvider->method('getResourceSet')->with(
            QueryType::COUNT,
            $this->mockResourceSet,
			$this->mockRequest,
	        $this->mockFilterInfo,
            $orderBy,
            $top,
            $skip
        )->willReturn($fakeQueryResult);

        $wrapper = $this->getMockedWrapper();

        try{
            $wrapper->getResourceSet(
                QueryType::COUNT,
                $this->mockResourceSet,
				$this->mockRequest,
                $this->mockFilterInfo,
                $orderBy,
                $top,
                $skip
            );
            $this->fail("expected exception not thrown");
        }
        catch(ODataException $ex){
            $this->assertEquals(Messages::queryProviderResultCountMissing("IQueryProvider::getResourceSet", QueryType::COUNT), $ex->getMessage());
            $this->assertEquals(500, $ex->getStatusCode());
        }

    }

    public function testGetResourceSetReturnsCountWhenQueryTypeIsEntitiesWithCountProviderHandlesPaging()
    {
        $orderBy = null;
        $top = 10;
        $skip = 10;


        $fakeQueryResult = new QueryResult();
        $fakeQueryResult->count = null; //null is not numeric

	    //Because the provider handles paging and this request needs a count, the count must be numeric
	    $this->mockQueryProvider->method('handlesOrderedPaging')
		    ->willReturn(true);

        $this->mockQueryProvider->method('getResourceSet')->with(
            QueryType::ENTITIES_WITH_COUNT,
            $this->mockResourceSet,
			$this->mockRequest,
	        $this->mockFilterInfo,
            $orderBy,
            $top,
            $skip
        )->willReturn($fakeQueryResult);

        $wrapper = $this->getMockedWrapper();

        try{
            $wrapper->getResourceSet(
                QueryType::ENTITIES_WITH_COUNT,
                $this->mockResourceSet,
				$this->mockRequest,
                $this->mockFilterInfo,
                $orderBy,
                $top,
                $skip
            );
            $this->fail("expected exception not thrown");
        }
        catch(ODataException $ex){
            $this->assertEquals(Messages::queryProviderResultCountMissing("IQueryProvider::getResourceSet", QueryType::ENTITIES_WITH_COUNT), $ex->getMessage());
            $this->assertEquals(500, $ex->getStatusCode());
        }

    }

	public function testGetResourceSetReturnsCountWhenQueryTypeIsEntitiesWithCountProviderDoesNotHandlePaging()
	{
		$orderBy = null;
		$top = 10;
		$skip = 10;


		$fakeQueryResult = new QueryResult();
		$fakeQueryResult->count = 444; //irrelevant
		$fakeQueryResult->results = null;

		//Because the provider does NOT handle paging and this request needs a count, the result must have results collection to calculate count from
		$this->mockQueryProvider->method('handlesOrderedPaging')
			->willReturn(false);

		$this->mockQueryProvider->method('getResourceSet')->with(
			QueryType::ENTITIES_WITH_COUNT,
			$this->mockResourceSet,
			$this->mockRequest,
			$this->mockFilterInfo,
			$orderBy,
			$top,
			$skip
		)->willReturn($fakeQueryResult);

		$wrapper = $this->getMockedWrapper();

		try{
			$wrapper->getResourceSet(
				QueryType::ENTITIES_WITH_COUNT,
				$this->mockResourceSet,
				$this->mockRequest,
				$this->mockFilterInfo,
				$orderBy,
				$top,
				$skip
			);
			$this->fail("expected exception not thrown");
		}
		catch(ODataException $ex){
			$this->assertEquals(Messages::queryProviderResultsMissing("IQueryProvider::getResourceSet", QueryType::ENTITIES_WITH_COUNT), $ex->getMessage());
			$this->assertEquals(500, $ex->getStatusCode());
		}

	}


    public function testGetResourceSetReturnsArrayWhenQueryTypeIsEntities()
    {
        $orderBy = null;
        $top = 10;
        $skip = 10;


        $fakeQueryResult = new QueryResult();
        $fakeQueryResult->count = 2;
        $fakeQueryResult->results = null; //null is not an array

        $this->mockQueryProvider->method('getResourceSet')->with(
            QueryType::ENTITIES,
            $this->mockResourceSet,
			$this->mockRequest,
	        $this->mockFilterInfo,
            $orderBy,
            $top,
            $skip
        )->willReturn($fakeQueryResult);

        $wrapper = $this->getMockedWrapper();

        try{
            $wrapper->getResourceSet(
                QueryType::ENTITIES,
                $this->mockResourceSet,
				$this->mockRequest,
                $this->mockFilterInfo,
                $orderBy,
                $top,
                $skip
            );
            $this->fail("expected exception not thrown");
        }
        catch(ODataException $ex){
            $this->assertEquals(Messages::queryProviderResultsMissing("IQueryProvider::getResourceSet", QueryType::ENTITIES), $ex->getMessage());
            $this->assertEquals(500, $ex->getStatusCode());
        }

    }

    public function testGetResourceSetReturnsArrayWhenQueryTypeIsEntitiesWithCount()
    {
        $orderBy = null;
        $top = 10;
        $skip = 10;


        $fakeQueryResult = new QueryResult();
        $fakeQueryResult->count = 4;
        $fakeQueryResult->results = null; //null is not an array

        $this->mockQueryProvider->method('getResourceSet')->with(
            QueryType::ENTITIES_WITH_COUNT,
            $this->mockResourceSet,
			$this->mockRequest,
	        $this->mockFilterInfo,
            $orderBy,
            $top,
            $skip
        )->willReturn($fakeQueryResult);

        $wrapper = $this->getMockedWrapper();

        try{
            $wrapper->getResourceSet(
                QueryType::ENTITIES_WITH_COUNT,
                $this->mockResourceSet,
				$this->mockRequest,
                $this->mockFilterInfo,
                $orderBy,
                $top,
                $skip
            );
            $this->fail("expected exception not thrown");
        }
        catch(ODataException $ex){
            $this->assertEquals(Messages::queryProviderResultsMissing("IQueryProvider::getResourceSet", QueryType::ENTITIES_WITH_COUNT), $ex->getMessage());
            $this->assertEquals(500, $ex->getStatusCode());
        }

    }


    public function testGetRelatedResourceSetJustEntities()
    {
        $orderBy = null;
        $top = 10;
        $skip = 10;


        $fakeQueryResult = new QueryResult();
        $fakeQueryResult->results = array();

        $fakeSourceEntity = new \stdClass();

        $this->mockQueryProvider->method('getRelatedResourceSet')->with(
            QueryType::ENTITIES,
            $this->mockResourceSet,
            $fakeSourceEntity,
            $this->mockResourceSet2,
            $this->mockResourceProperty,
            $this->mockFilterInfo,
            $orderBy,
            $top,
            $skip
        )->willReturn($fakeQueryResult);

        $wrapper = $this->getMockedWrapper();

        $actual = $wrapper->getRelatedResourceSet(
            QueryType::ENTITIES,
            $this->mockResourceSet,
            $fakeSourceEntity,
            $this->mockResourceSet2,
            $this->mockResourceProperty,
            $this->mockFilterInfo,
            $orderBy,
            $top,
            $skip
        );
        $this->assertEquals($fakeQueryResult, $actual);

    }


    public function testGetRelatedResourceSetReturnsNonQueryResult()
    {
        $orderBy = null;
        $top = 10;
        $skip = 10;

        $fakeSourceEntity = new \stdClass();

        $this->mockQueryProvider->method('getRelatedResourceSet')->with(
            QueryType::ENTITIES,
            $this->mockResourceSet,
            $fakeSourceEntity,
            $this->mockResourceSet2,
            $this->mockResourceProperty,
            $this->mockFilterInfo,
            $orderBy,
            $top,
            $skip
        )->willReturn(null);

        $wrapper = $this->getMockedWrapper();

        try{
            $wrapper->getRelatedResourceSet(
                QueryType::ENTITIES,
                $this->mockResourceSet,
                $fakeSourceEntity,
                $this->mockResourceSet2,
                $this->mockResourceProperty,
                $this->mockFilterInfo,
                $orderBy,
                $top,
                $skip
            );
            $this->fail("expected exception not thrown");
        }
        catch(ODataException $ex){
            $this->assertEquals(Messages::queryProviderReturnsNonQueryResult("IQueryProvider::getRelatedResourceSet"), $ex->getMessage());
            $this->assertEquals(500, $ex->getStatusCode());
        }

    }


    public function testGetRelatedResourceSetReturnsCountWhenQueryTypeIsCountProviderDoesNotHandlePaging()
    {
        $orderBy = null;
        $top = 10;
        $skip = 10;

        $fakeQueryResult = new QueryResult();
        $fakeQueryResult->count = 123; //this is irrelevant
        $fakeQueryResult->results = null;

        //Because the provider doe NOT handle paging and this request needs a count, there must be results to calculate a count from
        $this->mockQueryProvider->method('handlesOrderedPaging')
            ->willReturn(false);

        $fakeSourceEntity = new \stdClass();

        $this->mockQueryProvider->method('getRelatedResourceSet')->with(
            QueryType::COUNT,
            $this->mockResourceSet,
            $fakeSourceEntity,
            $this->mockResourceSet2,
            $this->mockResourceProperty,
            $this->mockFilterInfo,
            $orderBy,
            $top,
            $skip
        )->willReturn($fakeQueryResult);

        $wrapper = $this->getMockedWrapper();

        try{
            $wrapper->getRelatedResourceSet(
                QueryType::COUNT,
                $this->mockResourceSet,
                $fakeSourceEntity,
                $this->mockResourceSet2,
                $this->mockResourceProperty,
                $this->mockFilterInfo,
                $orderBy,
                $top,
                $skip
            );
            $this->fail("expected exception not thrown");
        }
        catch(ODataException $ex){
            $this->assertEquals(Messages::queryProviderResultsMissing("IQueryProvider::getRelatedResourceSet", QueryType::COUNT), $ex->getMessage());
            $this->assertEquals(500, $ex->getStatusCode());
        }

    }

    public function testGetRelatedResourceSetReturnsCountWhenQueryTypeIsCountProviderHandlesPaging()
    {
        $orderBy = null;
        $top = 10;
        $skip = 10;

        $fakeQueryResult = new QueryResult();
        $fakeQueryResult->count = null; //null is not numeric

        //Because the provider handles paging and this request needs a count, the count must be numeric
        $this->mockQueryProvider->method('handlesOrderedPaging')
            ->willReturn(true);

        $fakeSourceEntity = new \stdClass();

        $this->mockQueryProvider->method('getRelatedResourceSet')->with(
            QueryType::COUNT,
            $this->mockResourceSet,
            $fakeSourceEntity,
            $this->mockResourceSet2,
            $this->mockResourceProperty,
            $this->mockFilterInfo,
            $orderBy,
            $top,
            $skip
        )->willReturn($fakeQueryResult);

        $wrapper = $this->getMockedWrapper();

        try{
            $wrapper->getRelatedResourceSet(
                QueryType::COUNT,
                $this->mockResourceSet,
                $fakeSourceEntity,
                $this->mockResourceSet2,
                $this->mockResourceProperty,
                $this->mockFilterInfo,
                $orderBy,
                $top,
                $skip
            );
            $this->fail("expected exception not thrown");
        }
        catch(ODataException $ex){
            $this->assertEquals(Messages::queryProviderResultCountMissing("IQueryProvider::getRelatedResourceSet", QueryType::COUNT), $ex->getMessage());
            $this->assertEquals(500, $ex->getStatusCode());
        }

    }

    public function testGetRelatedResourceSetReturnsCountWhenQueryTypeIsEntitiesWithCountProviderHandlesPaging()
    {
        $orderBy = null;
        $top = 10;
        $skip = 10;


        $fakeQueryResult = new QueryResult();
        $fakeQueryResult->count = null; //null is not numeric

        //Because the provider handles paging and this request needs a count, the count must be numeric
        $this->mockQueryProvider->method('handlesOrderedPaging')
            ->willReturn(true);

        $fakeSourceEntity = new \stdClass();

        $this->mockQueryProvider->method('getRelatedResourceSet')->with(
            QueryType::ENTITIES_WITH_COUNT,
            $this->mockResourceSet,
            $fakeSourceEntity,
            $this->mockResourceSet2,
            $this->mockResourceProperty,
            $this->mockFilterInfo,
            $orderBy,
            $top,
            $skip
        )->willReturn($fakeQueryResult);

        $wrapper = $this->getMockedWrapper();

        try{
            $wrapper->getRelatedResourceSet(
                QueryType::ENTITIES_WITH_COUNT,
                $this->mockResourceSet,
                $fakeSourceEntity,
                $this->mockResourceSet2,
                $this->mockResourceProperty,
                $this->mockFilterInfo,
                $orderBy,
                $top,
                $skip
            );
            $this->fail("expected exception not thrown");
        }
        catch(ODataException $ex){
            $this->assertEquals(Messages::queryProviderResultCountMissing("IQueryProvider::getRelatedResourceSet", QueryType::ENTITIES_WITH_COUNT), $ex->getMessage());
            $this->assertEquals(500, $ex->getStatusCode());
        }

    }

    public function testGetRelatedResourceSetReturnsCountWhenQueryTypeIsEntitiesWithCountProviderDoesNotHandlePaging()
    {
        $orderBy = null;
        $top = 10;
        $skip = 10;


        $fakeQueryResult = new QueryResult();
        $fakeQueryResult->count = 444; //irrelevant
        $fakeQueryResult->results = null;

        //Because the provider does NOT handle paging and this request needs a count, the result must have results collection to calculate count from
        $this->mockQueryProvider->method('handlesOrderedPaging')
            ->willReturn(false);

        $fakeSourceEntity = new \stdClass();

        $this->mockQueryProvider->method('getRelatedResourceSet')->with(
            QueryType::ENTITIES_WITH_COUNT,
            $this->mockResourceSet,
            $fakeSourceEntity,
            $this->mockResourceSet2,
            $this->mockResourceProperty,
            $this->mockFilterInfo,
            $orderBy,
            $top,
            $skip
        )->willReturn($fakeQueryResult);

        $wrapper = $this->getMockedWrapper();

        try{
            $wrapper->getRelatedResourceSet(
                QueryType::ENTITIES_WITH_COUNT,
                $this->mockResourceSet,
                $fakeSourceEntity,
                $this->mockResourceSet2,
                $this->mockResourceProperty,
                $this->mockFilterInfo,
                $orderBy,
                $top,
                $skip
            );
            $this->fail("expected exception not thrown");
        }
        catch(ODataException $ex){
            $this->assertEquals(Messages::queryProviderResultsMissing("IQueryProvider::getRelatedResourceSet", QueryType::ENTITIES_WITH_COUNT), $ex->getMessage());
            $this->assertEquals(500, $ex->getStatusCode());
        }

    }


    public function testGetRelatedResourceSetReturnsArrayWhenQueryTypeIsEntities()
    {
        $orderBy = null;
        $top = 10;
        $skip = 10;


        $fakeQueryResult = new QueryResult();
        $fakeQueryResult->count = 2;
        $fakeQueryResult->results = null; //null is not an array

        $fakeSourceEntity = new \stdClass();

        $this->mockQueryProvider->method('getRelatedResourceSet')->with(
            QueryType::ENTITIES,
            $this->mockResourceSet,
            $fakeSourceEntity,
            $this->mockResourceSet2,
            $this->mockResourceProperty,
            $this->mockFilterInfo,
            $orderBy,
            $top,
            $skip
        )->willReturn($fakeQueryResult);

        $wrapper = $this->getMockedWrapper();

        try{
            $wrapper->getRelatedResourceSet(
                QueryType::ENTITIES,
                $this->mockResourceSet,
                $fakeSourceEntity,
                $this->mockResourceSet2,
                $this->mockResourceProperty,
                $this->mockFilterInfo,
                $orderBy,
                $top,
                $skip
            );
            $this->fail("expected exception not thrown");
        }
        catch(ODataException $ex){
            $this->assertEquals(Messages::queryProviderResultsMissing("IQueryProvider::getRelatedResourceSet", QueryType::ENTITIES), $ex->getMessage());
            $this->assertEquals(500, $ex->getStatusCode());
        }

    }

    public function testGetRelatedResourceSetReturnsArrayWhenQueryTypeIsEntitiesWithCount()
    {
        $orderBy = null;
        $top = 10;
        $skip = 10;


        $fakeQueryResult = new QueryResult();
        $fakeQueryResult->count = 4;
        $fakeQueryResult->results = null; //null is not an array

        $fakeSourceEntity = new \stdClass();

        $this->mockQueryProvider->method('getRelatedResourceSet')->with(
            QueryType::ENTITIES_WITH_COUNT,
            $this->mockResourceSet,
            $fakeSourceEntity,
            $this->mockResourceSet2,
            $this->mockResourceProperty,
            $this->mockFilterInfo,
            $orderBy,
            $top,
            $skip
        )->willReturn($fakeQueryResult);

        $wrapper = $this->getMockedWrapper();

        try{
            $wrapper->getRelatedResourceSet(
                QueryType::ENTITIES_WITH_COUNT,
                $this->mockResourceSet,
                $fakeSourceEntity,
                $this->mockResourceSet2,
                $this->mockResourceProperty,
                $this->mockFilterInfo,
                $orderBy,
                $top,
                $skip
            );
            $this->fail("expected exception not thrown");
        }
        catch(ODataException $ex){
            $this->assertEquals(Messages::queryProviderResultsMissing("IQueryProvider::getRelatedResourceSet", QueryType::ENTITIES_WITH_COUNT), $ex->getMessage());
            $this->assertEquals(500, $ex->getStatusCode());
        }

    }
}