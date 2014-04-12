<?php
/**
 * Builds the xrowMetaDataType-data-type on an existing node.
 *
 * @author Oliver Erdmann, <o.erdmann@finaldream.de>
 * @since 09.12.2013
 */
class xrowMetaDataTypeBuilder extends psdAbstractDatatypeBuilder
{

    /**
     * Fields required by data-type.
     *
     * @var array
     */
    protected $fields = array(
        'title'       => '',
        'keywords'    => array(),
        'description' => '',
        'priority'    => '',
        'change'      => '',
        'googlemap'   => '',
        'canonical'   => '',
        'robots'      => '',
        'extraMeta'   => '',
    );


    /**
     * Applies the provided value to the attribute with the specified name.
     *
     * Supply datatype in this format (YAML), all fields are optional:
     *
     *      metadata:
     *           title:       String
     *           keywords:    String
     *           description: String
     *           priority:    Float   (0.0 .. 1.0)
     *           change:      String  (always|hourly|daily|weekly|monthly|yearly|never)
     *           googlemap:   Boolean (0|1)
     *           canonical:   String
     *           robots:      String  (See xrowmetadata.ini/EditorInputSettings/RobotsTagOptions)
     *           extraMeta:   String
     *
     * If the value is an integer, it is treated as timestamp. If it's a string, it's tried to be interpreted as a
     * PHP DateTime String.
     *
     * @param eZContentObject $object                    Object to build the attribute for.
     * @param eZContentObjectAttribute $contentAttribute Current attribute to build.
     * @param mixed           $content                   Value to apply to the attribute.
     *
     * @return void
     *
     * @throws Exception When the attribute is not found on the specified object.
     */
    public function apply($object, $contentAttribute, $content)
    {

        $this->buildMetaData($contentAttribute, $content);
        $contentAttribute->store();

    }


    /**
     * Builds the Metadata-Type from an array.
     *
     * @param eZContentObjectAttribute $contentAttribute Current Attribute.
     * @param array                    $content          Fields to set. See $this->fields for options.
     *
     * @return void
     */
    protected function buildMetaData($contentAttribute, $content)
    {

        if (!is_array($content)) {
            $this->contentBuilder->logLine('Metadata must be specified as an array-structure.', __METHOD__);
        }

        if (isset($content['keywords'])) {
            $content['keywords'] = $this->cleanUpKeywords($content['keywords']);
        }

        $values = array_merge($this->fields, $content);

        $dataType = new xrowMetaDataType();
        $dataType->initializeObjectAttribute($contentAttribute, false, null);

        $meta = $dataType->fillMetaData($values);

        $contentAttribute->setContent($meta);

    }


    /**
     * Ensures keywords are an array and items are trimmed.
     *
     * @param mixed $keywords Comma-separated string or Array of strings.
     *
     * @return array of Strings.
     */
    protected function cleanUpKeywords($keywords)
    {

        $result = array();

        if (!is_array($keywords)) {
            $keywords = explode(',', (string) $keywords);
        }

        foreach ($keywords as $keyword) {
            $result[] = trim($keyword);
        }

        return $result;

    }


}
