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
class psdFetchYamlFunctionHandler extends psdAbstractYamlFunctionHandler
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
        $func = explode('/', $function);

        if (count($func) < 4) {
            throw new Exception(
                'A fetch function must consist at least of four parts: ezpublish/fetch/module/function'
            );
        }

        if ($func[0] != 'ezpublish' || $func[1] != 'fetch') {
            throw new Exception('This class is intended to handle "ezpublish/fetch/*" functions');
        }


        $as = false;
        if (array_key_exists('as', $parameters)) {
            $as = $parameters['as'];
            unset($parameters['as']);
        }

        $fetchResult = eZFunctionHandler::execute($func[2], $func[3], $parameters);

        // Return the raw fetch-result, if no mapping is specified.
        if (!is_array($as)) {
            return $fetchResult;
        }

        $result = array();

        // Re-map the result.
        if (is_array($fetchResult)) {

            for ($i = 0, $j = count($fetchResult); $i < $j; $i++) {
                $result[$i] = $this->getSelectedFields($fetchResult[$i], $as);
            }

        } else {

            $item = $this->getSelectedFields($fetchResult, $as);
            if (!empty($item)) {
                $result[] = $item;
            }

        }

        return $result;

    }


    /**
     * Maps attributes of an element to the specified fields.
     *
     * @param eZPersistentObject|array $element The element to read the fields from.
     * @param array                    $fields  Name the fields. If an associative array is provided, map the fields
     *                                          (keys) to the array's values (use this, for re-mapping field-names).
     *
     * @return array                   An array of the requested fields.
     */
    protected function getSelectedFields($element, $fields)
    {

        $result = array();

        // Ensure correct input-values.
        if (empty($fields) || (!is_array($element) && !($element instanceof eZPersistentObject))
        ) {
            return $result;
        }

        foreach ($fields as $key => $value) {

            $property = $value;

            if (!is_string($key)) {
                $key = $value;
            }

            if (is_array($element) && array_key_exists($key, $element)) {
                $result[$property] = $element[$key];
            } else if ($element instanceof eZPersistentObject) {
                $result[$property] = $element->attribute($key);
            }

        }

        return $result;

    }


}
