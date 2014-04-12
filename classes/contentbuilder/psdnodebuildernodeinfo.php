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
     * @var SQLIContentOptions
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
            $this->postPublishFields[$lang] = [];
            $this->fields[$lang]            = [];
            $this->customFields[$lang]      = [];
        }

    }

    public function addFieldWithLanguage($key, $value, $language = '')
    {

        $language = $this->addLanguage($language);

        $this->fields[$language][$key] = $value;

    }

    public function addCustomFieldWithLanguage($key, $value, $language = '')
    {

        $language = $this->addLanguage($language);

        $this->fields[$language][$key] = $value;

    }

    public function setPostPublishFieldsWithLanguage($fields, $language = '')
    {

        $language = $this->addLanguage($language);

        // PostPublish can be an array for multiple values or a string for a single value.
        if (is_array($fields)) {
            $this->postPublishFields[$language] = $fields;
        } else {
            $this->postPublishFields[$language] = array($fields);
        }

    }

    protected function addLanguage($language)
    {

        if (empty($language)) {
            $language = eZLocale::currentLocaleCode();
        }

        if (!in_array($language, $this->availableLanguages)) {
            $this->availableLanguages[] = $language;
        }

        return $language;

    }

}
