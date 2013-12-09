<?php
/**
 * Builds the ezTags-data-type on an existing node.
 *
 * @author Oliver Erdmann, <o.erdmann@finaldream.de>
 * @since 05.12.2013
 */
class eZTagsTypeBuilder extends psdAbstractDatatypeBuilder
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

        $this->buildTags($contentAttribute, $content);

        $contentAttribute->store();

    }


    /**
     * Builds an eZTags-attribute from an Array.
     *
     * @param eZContentObjectAttribute $contentAttribute Current Attribute.
     * @param array                    $content          Array of ids or Tag-Paths
     *
     * @return void
     */
    protected function buildTags($contentAttribute, $content)
    {

        $tagIds      = array();
        $tagKeywords = array();
        $tagParents  = array();

        $dataType = new eZTagsType();
        $dataType->initializeObjectAttribute($contentAttribute, false, null);

        foreach ($content as $pathString) {

            $tag = $this->validateTag($pathString);

            if ($tag !== null) {
                $tagIds[]      = $tag->attribute('id');
                $tagKeywords[] = $tag->attribute('keyword');
                $tagParents[]  = $tag->attribute('parent_id');
            }
        }

        // Build the string-value for tags.
        if (count($tagIds) > 0) {

            $value  = implode('|#', $tagIds).'|#';
            $value .= implode('|#', $tagKeywords).'|#';
            $value .= implode('|#', $tagParents);

            $dataType->fromString($contentAttribute, $value);

        }



    }


    /**
     * Fetches the associated tag for a path-string. If there is no such a tag, it will be created.
     *
     * @param string $pathString Strings represent path-strings, Levels are separated by "/".
     *
     * @return eZTagsObject|null A valid tag-object or null on failure.
     */
    protected function validateTag($pathString)
    {

        $parts    = explode('/', trim($pathString, '/'));
        $path     = '';
        $parentId = 0;
        $tag      = null;

        foreach ($parts as $index => $part) {
            $path .= '/'.$part;

            // Fetches an array.
            $fetched = $this::fetchByRalPathString($path);

            // Create Tag if not exists.
            if (count($fetched) < 1) {
                $tag = new eZTagsObject(
                    array(
                        'id'               => null,
                        'parent_id'        => $parentId,
                        'main_tag_id'      => 0,
                        'keyword'          => $part,
                        'depth'            => $index + 1,
                        'path_string'      => null,
                        'real_path_string' => $path,
                        'modified'         => null
                    )
                );

                $tag->store();
            } else {
                $tag = $fetched[0];
            }

            $parentId = $tag->attribute('id');
        }//end foreach

        return $tag;

    }


    /**
     * Fetches an eZTagsObject by Real Path String.
     *
     * @param string $realPathString RealPathString of Tag.
     *
     * @return mixed
     */
    static function fetchByRalPathString($realPathString)
    {

        return eZPersistentObject::fetchObjectList(
            eZTagsObject::definition(),
            null,
            array(
                'real_path_string' => array(
                    'like',
                    $realPathString.'%'
                ),
                'main_tag_id'      => 0
            )
        );

    }


}
