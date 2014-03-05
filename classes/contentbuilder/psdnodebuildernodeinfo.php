<?php
/**
 * A unified data-container for the psdNodeBuilder::createNode-function.
 *
 * @author Oliver Erdmann, <o.erdmann@finaldream.de>
 * @since  04.03.14
 */

class psdNodeBuilderNodeInfo {


    /**
     * Node-name
     *
     * @var string
     */
    public $name = '';

    /**
     * Remote-id
     *
     * @var string
     */
    public $remoteId = '';

    /**
     * Fields handled by the default sqliImport
     *
     * @var array
     */
    public $fields = [];

    /**
     * Fields with registered own handler
     *
     * @var array
     */
    public $customFields = [];

    /**
     * Fields to be processed after all children were created.
     *
     * @var string[]
     */
    public $postPublishFields = [];

    /**
     * @var SQLIContentOptions
     */
    public $options;


    /**
     * The node's children.
     *
     * @var array
     */
    public $children = [];
}