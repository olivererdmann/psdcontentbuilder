<?php
/**
 * Builds the psdAirdate-data-type on an existing node.
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


    protected function validateObjectRelationItem($item)
    {
        $object   = null;
        $objectId = 0;

        // Get Content-Object-ID either from an int or a fetch-result.
        if ($item instanceof eZContentObject) {
            $object = $item;
        } else if ($item instanceof eZContentObjectTreeNode) {
            $object = $item->object();
        } else if (is_numeric($item)) {
            $objectId = $item;
        } else if (is_array($item) && array_key_exists('contentobject_id', $item)) {
            $objectId = $item['contentobject_id'];
        }

        if ($object instanceof eZContentObject) {
            $objectId = $object->ID;
        } else {
            $object = eZContentObject::fetch((int) $objectId);
        }

        if ($object instanceof eZContentObject) {
            return $objectId;
        }

        return 0;

    }


}
