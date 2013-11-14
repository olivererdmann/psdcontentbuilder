<?php
/**
 * Walks through an already parsed YAML-structure and resolves custom functions that may enhance the overall structure.
 *
 * @author  Oliver Erdmann, <o.erdmann@finaldream.de>
 * @license GNU General Public License v2
 * @since   29.11.12
 */
class psdYamlProcessor
{

    /**
     * Keeps all handlers for processing YAML-functions.
     *
     * @var array
     */
    protected $functionHandlers = array();


    /**
     * Registers a handler for a YAML-function.
     *
     * @param string $function Name of the YAML-function to handle (aka custom data-types). In YAML these custom
     *                         functions start with a single "!". E.g. "!php/object".
     *                         Specify the functions without the exclamation mark!
     * @param string $handler  Name of the class to handle this data-type.
     */
    public function addFunctionHandler($function, $handler)
    {

        $this->functionHandlers[$function] = $handler;

    }


    /**
     * Adds multiple function handlers to the instance.
     *
     * @param string[] $handlers Associative array. Keys define the function, values the name of the handler.
     */
    public function addMultipleFunctionHandlers($handlers)
    {

        foreach ($handlers as $function => $handler) {
            $this->addFunctionHandler($function, $handler);
        }

    }


    /**
     * Walks through the parsed structure and tries to execute function-handlers starting with "!" in their keys.
     * Keys starting with an exclamation mark indicate a function-result, the mark will be removed in return-value.
     *
     * @param mixed $structure The current structural level.
     *
     * @return array The processed level of structure.
     */
    public function process($structure)
    {

        // Dead-end.
        if (!is_array($structure)) {
            return $structure;
        }

        foreach ($structure as $key => $value) {

            // Dead-end, a function call does not require digging deeper.
            if (is_array($value) && array_key_exists('function', $value)) {
                $structure[$key] = $this->processNode($value);
            } else {
                // Follow the structure down deeper.
                $structure[$key] = $this->process($value);
            }

        }

        return $structure;

    }


    /**
     * Walks through the parsed structure and tries to execute function-handlers starting with "!...".
     *
     * @param array $structure The current structural level.
     * @param int   $key       Node key, starts with an "!" to indicate a function.
     * @param int   $value     Node Value.
     *
     * @return array The processed level of structure.
     */
    public function processNode($value)
    {

        return $this->invokeCallbackHandler($value);

    }


    /**
     * Invokes the handler for a Yaml-function.
     *
     * @param array $callbackStack Provides the function and arguments. Requires key "function" to be a registered
     *                             function-handler. All other keys are treated as arguments.
     *
     * @return mixed An empty array if the function is unknown, otherwise, the result of the function.
     * @throws Exception If the function or the handler is unknown.
     */
    protected function invokeCallbackHandler($callbackStack)
    {

        // Clean-up function-name.
        if (!is_array($callbackStack) || !array_key_exists('function', $callbackStack)) {
            return $callbackStack;
        }

        $function = trim($callbackStack['function']);

        // Can differ from function, as it may contain wild-cards.
        $registeredFunction = $this->resolveFunctionName($function);

        if ($registeredFunction === false || !array_key_exists($registeredFunction, $this->functionHandlers)) {
            throw new Exception(sprintf('Encountered unregistered function name "%s".', $function));
        }

        $className = $this->functionHandlers[$registeredFunction];

        if (!class_exists($className)) {
            throw new Exception(sprintf('Handler for %s (%s) does not exist.', $function, $className));
        }

        $handler = new $className();

        if (!($handler instanceof psdAbstractYamlFunctionHandler)) {
            throw new Exception(
                sprintf(
                    'Handler for %s (%s) does not extend from psdContentBuilderAbstractHandler.',
                    $function,
                    $className
                )
            );
        }

        return $handler->apply($function, $callbackStack);

    }


    /**
     * Resolves optional wird-cards in the function-name and ensures that the function is known.
     * You can for example add a handler for "!ezpublish/fetch/*" which would apply for a function like
     * "!ezpublish/fetch/content/list".
     *
     * @param string $function The raw function-name. If it starts with a "!", that will automatically be removed.
     *
     * @return string|bool     The valid function name. False if no match was found.
     */
    protected function resolveFunctionName($function)
    {

        // Resolve optional wild-cards and ensure the function is known.
        foreach ($this->functionHandlers as $key => $value) {
            if (fnmatch($key, $function)) {
                return $key;
            }
        }

        return false;

    }


}
