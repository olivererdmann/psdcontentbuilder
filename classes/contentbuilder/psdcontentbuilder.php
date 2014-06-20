<?php
/**
 * Creates Content Structures from YAML-files. Both default eZ Publish and psdFlow-Structures can be built.
 *
 * @author  Oliver Erdmann, <o.erdmann@finaldream.de>
 * @license GNU General Public License v2
 * @since   29.10.12
 */

// Get eZ!
require_once 'autoload.php';

use Symfony\Component\Yaml\Parser;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use extension\psdcontentbuilder\classes\psdPathLevels;

class psdContentBuilder
{

    const INI_CONTENTBUILDER      = 'psdcontentbuilder.ini';
    const INI_GROUP_VARIABLES     = 'Variables';
    const INI_GROUP_HANDLERS      = 'Handlers';
    const INI_KEY_YAMLFUNCTIONS   = 'YamlFunctions';
    const INI_KEY_DATATYPEBUILDER = 'DatatypeBuilder';


    /**
     * The file to run.
     *
     * @var string
     */
    protected $fileName = '';

    /**
     * Holds the parsed structure.
     *
     * @var array|null
     */
    protected $structure = null;

    /**
     * Current yaml-parser.
     *
     * @var Parser|null
     */
    protected $yaml = null;

    /**
     * Holds an instance to the YamlProcessor.
     *
     * @var null|psdYamlProcessor
     */
    public $postProcessor = null;

    /**
     * If no remote-is specified, use this as a prefix and add some random noise.
     *
     * @var string
     */
    protected $remoteIdPrefix = '';

    /**
     * Remembers node-ids created during operation for easy removal.
     *
     * @var array
     */
    protected $undoNodes = array();

    /**
     * Tell the builder to be talkative.
     *
     * @var boolean
     */
    public $verbose = false;

    /**
     * Holds an external logline callback. Must accept 2 string-parameters.
     *
     * @var void
     */
    public $logLineCallback;

    /**
     * Holds the current path for debugging purposes.
     *
     * @var psdPathLevels
     */
    public $execPath;


    /**
     * Holds configurable parameters, which can be used to create dynamic strings.
     *
     * @var ParameterBag
     */
    public $parameterBag;


    /**
     * Creates a new instance and initializes it with a filename.
     * @todo Constructors for loading from string and array-structure.
     *
     */
    public function __construct()
    {

        $this->execPath     = new psdPathLevels();
        $this->parameterBag = new ParameterBag();

        // Initialize the YAML-Postprocessing.
        $this->loadPostProcessor();
        $this->loadParameterBag();

    }

    protected function loadParameterBag()
    {

        $ini = eZINI::instance(self::INI_CONTENTBUILDER);

        if (!$ini->hasGroup(self::INI_GROUP_VARIABLES)) {
            return;
        }

        $variables = $ini->group(self::INI_GROUP_VARIABLES);

        if (empty($variables)) {
            return;
        }

        $this->parameterBag->add($variables);

    }

    /**
     * Loads and parses the specified YAML-file. The parsed structure is available through $this->structure.
     *
     * @param string $fileName Filename to load. Fails if not exists or not a valid YAML-format.
     *
     * @return void
     *
     * @throws Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException If file not found.
     * @throws Symfony\Component\Yaml\Exception\ParseException If the YAML is not valid.
     *
     * @return void
     */
    public function loadFromFile($fileName)
    {

        $this->structure      = null;
        $this->remoteIdPrefix = basename($fileName);
        $this->fileName       = $fileName;
        $this->structure      = $this->parseFile($fileName);

    }


    /**
     * Provides basic validation of the provided structure by checking some vital elements.
     *
     * @throws psdContentBuilderValidationException If the structure is empty or missing vital elements.
     *
     * @return void
     */
    protected function validate()
    {

        if (!is_array($this->structure)) {
            throw new psdContentBuilderValidationException('Structure is not an array.');
        }

        if (!array_key_exists('content', $this->structure) || !is_array($this->structure['content'])) {
            throw new psdContentBuilderValidationException('Structure is missing a "content"-key.');
        }

        // Resolve functions in all top-level nodes except of "content". This allows eg. includes for assets.
        foreach ($this->structure as $key => $value) {

            if ($key == 'content') {
                continue;
            }

            $this->structure[$key] = $this->postProcess($value);
        }

    }


    /**
     * Returns the structure parsed from the YAML-file.
     *
     * @return array|null The structure or null, not loaded or an error occurred.
     */
    public function getStructure()
    {

        return $this->structure;

    }


    /**
     * Returns the filename of the currently loaded file.
     *
     * @return string Current filename.
     */
    public function getFileName()
    {

        return $this->fileName;

    }


    /**
     * Returns the current parser-instance.
     *
     * @return null|Parser
     */
    public function getParser()
    {

        return $this->yaml;

    }


    public function parseFile($filename)
    {

        if (!file_exists($filename) ||  (false === is_readable($filename))) {
            throw new \Symfony\Component\Yaml\Exception\ParseException(
                sprintf('Unable to parse "%s" as the file is not readable.', $filename)
            );
        }

        if (!($this->yaml instanceof Parser)) {
            $this->yaml = new Parser();
        }

        $content = file_get_contents($filename);

        if (!empty($content)) {
            $content = $this->parameterBag->resolveString($content);
        }

        return $this->yaml->parse($content);

    }


    /**
     * Applies the loaded structure to the content-tree.
     *
     * @param array $structure Optional structure to apply. If empty, expects a previously loaded structure.
     *
     * @return void.
     *
     * @throws psdContentBuilderValidationException If validation fails.
     */
    public function apply(array $structure = array())
    {

        if (!empty($structure)) {
            $this->structure = $structure;
        }

        $this->validate();

        $cli      = eZCLI::instance();
        $this->execPath->add('content');

        $children = $this->structure['content'];


        if (array_key_exists('remoteIdPrefix', $this->structure)) {
            $this->remoteIdPrefix = $this->structure['remoteIdPrefix'];
        }

        try {

            $nodeBuilder          = new psdNodeBuilder($this);
            $nodeBuilder->verbose = $this->verbose;

            $nodeBuilder->setRemoteIdPrefix($this->remoteIdPrefix);
            $nodeBuilder->apply($children);

        } catch (Exception $e) {
            $cli->output('', true);
            $cli->error($e->getMessage(), true);
            $cli->error('Current execution path in '.$this->fileName, true);
            $cli->error('> '.(string) $this->execPath, true);
            $cli->output('', true);

            if ($this->verbose) {
                $cli->output($e->getTraceAsString(), true);
            }
        }

        // Output undo-information.
        if (empty($this->undoNodes)) {
            $cli->output('No nodes created, nothing to undo.', true);
        } else {
            $cli->output('Undo String:', true);
            $cli->output(implode(',', array_reverse($this->undoNodes)), true);
        }

    }


    /**
     * Resolves the provided location into a valid node. The provided location can be a node-instance,
     * a valid node-ID (numeric), a remote-ID (string) or a path (starts with a "/").
     *
     * @param mixed   $location        Specifies the location of a node.
     *                                 Can be node, object, nodeID, remoteID or a path-string.
     * @param boolean $ensureExistence If true, tries to make sure the localtion exists.
     *                                 This only works if a path-string is passed (stating with a "/").
     *
     * @return eZContentObjectTreeNode|null The requested node or null, if the provided data did not lead to anything.
     */
    public static function resolveNode($location, $ensureExistence = false)
    {

        // Node.
        if ($location instanceof eZContentObjectTreeNode) {
            return $location;
        }

        // Object.
        if ($location instanceof eZContentObject) {
            return $location->attribute('main_node');
        }

        if (empty($location)) {
            $location = '/';
        }

        if (is_numeric($location)) {

            // Node-ID.
            return eZContentObjectTreeNode::fetch((int) $location);

        } elseif (is_string($location) && substr($location, 0, 1) === '/') {

            $node = null;

            // Try to ensure the path's existence.
            if ($ensureExistence) {
                $node = self::ensurePathExists($location);
            }

            // Path-String.
            if (!($node instanceof eZContentObjectTreeNode)) {
                $node_id = (int) eZURLAliasML::fetchNodeIDByPath($location);
                $node    = eZContentObjectTreeNode::fetch($node_id);
            }

            if (!($node instanceof eZContentObjectTreeNode)) {
                return null;
            }

            return $node;

        } elseif (is_string($location)) {

            // RemoteID.
            $node = eZContentObjectTreeNode::fetchByRemoteID($location);

            if ($node instanceof eZContentObjectTreeNode) {
                return $node;
            }

            $object = eZContentObject::fetchByRemoteID($location);

            if ($object instanceof eZContentObject) {
                return $object->mainNode();
            }

        }//end if

        return null;

    }


    /**
     * Remembers a node-id for stats and later easy removal.
     *
     * @param integer $nodeID Node-ID to remember.
     */
    public function addUndoNode($nodeID)
    {

        $this->undoNodes[] = $nodeID;

    }


    /**
     * Creates and checks the supplied path, skips existing nodes.
     *
     * @param string $path      The path to create and check.
     * @param string $className Provide a class-name for the newly created nodes.
     *
     * @return bool|eZPersistentObject Returns the lowest level node which the provided path points at.
     *                                 Returns false if the path is not a string.
     * @throws Exception If the creation of missing nodes fails.
     */
    public static function ensurePathExists($path, $className = 'folder')
    {

        if (!is_string($path)) {
            return false;
        }

        $parts    = explode('/', $path);
        $location = '/';
        $nodeId   = eZURLAliasML::fetchNodeIDByPath($location);

        if ($nodeId === false) {
            return false;
        }

        $currentUserId = (int) eZUser::currentUserID();

        $parentNode = eZContentObjectTreeNode::fetch($nodeId);

        foreach ($parts as $part) {
            $location .= $part.'/';
            $nodeId    = eZURLAliasML::fetchNodeIDByPath($location);

            // Node exists, skip here.
            if ($nodeId !== false) {
                $parentNode = eZContentObjectTreeNode::fetch($nodeId);
                continue;
            }

            // Create node.
            $params = array(
                'class_identifier' => $className,
                'creator_id'       => $currentUserId,
                'parent_node_id'   => $parentNode->attribute('node_id')
            );
            $params['creator_id'] = $currentUserId;
            $params['attributes'] = array(
                'name' => ucfirst($part)
            );

            $newObject = eZContentFunctions::createAndPublishObject($params);

            if (!($newObject instanceof eZContentObject)) {
                throw new Exception(sprintf('Failed to create object at location %s', $location));
            }

            $parentNode = $newObject->attribute('main_node');

        }//end foreach

        return $parentNode;

    }


    /**
     * Walks through the parsed structure and tries to execute function-handlers starting with "!...".
     *
     * @param mixed $value Node Value.
     *
     * @return array The processed level of structure.
     */
    public function postProcess($value)
    {

        return $this->postProcessor->process($value);

    }


    /**
     * Processes a single node with the registered post-processor.
     *
     * @param mixed $value Node Value.
     *
     * @return array The processed level of structure.
     */
    public function postProcessNode($value)
    {

        return $this->postProcessor->processNode($value);

    }


    /**
     * Loads the function-handlers from psdcontentbuilder.ini and processes the structure.
     *
     * @param array $handlers Key=function-call, Value=functionHandler.
     *                        If empty handlers are loaded from psdcontentbuilder.ini::Handlers/YamlFunctions[]
     *
     * @return void.
     */
    public function loadPostProcessor(array $handlers = array())
    {

        $this->postProcessor = new psdYamlProcessor();

        // Load the function-handlers from the INI.
        if (empty($handlers)) {
            $ini = eZINI::instance(self::INI_CONTENTBUILDER);

            // No handlers, nothing to do.
            if (!$ini->hasVariable(self::INI_GROUP_HANDLERS, self::INI_KEY_YAMLFUNCTIONS)) {
                return;
            }

            $handlers = $ini->variable(self::INI_GROUP_HANDLERS, self::INI_KEY_YAMLFUNCTIONS);
        }

        $this->postProcessor->setBuilder($this);
        $this->postProcessor->addMultipleFunctionHandlers($handlers);

    }


    /**
     * Calls an optional external logLine-function. Defaults to eZDebug::writeNotice if no callback is defined.
     *
     * @param string $str    String to log.
     * @param string $method Calling method.
     *
     * @return void
     */
    public function logLine($str, $method = '')
    {

        if (is_callable($this->logLineCallback)) {
            $this->logLineCallback($str, $method);

            return;
        }

        eZDebug::writeNotice('*'.__CLASS__.': '.$str, $method);

    }





}

