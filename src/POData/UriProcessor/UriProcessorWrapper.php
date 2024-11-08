<?php

namespace POData\UriProcessor;

use POData\IService;
use POData\UriProcessor\UriProcessor;

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
class UriProcessorWrapper
{
   
    /**
     * Process the resource path and query options of client's request uri.
     *
     * @param IService $service Reference to the data service instance.
     *
     * @return URIProcessor
     *
     * @throws ODataException
     */
    public function process(IService $service)
    {
        return UriProcessor::process($service);
    }

}
