<?php
/**
 * Simple path-management class. Allows to build up paths of strings.
 *
 * @author Oliver Erdmann, <o.erdmann@finaldream.de>
 * @since  20.12.13
 */

namespace extension\psdcontentbuilder\classes;


class psdPathLevels {

    /**
     * Current path of levels.
     *
     * @var array
     */
    protected $levels = array();

    /**
     * Stored history of paths.
     *
     * @var array
     */
    protected $stack = array();


    /**
     * Adds a new level to the current path;
     *
     * @param integer|string $level
     */
    public function add($level)
    {

        if (is_int($level)) {
            $this->levels[] = '['.$level.']';
        } else {
            $this->levels[] = $level;
        }

    }

    /**
     * Adds a new filename as level to the current path; file-names get removed automatically.
     *
     * @param string $filename
     */
    public function addFile($filename)
    {

        $this->levels[] = 'file://'.$filename;

    }


    /**
     * Pops the last level off the path.
     *
     * @return mixed
     */
    public function pop()
    {
        // If the next level is a file, remove this level as well.
        if (count($this->levels) > 0) {

            if (substr_compare($this->levels[count($this->levels) - 1], 'file://', 0, 7) === 0) {
                array_pop($this->levels);
            }

        }

        $result = array_pop($this->levels);


        return $result;

    }


    /**
     * Stores the current path in the internal history,
     */
    public function store()
    {

        $this->stack[] = $this->levels;

    }


    /**
     * Restores the last path from the internal history.
     */
    public function restore()
    {

        if (count($this->stack) < 1) {
            return;
        }

        $this->levels = array_pop($this->stack);

    }


    /**
     * Returns the String-representation of the current path.
     *
     * @return string
     */
    public function __toString()
    {

        return implode(' > ', $this->levels);

    }

}