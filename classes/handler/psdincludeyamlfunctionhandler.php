<?php

use Symfony\Component\Yaml\Parser;

/**
 * Handles the include Yaml-Function, for including other files.
 *
 * @author  Oliver Erdmann, <o.erdmann@finaldream.de>
 * @license GNU General Public License v2
 * @since 26.02.2014
 */
class psdIncludeYamlFunctionHandler extends psdAbstractYamlFunctionHandler
{

    /**
     * Is called by the invoke-function of the Content-Builder.
     *
     * @param string $function   Name of the called function.
     * @param mixed  $parameters Parameter String (everything on the same line, after the function-name).
     *
     * @return mixed The processed structure. Must be returned, as it will replace the part of the original structure.
     *
     * @throws Exception If input-parameters are wrong / missing.
     */
    public function apply($function, $parameters)
    {

        if (!isset($parameters['file'])) {
            throw new Exception('Include file not defined. Use the "file"-key.');
        }

        $file = $this->resolveIncludeFilename($parameters['file']);

        if (!file_exists($file) || (is_readable($file) === false)) {
            throw new Exception(sprintf('Include file not found or not readable: %s.', $file));
        }

        if ($this->builder instanceof psdContentBuilder) {
            $this->builder->execPath->addFile($file);
        }

        $content = file_get_contents($file);

        $parser  = null;

        // Allow the parser to be set from out-side in order to carry over previously collected references.
        if ($this->builder instanceof psdContentBuilder) {
            $parser = $this->builder->getParser();
        }

        if (!($parser instanceof Parser)) {
            $parser = new Parser();
        }

        $result = $parser->parse($content);

        return $result;

    }


    /**
     * Figures out the absolute path for an include. Uses the contentBuilder-instance for querying the filename.
     *
     * @param string $file Include-File, either absolute or relative to the initial structure-file.
     *
     * @return bool|string Filename or FALSE if failed.
     */
    protected function resolveIncludeFilename($file)
    {

        // Try absolute file-paths first.
        if (file_exists($file)) {
            return realpath($file);
        }

        if (!($this->builder instanceof psdContentBuilder)) {
            return false;
        }

        // Resolve files relative to the initial structure-file.
        $relativePath = dirname($this->builder->getFileName()).'/'.$file;

        if (file_exists($relativePath)) {
            return realpath($relativePath);
        }

        return false;

    }

}
