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

        $htmlParser                      = new psdExtendendXMLInputParser();

        $htmlParser->InputTags['embed'] = array(
            'nameHandler' => 'tagEmbedHandler'
        );

        $htmlParser->setParseLineBreaks(true);

        $document = $htmlParser->process($content);
        $xml      = (string) eZXMLTextType::domString($document);

        $contentAttribute->setAttribute('data_text', $xml);

    }


}
