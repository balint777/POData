<?php


namespace POData\ObjectModel;

use POData\ObjectModel\ODataLink;
use POData\ObjectModel\ODataEntry;
use POData\ObjectModel\ODataPropertyContent;

/**
 * Class ODataFeed
 * @package POData\ObjectModel
 */
class ODataFeed
{
    /**
     *
     * Feed iD
     * @var string
     */
    public $id;

    /**
     *
     * Feed title
     * @var string
     */
    public $title;

    /**
     *
     * Feed self link
     * @var ODataLink
     */
    public $selfLink;

    /**
     *
     * Row count, in case of $inlinecount option
     * @var int
     */
    public $rowCount = null;

    /**
     *
     * Enter URL to next page, if pagination is enabled
     * @var ODataLink
     */
    public $nextPageLink = null;

    /**
     *
     * List of custom properties
     * @var array
     */
    public ODataPropertyContent $customProperties;

    /**
     *
     * Collection of entries under this feed
     * @var ODataEntry[]
     */
    public $entries = array();

    public function __construct()
    {
        $this->customProperties = new ODataPropertyContent();
    }
}
