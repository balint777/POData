<?php

namespace UnitTests\POData\Providers\Metadata;

use POData\Providers\Metadata\ResourceAssociationSet;
use POData\Providers\Metadata\ResourceAssociationSetEnd;
use POData\Providers\Metadata\ResourceAssociationType;
use POData\Common\ODataConstants;
use POData\Providers\Metadata\ResourceAssociationTypeEnd;
use POData\Providers\Metadata\ResourceSet;
use POData\Providers\Metadata\Type\TypeCode;
use POData\Providers\Metadata\ResourceStreamInfo;
use POData\Providers\Metadata\ResourceTypeKind;
use POData\Providers\Metadata\ResourceType;
use POData\Providers\Metadata\ResourcePropertyKind;
use POData\Providers\Metadata\ResourceProperty;
use POData\Providers\Metadata\Type\EdmPrimitiveType;
use POData\Providers\Metadata\Type\Int32;
use POData\Providers\Metadata\Type\Int16;
use POData\Common\InvalidOperationException;
use PHPUnit\Framework\TestCase;
use UnitTests\POData\Facets\NorthWind1\NorthWindMetadata;


class ResourceClassesTest extends TestCase
{
    protected function setUp() : void
    {
	    //TODO: move the entity types into their own files
	    //unit then we need to ensure they are "in scope"
	    $x = NorthWindMetadata::Create();

    }

    protected function tearDown() : void
    {

    }

    /**
     * test ResourceType class
     */
    public function testResourceType()
    {
        $exceptionThrown = false;
        try {
            ResourceType::getPrimitiveResourceType(TypeCode::VOID);
            $this->fail('An expected InvalidArgumentException for \'EdmPrimitiveType\' has not been raised');
        } catch (\InvalidArgumentException $exception) {
            $this->assertStringEndsWith('is not a valid EdmPrimitiveType Enum value', $exception->getMessage());
        }

        $int16ResType = new ResourceType(new Int16(), ResourceTypeKind::PRIMITIVE, 'Int16', 'Edm');
        try {
            $int32ResType = new ResourceType(new Int32(), ResourceTypeKind::PRIMITIVE, 'Int32', 'Edm', $int16ResType);
            $this->fail('An expected InvalidArgumentException for \'basetype\' has not been raised');
        } catch (\InvalidArgumentException $exception) {
            $this->AssertEquals('Primitive type cannot have base type', $exception->getMessage());
        }

        try {
            $int32ResType = new ResourceType(new Int32(), ResourceTypeKind::PRIMITIVE, 'Int32', 'Edm', null, true);
            $this->fail('An expected InvalidArgumentException for \'abstract\' has not been raised');
        } catch (\InvalidArgumentException $exception) {
            $this->AssertEquals('Primitive type cannot be abstract', $exception->getMessage());
        }


        try {
            $int32ResType = new ResourceType(null, ResourceTypeKind::PRIMITIVE, 'Int32', 'Edm');
            $this->fail('An expected InvalidArgumentException for \'IType\' has not been raised');
        } catch (\InvalidArgumentException $exception) {
            $this->assertStringEndsWith('should be an \'IType\' implementor instance', $exception->getMessage());
        }


        try {
            $customerResType = new ResourceType(null, ResourceTypeKind::ENTITY, 'Customer', 'Northwind');
            $this->fail('An expected InvalidArgumentException for \'ReflectionClass\' has not been raised');
        } catch (\InvalidArgumentException $exception) {
            $this->assertStringEndsWith('argument should be an \'ReflectionClass\' instance', $exception->getMessage());
        }

        $customerResType = new ResourceType(new \ReflectionClass('UnitTests\POData\Facets\NorthWind1\Customer2'), ResourceTypeKind::ENTITY, 'Customer', 'Northwind');
        $this->AssertEquals($customerResType->getName(), 'Customer');
        $this->AssertEquals($customerResType->getFullName(), 'Northwind.Customer');
        $this->assertTrue($customerResType->getInstanceType() instanceof \ReflectionClass);
        $this->AssertEquals($customerResType->getNamespace(), 'Northwind');
        $this->AssertEquals($customerResType->getResourceTypeKind(), ResourceTypeKind::ENTITY);
        $this->AssertEquals($customerResType->isMediaLinkEntry(), false);

        try {
            $customerResType->validateType();
            $this->fail('An expected InvalidOperationException for \'No key defined\' has not been raised');
        } catch(InvalidOperationException $exception) {
            $this->assertStringEndsWith('Please make sure the key properties are defined for this entity type', $exception->getMessage());
        }


        $int32ResourceType = ResourceType::getPrimitiveResourceType(EdmPrimitiveType::INT32);
        $primitiveResourceType = ResourceType::getPrimitiveResourceType(EdmPrimitiveType::STRING);
        $testProperty = new ResourceProperty('test', null, ResourcePropertyKind::PRIMITIVE, $primitiveResourceType);
        try {
            $int32ResourceType->addProperty($testProperty);
            $this->fail('An expected InvalidOperationException for \'property on primitive\' has not been raised');

        } catch (InvalidOperationException $exception) {
            $this->assertStringEndsWith('ResourceType instances with a ResourceTypeKind equal to \'Primitive\'', $exception->getMessage());
        }


        $stringResourceType = ResourceType::getPrimitiveResourceType(EdmPrimitiveType::STRING);
        $customerIDPrimProperty   = new ResourceProperty('CustomerID', null, ResourcePropertyKind::PRIMITIVE | ResourcePropertyKind::KEY, $stringResourceType);
        $customerNamePrimProperty = new ResourceProperty('CustomerName', null, ResourcePropertyKind::PRIMITIVE, $stringResourceType);
        $intResourceType = ResourceType::getPrimitiveResourceType(EdmPrimitiveType::INT32);
        $ratingPrimProperty       = new ResourceProperty('Rating', null, ResourcePropertyKind::PRIMITIVE, $intResourceType);

        $addressResType = new ResourceType(new \ReflectionClass('UnitTests\POData\Facets\NorthWind1\Address2'), ResourceTypeKind::COMPLEX, 'Address', 'Northwind');
        $booleanResourceType = ResourceType::getPrimitiveResourceType(EdmPrimitiveType::BOOLEAN);
        $isPrimaryPrimProperty   = new ResourceProperty('IsPrimary', null, ResourcePropertyKind::PRIMITIVE | ResourcePropertyKind::KEY, $booleanResourceType);
        try {
            $addressResType->addProperty($isPrimaryPrimProperty);
            $this->fail('An expected InvalidOperationException for \'Key on non-entity\' has not been raised');
        } catch(InvalidOperationException $exception) {
            $this->assertStringEndsWith('ResourceType instances with a ResourceTypeKind equal to \'EntityType\'', $exception->getMessage());
        }


        $booleanResourceType = ResourceType::getPrimitiveResourceType(EdmPrimitiveType::BOOLEAN);
        $isPrimaryPrimProperty   = new ResourceProperty('IsPrimary', null, ResourcePropertyKind::PRIMITIVE, $booleanResourceType);
        $addressResType->addProperty($isPrimaryPrimProperty);

        try {
            $addressResType->addProperty($isPrimaryPrimProperty);
            $this->fail('An expected InvalidArgumentException for \'Property duplication\' has not been raised');
        } catch(InvalidOperationException $exception) {
            $this->assertStringStartsWith('Property with same name \'IsPrimary\' already exists in type \'Address\'', $exception->getMessage());
        }

        try {
            $addressResType->setMediaLinkEntry(true);
            $this->fail('An expected InvalidOperationException for \'MLE on non-entity\' has not been raised');
        } catch (InvalidOperationException $exception) {
            $this->assertStringStartsWith('Cannot apply the HasStreamAttribute', $exception->getMessage());
        }


        $customerAdrComplexType = new ResourceProperty('Address', null, ResourcePropertyKind::COMPLEX_TYPE, $addressResType);
        $customerResType->addProperty($customerIDPrimProperty);
        $customerResType->addProperty($customerNamePrimProperty);
        $customerResType->addProperty($ratingPrimProperty);
        $customerResType->addProperty($customerAdrComplexType);
        $customerResType->validateType();

        $customerProperties = $customerResType->getPropertiesDeclaredOnThisType();
        $this->AssertEquals(count($customerProperties), 4);
        $customerAllProperties = $customerResType->getAllProperties();
        $this->AssertEquals(count($customerProperties), count($customerAllProperties));
        $keys = array('CustomerID', 'CustomerName', 'Rating', 'Address');
        $i = 0;
        foreach ($customerAllProperties as $key => $customerProperty) {
            $this->AssertEquals($key, $keys[$i++]);
        }

        $entityKeys = array('CustomerID');
        $customerKeyProperties = $customerResType->getKeyProperties();
        $i = 0;
        foreach ($customerKeyProperties as $key => $customerKeyProperty) {
            $this->AssertEquals($key, $entityKeys[$i++]);
        }

        $this->AssertEquals(count($customerResType->getETagProperties()), 0);
        $this->AssertEquals($customerResType->resolveProperty('PropNotExists'), null);
        $property = $customerResType->resolveProperty('CustomerName');
        $this->AssertNotEquals($property, null);
        $this->AssertEquals($property->getName(), 'CustomerName');

        $employeeResType = new ResourceType(new \ReflectionClass('UnitTests\POData\Facets\NorthWind1\Employee2'), ResourceTypeKind::ENTITY, 'Employee', 'Northwind');
        $stringResourceType = ResourceType::getPrimitiveResourceType(EdmPrimitiveType::STRING);
        $employeeResType->addProperty(new ResourceProperty('EmployeeID', null, ResourcePropertyKind::PRIMITIVE | ResourcePropertyKind::KEY, $stringResourceType));
        $employeeResType->addProperty(new ResourceProperty('Emails', null, ResourcePropertyKind::PRIMITIVE | ResourcePropertyKind::BAG, $stringResourceType));
        $employeeResType->setMediaLinkEntry(true);
        $employeeResType->addNamedStream(new ResourceStreamInfo('ThumNail_64X64'));
        try {
            $employeeResType->addNamedStream(new ResourceStreamInfo('ThumNail_64X64'));
            $this->fail('An expected InvalidOperationException for \'named stream duplication\' has not been raised');
        } catch (InvalidOperationException $exception) {
            $this->assertStringStartsWith('Named stream with the name \'ThumNail_64X64\' already exists in type \'Employee\'', $exception->getMessage());
        }


        $this->AssertEquals($employeeResType->hasNamedStream(), true);
        $b = array();
        $this->AssertEquals($employeeResType->hasBagProperty($b), true);

        $namedStreams = $employeeResType->getAllNamedStreams();
        $this->AssertEquals(count($namedStreams), 1);
        $this->AssertTrue(array_key_exists('ThumNail_64X64', $namedStreams));

        $name = $employeeResType->tryResolveNamedStreamByName('ThumNail_64X64')->getName();
        $this->AssertEquals($name, 'ThumNail_64X64');

    }

    public function testResourceProperty()
    {

        $addressResType = new ResourceType(new \ReflectionClass('UnitTests\POData\Facets\NorthWind1\Address2'), ResourceTypeKind::COMPLEX, 'Address', 'Northwind');
        $booleanResourcetype = ResourceType::getPrimitiveResourceType(EdmPrimitiveType::BOOLEAN);
        $isPrimaryPrimProperty = new ResourceProperty('IsPrimary', null, ResourcePropertyKind::PRIMITIVE, $booleanResourcetype);
        $addressResType->addProperty($isPrimaryPrimProperty);

        try {
            $addressComplexProperty = new ResourceProperty('Address', null, ResourcePropertyKind::COMPLEX_TYPE | ResourcePropertyKind::KEY, $addressResType);
            $this->fail('An expected InvalidArgumentException for \'invalid ResourcePropertyKind\' has not been raised');
        } catch(\InvalidArgumentException $exception) {
            $this->assertStringEndsWith('not a valid ResourcePropertyKind enum value or valid combination of ResourcePropertyKind enum values', $exception->getMessage());
        }
            

        $stringResourceType = ResourceType::getPrimitiveResourceType(EdmPrimitiveType::STRING);
        try {
            $addressComplexProperty = new ResourceProperty('Address', null, ResourcePropertyKind::COMPLEX_TYPE, $stringResourceType);
            $this->fail('An expected InvalidArgumentException for \'Property and ResourceType kind mismatch\' has not been raised');
        } catch(\InvalidArgumentException $exception) {
            $this->assertStringStartsWith('The \'$kind\' parameter does not match with the type of the resource type', $exception->getMessage());
        }
            

        $customerResType = new ResourceType(new \ReflectionClass('UnitTests\POData\Facets\NorthWind1\Customer2'), ResourceTypeKind::ENTITY, 'Customer', 'Northwind');
        $stringResourceType = ResourceType::getPrimitiveResourceType(EdmPrimitiveType::STRING);
        $customerIDPrimProperty   = new ResourceProperty('CustomerID', null, ResourcePropertyKind::PRIMITIVE | ResourcePropertyKind::KEY, $stringResourceType);
        $customerNamePrimProperty = new ResourceProperty('CustomerName', null, ResourcePropertyKind::PRIMITIVE, $stringResourceType);
        $intResourceType = ResourceType::getPrimitiveResourceType(EdmPrimitiveType::INT32);
        $ratingPrimProperty       = new ResourceProperty('Rating', null, ResourcePropertyKind::PRIMITIVE, $intResourceType);
        $customerResType->addProperty($customerIDPrimProperty);
        $customerResType->addProperty($customerNamePrimProperty);
        $customerResType->addProperty($ratingPrimProperty);
        $this->assertTrue($customerIDPrimProperty->isKindOf(ResourcePropertyKind::KEY));
        $this->assertTrue($customerIDPrimProperty->isKindOf(ResourcePropertyKind::PRIMITIVE));

        $customerReferenceSetProperty  = new ResourceProperty('Customers', null, ResourcePropertyKind::RESOURCESET_REFERENCE, $customerResType);
        $this->assertEquals($customerReferenceSetProperty->getName(), 'Customers');
        $this->assertEquals($customerReferenceSetProperty->getKind(), ResourcePropertyKind::RESOURCESET_REFERENCE);
        $this->assertEquals($customerReferenceSetProperty->getInstanceType() instanceof \ReflectionClass, true);
        $this->assertEquals($customerReferenceSetProperty->getResourceType()->getName(), 'Customer');
        $this->assertEquals($customerReferenceSetProperty->getTypeKind(), ResourceTypeKind::ENTITY);
        $this->assertFalse($customerReferenceSetProperty->isKindOf(ResourcePropertyKind::RESOURCE_REFERENCE));

    }

    /**
     * test ResourceSet class
     */
    public function testResourceSet()
    {

        $int64 = ResourceType::getPrimitiveResourceType(EdmPrimitiveType::INT64);

        try {
            $customerResourceSet = new ResourceSet('Customers', $int64);
            $this->fail('An expected InvalidArgumentException for \'non-entity type resource type\' has not been raised');

        } catch(\InvalidArgumentException $exception) {
            $this->assertStringStartsWith('The ResourceTypeKind property of a ResourceType instance associated with a ResourceSet', $exception->getMessage());
        }

        $customerResType = $this->_getCustomerResourceType();
        $customerResourceSet = new ResourceSet('Customers', $customerResType);
        $this->assertEquals($customerResourceSet->getName(), 'Customers');
        $this->assertEquals($customerResourceSet->getResourceType()->getName(), 'Customer');
    }
    
    /**
     * Test ResourceAssociationTypeEnd class
     * Note: ResourceAssociationTypeEnd is an internal class used for metadata generation, not suppose to used by the developers 
     */
  public function testResourceAssociationTypeEnd()
  {

        $customerResType = $this->_getCustomerResourceType();
        $orderResType = $this->_getOrderResourceType();
        //Creates a one-to-many relationship from Customer to  Order entity
        $customerReferenceProperty = new ResourceProperty('Customer', null, ResourcePropertyKind::RESOURCE_REFERENCE, $customerResType);
        $ordersReferenceSetProperty = new ResourceProperty('Orders', null, ResourcePropertyKind::RESOURCESET_REFERENCE, $orderResType);
        $customerResType->addProperty($ordersReferenceSetProperty);
        $orderResType->addProperty($customerReferenceProperty);

        $customerToOrderAssoEnd1 = new ResourceAssociationTypeEnd('Orders', $customerResType, $ordersReferenceSetProperty, $customerReferenceProperty);
        $customerToOrderAssoEnd2 = new ResourceAssociationTypeEnd('Customers', $orderResType, $customerReferenceProperty, $ordersReferenceSetProperty);

        $this->assertEquals($customerToOrderAssoEnd1->getName(), 'Orders');
        $this->assertEquals($customerToOrderAssoEnd1->getResourceType()->getFullName(), 'Northwind.Customer');
        $this->assertEquals($customerToOrderAssoEnd1->getResourceProperty()->getName(), 'Orders');
        $this->assertEquals($customerToOrderAssoEnd1->getMultiplicity(), ODataConstants::ZERO_OR_ONE);
        $this->assertEquals($customerToOrderAssoEnd2->getMultiplicity(), ODataConstants::MANY);
        $this->assertTrue($customerToOrderAssoEnd1->isBelongsTo($customerResType, $ordersReferenceSetProperty));

    }

    /**
     * Test ResourceAssociationType class
     * Note: ResourceAssociationType is an internal class used for metadata generation, not suppose to used by the developers 
     */
    public function testResourceAssociationType()
    {
        $customerResType = $this->_getCustomerResourceType();
        $orderResType = $this->_getOrderResourceType();
        //Creates a one-to-many relationship from Customer to  Order entity
        $customerReferenceProperty = new ResourceProperty('Customer', null, ResourcePropertyKind::RESOURCE_REFERENCE, $customerResType);
        $ordersReferenceSetProperty = new ResourceProperty('Orders', null, ResourcePropertyKind::RESOURCESET_REFERENCE, $orderResType);
        $customerResType->addProperty($ordersReferenceSetProperty);
        $orderResType->addProperty($customerReferenceProperty);

        $customerToOrderAssoEnd1 = new ResourceAssociationTypeEnd('Orders', $customerResType, $ordersReferenceSetProperty, $customerReferenceProperty);
        $customerToOrderAssoEnd2 = new ResourceAssociationTypeEnd('Customers', $orderResType, $customerReferenceProperty, $ordersReferenceSetProperty);
        $customerToOrderAssoType = new ResourceAssociationType('FK_Orders_Customers', 'Northwind', $customerToOrderAssoEnd1, $customerToOrderAssoEnd2);
        $this->assertEquals($customerToOrderAssoType->getName(), 'FK_Orders_Customers');
        $this->assertEquals($customerToOrderAssoType->getFullName(), 'Northwind.FK_Orders_Customers');

        $actual = $customerToOrderAssoType->getResourceAssociationTypeEnd($customerResType, $ordersReferenceSetProperty);
        $this->assertSame($customerToOrderAssoEnd1, $actual);

        $actual = $customerToOrderAssoType->getRelatedResourceAssociationSetEnd($customerResType, $ordersReferenceSetProperty);
        $this->assertSame($customerToOrderAssoEnd2, $actual);
    }

    /**
     * Test ResourceAssociationSetEnd class
     * Note: ResourceAssociationSetEnd is an internal class used for metadata generation, not suppose to used by the developers 
     */
    public function testResourceAssociationSetEnd()
    {

        $customerResType = $this->_getCustomerResourceType();
        $orderResType = $this->_getOrderResourceType();
        $ordersReferenceSetProperty = new ResourceProperty('Orders', null, ResourcePropertyKind::RESOURCESET_REFERENCE, $orderResType);
        $customerResType->addProperty($ordersReferenceSetProperty);
        $customerResourceSet = new ResourceSet('Customers', $customerResType);

        $customerIDPrimProperty = $customerResType->resolveProperty('CustomerID');

        try {
            $assoSetEnd = new ResourceAssociationSetEnd($customerResourceSet, $customerResType, $customerIDPrimProperty);
            $this->fail('An expected InvalidArgumentException for \'not valid navigation property\' has not been raised');
        } catch (\InvalidArgumentException $exception) {
            $this->assertEquals('The property CustomerID must be a navigation property of the resource type Northwind.Customer', $exception->getMessage());
        }


        try {
            $assoSetEnd = new ResourceAssociationSetEnd($customerResourceSet, $orderResType, $ordersReferenceSetProperty);
            $this->fail('An expected InvalidArgumentException for \'not valid navigation property\' has not been raised');
        } catch (\InvalidArgumentException $exception) {
            $this->assertEquals('The property Orders must be a navigation property of the resource type Northwind.Order', $exception->getMessage());
        }

        $assoSetEnd = new ResourceAssociationSetEnd($customerResourceSet, $customerResType, $ordersReferenceSetProperty);
        $this->assertEquals($assoSetEnd->getResourceSet()->getName(), 'Customers');
        $this->assertEquals($assoSetEnd->getResourceType()->getName(), 'Customer');
        $this->assertEquals($assoSetEnd->getResourceProperty()->getName(), 'Orders');

        $this->assertTrue($assoSetEnd->isBelongsTo($customerResourceSet, $customerResType, $ordersReferenceSetProperty));

    }

    /**
     * Test ResourceAssociationSet class
     * Note: ResourceAssociationSet is an internal class used for metadata generation, not suppose to used by the developers 
     */
    public function testResourceAssociationSet()
    {
        $customerResType = $this->_getCustomerResourceType();
        $customerResourceSet = new ResourceSet('Customers', $customerResType);

        $orderResType = $this->_getOrderResourceType();
        $orderResourceSet = new ResourceSet('Orders', $orderResType);

        $customerReferenceProperty = new ResourceProperty('Customer', null, ResourcePropertyKind::RESOURCE_REFERENCE, $customerResType);
        $ordersReferenceSetProperty = new ResourceProperty('Orders', null, ResourcePropertyKind::RESOURCESET_REFERENCE, $orderResType);

        $customerResType->addProperty($ordersReferenceSetProperty);
        $orderResType->addProperty($customerReferenceProperty);


        $assoSetEnd1 = new ResourceAssociationSetEnd($customerResourceSet, $customerResType, $ordersReferenceSetProperty);
        $assoSetEnd2 = new ResourceAssociationSetEnd($orderResourceSet, $orderResType, $customerReferenceProperty);
        $assoSet = new ResourceAssociationSet('Orders_Customers', $assoSetEnd1, $assoSetEnd2);

        $this->assertEquals($assoSet->getName(), 'Orders_Customers');
        $this->assertSame($assoSet->getEnd1(), $assoSetEnd1);
        $this->assertSame($assoSet->getEnd2(), $assoSetEnd2);

        $actual = $assoSet->getResourceAssociationSetEnd($customerResourceSet, $customerResType, $ordersReferenceSetProperty);
        $this->assertSame($assoSetEnd1, $actual);

        $actual = $assoSet->getRelatedResourceAssociationSetEnd($customerResourceSet, $customerResType, $ordersReferenceSetProperty);
        $this->assertSame($assoSetEnd2, $actual);

    }
    
    private function _getCustomerResourceType()
    {
        $customerResType = new ResourceType(new \ReflectionClass('UnitTests\POData\Facets\NorthWind1\Customer2'), ResourceTypeKind::ENTITY, 'Customer', 'Northwind');
        $stringResourceType = ResourceType::getPrimitiveResourceType(EdmPrimitiveType::STRING);
        $intResourceType = ResourceType::getPrimitiveResourceType(EdmPrimitiveType::INT32);
        $customerIDPrimProperty   = new ResourceProperty('CustomerID', null, ResourcePropertyKind::PRIMITIVE | ResourcePropertyKind::KEY, $stringResourceType);
        $customerNamePrimProperty = new ResourceProperty('CustomerName', null, ResourcePropertyKind::PRIMITIVE, $stringResourceType);
        $ratingPrimProperty       = new ResourceProperty('Rating', null, ResourcePropertyKind::PRIMITIVE, $intResourceType);
        $customerResType->addProperty($customerIDPrimProperty);
        $customerResType->addProperty($customerNamePrimProperty);
        $customerResType->addProperty($ratingPrimProperty);
        return $customerResType;
    }

    private function _getOrderResourceType()
    {
        $orderResType =  new ResourceType(new \ReflectionClass('UnitTests\POData\Facets\NorthWind1\Order2'), ResourceTypeKind::ENTITY, 'Order', 'Northwind');
        $intResourceType = ResourceType::getPrimitiveResourceType(EdmPrimitiveType::INT32);
        $orderIDPrimProperty = new ResourceProperty('OrderID', null, ResourcePropertyKind::PRIMITIVE | ResourcePropertyKind::KEY, $intResourceType);
        $dateTimeResourceType = ResourceType::getPrimitiveResourceType(EdmPrimitiveType::DATETIME);
        $orderDatePrimProperty = new ResourceProperty('OrderDate', null, ResourcePropertyKind::PRIMITIVE, $dateTimeResourceType);
        $stringResourceType = ResourceType::getPrimitiveResourceType(EdmPrimitiveType::STRING);
        $orderShipNamePrimProperty = new ResourceProperty('ShipName', null, ResourcePropertyKind::PRIMITIVE, $stringResourceType);
        $orderResType->addProperty($orderIDPrimProperty);
        $orderResType->addProperty($orderDatePrimProperty);
        $orderResType->addProperty($orderShipNamePrimProperty);
        return $orderResType;
    }


}