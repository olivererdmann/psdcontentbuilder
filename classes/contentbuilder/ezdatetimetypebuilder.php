<?php
/**
 * Builds the eZDateTime-data-type on an existing node.
 *
 * @author Oliver Erdmann, <o.erdmann@finaldream.de>
 * @since 03.12.2013
 */
class ezDateTimeTypeBuilder extends psdAbstractDatatypeBuilder
{


    /**
     * Applies the provided value to the attribute with the specified name.
     *
     * Supply datatype in this format (YAML):
     *      datetime: 1386000000
     *      datetime: 2013-12-03
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

        $dataType = new eZDateTimeType();
        $dataType->initializeObjectAttribute($contentAttribute, false, null);

        $this->buildDateTimeType($contentAttribute, $content);

        $contentAttribute->store();

    }


    /**
     * Applies a content-structure to the provieded contentObjectAttribute.
     *
     * @param eZContentObjectAttribute $contentObjectAttribute Current ContentAttribute.
     * @param array                    $content                Array-structure with one or multiple air-dates.
     *
     * @return void
     */
    protected function buildDateTimeType($contentObjectAttribute, $content)
    {

        if (!is_string($content) && !is_numeric($content)) {
            $this->contentBuilder->logLine('eZDateTime must be an integer or a string!', __METHOD__);
            return;
        }

        $result = $this->validateTimeString($content);

        $contentObjectAttribute->setAttribute('data_int', $result);

    }


    /**
     * Gets the Timestamp from a provided value.
     *
     * @param int|string $value An int is treated as timestamp, a string is tried to be interpreted as Time-String by
     *                          DateTime. All valid formats can be used.
     *
     * @return int Timestamp. If an invalid value is supplied, 0 is returned.
     */
    protected function validateTimeString($value)
    {

        if (is_numeric($value)) {
            return (int) $value;
        }

        try {

            $dateTime = new DateTime($value);
            return $dateTime->getTimestamp();

        } catch (Exception $e) {
            $this->contentBuilder->logLine(sprintf('Invalid DateTime-String in Airdate! (%s)', $value), __METHOD__);
        }

        return 0;

    }


}
