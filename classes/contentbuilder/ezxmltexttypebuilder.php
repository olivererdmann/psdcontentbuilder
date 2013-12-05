<?php
/**
 * Builds the ezXMLText-data-type on an existing node.
 *
 * @author Oliver Erdmann, <o.erdmann@finaldream.de>
 * @since 03.12.2013
 */
class eZXMLTextTypeBuilder extends psdAbstractDatatypeBuilder
{


    /**
     * Applies the provided value to the attribute with the specified name.
     *
     * Supply datatype either as plain ez-fetch results or as an array of content-object-ids.
     *
     * @param eZContentObject $object    Object to build the attribute for.
     * @param string          $attribute Name of the Object.
     * @param mixed           $content   Value to apply to the attribute.
     *
     * @return void
     *
     * @throws Exception When the attribute is not found on the specified object.
     */
    public function apply($object, $attribute, $content)
    {

        // Resolve possible Yaml-functions below this structure.
        $content = $this->contentBuilder->postProcess($content);

        $dataMap = $object->attribute('data_map');

        if (!array_key_exists($attribute, $dataMap)) {
            throw new Exception(sprintf('Attribute %s not found on object.', $attribute));
        }
        $contentAttribute = $dataMap[$attribute];
        $contentAttribute->setContent(null);
        $contentAttribute->store();

        $this->buildXMLText($contentAttribute, $content);

        $contentAttribute->store();

    }


    /**
     * Builds an eZXMLText-attribute from an HTML-String.
     *
     * @param eZContentObjectAttribute $contentAttribute Current Attribute.
     * @param mixed                    $content          Array or single resolvable Object-location.
     *
     * @return void
     */
    protected function buildXMLText($contentAttribute, $content)
    {

        $dataType = new eZXMLTextType();
        $dataType->initializeObjectAttribute($contentAttribute, false, null);

        $xml = SQLIContentUtils::getRichContent((string) $content);

        $contentAttribute->setAttribute('data_text', $xml);

    }


}
