<?php
/**
 * A unified data-container for the psdNodeBuilder::createNode-function.
 *
 * @author Oliver Erdmann, <o.erdmann@finaldream.de>
 * @since  04.03.14
 */

class psdNodeBuilderNodeInfo {


    /**
     * Node-name
     *
     * @var string
     */
    public $name = '';

    /**
     * Remote-id
     *
     * @var string
     */
    public $remoteId = '';

    /**
     * Fields handled by the default sqliImport
     *
     * @var array
     */
    public $fields = [];

    /**
     * Fields with registered own handler
     *
     * @var array
     */
    public $customFields = [];

    /**
     * Fields to be processed after all children were created.
     *
     * @var string[]
     */
    public $postPublishFields = [];

    /**
     * Options are defined per object and not translated.
     *
     * @var array
     */
    public $options;


    /**
     * The node's children.
     *
     * @var array
     */
    public $children = [];

    public $availableLanguages = [];


    public function __construct(array $languages = array())
    {

        if (empty($languages)) {
            $languages = eZContentLanguage::prioritizedLanguageCodes();
        }

        foreach ($languages as $lang) {
            $this->fields[$lang]       = [];
            $this->customFields[$lang] = [];
        }

    }

    public function addFieldWithLanguage($key, $value, $language = '')
    {

        $language = $this->addLanguage($language);

        $this->fields[$language][$key] = $value;

    }


    /**
     * Adds a customFields with a given language.
     * Custom fields are separately processed by dedicated data-type builders.
     *
     * @param $key
     * @param $value
     * @param string $language
     */
    public function addCustomFieldWithLanguage($key, $value, $language = '')
    {

        $language = $this->addLanguage($language);

        $this->customFields[$language][$key] = $value;

    }


    /**
     * Internal, adds a given language to the availableLanguages-array, which reoresents a list of
     * the actual used language-codes. Also adds the first language to the options-array, marking it as
     * default language for creating content.
     *
     * @param string $language Language code in the form `ger-DE`. If empty, the current locale is used.
     * @return string Returns the provided language, or the current language-code.
     */
    protected function addLanguage($language)
    {

        if (empty($language)) {
            $language = eZLocale::currentLocaleCode();
        }

        if (!in_array($language, $this->availableLanguages)) {
            $this->availableLanguages[] = $language;
        }

        // Set the first valid language as default.
        if (!isset($this->options['language'])) {
            $this->options['language'] = $language;
        }

        return $language;

    }


    /**
     * Marks field-names which will be re-published in the event all children are created.
     * Setting field-names, replaces existing ones.
     *
     * @param string|array $fields Array of attribute-identifiers. If you provide a string, it is wrapped in an array.
     */
    public function setPostPublishFields($fields)
    {

        // PostPublish can be an array for multiple values or a string for a single value.
        if (is_array($fields)) {
            $this->postPublishFields = $fields;
        } else {
            $this->postPublishFields = array($fields);
        }

    }

}
