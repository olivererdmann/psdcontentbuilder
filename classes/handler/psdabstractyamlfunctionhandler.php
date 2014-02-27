<?php
/**
 * Abstract class that Yaml-Function-handlers need to be based off.
 *
 * @author  Oliver Erdmann, <o.erdmann@finaldream.de>
 * @license GNU General Public License v2
 * @since   22.11.12
 */
abstract class psdAbstractYamlFunctionHandler
{

    /**
     * Points to the current content-builder instance. Allows for querying information about the running job.
     *
     * @var psdContentBuilder|null
     */
    public $builder = null;

    /**
     * Is called by the invoke-function of the Content-Builder.
     *
     * @abstract
     *
     * @param string $function   Name of the called function.
     * @param mixed  $parameters Parameter String (everything on the same line, after the function-name).
     *
     * @return mixed The processed structure. Must be returned, as it will replace the part of the original structure.
     */
    abstract public function apply($function, $parameters);

}
