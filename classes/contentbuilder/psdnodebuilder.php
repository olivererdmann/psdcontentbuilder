<?php
/**
 * Builds a ezContentObjectTreeNode from a provided structure, spawns the creation of children, if specified in the
 * provided structure.
 *
 * @author  Oliver Erdmann, <o.erdmann@finaldream.de>
 * @license GNU General Public License v2
 * @since:  30.10.12
 */
class psdNodeBuilder
{

    const FIELD_CONTEXT_CLASS   = 'class';
    const FIELD_CONTEXT_DATAMAP = 'datamap';

    /**
     * References the contentBuilder-object.
     *
     * @var null|psdContentBuilder;
     */
    public $contentBuilder = null;

    /**
     * Instance of the CLI.
     *
     * @var eZCLI|null
     */
    protected $cli = null;

    /**
     * Structure to apply.
     *
     * @var array|null
     */
    protected $structure = null;

    /**
     * Default part for creating node-id's.
     *
     * @var string
     */
    protected $remoteIdPrefix = 'psdcontentbuilder';

    /**
     * Custom builders can be used to handle certain data-types. Builders are invoked after the actual node is created.
     *
     * @var psdAbstractDatatypeBuilder[]
     */
    protected $dataTypeBuilders = array();

    /**
     * Tell the builder to be talkative.
     *
     * @var boolean
     */
    public $verbose = false;

    /**
     * eZFind search-engine.
     *
     * @var eZSolr
     */
    protected $searchEngine;


    /**
     * Constructs the object and takes a structure.
     *
     * @param psdContentBuilder $builder psdContentBuilder Instance of the calling ContentBuilder.
     */
    public function __construct(psdContentBuilder $builder)
    {

        $this->contentBuilder = $builder;
        $this->cli            = eZCLI::instance();

        $this->loadDatatypeBuilders();

    }


    /**
     * Loads all registered builders for custom data-types.
     *
     * @throws Exception
     *
     * @return void
     */
    protected function loadDatatypeBuilders()
    {
        // Load the function-handlers from the INI.
        $ini = eZINI::instance('psdcontentbuilder.ini');

        // No handlers, nothing to do.
        if (!$ini->hasVariable('Handlers', 'DatatypeBuilder')) {
            return;
        }

        $handlers = $ini->variable('Handlers', 'DatatypeBuilder');

        if (!is_array($handlers)) {
            return;
        }

        foreach ($handlers as $handler => $class) {
            if (!class_exists($class)) {
                throw new Exception(sprintf('DatatypeBuilders: Class %s not found!', $class));
            }

            $instance                         = new $class();
            $instance->contentBuilder         = $this->contentBuilder;
            $instance->verbose                = $this->verbose;
            $this->dataTypeBuilders[$handler] = $instance;
        }

    }


    /**
     * Applies the structure at the specified location.
     *
     * @param array $structure Array structure to apply.
     *
     * @throws psdContentBuilderValidationException If the provided structure is invalid.
     *
     * @return void
     */
    public function apply($structure = array())
    {

        if (!is_array($structure)) {
            throw new psdContentBuilderValidationException('Invalid structure, must be an array.');
        }

        $this->searchEngine = new eZSolr();
        $this->structure = $structure;

        foreach ($structure as $index => $child) {

            $this->contentBuilder->execPath->add($index);

            $value = $this->contentBuilder->postProcessNode($child);

            $this->createNode('', $value);
            $this->contentBuilder->execPath->pop();

        }

        $this->searchEngine->commit();

    }


    /**
     * @todo Resolve title vs. name conflict (try to use url-pattern?).
     *
     * @param string $location  Node location (node, nodeID, remoteID or path).
     * @param array  $structure Structure to create at the specified location.
     *
     * @return eZContentObject
     *
     * @throws psdContentBuilderValidationException
     * @throws Exception
     */
    public function createNode($location = '', $structure = array())
    {

        if (empty($structure)) {
            $structure = $this->structure;
        }

        $structure = array_merge(array(), $structure);

        // Pull location from the structure, in case this is a top-level node.
        if (array_key_exists('parentNode', $structure)) {
            $location = $structure['parentNode'];
            // If parentNode is specified, try to ensure it exists.
            $locationNode = psdContentBuilder::resolveNode($location, true);
        } else {
            $locationNode = psdContentBuilder::resolveNode($location);
        }

        if (!($locationNode instanceof eZContentObjectTreeNode)) {
            throw new Exception('Invalid location.');
        }

        $info = $this->getCreateNodeInfo($structure);

        // Remove existing objects with same Remote-ID, but no Main-Node (orphaned objects from failed builds).
        $nodeWithRemoteId = eZContentObject::fetchByRemoteID($info->remoteId);

        if ($nodeWithRemoteId instanceof eZContentObject && !$nodeWithRemoteId->attribute('main_node_id')) {
            $this->cli->output(
                sprintf(
                    'Orphaned Object with Remote-ID "%s" already exists and will be removed.',
                    $info->remoteId
                )
            );
            $nodeWithRemoteId->remove();
        }

        $url = '/'.$locationNode->attribute('url');
        $this->cli->output(
            sprintf(
                'Create node "%s" below "%s" with remoteId "%s" ',
                $info->name,
                $url,
                $info->remoteId
            )
        );

        $object = $this->publishNode($locationNode, $info);

        if (!($object instanceof eZContentObject)) {
            throw new psdContentBuilderValidationException('Failed to create new node.');
        }

        $this->cli->output(
            sprintf(
                '  -> Created with NodeID %s, ObjectID %s.',
                $object->mainNodeID(),
                $object->ID
            ),
            true
        );

        $this->searchEngine->addObject($object);

        // Remember created node.
        $this->contentBuilder->addUndoNode($object->mainNodeID());

        // Continue to recursively create children, if defined.
        if (is_array($info->children)) {
            $this->contentBuilder->execPath->add('children');

            foreach ($info->children as $index => $child) {
                $this->contentBuilder->execPath->add($index);

                // Process Yaml functions in arrays.
                $child = $this->contentBuilder->postProcessNode($child);

                $this->createNode($object->mainNode(), $child);

                $this->contentBuilder->execPath->pop();
            }

            $this->contentBuilder->execPath->pop();
        }

        // Post Publish, if fields are marked accordingly. Allows the parent to reference child-content.
        if (!empty($info->postPublishFields)) {
            $this->cli->output(sprintf('Re-publish node "%s".', $info->name));
            $this->publishNode($locationNode, $info, true);
        }

    }


    /**
     * Collects required information on the current node-definition. Processes each field with the postProcessor.
     *
     * @param array $structure Class-key is required.
     *
     * @throws Exception If class does not exists.
     * @return psdNodeBuilderNodeInfo With fields set.
     */
    protected function getCreateNodeInfo(array $structure)
    {

        $result = new psdNodeBuilderNodeInfo();

        $options      = array();

        // The class-key is required.
        $classIdentifier  = $structure['class'];
        $class            = eZContentClass::fetchByIdentifier($classIdentifier);

        if (!$class instanceof eZContentClass) {
            throw new Exception(sprintf('Can not create Node, the class "%s" is not registered.', $classIdentifier));
        }

        foreach ($structure as $key => $value) {

            // Process Yaml functions as they are encountered.
            $value = $this->contentBuilder->postProcessNode($value);

            // Translate a few properties.
            switch ($key) {
                case 'children':
                    $result->children = $value;
                    break;
                case 'postPublish':
                    if (is_array($value)) {
                        $result->postPublishFields = $value;
                    } else {
                        $result->postPublishFields = array($value);
                    }

                    break;
                case 'name':
                case 'title':
                    if (empty($result->name)) {
                        $result->name = $value;
                    }
                    // Pass on to default.
                default:

                    $context = $this->getFieldContext($class, $key);

                    // Sort the options into different arrays depending on the class-definition.
                    if ($context == self::FIELD_CONTEXT_CLASS) {
                        $options[$key] = $value;
                    } elseif ($context == self::FIELD_CONTEXT_DATAMAP) {

                        // Check if there's custom builder registered for this data-type.
                        $attr = $class->fetchAttributeByIdentifier($key);
                        if ($attr instanceof eZContentClassAttribute
                            && array_key_exists($attr->attribute('data_type_string'), $this->dataTypeBuilders)
                        ) {
                            $result->customFields[$key] = array($attr->attribute('data_type_string'), $value);
                            continue;
                        }

                        // Otherwise let the SQLi-Import handle this.
                        $result->fields[$key] = $value;
                    }

            }//end switch

        }//end foreach

        $options['class_identifier'] = $classIdentifier;

        if (!isset($options['remote_id'])) {
            $options['remote_id'] = $this->remoteIdPrefix.':'.md5((string) microtime().(string) mt_rand());
        }

        $result->remoteId = $options['remote_id'];

        // Create new ContentObject.
        $result->options = new \SQLIContentOptions($options);

        return $result;

    }


    /**
     * Publishes a node with the specified info-set.
     *
     * @param eZContentObjectTreeNode $locationNode
     * @param psdNodeBuilderNodeInfo  $info
     * @param boolean $postPublish    Defines if psdNodeBuilderNodeInfo::postPublishFields are evaluated.
     *
     * @return eZContentObject
     */
    protected function publishNode(
        eZContentObjectTreeNode $locationNode,
        psdNodeBuilderNodeInfo $info,
        $postPublish = false
    ) {

        $content = \SQLIContent::create($info->options);

        foreach ($info->fields as $property => $value) {
            $content->fields->$property = $value;
        }

        // Only add new locations in order to catch warnings on existing ones.
        $location = \SQLILocation::fromNodeID($locationNode->attribute('node_id'));
        if (!self::contentHasLocation($location, $content)) {
            $content->addLocation($location, $content);
        }

        $object = $content->getRawContentObject();

        // Build custom attributes.
        foreach ($info->customFields as $attribute => $value) {

            // Skip fields marked as post-Publish if not in Post-Publish mode.
            if (!$postPublish && in_array($attribute, $info->postPublishFields)) {
                continue;
            }

            // Store and restore exec-path, because the data-type builder may modify the data on its own.
            $this->contentBuilder->execPath->store();
            $this->contentBuilder->execPath->add($attribute);

            $this->dataTypeBuilders[$value[0]]->apply($object, $attribute, $value[1]);

            $this->contentBuilder->execPath->restore();
        }

        // Publish page, force publishing built nodes by disabling the modification check.
        $publishOptions = new SQLIContentPublishOptions(array('modification_check' => false));
        $publisher      = \SQLIContentPublisher::getInstance();

        $publisher->setOptions($publishOptions);
        $publisher->publish($content);

        // Reload object in order to reflect changes made during publishing.
        $object = eZContentObject::fetch($object->ID);

        return $object;

    }


    /**
     * Checks if the provided content already has a specified location.
     * Based on @see \SQLIContentPublisher::addLocationToContent
     *
     * @param SQLILocation $location Location to test.
     * @param SQLIContent  $content  Content to test.
     *
     * @return bool Whether the content has the location. Unpublished content will also return FALSE.
     */
    public static function contentHasLocation(SQLILocation $location, SQLIContent $content)
    {

        $nodeID = $content->attribute('main_node_id');

        // No main node ID, object has not been published at least once.
        if (!$nodeID) {
            return false;
        }

        $locationNodeID = $location->getNodeID();

        // Check if content has already an assigned node in provided location.
        $assignedNodes = $content->assignedNodes(false);
        for ($i = 0, $iMax = count($assignedNodes); $i < $iMax; ++$i) {
            if ($locationNodeID == $assignedNodes[$i]['parent_node_id']) {
                return true;
            }
        }

        return false;

    }


    /**
     * Returns if a field is an class-attribute or a data-map attribute.
     *
     * @param eZContentClass $class Class to check.
     * @param string         $field Name of the field.
     *
     * @return null|string
     */
    public function getFieldContext(eZContentClass $class, $field)
    {

        $dataMap = $class->dataMap();

        if (array_key_exists($field, $dataMap)) {
            return self::FIELD_CONTEXT_DATAMAP;
        }

        if ($class->hasAttribute($field)) {
            return self::FIELD_CONTEXT_CLASS;
        }

        return null;

    }


    /**
     * Sets a new Prefix for generating remote-ids.
     *
     * @param string $value New Prefix.
     *
     * @return void
     */
    public function setRemoteIdPrefix($value)
    {

        $this->remoteIdPrefix = $value;

    }


}
