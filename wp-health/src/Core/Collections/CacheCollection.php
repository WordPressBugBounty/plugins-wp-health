<?php
namespace WPUmbrella\Core\Collections;

if (!defined('ABSPATH')) {
    exit;
}

use WPUmbrella\Core\Iterator\AlphabeticalOrderIterator;
use Iterator;

class CacheCollection implements \IteratorAggregate
{
    protected $items = [];

    public function isEmpty()
    {
        return empty($this->items);
    }

    public function getItems()
    {
        return $this->items;
    }

    public function addItem(CacheCollectionItem $item)
    {
        $this->items[] = $item;
    }

    public function getIterator(): Iterator
    {
        return new AlphabeticalOrderIterator($this);
    }

    public function getReverseIterator(): Iterator
    {
        return new AlphabeticalOrderIterator($this, true);
    }
}
