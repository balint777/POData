<?php

namespace UnitTests\POData\Facets;

/**
 * Note: This is a dummy class for making the testing of 
 * BaseService and UriProcessor.
 */

use POData\Common\ODataConstants;
use POData\OperationContext\ServiceHost;

class ServiceHostTestFake extends ServiceHost
{
    private $_hostInfo;
    
    public function __construct(array $hostInfo)
    {
        $this->_hostInfo = $hostInfo;
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER[ODataConstants::HTTPREQUEST_HEADER_PROTOCOL] = $this->_hostInfo['AbsoluteRequestUri']->getScheme();
        $_SERVER[ODataConstants::HTTPREQUEST_HEADER_HOST] = $this->_hostInfo['AbsoluteRequestUri']->getHost() . ':' .  $this->_hostInfo['AbsoluteRequestUri']->getPort();
        $_SERVER[ODataConstants::HTTPREQUEST_HEADER_URI] = $this->_hostInfo['AbsoluteRequestUri']->getPath();

        if (array_key_exists('DataServiceVersion', $this->_hostInfo)) {
            $_SERVER[ODataConstants::ODATASERVICEVERSION] 
                = $this->_hostInfo['DataServiceVersion']->toString();            
        }

        if (array_key_exists('MaxDataServiceVersion', $this->_hostInfo)) {
            $_SERVER[ODataConstants::ODATAMAXSERVICEVERSION] 
                = $this->_hostInfo['MaxDataServiceVersion']->toString();
        }

        if (array_key_exists('RequestIfMatch', $this->_hostInfo)) {
            $_SERVER[ODataConstants::HTTPREQUEST_HEADER_IFMATCH] 
                = $this->_hostInfo['RequestIfMatch']; 
        }

        if (array_key_exists('RequestIfNoneMatch', $this->_hostInfo)) {
            $_SERVER[ODataConstants::HTTPREQUEST_HEADER_IFNONE]
                = $this->_hostInfo['RequestIfNoneMatch'];
            
        }

        if (array_key_exists('QueryString', $this->_hostInfo)) {
            $_SERVER[ODataConstants::HTTPREQUEST_HEADER_QUERY_STRING] = $this->_hostInfo['QueryString'];
        }
        //print_r($_SERVER); 
        parent::__construct();

        if (array_key_exists('AbsoluteServiceUri', $this->_hostInfo)) {
            $this->setServiceUri($this->_hostInfo['AbsoluteServiceUri']->getUrlAsString());

        }
    }
}