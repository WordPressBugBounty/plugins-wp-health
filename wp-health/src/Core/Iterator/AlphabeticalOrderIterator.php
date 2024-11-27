<?php
namespace WPUmbrella\Core\Iterator;

defined('ABSPATH') or exit('Cheatin&#8217; uh?');

class AlphabeticalOrderIterator implements \Iterator
{
    /**
     * @var WordsCollection
     */
    private $collection;

    private $position = 0;

    /**
     * @var bool This variable indicates the traversal direction.
     */
    private $reverse = false;

    #[\ReturnTypeWillChange]
    public function __construct($collection, $reverse = false)
    {
        $this->collection = $collection;
        $this->reverse = $reverse;
    }

    #[\ReturnTypeWillChange]
    public function rewind()
    {
        $this->position = $this->reverse ?
            count($this->collection->getItems()) - 1 : 0;
    }

    #[\ReturnTypeWillChange]
    public function current()
    {
        return $this->collection->getItems()[$this->position];
    }

    #[\ReturnTypeWillChange]
    public function key()
    {
        return $this->position;
    }

    #[\ReturnTypeWillChange]
    public function next()
    {
        $this->position = $this->position + ($this->reverse ? -1 : 1);
    }

    #[\ReturnTypeWillChange]
    public function valid()
    {
        return isset($this->collection->getItems()[$this->position]);
    }
}
