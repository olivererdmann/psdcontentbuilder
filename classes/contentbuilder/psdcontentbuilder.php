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

use Symfony\Component\Yaml\Yaml;
class psdContentBuilder
{

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
    public $structure = null;

    /**
     * Current yaml-parser.
     *
     * @var Yaml|null
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
     * @var bool
     */
    public $verbose = false;


    /**
     * Creates a new instance and initializes it with a filename.
     * @todo Constructors for loading from string and array-structure.
     *
     * @param string $fileName File to parse for a structure.
     *
     * @return void
     */
    public function __construct($fileName)
    {

        $this->fileName       = $fileName;
        $this->remoteIdPrefix = basename($fileName);

        $this->initialize();

    }


    /**
     * Loads and parses the specified YAML-file. The parsed structure is available through $this->structure.
     *
     * @return void
     *
     * @throws Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException If file not found.
     * @throws Symfony\Component\Yaml\Exception\ParseException If the YAML is not valid.
     *
     * @return void
     */
    protected function initialize()
    {

        $this->structure = null;

        if (!file_exists($this->fileName)) {
            throw new \Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException($this->fileName);
        }

        $this->yaml      = new Yaml();
        $this->structure = $this->yaml->parse($this->fileName);

        // Initialize the YAML-Postprocessing.
        $this->loadPostProcessor();

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
     * Applies the loaded structure to the content-tree.
     *
     * @return void.
     *
     * @throws psdContentBuilderValidationException If validation fails.
     */
    public function apply()
    {

        $this->validate();

        // @todo: in some cases the location may needs to be created first (path).
        $children = $this->structure['content'];

        if (array_key_exists('remoteIdPrefix', $this->structure)) {
            $this->remoteIdPrefix = $this->structure['remoteIdPrefix'];
        }

        foreach ($children as $child) {

            $nodeBuilder          = new psdNodeBuilder($this);
            $nodeBuilder->verbose = $this->verbose;

            $nodeBuilder->setRemoteIdPrefix($this->remoteIdPrefix);
            $nodeBuilder->apply($child);

        }

        // Output undo-information.
        $cli = eZCLI::instance();
        if (empty($this->undoNodes)) {
            $cli->output('No nodes created, nothing to undo.', true);
        } else {
            $cli->output('Undo String:', true);
            $cli->output(implode(',', $this->undoNodes), true);
        }

    }


    /**
     * Resolves the provided location into a valid node. The provided location can be a node-instance,
     * a valid node-ID (numeric), a remote-ID (string) or a path (starts with a "/").
     *
     * @param mixed   $location        Specifies the location of a node.
     *                                 Can be node, nodeID, remoteID or a path-string.
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

        if (empty($location)) {
            $location = '/';
        }

        if (is_numeric($location)) {

            // Node-ID.
            return eZContentObjectTreeNode::fetch(intval($location));

        } else if (is_string($location) && substr($location, 0, 1) === '/') {

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

        } else if (is_string($location)) {

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
    public function ensurePathExists($path, $className = 'folder')
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
     * @return void.
     */
    protected function loadPostProcessor()
    {

        // Load the function-handlers from the INI.
        $ini = eZINI::instance('psdcontentbuilder.ini');

        $this->postProcessor = new psdYamlProcessor();

        // No handlers, nothing to do.
        if (!$ini->hasVariable('Handlers', 'YamlFunctions')) {
            return;
        }

        $handlers = $ini->variable('Handlers', 'YamlFunctions');

        $this->postProcessor->addMultipleFunctionHandlers($handlers);

    }


}

