<?php

use Symfony\Component\Yaml\Parser;

/**
 * Handle paths in Yaml..
 *
 * @author  Thomas Köhn, <thomas.koehn@prosiebensat1digital.de>
 * @license GNU General Public License v2
 * @since   17.12.12
 */
class psdRealPathFunctionHandler extends psdAbstractYamlFunctionHandler
{

    /**
     * Is called by the invoke-function of the Content-Builder.
     *
     * @param string $function   Name of the called function.
     * @param string $parameters Parameter String (everything on the same line, after the function-name).
     *
     * @return string Real path.
     *
     * @throws Exception If input-parameters are wrong / missing.
     */
    public function apply($function, $parameters)
    {
        if (!array_key_exists('path', $parameters)) {
            throw new Exception(
                'The realpath function need path parameter.'
            );
        }

        $realpath = realpath($parameters['path']);

        if (!$realpath) {
            throw new Exception(
                'The file with given path "'.$parameters['path'].'" not exist .'
            );
        }

        return $realpath;

    }

}
