<?php

// Get eZ!
require_once 'autoload.php';

/**
 * Commandline-interface for psdContentBuilder.
 * See function printHelp() for usage-instruction.
 *
 * @TODO: Optimize Structure
 * @TODO: Block-Items, add timings.
 * @TODO: Add proper verbose cli-output and error-reporting.
 * @TODO: Check ObjectRelation/List.
 * @TODO: check if raw input on data_text / data_int works. Allow raw XML with the toXML-function.
 *
 * @author Oliver Erdmann, <o.erdmann@finaldream.de>
 * @license GNU General Public License v2
 */
class psdContentBuilderCLI
{
    /**
     * Properties for eZScript.
     *
     * @var array
     */
    public $scriptSettings = array(
        'description'    => 'Provides a CLI for text-based content-class installation.',
        'use-session'    => true,
        'use-modules'    => true,
        'use-extensions' => true,
    );

    /**
     * The eZScript instance.
     *
     * @var eZScript|null
     */
    protected $script = null;

    /**
     * Commanline interface for console output.
     *
     * @var eZCLI|null
     */
    protected $cli = null;

    /**
     * Holds the processed cli-arguments.
     *
     * @var array
     */
    protected $arguments = array();

    /**
     * Switch between shut-up and talkative.
     *
     * @var boolean
     */
    protected $verbose = false;


    /**
     * Constructor.
     */
    public function __construct()
    {

    }


    /**
     * Main execution loop. Specifies the command-line arguments and loops through a set of functions, each picking
     * their options.
     *
     * May exit with error-code 1, which means an eZDBNoConnectionException occurred. This can happen on the initial
     * import.
     *
     * @param array|boolean $arguments An optional array for providing arguments and therefore bypassing the
     *                                 commandline.
     *
     * @return void
     */
    public function main($arguments = false)
    {
        $handlers = array(
            array($this, 'doApply'),
            array($this, 'doRemove'),
        );

        if (empty($arguments)) {

            $this->arguments = getopt(
                '',
                array(
                    'apply:',
                    'remove:',
                    'site-access:',
                    'verbose::',
                )
            );

        } else {
            $this->arguments = $arguments;
        }

        $this->cli = eZCLI::instance();

        try {
            $this->initScript();

            // Test if database is empty.
            $db = eZDB::instance();
            eZDB::setErrorHandling(eZDB::ERROR_HANDLING_EXCEPTIONS);
            $db->arrayQuery('SELECT * FROM ezcontentclass');
        }
        catch (eZDBNoConnectionException $e) {
            $this->verbose = true;
            $this->logLine($e->originalMessage, __METHOD__);
            $this->script->shutdown(1, 'No Database connection possible. Aborting.');
        }
        catch (eZDBException $e) {
            $this->verbose = true;
            $this->script->shutdown(2, 'Database is empty, please import an initial dump!');
        }

        if (is_array($this->arguments) && count($this->arguments) > 0) {

            $this->verbose             = array_key_exists('verbose', $this->arguments);
            $GLOBALS['eZDebugEnabled'] = $this->verbose;

            foreach ($handlers as $handler) {
                if (call_user_func($handler) === true) {
                    $this->shutdownScript();
                    return;
                }
            }

        }

        $this->printHelp();

    }


    /**
     * Init eZScript for functions the need db-access, only initializes once.
     * This function needs the --site-access argument set, if left blank, the DefaultAccess from site.ini is used.
     *
     * @return void
     */
    public function initScript()
    {

        if ($this->script) {
            return;
        }

        if (array_key_exists('site-access', $this->arguments)) {
            $this->scriptSettings['site-access'] = $this->arguments['site-access'];
        } else {
            $ini                                 = eZINI::instance('site.ini');
            $this->scriptSettings['site-access'] = $ini->variable('SiteSettings', 'DefaultAccess');
        }

        $this->script = eZScript::instance($this->scriptSettings);
        $this->script->startup();
        $this->script->initialize();

        $this->logLine('Initializing. Using site-access '.$this->scriptSettings['site-access'], __METHOD__);

    }


    /**
     * Shut's down the script, if available.
     *
     * @return void
     */
    public function shutdownScript()
    {

        if ($this->script) {
            $this->script->shutdown();
        }

    }


    public function doApply() {

        if (is_array($this->arguments) && array_key_exists('apply', $this->arguments)) {
            $pattern = $this->arguments['apply'];
        }

        if (empty($pattern)) {
            return false;
        }

        $files = glob($pattern);

        foreach ($files as $file) {
            $this->logLine('Applying structure to content-tree: '.$file, __METHOD__);

            $builder = new psdContentBuilder($file);

            $builder->verbose = $this->verbose;
            $builder->logLineCallback = array($this, 'logLine');
            $builder->apply();

        }

        return true;

    }


    public function doRemove()
    {

        if (is_array($this->arguments) && array_key_exists('remove', $this->arguments)) {
            $nodes = $this->arguments['remove'];
        }

        if (empty($nodes)) {
            return false;
        }

        $nodes = explode(',', $nodes);

        $db = eZDB::instance();
        $db->begin();

        foreach ($nodes as $nodeId) {

            $node = psdContentBuilder::resolveNode(trim($nodeId));

            if (!($node instanceof eZContentObjectTreeNode)) {

                $this->logline(sprintf('Failed to remove node with the identifier "%s"', $nodeId), __METHOD__);
                continue;

            }

            $name = $node->getName();

            $node->removeNodeFromTree(false);

            $this->logLine(sprintf('Removed node "%s" (%s)', $name, $nodeId), __METHOD__);

        }

        $db->commit();

        return true;

    }


    /**
     * Output's the script's help-text.
     *
     * @return void
     */
    public function printHelp()
    {
        $lines = '
            PSD ContentBuilder CLI.

            Commandline-Interface for building content from YAML-files.

            ARGUMENTS
            --apply        PATH      Applies the structure defined in the file to the content-tree.
                                     Requires the --site-access option set.
            --remove       NODE-LIST List of NODE-LOCATIONs separated by commas.
            --help                   This text.
                                     defined in the package.xml-structure. Will overwrite existing classes, unless
                                     the option --ignore-version is specified.
            --site-access  STRING    Site-Access that will be needed to perform database-actions. If left blank, the
                                     DefaultAccess is taken from site.ini.
            --verbose                Keeps the script telling about what it\'s doing.

            DEFINITIONS:
              PATH:                  Points to a folder or file and may contain wild-cards (eg. "*").
                                     Wild-cards are resolved and allow the script to process multiple files at once.
                                     In order to use wild-cards, you have to put the path in single- or
                                     double-quotes.
              NODE-LOCATION          Either a Node-Id, Path-String (starting with "/") or a Remote-ID.

            EXAMPLES:

              FYI: Run all commands relative to the root of your eZ Publish installation!

              Apply a structure to the default site-access:
              $ php psdcontentbuildercli.php --apply="path/to/structure.yaml"

              Apply a structure to a defined site-access:
              $ php psdcontentbuildercli.php --apply="path/to/structure.yaml" --site-access=NAME-OF-SITEACCESS


              Undo a recent application:
              > Undo String:
              > 123456,456778

              $ php psdcontentbuildercli.php --remove=123456,456778 --site-access=NAME-OF-SITEACCESS

            ';

            $this->cli->output($lines, true);

    }


    /**
     * Writes a line to the console if $verbose is enabled.
     *
     * @param string $str    Message to be written.
     * @param string $method Optional Method name, only used for debug-log.
     *
     * @return void
     */
    public function logLine($str, $method = '')
    {

        eZDebug::writeNotice('*'.__CLASS__.': '.$str, $method);

        if (!$this->verbose) {
            return;
        }

        $this->cli->output($str, true);

    }


}


// Run only if called directly from command-line.
if (count($_SERVER['argv']) > 0) {

    $info = pathinfo($_SERVER['argv'][0]);

    if ($info['basename'] !== 'psdcontentbuildercli.php') {
        return;
    }

    $inst = new psdContentBuilderCLI();
    $inst->main();

}