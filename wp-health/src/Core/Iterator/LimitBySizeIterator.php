<?php
namespace WPUmbrella\Core\Iterator;

use WPUmbrella\Helpers\DataTemporary;

class LimitBySizeIterator extends \LimitIterator
{
    protected $maxSize = 1048576 * 50; // default 50MB

    protected $currentSize = 0;

    public function setMaxSize($maxSize)
    {
        $this->maxSize = $maxSize;
        return $this;
    }

    public function valid()
    {
        if ($this->current() === null) {
            return false;
        }

        try {
            $size = $this->current()->getSize();
            if ($this->currentSize > $this->maxSize) {
                return false;
            }
            $this->currentSize += $size;
        } catch (\Exception $e) {
            $iteratorException = DataTemporary::getDataByKey('iterator_exception');
            if (!$iteratorException) {
                $iteratorException = 0;
            }

            $iteratorException++;
            DataTemporary::setDataByKey('iterator_exception', $iteratorException);

            return false;
        }

        return parent::valid();
    }
}
