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
     * Pops the last level off the path.
     *
     * @return mixed
     */
    public function pop()
    {

        return array_pop($this->levels);

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

        return implode('/', $this->levels);

    }

}