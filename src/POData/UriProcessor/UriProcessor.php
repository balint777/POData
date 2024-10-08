<?php

namespace POData\UriProcessor;

use POData\Providers\ProvidersWrapper;
use POData\Providers\Metadata\ResourcePropertyKind;
use POData\Providers\Metadata\ResourceTypeKind;
use POData\Providers\Metadata\ResourceSetWrapper;
use POData\Providers\Metadata\ResourceProperty;
use POData\Providers\Query\QueryType;
use POData\UriProcessor\QueryProcessor\QueryProcessor;
use POData\UriProcessor\QueryProcessor\ExpandProjectionParser\ExpandedProjectionNode;
use POData\UriProcessor\ResourcePathProcessor\ResourcePathProcessor;
use POData\UriProcessor\ResourcePathProcessor\SegmentParser\SegmentDescriptor;
use POData\UriProcessor\ResourcePathProcessor\SegmentParser\TargetKind;
use POData\UriProcessor\ResourcePathProcessor\SegmentParser\TargetSource;
use POData\IService;
use POData\Common\Messages;
use POData\Common\ODataException;
use POData\Common\InvalidOperationException;
use POData\Common\ODataConstants;
use POData\Providers\Query\QueryResult;
use POData\OperationContext\HTTPRequestMethod;

/**
 * Class UriProcessor
 *
 * A type to process client's requets URI
 * The syntax of request URI is:
 *  Scheme Host Port ServiceRoot ResourcePath ? QueryOption
 * For more details refer:
 * http://www.odata.org/developers/protocols/uri-conventions#UriComponents
 *
 * @package POData\UriProcessor
 */
class UriProcessor
{
    /**
     * Description of the OData request that a client has submitted.
     *
     * @var RequestDescription
     */
    private $request;

    /**
     * Holds reference to the data service instance.
     *
     * @var IService
     */
    private $service;

    /**
     * Holds reference to the wrapper over IDSMP and IDSQP implementation.
     *
     * @var ProvidersWrapper
     */
    private $providers;

    /**
     * Collection of segment names.
     *
     * @var string[]
     */
    private $_segmentNames;

    /**
     * Collection of segment ResourceSetWrapper instances.
     *
     * @var ResourceSetWrapper[]
     */
    private $_segmentResourceSetWrappers;

    /**
     * Constructs a new instance of UriProcessor
     *
     * @param IService $service Reference to the data service instance.
     */
    private function __construct(IService $service)
    {
        $this->service = $service;
        $this->providers = $service->getProvidersWrapper();
        $this->_segmentNames = array();
        $this->_segmentResourceSetWrappers = array();
    }

    /**
     * Process the resource path and query options of client's request uri.
     *
     * @param IService $service Reference to the data service instance.
     *
     * @return URIProcessor
     *
     * @throws ODataException
     */
    public static function process(IService $service)
    {
        $absoluteRequestUri = $service->getHost()->getAbsoluteRequestUri();
        $absoluteServiceUri = $service->getHost()->getAbsoluteServiceUri();

        if (!$absoluteServiceUri->isBaseOf($absoluteRequestUri)) {
            throw ODataException::createInternalServerError(
                Messages::uriProcessorRequestUriDoesNotHaveTheRightBaseUri(
                    $absoluteRequestUri->getUrlAsString(),
                    $absoluteServiceUri->getUrlAsString()
                )
            );
        }

        $uriProcessor = new UriProcessor($service);
        //Parse the resource path part of the request Uri.
        $uriProcessor->request = ResourcePathProcessor::process($service);

        $uriProcessor->request->setUriProcessor($uriProcessor);

        //Parse the query string options of the request Uri.
        QueryProcessor::process($uriProcessor->request, $service);

        return $uriProcessor;
    }

    /**
     * Process the resource path and query options of client's request uri.
     *
     * @param IService $service Reference to the data service instance.
     *
     * @return URIProcessor
     *
     * @throws ODataException
     */
    public static function processPart($service, $request)
    {
        $uriProcessor = new UriProcessor($service);
        //Parse the resource path part of the request Uri.
        $uriProcessor->request = $request;

        $request->setUriProcessor($uriProcessor);


        //Parse the query string options of the request Uri.
        QueryProcessor::process($uriProcessor->request, $service);

        return $uriProcessor;
    }

    /**
     * Gets reference to the request submitted by client.
     *
     * @return RequestDescription
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * Execute the client submitted request against the data source.
     */
    public function execute()
    {
        $operationContext = $this->service->getOperationContext();
        if (!$operationContext) {
            $this->executeBase($this->request);
            return;
        }

        $requestMethod = $operationContext->incomingRequest()->getMethod();
        if ($requestMethod == HTTPRequestMethod::GET) {
            $this->executeGet($this->request);
        } elseif ($requestMethod == HTTPRequestMethod::PUT) {
            $this->executePut($this->request);
        } elseif ($requestMethod == HTTPRequestMethod::POST) {
            if ($this->request->getLastSegment()->getTargetKind() == TargetKind::BATCH) {
                $this->executeBatch($this->request);
            } else {
                $this->executePost($this->request);
            }
        } elseif ($requestMethod == HTTPRequestMethod::DELETE) {
            $this->executeDelete($this->request);
        } else {
            throw ODataException::createNotImplementedError(Messages::unsupportedMethod($requestMethod));
        }
    }

    /**
     * Execute the client submitted request against the data source (GET)
     */
    protected function executeGet(RequestDescription $request)
    {
        return $this->executeBase($request);
    }

    /**
     * Execute the client submitted request against the data source (PUT)
     */
    protected function executePut(RequestDescription $request)
    {
        $callback = function($uriProcessor, $segment) use ($request) {
            $requestMethod = $request->getRequestMethod();
            $resourceSet = $segment->getTargetResourceSetWrapper();
            $keyDescriptor = $segment->getKeyDescriptor();
            $data = $uriProcessor->request->getData();

            if (!$resourceSet || !$keyDescriptor) {
                $url = $request->getRequestUrl()->getUrlAsString();
                throw ODataException::createBadRequestError(Messages::badRequestInvalidUriForThisVerb($url, $requestMethod));
            }

            if (!$data) {
                throw ODataException::createBadRequestError(Messages::noDataForThisVerb($requestMethod));
            }

            $expand = $this->_getExpandedProjectionNodes($request);
            $filter = $request->getFilterInfo();
            $result = $uriProcessor->providers->putResource($resourceSet, $request, $keyDescriptor, $data, $filter, $expand);

            $segment->setSingleResult(true);
            $segment->setResult($result);

            return $result;
        };

        $segments = $request->getSegments();

        foreach ($segments as $segment) {
            if (is_null($segment->getNext()) || $segment->getNext()->getIdentifier() == ODataConstants::URI_COUNT_SEGMENT) {
                $this->applyQueryOptions($request, $segment, $callback);
            }
        }
            //?? TODO : TEST
            // Apply $select and $expand options to result set, this function will be always applied
            // irrespective of return value of IDSQP2::canApplyQueryOptions which means library will
            // not delegate $expand/$select operation to IDSQP2 implementation
        $this->handleExpansion($request);
    }

    /**
     * Execute the client submitted request against the data source (POST)
     */
    protected function executePost(RequestDescription $request)
    {
        $callback = function($uriProcessor, $segment) use ($request) {
            $requestMethod = $request->getRequestMethod();
            $resourceSet = $segment->getTargetResourceSetWrapper();
            $data = $request->getData();

            if (!$resourceSet) {
                $url = $uriProcessor->service->getHost()->getAbsoluteRequestUri()->getUrlAsString();
                throw ODataException::createBadRequestError(Messages::badRequestInvalidUriForThisVerb($url, $requestMethod));
            }

            if (!$data) {
                throw ODataException::createBadRequestError(Messages::noDataForThisVerb($requestMethod));
            }

            $result = $uriProcessor->providers->postResource($resourceSet, $request, $data);

            $segment->setSingleResult(true);
            $segment->setResult($result);

            return $result;
        };

        $segments = $request->getSegments();

        foreach ($segments as $segment) {
            if (is_null($segment->getNext()) || $segment->getNext()->getIdentifier() == ODataConstants::URI_COUNT_SEGMENT) {
                $this->applyQueryOptions($request, $segment, $callback);
            }
        }
            //?? TODO : TEST
            // Apply $select and $expand options to result set, this function will be always applied
            // irrespective of return value of IDSQP2::canApplyQueryOptions which means library will
            // not delegate $expand/$select operation to IDSQP2 implementation
        $this->handleExpansion($request);
    }

    /**
     * Execute the client submitted request against the data source (DELETE)
     */
    protected function executeDelete(RequestDescription $request)
    {
        return $this->executeBase($request, function($uriProcessor, $segment) use ($request) {
            $requestMethod = $request->getRequestMethod();
            $resourceSet = $segment->getTargetResourceSetWrapper();
            $keyDescriptor = $segment->getKeyDescriptor();

            if (!$resourceSet || !$keyDescriptor) {
                $url = $uriProcessor->service->getHost()->getAbsoluteRequestUri()->getUrlAsString();
                throw ODataException::createBadRequestError(Messages::badRequestInvalidUriForThisVerb($url, $requestMethod));
            }

            $result = $uriProcessor->providers->deleteResource($resourceSet, $request, $keyDescriptor);
            $segment->setResult($result);
            return $result;
        });
    }

    /**
     * Execute the client submitted batch request against the data source (POST)
     */
    protected function executeBatch(RequestDescription $request)
    {
        $callback = null;
        $post_callback = function($uriProcessor, $segment) {
            $requestMethod = $uriProcessor->service->getOperationContext()->incomingRequest()->getMethod();
            $resourceSet = $segment->getTargetResourceSetWrapper();
            $data = $uriProcessor->request->getData();

            if (!$resourceSet) {
                $url = $uriProcessor->service->getHost()->getAbsoluteRequestUri()->getUrlAsString();
                throw ODataException::createBadRequestError(Messages::badRequestInvalidUriForThisVerb($url, $requestMethod));
            }

            if (!$data) {
                throw ODataException::createBadRequestError(Messages::noDataForThisVerb($requestMethod));
            }

            $entity = $uriProcessor->providers->postResource($resourceSet, $data);

            $segment->setSingleResult(true);
            $segment->setResult($entity);

            return $entity;
        };

        foreach ($request->getParts() as $request) {
            $this->providers->getExpressionProvider($request)->clear();

            switch ($request->getRequestMethod()) {
                case HTTPRequestMethod::GET:
                    $this->executeGet($request);
                    $request->setExecuted();
                    break;
                case HTTPRequestMethod::PUT:
                    $this->executePut($request);
                    $request->setExecuted();
                case HTTPRequestMethod::POST:
                    $this->executePost($request);
                    $request->setExecuted();
                    break;
                case HTTPRequestMethod::DELETE:
                    $this->executeDelete($request);
                    $request->setExecuted();
                    break;
            }
        }

        return;
        return $this->executeBase($request, function($uriProcessor, $segment) {
            $requestMethod = $uriProcessor->service->getOperationContext()->incomingRequest()->getMethod();
            $resourceSet = $segment->getTargetResourceSetWrapper();
            $data = $uriProcessor->request->getData();

            if (!$resourceSet) {
                $url = $uriProcessor->service->getHost()->getAbsoluteRequestUri()->getUrlAsString();
                throw ODataException::createBadRequestError(Messages::badRequestInvalidUriForThisVerb($url, $requestMethod));
            }

            if (!$data) {
                throw ODataException::createBadRequestError(Messages::noDataForThisVerb($requestMethod));
            }

            $entity = $uriProcessor->providers->postResource($resourceSet, $data);

            $segment->setSingleResult(true);
            $segment->setResult($entity);

            return $entity;
        });
    }

    /**
     * Execute the client submitted request against the data source
     *
     * @param callable $callback Function, what must be called
     */
    protected function executeBase(RequestDescription $request, $callback = null)
    {
        $segments = $request->getSegments();

        foreach ($segments as $segment) {

            $requestTargetKind = $segment->getTargetKind();

            if ($segment->getTargetSource() == TargetSource::ENTITY_SET) {
                $this->handleSegmentTargetsToResourceSet($segment, $request);
            } else if ($requestTargetKind == TargetKind::RESOURCE) {
                if (is_null($segment->getPrevious()->getResult())) {
                    throw ODataException::createResourceNotFoundError(
                        $segment->getPrevious()->getIdentifier()
                    );
                }
                $this->_handleSegmentTargetsToRelatedResource($request, $segment);
            } else if ($requestTargetKind == TargetKind::LINK) {
                $segment->setResult($segment->getPrevious()->getResult());
            } else if ($segment->getIdentifier() == ODataConstants::URI_COUNT_SEGMENT) {
                // we are done, $count will the last segment and
                // taken care by _applyQueryOptions method
                $segment->setResult($request->getCountValue());
                break;
            } else {
                if ($requestTargetKind == TargetKind::MEDIA_RESOURCE) {
                    if (is_null($segment->getPrevious()->getResult())) {
                        throw ODataException::createResourceNotFoundError(
                            $segment->getPrevious()->getIdentifier()
                        );
                    }
                    // For MLE and Named Stream the result of last segment
                    // should be that of previous segment, this is required
                    // while retrieving content type or stream from IDSSP
                    $segment->setResult($segment->getPrevious()->getResult());
                    // we are done, as named stream property or $value on
                    // media resource will be the last segment
                    break;
                }

                $value = $segment->getPrevious()->getResult();
                while (!is_null($segment)) {
                    //TODO: what exactly is this doing here?  Once a null's found it seems everything will be null
                    if (!is_null($value)) {
                        $value = null;
                    } else {
                        try {
                            //see #88
                            $property = new \ReflectionProperty($value, $segment->getIdentifier());
                            $value = $property->getValue($value);
                        } catch (\ReflectionException $reflectionException) {
                            //throw ODataException::createInternalServerError(Messages::orderByParserFailedToAccessOrInitializeProperty($resourceProperty->getName(), $resourceType->getName()));
                        }
                    }

                    $segment->setResult($value);
                    $segment = $segment->getNext();
                    if (!is_null($segment) && $segment->getIdentifier() == ODataConstants::URI_VALUE_SEGMENT) {
                        $segment->setResult($value);
                        $segment = $segment->getNext();
                    }
                }

                break;

            }

            if (is_null($segment->getNext()) || $segment->getNext()->getIdentifier() == ODataConstants::URI_COUNT_SEGMENT) {
                $this->applyQueryOptions($request, $segment, $callback);
            }
        }

            // Apply $select and $expand options to result set, this function will be always applied
            // irrespective of return value of IDSQP2::canApplyQueryOptions which means library will
            // not delegate $expand/$select operation to IDSQP2 implementation
        $this->handleExpansion($request);
    }

    /**
     * Query for a resource set pointed by the given segment descriptor and update the descriptor with the result.
     *
     * @param SegmentDescriptor $segment Describes the resource set to query
     * @return void
     *
     */
    private function handleSegmentTargetsToResourceSet(SegmentDescriptor $segment, RequestDescription $request) {
        if ($segment->isSingleResult()) {
            $entityInstance = $this->providers->getResourceFromResourceSet(
                $segment->getTargetResourceSetWrapper(),
                $request,
                $segment->getKeyDescriptor(),
                $this->_getExpandedProjectionNodes($request)
            );

            $segment->setResult($entityInstance);

        } else {

            $internalskiptokentinfo = $request->getInternalSkipTokenInfo();

            $queryResult = $this->providers->getResourceSet(
                $request->queryType,
                $segment->getTargetResourceSetWrapper(),
                $request,
                $request->getFilterInfo(),
                $request->getInternalOrderByInfo(),
                $request->getTopOptionCount() ?? $request->getTopCount(),
                $request->getSkipCount(),
                $internalskiptokentinfo ? $internalskiptokentinfo->getSkipTokenInfo() : null,
                $this->_getExpandedProjectionNodes($request)
            );
            $segment->setResult($queryResult);
        }
    }

    /**
     * Query for a related resource set or resource set reference pointed by the
     * given segment descriptor and update the descriptor with the result.
     *
     * @param SegmentDescriptor &$segment Describes the related resource
     *                                              to query.
     *
     * @return void
     */
    private function _handleSegmentTargetsToRelatedResource(RequestDescription $request, SegmentDescriptor $segment) {
        $projectedProperty = $segment->getProjectedProperty();
        $projectedPropertyKind = $projectedProperty->getKind();

        if ($projectedPropertyKind == ResourcePropertyKind::RESOURCESET_REFERENCE) {
            if ($segment->isSingleResult()) {
                $entityInstance = $this->providers->getResourceFromRelatedResourceSet(
                    $segment->getPrevious()->getTargetResourceSetWrapper(),
                    $segment->getPrevious()->getResult(),
                    $segment->getTargetResourceSetWrapper(),
                    $projectedProperty,
                    $segment->getKeyDescriptor()
                );

                $segment->setResult($entityInstance);
            } else {
                $queryResult = $this->providers->getRelatedResourceSet(
                    $request->queryType,
                    $segment->getPrevious()->getTargetResourceSetWrapper(),
                    $segment->getPrevious()->getResult(),
                    $segment->getTargetResourceSetWrapper(),
                    $segment->getProjectedProperty(),
                    $request->getFilterInfo(),
                    //TODO: why are these null?  see #98
                    null, // $orderby
                    null, // $top
                    null  // $skip
                );

                $segment->setResult($queryResult);
            }
        } else if ($projectedPropertyKind == ResourcePropertyKind::RESOURCE_REFERENCE) {
            $entityInstance = $this->providers->getRelatedResourceReference(
                $segment->getPrevious()->getTargetResourceSetWrapper(),
                $segment->getPrevious()->getResult(),
                $segment->getTargetResourceSetWrapper(),
                $segment->getProjectedProperty()
            );

            $segment->setResult($entityInstance);
        } else {
            //Unexpected state
        }
    }

    /**
     * Applies the query options to the resource(s) retrieved from the data source.
     *
     * @param SegmentDescriptor $segment The descriptor which holds resource(s) on which query options to be applied.
     * @param callable $callback Function, what must be called
     *
     */
    private function applyQueryOptions(RequestDescription $request, SegmentDescriptor $segment, $callback = null)
    {
        // For non-GET methods
        if ($callback) {
            $callback($this, $segment);
            return;
        }

        //TODO: I'm not really happy with this..i think i'd rather keep the result the QueryResult
        //not even bother with the setCountValue stuff (shouldn't counts be on segments?)
        //and just work with the QueryResult in the object model serializer
        $result = $segment->getResult();

        if (!$result instanceof QueryResult) {
            //If the segment isn't a query result, then there's no paging or counting to be done
            return;
        }


        // Note $inlinecount=allpages means include the total count regardless of paging..so we set the counts first
        // regardless if POData does the paging or not.
        if ($request->queryType == QueryType::ENTITIES_WITH_COUNT) {
            if ($this->providers->handlesOrderedPaging()) {
                $request->setCountValue($result->count);
            } else {
                $request->setCountValue(count($result->results));
            }
        }

        //Have POData perform paging if necessary
        if (!$this->providers->handlesOrderedPaging() && !empty($result->results)) {
            $result->results = $this->performPaging($request, $result->results);
        }

        //a bit surprising, but $skip and $top affects $count so update it here, not above
        //IE  data.svc/Collection/$count?$top=10 returns 10 even if Collection has 11+ entries
        if ($request->queryType == QueryType::COUNT) {
            if ($this->providers->handlesOrderedPaging()) {
                $request->setCountValue($result->count);
            } else {
                $request->setCountValue(count($result->results));
            }
        }

        $segment->setResult($result->results);
    }

    /**
     * If the provider does not perform the paging (ordering, top, skip) then this method does it
     *
     * @param array $result
     * @return array
     */
    private function performPaging(RequestDescription $request, array $result)
    {
        //Apply (implicit and explicit) $orderby option
        $internalOrderByInfo = $request->getInternalOrderByInfo();
        if (!is_null($internalOrderByInfo)) {
            $orderByFunction = $internalOrderByInfo->getSorterFunction()->getReference();
            usort($result, $orderByFunction);
        }

        //Apply $skiptoken option
        $internalSkipTokenInfo = $request->getInternalSkipTokenInfo();
        if (!is_null($internalSkipTokenInfo)) {
            $matchingIndex = $internalSkipTokenInfo->getIndexOfFirstEntryInTheNextPage($result);
            $result = array_slice($result, $matchingIndex);
        }

        //Apply $top and $skip option
        if (!empty($result)) {
            $top = $request->getTopCount();
            $skip = $request->getSkipCount();
            if (is_null($skip)) {
                $skip = 0;
            }

            $result = array_slice($result, $skip, $top);
        }

        return $result;
    }


    /**
     * Perform expansion.
     *
     * @return void
     */
    private function handleExpansion(RequestDescription $request)
    {
        $node = $request->getRootProjectionNode();
        if (!is_null($node) && $node->isExpansionSpecified()) {
            $result = $request->getTargetResult();
            if (!is_null($result) || is_iterable($result) && !empty($result)) {
                $needPop = $this->_pushSegmentForRoot($request);
                $this->_executeExpansion($request, $result);
                $this->_popSegment($needPop);
            }
        }
    }

    /**
     * Execute queries for expansion.
     *
     * @param array(mixed)/mixed $result Resource(s) whose navigation properties needs to be expanded.
     *
     *
     * @return void
     */
    private function _executeExpansion(RequestDescription $request, $result)
    {
        $expandedProjectionNodes = $this->_getExpandedProjectionNodes($request);
        foreach ($expandedProjectionNodes as $expandedProjectionNode) {
            $isCollection = $expandedProjectionNode->getResourceProperty()->getKind() == ResourcePropertyKind::RESOURCESET_REFERENCE;
            $projectedProperty = $expandedProjectionNode->getResourceProperty();
            $expandedPropertyName = $projectedProperty->getName();
            $currentResourceSet = $this->_getCurrentResourceSetWrapper($request)->getResourceSet();
            $currentResourceSetType = $currentResourceSet->getResourceType()->getInstanceType();
            $expandedPropertyReflection = $currentResourceSetType->getProperty($expandedPropertyName);
            $expandedPropertyReflection->setAccessible(true);
            $resourceSetOfProjectedProperty = $expandedProjectionNode->getResourceSetWrapper()->getResourceSet();

            if (is_iterable($result)) {
                foreach ($result as $entry) {
                    // Check for null entry
                    if ($isCollection) {
                        $result1 = $this->providers->getRelatedResourceSet(
                            QueryType::ENTITIES, //it's always entities for an expansion
                            $currentResourceSet,
                            $entry,
                            $resourceSetOfProjectedProperty,
                            $projectedProperty,
                            null, // $filter
                            null, // $orderby
                            null, // $top
                            null  // $skip
                        )->results;
                        if (!empty($result1)) {
                            $internalOrderByInfo = $expandedProjectionNode->getInternalOrderByInfo();
                            /*if (!is_null($internalOrderByInfo)) {
                                $orderByFunction = $internalOrderByInfo->getSorterFunction()->getReference();
                                usort($result1, $orderByFunction);
                                unset($internalOrderByInfo);
                                $takeCount = $expandedProjectionNode->getTakeCount();
                                if (!is_null($takeCount)) {
                                    $result1 = array_slice($result1, 0, $takeCount);
                                }
                            }*/

                            $expandedPropertyReflection->setValue($entry, $result1);

                            $projectedProperty = $expandedProjectionNode->getResourceProperty();
                            $needPop = $this->_pushSegmentForNavigationProperty(
                                $request,
                                $projectedProperty
                            );
                            $this->_executeExpansion($request, $result1);
                            $this->_popSegment($needPop);
                        } else {
                            $expandedPropertyReflection->setValue($entry, $result1);
                        }
                    } else {
                        $result1 = $this->providers->getRelatedResourceReference(
                            $currentResourceSet,
                            $entry,
                            $resourceSetOfProjectedProperty,
                            $projectedProperty
                        );

                        if (is_array($entry)) $entry[$expandedPropertyName] = $result1;
                        else $expandedPropertyReflection->setValue($entry, $result1);

                        if (!is_null($result1)) {
                            $projectedProperty3 = $expandedProjectionNode->getResourceProperty();
                            $needPop = $this->_pushSegmentForNavigationProperty(
                                $request,
                                $projectedProperty3
                            );
                            $this->_executeExpansion($request, $result1);
                            $this->_popSegment($needPop);
                        }
                    }
                }
            } else {
                if ($isCollection) {
                    $currentResourceSet2 = $this->_getCurrentResourceSetWrapper($request)->getResourceSet();
                    $resourceSetOfProjectedProperty2 = $expandedProjectionNode->getResourceSetWrapper()->getResourceSet();
                    $projectedProperty4 = $expandedProjectionNode->getResourceProperty();
                    $result1 = $this->providers->getRelatedResourceSet(
                        QueryType::ENTITIES, //it's always entities for an expansion
                        $currentResourceSet2,
                        $result,
                        $resourceSetOfProjectedProperty2,
                        $projectedProperty4,
                        null, // $filter
                        null, // $orderby
                        null, // $top
                        null  // $skip
                    )->results;
                    if (!empty($result1)) {
                        $internalOrderByInfo = $expandedProjectionNode->getInternalOrderByInfo();
                        /*
                        if (!is_null($internalOrderByInfo)) {
                            $orderByFunction = $internalOrderByInfo->getSorterFunction()->getReference();
                            usort($result1, $orderByFunction);
                            unset($internalOrderByInfo);
                            $takeCount = $expandedProjectionNode->getTakeCount();
                            if (!is_null($takeCount)) {
                                $result1 = array_slice($result1, 0, $takeCount);
                            }
                        }
                        */

                        $expandedPropertyReflection->setValue($result, $result1);
                        $projectedProperty7 = $expandedProjectionNode->getResourceProperty();
                        $needPop = $this->_pushSegmentForNavigationProperty(
                            $request,
                            $projectedProperty7
                        );
                        $this->_executeExpansion($request, $result1);
                        $this->_popSegment($needPop);
                    } else {
                        $expandedPropertyReflection->setValue($result, $result1);
                    }
                } else {
                    $currentResourceSet3 = $this->_getCurrentResourceSetWrapper($request)->getResourceSet();
                    $resourceSetOfProjectedProperty3 = $expandedProjectionNode->getResourceSetWrapper()->getResourceSet();
                    $projectedProperty5 = $expandedProjectionNode->getResourceProperty();
                    $result1 = $this->providers->getRelatedResourceReference(
                        $currentResourceSet3,
                        $result,
                        $resourceSetOfProjectedProperty3,
                        $projectedProperty5
                    );
                    $expandedPropertyReflection->setValue($result, $result1);
                    if (!is_null($result1)) {
                        $projectedProperty6 = $expandedProjectionNode->getResourceProperty();
                        $needPop = $this->_pushSegmentForNavigationProperty(
                            $request,
                            $projectedProperty6
                        );
                        $this->_executeExpansion($request, $result1);
                        $this->_popSegment($needPop);
                    }
                }
            }
        }
    }

    /**
     * Resource set wrapper for the resource being retireved.
     *
     * @return ResourceSetWrapper
     */
    private function _getCurrentResourceSetWrapper(RequestDescription $request)
    {
        $count = count($this->_segmentResourceSetWrappers);
        if ($count == 0) {
            return $request->getTargetResourceSetWrapper();
        } else {
            return $this->_segmentResourceSetWrappers[$count - 1];
        }
    }

    /**
     * Pushes a segment for the root of the tree
     * Note: Calls to this method should be balanced with calls to popSegment.
     *
     * @return bool true if the segment was pushed, false otherwise.
     */
    private function _pushSegmentForRoot($request)
    {
        $segmentName = $request->getContainerName();
        $segmentResourceSetWrapper
            = $request->getTargetResourceSetWrapper();
        return $this->_pushSegment($request, $segmentName, $segmentResourceSetWrapper);
    }

    /**
     * Pushes a segment for the current navigation property being written out.
     * Note: Refer 'ObjectModelSerializerNotes.txt' for more details about
     * 'Segment Stack' and this method.
     * Note: Calls to this method should be balanced with calls to popSegment.
     *
     * @param ResourceProperty &$resourceProperty Current navigation property
     *                                            being written out
     *
     * @return bool true if a segment was pushed, false otherwise
     *
     * @throws InvalidOperationException If this function invoked with non-navigation
     *                                   property instance.
     */
    private function _pushSegmentForNavigationProperty(RequestDescription $request, ResourceProperty &$resourceProperty)
    {
        if ($resourceProperty->getTypeKind() == ResourceTypeKind::ENTITY) {
            $this->assert(
                !empty($this->_segmentNames),
                '!is_empty($this->_segmentNames'
            );
            $currentResourceSetWrapper = $this->_getCurrentResourceSetWrapper($request);
            $currentResourceType = $currentResourceSetWrapper->getResourceType();
            $currentResourceSetWrapper = $this->service
                ->getProvidersWrapper()
                ->getResourceSetWrapperForNavigationProperty(
                    $currentResourceSetWrapper,
                    $currentResourceType,
                    $resourceProperty
                );

            $this->assert(
                !is_null($currentResourceSetWrapper),
                '!null($currentResourceSetWrapper)'
            );
            return $this->_pushSegment(
                $request,
                $resourceProperty->getName(),
                $currentResourceSetWrapper
            );
        } else {
            throw new InvalidOperationException(
                'pushSegmentForNavigationProperty should not be called with non-entity type'
            );
        }
    }

    /**
     * Gets collection of expanded projection nodes under the current node.
     *
     * @return ExpandedProjectionNode[] List of nodes describing expansions for the current segment
     *
     */
    private function _getExpandedProjectionNodes($request)
    {
        $expandedProjectionNode = $this->_getCurrentExpandedProjectionNode($request);
        $expandedProjectionNodes = array();
        if (!is_null($expandedProjectionNode)) {
            foreach ($expandedProjectionNode->getChildNodes() as $node) {
                if ($node instanceof ExpandedProjectionNode) {
                    $expandedProjectionNodes[] = $node;
                }
            }
        }

        return $expandedProjectionNodes;
    }

    /**
     * Find a 'ExpandedProjectionNode' instance in the projection tree
     * which describes the current segment.
     *
     * @return ExpandedProjectionNode|null
     */
    private function _getCurrentExpandedProjectionNode($request)
    {
        $expandedProjectionNode
            = $request->getRootProjectionNode();
        if (!is_null($expandedProjectionNode)) {
            $depth = count($this->_segmentNames);
            if ($depth != 0) {
                for ($i = 1; $i < $depth; $i++) {
                    $expandedProjectionNode
                        = $expandedProjectionNode->findNode($this->_segmentNames[$i]);
                        $this->assert(
                            !is_null($expandedProjectionNode),
                            '!is_null($expandedProjectionNode)'
                        );
                        $this->assert(
                            $expandedProjectionNode instanceof ExpandedProjectionNode,
                            '$expandedProjectionNode instanceof ExpandedProjectionNode'
                        );
                }
            }
        }

        return $expandedProjectionNode;
    }

    /**
     * Pushes information about the segment whose instance is going to be
     * retrieved from the IDSQP implementation
     * Note: Calls to this method should be balanced with calls to popSegment.
     *
     * @param string             $segmentName         Name of segment to push.
     * @param ResourceSetWrapper &$resourceSetWrapper The resource set wrapper
     *                                                to push.
     *
     * @return bool true if the segment was push, false otherwise
     */
    private function _pushSegment($request, $segmentName, ResourceSetWrapper &$resourceSetWrapper)
    {
        $rootProjectionNode = $request->getRootProjectionNode();
        if (!is_null($rootProjectionNode)
            && $rootProjectionNode->isExpansionSpecified()
        ) {
            array_push($this->_segmentNames, $segmentName);
            array_push($this->_segmentResourceSetWrappers, $resourceSetWrapper);
            return true;
        }

        return false;
    }

    /**
     * Pops segment information from the 'Segment Stack'
     * Note: Calls to this method should be balanced with previous calls
     * to _pushSegment.
     *
     * @param boolean $needPop Is a pop required. Only true if last push
     *                         was successful.
     *
     * @return void
     *
     * @throws InvalidOperationException If found un-balanced call
     *                                   with _pushSegment
     */
    private function _popSegment($needPop)
    {
        if ($needPop) {
            if (!empty($this->_segmentNames)) {
                array_pop($this->_segmentNames);
                array_pop($this->_segmentResourceSetWrappers);
            } else {
                throw new InvalidOperationException(
                    'Found non-balanced call to _pushSegment and popSegment'
                );
            }
        }
    }

    /**
     * Assert that the given condition is true.
     *
     * @param boolean $condition         Constion to assert.
     * @param string  $conditionAsString Message to show incase assertion fails.
     *
     * @return void
     *
     * @throws InvalidOperationException
     */
    protected function assert($condition, $conditionAsString)
    {
        if (!$condition) {
            throw new InvalidOperationException(
                "Unexpected state, expecting $conditionAsString"
            );
        }
    }
}
