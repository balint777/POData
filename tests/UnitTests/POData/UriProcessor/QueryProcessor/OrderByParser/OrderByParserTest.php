<?php

namespace UnitTests\POData\UriProcessor\QueryProcessor\OrderByParser;

use POData\Providers\Metadata\ResourceProperty;
use POData\Configuration\EntitySetRights;
use POData\Providers\ProvidersWrapper;
use POData\Configuration\ServiceConfiguration;
use POData\Common\ODataException;
use POData\UriProcessor\QueryProcessor\OrderByParser\OrderByParser;

use UnitTests\POData\Facets\NorthWind1\NorthWindMetadata;
//These are in the file loaded by above use statement
//TODO: move to own class files
use UnitTests\POData\Facets\NorthWind1\Address2;
use UnitTests\POData\Facets\NorthWind1\Address4;
use UnitTests\POData\Facets\NorthWind1\Customer2;
use UnitTests\POData\Facets\NorthWind1\Order2;
use UnitTests\POData\Facets\NorthWind1\Order_Details2;
use UnitTests\POData\Facets\NorthWind1\Product2;
use PHPUnit\Framework\TestCase;

use POData\Providers\Query\IQueryProvider;

class OrderByParserTest extends TestCase
{
	protected IQueryProvider $mockQueryProvider;

    protected function setUp() : void
    {
	    $this->mockQueryProvider = \Phockito::mock('POData\Providers\Query\IQueryProvider');
    }

    //All all test case (which are +ve) check the generated function and

    /**
     * Entities cannot be sorted using bag property
     */
    public function testOrderByBag()
    {

        $northWindMetadata = NorthWindMetadata::Create();
        $configuration = new ServiceConfiguration($northWindMetadata);
        $configuration->setEntitySetAccessRule('*', EntitySetRights::ALL);
        $providersWrapper = new ProvidersWrapper(
                                          $northWindMetadata, //IMetadataProvider implementation
	                                        $this->mockQueryProvider,
                                          $configuration, //Service configuuration
                                          false
                                         );

        $resourceSetWrapper = $providersWrapper->resolveResourceSet('Employees');
        $resourceType = $resourceSetWrapper->getResourceType();
        $orderBy = 'Emails';

        try {
            OrderByParser::parseOrderByClause($resourceSetWrapper, $resourceType, $orderBy, $providersWrapper);
            $this->fail('An expected ODataException for bag property in the path');
        } catch (ODataException $odataException) {
            $this->assertStringStartsWith('orderby clause does not support Bag property in the path, the property', $odataException->getMessage());
        }


    }

    /**
     * Entities cannot be sorted using complex property
     */
    public function testOrderByComplex()
    {

        $northWindMetadata = NorthWindMetadata::Create();
        $configuration = new ServiceConfiguration($northWindMetadata);
        $configuration->setEntitySetAccessRule('*', EntitySetRights::ALL);
        $providersWrapper = new ProvidersWrapper(
                                          $northWindMetadata, //IMetadataProvider implementation
	                                        $this->mockQueryProvider,
                                          $configuration, //Service configuuration
                                          false
                                         );

        $resourceSetWrapper = $providersWrapper->resolveResourceSet('Customers');
        $resourceType = $resourceSetWrapper->getResourceType();
        $orderBy = 'Address';

        try {
            OrderByParser::parseOrderByClause($resourceSetWrapper, $resourceType, $orderBy, $providersWrapper);
            $this->fail('An expected ODataException for using complex property as sort key has not been thrown');
        } catch (ODataException $odataException) {
            $this->assertStringStartsWith('Complex property cannot be used as sort key,', $odataException->getMessage());
        }

        $resourceSetWrapper = $providersWrapper->resolveResourceSet('Customers');
        $resourceType = $resourceSetWrapper->getResourceType();
        $orderBy = 'Address/Address2';

        try {
            OrderByParser::parseOrderByClause($resourceSetWrapper, $resourceType, $orderBy, $providersWrapper);
            $this->fail('An expected ODataException for using complex property as sort key has not been thrown');
        } catch (ODataException $odataException) {
            $this->assertStringStartsWith('Complex property cannot be used as sort key,', $odataException->getMessage());
        }

    }

    /**
     * Entities cannot be sorted using resource set reference property
     * even resource set is not allowed in order by path     
     */
    public function testOrderByResourceSetReference()
    {

        $northWindMetadata = NorthWindMetadata::Create();
        $configuration = new ServiceConfiguration($northWindMetadata);
        $configuration->setEntitySetAccessRule('*', EntitySetRights::ALL);
        $providersWrapper = new ProvidersWrapper(
                                          $northWindMetadata, //IMetadataProvider implementation
	                                        $this->mockQueryProvider,
                                          $configuration, //Service configuuration
                                          false
                                         );

        $resourceSetWrapper = $providersWrapper->resolveResourceSet('Customers');
        $resourceType = $resourceSetWrapper->getResourceType();
        $orderBy = 'Orders/OrderID';
        try {
            OrderByParser::parseOrderByClause($resourceSetWrapper, $resourceType, $orderBy, $providersWrapper);
            $this->fail('An expected ODataException for usage of resource reference set has not been thrown');
        } catch (ODataException $odataException) {
            $this->assertStringStartsWith('Navigation property points to a collection cannot be used in orderby clause', $odataException->getMessage());
        }
    }

    /**
     * Entities cannot be sorted using resource reference property
     */
    public function testOrderByResourceReference()
    {

        $northWindMetadata = NorthWindMetadata::Create();
        $configuration = new ServiceConfiguration($northWindMetadata);
        $configuration->setEntitySetAccessRule('*', EntitySetRights::ALL);
        $providersWrapper = new ProvidersWrapper(
                                          $northWindMetadata, //IMetadataProvider implementation
	        $this->mockQueryProvider,
                                          $configuration, //Service configuuration
                                          false
                                         );

        $resourceSetWrapper = $providersWrapper->resolveResourceSet('Orders');
        $resourceType = $resourceSetWrapper->getResourceType();
        $orderBy = 'Customer';

        try {
            OrderByParser::parseOrderByClause($resourceSetWrapper, $resourceType, $orderBy, $providersWrapper);
            $this->fail('An expected ODataException for usage of resource reference as sort key has not been thrown');
        } catch (ODataException $odataException) {
            $this->assertStringStartsWith('Navigation property cannot be used as sort key,', $odataException->getMessage());
        }

        $resourceSetWrapper = $providersWrapper->resolveResourceSet('Order_Details');
        $resourceType = $resourceSetWrapper->getResourceType();
        $orderBy = 'Order/Customer';

        try {
            OrderByParser::parseOrderByClause($resourceSetWrapper, $resourceType, $orderBy, $providersWrapper);
            $this->fail('An expected ODataException for usage of resource reference as sort key has not been thrown');
        } catch (ODataException $odataException) {
            $this->assertStringStartsWith('Navigation property cannot be used as sort key,', $odataException->getMessage());
        }

    }

    /**
     * Entities cannot be sorted using binary property     
     */
    public function testOrderByBinaryProperty()
    {

        $northWindMetadata = NorthWindMetadata::Create();
        $configuration = new ServiceConfiguration($northWindMetadata);
        $configuration->setEntitySetAccessRule('*', EntitySetRights::ALL);
        $providersWrapper = new ProvidersWrapper(
                                          $northWindMetadata, //IMetadataProvider implementation
	                                        $this->mockQueryProvider,
                                          $configuration, //Service configuuration
                                          false
                                         );

        $resourceSetWrapper = $providersWrapper->resolveResourceSet('Customers');
        $resourceType = $resourceSetWrapper->getResourceType();
        $orderBy = 'Photo';

        try {
            OrderByParser::parseOrderByClause($resourceSetWrapper, $resourceType, $orderBy, $providersWrapper);
            $this->fail('An expected ODataException for usage of binary property has not been thrown');
        } catch (ODataException $odataException) {
            $this->assertStringStartsWith('Binary property is not allowed in orderby', $odataException->getMessage());

        }

    }

    /**
     * Orderby path cannot contain invisible resource reference property.
     */
    public function testOrderByWithInvisibleResourceReferencePropertyInThePath()
    {

        $northWindMetadata = NorthWindMetadata::Create();
        $configuration = new ServiceConfiguration($northWindMetadata);
        //Make 'Orders' visible, make 'Customers' invisible
        $configuration->setEntitySetAccessRule('Orders', EntitySetRights::ALL);
        $providersWrapper = new ProvidersWrapper(
                                              $northWindMetadata, //IMetadataProvider implementation
	                                         $this->mockQueryProvider,
                                              $configuration, //Service configuuration
                                              false
                                             );

        $resourceSetWrapper = $providersWrapper->resolveResourceSet('Orders');
        $resourceType = $resourceSetWrapper->getResourceType();
        $orderBy = 'Customer/CustomerID';

        try {
            OrderByParser::parseOrderByClause($resourceSetWrapper, $resourceType, $orderBy, $providersWrapper);
            $this->fail('An expected ODataException for navigation to invisible resource set has not been thrown');
        } catch (ODataException $odataException) {
            $this->assertStringEndsWith("(Check the resource set of the navigation property 'Customer' is visible)", $odataException->getMessage());
        }

    }

    /**
     * Test whether order by parser identify and remove path duplication
     */
    public function testOrderByWithPathDuplication()
    {
        $northWindMetadata = NorthWindMetadata::Create();
        $configuration = new ServiceConfiguration($northWindMetadata);
        $configuration->setEntitySetAccessRule('*', EntitySetRights::ALL);
        $providersWrapper = new ProvidersWrapper(
                                              $northWindMetadata, //IMetadataProvider implementation
	                                        $this->mockQueryProvider,
                                              $configuration, //Service configuuration
                                              false
                                             );

        $resourceSetWrapper = $providersWrapper->resolveResourceSet('Order_Details');
        $resourceType = $resourceSetWrapper->getResourceType();
        $orderBy = 'Order/Price desc, Product/ProductName asc, Order/Price asc';
        $internalOrderInfo = OrderByParser::parseOrderByClause($resourceSetWrapper, $resourceType, $orderBy, $providersWrapper);
        //The orderby path Order/Price appears twice, but parser will consider only first path
        $orderByInfo = $internalOrderInfo->getOrderByInfo();
        //There are navigation (resource reference) properties in the orderby path so getNavigationPropertiesUsed should
        //not be null
        $naviUsed = $orderByInfo->getNavigationPropertiesUsed();
        $this->assertFalse(is_null($naviUsed));
        //3 path segment are there, but last one is duplicate of first one, so parser removes last one
        $this->assertEquals(count($naviUsed), 2);
        $this->assertTrue(is_array($naviUsed[0]));
        $this->assertTrue(is_array($naviUsed[1]));
        //one navgations used in first orderby 'Order'
        $this->assertEquals(count($naviUsed[0]), 1);
        //one navgations used in second orderby 'Prodcut'
        $this->assertEquals(count($naviUsed[1]), 1);
        $this->assertEquals($naviUsed[0][0]->getName(), 'Order');
        $this->assertEquals($naviUsed[1][0]->getName(), 'Product');
        $orderByPathSegments = $orderByInfo->getOrderByPathSegments();
        $this->assertEquals(count($orderByPathSegments), 2);

    }

    protected function tearDown() : void
    {
    }
}