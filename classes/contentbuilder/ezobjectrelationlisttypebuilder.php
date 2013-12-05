<?php
/**
 * Builds the eZObjectRelationList-data-type on an existing node.
 *
 * @author Oliver Erdmann, <o.erdmann@finaldream.de>
 * @since 03.12.2013
 */
class eZObjectRelationListTypeBuilder extends psdAbstractDatatypeBuilder
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

        switch ($contentAttribute->DataTypeString) {

            case 'ezobjectrelationlist':

                $this->buildObjectRelationList($contentAttribute, $content);
                break;

            case 'ezobjectrelation':

                $this->buildObjectRelation($contentAttribute, $content);
                break;

        }


        $contentAttribute->store();

    }


    /**
     * Builds an eZObjectRelationList-attribute.
     *
     * @param eZContentObjectAttribute $contentAttribute Current Attribute.
     * @param array                    $content          List of resolvable Object-location(s).
     *
     * @see psdContentBuilder::resolveNode.
     *
     * @return void
     */
    protected function buildObjectRelationList($contentAttribute, $content)
    {

        $dataType = new eZObjectRelationListType();
        $dataType->initializeObjectAttribute($contentAttribute, false, null);

        $result   = $dataType->defaultObjectAttributeContent();
        $priority = 0;
        foreach ($content as $item) {

            $objectId = $this->validateObjectRelationItem($item);

            if ($objectId < 1) {
                continue;
            }

            ++$priority;

            $result['relation_list'][] = $dataType->appendObject($objectId, $priority, false);

        }//end foreach

        $contentAttribute->setContent($result);

    }


    /**
     * Builds an eZObjectRelation-attribute.
     *
     * @param eZContentObjectAttribute $contentAttribute Current Attribute.
     * @param mixed                    $content          Array or single resolvable Object-location.
     *
     * @see  psdContentBuilder::resolveNode.
     *
     * @return void
     */
    protected function buildObjectRelation($contentAttribute, $content)
    {

        $dataType = new eZObjectRelationType();
        $dataType->initializeObjectAttribute($contentAttribute, false, null);

        // Assume $content directly contains the value to work with.
        $item = $content;
        // Check if $content is a sequential array.
        if (is_array($content) && count($content) > 0 && isset($content[0])) {
            $item = $content[0];
        }

        $objectId = $this->validateObjectRelationItem($item);

        if ($objectId < 1) {
            return;
        }

        $contentAttribute->setAttribute('data_int', $objectId);

    }


    /**
     * Returns a node-id for a specified location.
     *
     * @param mixed $item Location representation.
     *
     * @return int
     */
    protected function validateObjectRelationItem($item)
    {

        $node = psdContentBuilder::resolveNode($item);

        if (!($node instanceof eZContentObjectTreeNode)) {
            return 0;
        }

        return $node->attribute('contentobject_id');

    }


}
