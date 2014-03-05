<?php
/**
 * Overrides the input-parser for additional tag-support.
 *
 * @author Oliver Erdmann, <o.erdmann@finaldream.de>
 * @since  28.02.14
 */

class psdExtendendXMLInputParser extends SQLIXMLInputParser
{

    /**
     * tagNameDivnImg (tag mapping handler)
     * Handles div|img tags and maps them to embed|embed-inline|custom tag
     *
     * @param string $tagName name of input (xhtml) tag
     * @param array $attributes byref value of tag attributes
     * @return string name of ezxml tag or blank (then tag is removed, but not it's content)
     */
    public function tagNameDivnImg($tagName, &$attributes)
    {

        $name = '';

        if (isset($attributes['src'])) {

            $url = $attributes['src'];
            $node = psdContentBuilder::resolveNode($url);

            if ($node instanceof eZContentObjectTreeNode) {
                $name = 'embed';
                $attributes['object_id'] = $node->attribute('contentobject_id');

                if (!isset($attributes['view'])) {
                    $attributes['view'] = 'embed';
                }
            }
        }

        return $name;
    }


    /**
     * Custom Embed-Tag handler. Supports locations via href-attribute.
     * @param string $tagName
     * @param array $attributes
     *
     * @return string Name.
     */
    public function tagEmbedHandler($tagName, &$attributes)
    {

        $name = '';

        if (isset($attributes['href'])) {

            $url = $attributes['href'];
            $node = psdContentBuilder::resolveNode($url);

            if ($node instanceof eZContentObjectTreeNode) {
                $name = 'embed';
                $attributes['object_id'] = $node->attribute('contentobject_id');

                if (!isset($attributes['view'])) {
                    $attributes['view'] = 'singleTeaser';
                }
            }
        }

        return $name;
    }

}