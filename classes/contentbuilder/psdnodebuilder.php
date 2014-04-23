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

    const STR_ORPHANED     = 'Orphaned Object with Remote-ID "%s" already exists and will be removed.';
    const STR_CREATE_BELOW = 'Create node "%s" below "%s" with remoteId "%s" ';
    const STR_CREATED_WITH = '  -> Created with NodeID %s, ObjectID %s.';


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

    protected $systemLanguages = [];

    protected $languages = [];

    protected $defaultLanguage = '';


    /**
     * Constructs the object and takes a structure.
     *
     * @param psdContentBuilder $builder psdContentBuilder Instance of the calling ContentBuilder.
     */
    public function __construct(psdContentBuilder $builder)
    {

        $this->contentBuilder  = $builder;
        $this->cli             = eZCLI::instance();
        $this->languages       = eZContentLanguage::prioritizedLanguageCodes();
        $this->defaultLanguage = eZLocale::currentLocaleCode();
        $this->systemLanguages = eZLocale::languageList();

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
            $this->cli->output(sprintf(self::STR_ORPHANED, $info->remoteId));
            $nodeWithRemoteId->remove();
        }

        $url = '/'.$locationNode->attribute('url');
        $this->cli->output(sprintf(self::STR_CREATE_BELOW, $info->name, $url, $info->remoteId));

        $object = $this->publishNode($locationNode, $info);

        if (!($object instanceof eZContentObject)) {
            throw new psdContentBuilderValidationException('Failed to create new node.');
        }

        $this->cli->output(sprintf(self::STR_CREATED_WITH, $object->mainNodeID(), $object->ID), true);

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

        $result  = new psdNodeBuilderNodeInfo($this->languages);

        // The class-key is required.
        if (!array_key_exists('class', $structure) || empty($structure['class'])) {
            throw new Exception('Required key "class" for node is missing or empty.');
        }
        $classIdentifier  = $structure['class'];
        $class            = eZContentClass::fetchByIdentifier($classIdentifier);

        if (!$class instanceof eZContentClass) {
            throw new Exception(sprintf('Can not create Node, the class "%s" is not registered.', $classIdentifier));
        }

        $result->name = $this->getObjectNameWithPattern($class->ContentObjectName, $structure);

        if (empty($result->name)) {
            $class->name();
        }

        if (array_key_exists('children', $structure) && is_array($structure['children'])) {
            $result->children = $structure['children'];
        }

        if (array_key_exists('_post_publish', $structure)) {
            $result->setPostPublishFields($structure['_post_publish']);
        }

        $result->options = $this->getNodeInfoOptions($class, $structure);
        $result->options['class_identifier'] = $classIdentifier;

        $result->remoteId = $result->options['remote_id'];

        // First get fields for default language (no additional hierarchy).
        $result = $this->getNodeInfoFieldsForLanguage($result, $class, $structure, '');

        // Then aquire fields for all additional registered languages.
        foreach ($this->languages as $language) {
            $result = $this->getNodeInfoFieldsForLanguage($result, $class, $structure, $language);
        }

        return $result;

    }


    /**
     * Builds an object-name from the provided structure, based on the class's object-name-pattern.
     *
     * @param string $pattern eZ Object-Name-Pattern.
     * @param array $structure Structure with attributes (array, not a data-map!)
     * @return string Object name.
     */
    protected function getObjectNameWithPattern($pattern, $structure)
    {

        // get parts of object's name pattern( like <attr1|attr2>, <attr3> )
        $objectNamePattern = '|<([^>]+)>|U';

        $result = preg_replace_callback(
            $objectNamePattern,
            function ($matches) use ($structure) {

                $tagName = str_replace("<", "", $matches[0]);
                $tagName = str_replace(">", "", $tagName);

                $tagParts = explode('|', $tagName);

                foreach ($tagParts as $part) {
                    if (array_key_exists($part, $structure) && !empty($structure[$part])) {
                        return strval($structure[$part]);
                    }
                }

                return '';

            },
            $pattern
        );

        return $result;

    }


    /**
     * collects content-object-attributes (non-data-map attributes) from the strucutre.
     *
     * @param eZContentClass $class
     * @param array $structure
     * @return array
     */
    protected function getNodeInfoOptions(eZContentClass $class, array $structure)
    {

        $options = [
            'remote_id' => $this->remoteIdPrefix.':'.md5((string) microtime().(string) mt_rand())
        ];

        foreach ($structure as $key => $value) {

            $context = $this->getFieldContext($class, $key);

            if ($context == self::FIELD_CONTEXT_CLASS) {
                $options[$key] = $value;
            }
        }

        return $options;

    }


    /**
     * Collects data-map attributes per language from provided structure.
     *
     * @param psdNodeBuilderNodeInfo $nodeInfo Existing node-info to operate on.
     * @param eZContentClass $class Class of the node.
     * @param array $structure Structure to collect the attributes from.
     * @param string $language Language-code, empty for default.
     *
     * @return psdNodeBuilderNodeInfo Amended node-info.
     */
    protected function getNodeInfoFieldsForLanguage(
        psdNodeBuilderNodeInfo $nodeInfo,
        eZContentClass $class,
        array $structure,
        $language = ''
    ) {

        if (empty($language)) {
            $language = $this->defaultLanguage;
        }

        // Ignore non-existent languages.
        if (!in_array($language, $this->languages)) {
            return $nodeInfo;
        }

        // Only process existing language-definitions beside the default language.
        if ($language != $this->defaultLanguage
            && !array_key_exists($language, $structure)
            && !empty($structure['language'])
        ) {
            return $nodeInfo;
        }

        $attrs   = [];

        // Use either the the whole structure as context or just the part for the language.
        if ($language == $this->defaultLanguage) {
            $attrs = $structure;
        } elseif ($language != $this->defaultLanguage && array_key_exists($language, $structure)) {
            $attrs = $structure[$language];
        }

        foreach ($attrs as $key => $value) {

            // Skip any language-keys.
            if (in_array($key, $this->systemLanguages)) {
                continue;
            }

            // Process Yaml functions as they are encountered.
            $value = $this->contentBuilder->postProcessNode($value);

            // Translate properties. Only process data-map attributes according to the class-definition.
            $context = $this->getFieldContext($class, $key);

            if ($context == self::FIELD_CONTEXT_DATAMAP) {

                // Check if there's custom builder registered for this data-type.
                $attr = $class->fetchAttributeByIdentifier($key);
                if ($attr instanceof eZContentClassAttribute
                    && array_key_exists($attr->attribute('data_type_string'), $this->dataTypeBuilders)
                ) {

                    $nodeInfo->addCustomFieldWithLanguage(
                        $key,
                        array($attr->attribute('data_type_string'), $value),
                        $language
                    );

                    continue;
                }

                // Otherwise let the SQLi-Import handle this.
                $nodeInfo->addFieldWithLanguage($key, $value, $language);
            }

        }//end foreach

        return $nodeInfo;

    }


    /**
     * Publishes a node with the specified info-set.
     *
     * @param eZContentObjectTreeNode $locationNode Parent-node for the new node.
     * @param psdNodeBuilderNodeInfo $info NodeInfo-Structure that defines the node to be built.
     * @param boolean $postPublish Defines if psdNodeBuilderNodeInfo::postPublishFields are evaluated.
     *
     * @throws Exception
     * @return eZContentObject
     */
    protected function publishNode(
        eZContentObjectTreeNode $locationNode,
        psdNodeBuilderNodeInfo $info,
        $postPublish = false
    ) {

        $emptyDefaultTranslation = false;
        if (empty($info->fields[$this->defaultLanguage]) && empty($info->customFields[$this->defaultLanguage])) {
            $emptyDefaultTranslation = true;
        }

        $options = new \SQLIContentOptions($info->options);
        $content = \SQLIContent::create($options);

        // Create translations and add from-string-properties.
        foreach ($info->availableLanguages as $language) {

            // Skip default language if empty.
            if ($emptyDefaultTranslation && $language == $this->defaultLanguage) {
                continue;
            }

            // Properties always base on the default language.
            $properties = $info->fields[$this->defaultLanguage];

            // Distinguish between default and additional language.
            if ($language == $this->defaultLanguage) {
                $fields = $content->fields;
            } else {
                $content->addTranslation($language);
                $fields = $content->fields[$language];

                // Merges properties of the default language with an additional language's properties.
                $properties = array_merge($properties, $info->fields[$language]);
            }

            foreach ($properties as $property => $value) {

                // Skip fields marked as post-Publish if not in Post-Publish mode.
                if (!$postPublish && in_array($property, $info->postPublishFields)) {
                    continue;
                }

                $fields->$property = $value;
            }

        }


        // Only add new locations in order to catch warnings on existing ones.
        $location = \SQLILocation::fromNodeID($locationNode->attribute('node_id'));
        if (!self::contentHasLocation($location, $content)) {
            $content->addLocation($location, $content);
        }

        $object = $content->getRawContentObject();

        // Build custom attributes for each language.
        foreach ($info->availableLanguages as $language) {

            // Skip default language if empty.
            if ($emptyDefaultTranslation && $language == $this->defaultLanguage) {
                continue;
            }

            // Properties always base on the default language.
            $properties = $info->customFields[$this->defaultLanguage];

            // Distinguish between default and additional language.
            if ($language == $this->defaultLanguage) {
                //$fields = $content->fields;
            } else {
                //$fields = $content->fields[$language];

                // Merges properties of the default language with an additional language's properties.
                $properties = array_merge($properties, $info->customFields[$language]);
            }

            $this->contentBuilder->execPath->add($language);

            // Get current language version.
            if ($language == $this->defaultLanguage) {
                $dataMap = $object->dataMap();
            } else {
                $version = $object->currentVersion(true);
                $dataMap = $object->fetchDataMap($version->attribute('version'), $language);
            }

            foreach ($properties as $attribute => $value) {

                // Skip fields marked as post-Publish if not in Post-Publish mode.
                if (!$postPublish && in_array($attribute, $info->postPublishFields)) {
                    continue;
                }

                // Store and restore exec-path, because the data-type builder may modify the data on its own.
                $this->contentBuilder->execPath->store();
                $this->contentBuilder->execPath->add($attribute);

                if (!array_key_exists($attribute, $dataMap)) {
                    throw new Exception(sprintf('Attribute %s not found on object.', $attribute));
                }

                // Resolve possible Yaml-functions below this structure.
                // Do it exactly before creating the attribute, in order to allow fetches in post-publish run.
                $value[1] = $this->contentBuilder->postProcess($value[1]);

                // Reset attribute, just in case.
                $contentAttribute = $dataMap[$attribute];
                $contentAttribute->setContent(null);
                $contentAttribute->store();

                // $value[0] = data_type_string, $value[1] = value from YAML. Ssee getNodeInfoFieldsForLanguage();
                $this->dataTypeBuilders[$value[0]]->apply($object, $contentAttribute, $value[1]);

                $this->contentBuilder->execPath->restore();
            }

            $this->contentBuilder->execPath->pop();
        }

        // Publish page, force publishing built nodes by disabling the modification check.
        $publishOptions = new SQLIContentPublishOptions(array('modification_check' => false));
        $publisher      = \SQLIContentPublisher::getInstance();

        $publisher->setOptions($publishOptions);
        $publisher->publish($content);

        // Reload object in order to reflect changes made during publishing.
        $object = eZContentObject::fetch($object->ID);

        // Re-Publish additional languages.
        foreach ($info->availableLanguages as $language) {

            // Default-language is already published, skip it.
            if ($language == $this->defaultLanguage) {
                continue;
            }

            eZContentFunctions::updateAndPublishObject($object, ['language' => $language, 'attributes' => []]);

        }

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
