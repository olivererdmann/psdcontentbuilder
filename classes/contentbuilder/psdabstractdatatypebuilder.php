<?php
/**
 * Provides an abstract implementation for custom Attribute Builders.
 *
 * @author  Oliver Erdmann, <o.erdmann@finaldream.de>
 * @license GNU General Public License v2
 * @since   19.11.12
 */
abstract class psdAbstractDatatypeBuilder
{

    /**
     * References the contentBuilder-object.
     *
     * @var null|psdContentBuilder;
     */
    public $contentBuilder = null;

    /**
     * Tells the builder to be talkative.
     *
     * @var boolean
     */
    public $verbose = false;


    /**
     * Applies the provided value to the attribute with the specified name.
     * Keep in mind: custom datatype-builds need to handle yaml post-processing themselves (there is access to the
     * post-processor via $this->contentBuilder).
     *
     * @param eZContentObject $object                    Object to build the attribute for.
     * @param eZContentObjectAttribute $contentAttribute Current attribute to build.
     * @param mixed           $content                   Value to apply to the attribute.
     *
     * @return void
     */
    abstract public function apply($object, $contentAttribute, $content);


}
