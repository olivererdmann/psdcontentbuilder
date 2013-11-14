<?php

use Symfony\Component\Yaml\Parser;

/**
 * Handles Yaml-Functions like !ezpublish/fetch/*.
 * Provides access to fetch-functions.
 *
 * @author  Oliver Erdmann, <o.erdmann@finaldream.de>
 * @license GNU General Public License v2
 * @since   22.11.12
 */
class psdLocationYamlFunctionHandler extends psdAbstractYamlFunctionHandler
{


    /**
     * Is called by the invoke-function of the Content-Builder.
     *
     * @param string $function   Name of the called function.
     * @param string $parameters Parameter String (everything on the same line, after the function-name).
     *
     * @return mixed The processed structure. Must be returned, as it will replace the part of the original structure.
     *
     * @throws Exception If input-parameters are wrong / missing.
     */
    public function apply($function, $parameters)
    {
        $func   = explode('/', $function);
        $result = null;

        if (count($func) < 2) {
            throw new Exception(
                'The locate-function must consist at least of three parts: ezpublish/locate[/object|node]'
            );
        }

        if ($func[0] != 'ezpublish' || $func[1] != 'locate') {
            throw new Exception('This class is intended to handle "ezpublish/locate/*" functions');
        }

        if (!isset($parameters['path'])) {
            throw new Exception('Argument "path" is required for funtion ezpublish/locate/*');
        }


        // Default value for leaving the operator blank.
        $operator = 'node';
        if (isset($func[2])) {
            $operator = $func[2];
        }

        $node = psdContentBuilder::resolveNode($parameters['path']);

        if (!($node instanceof eZContentObjectTreeNode)) {
            return $result;
        }

        if ($operator == 'object') {
            $result = $node->attribute('contentobject_id');
        } else {
            $result = $node->attribute('node_id');
        }

        return $result;

    }


}
