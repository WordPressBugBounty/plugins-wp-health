<?php
namespace WPUmbrella\Core;

use DateTime;
use DateTimeZone;

class UmbrellaDateTime extends DateTime
{
    protected $utcOffset = 0;

    public function __construct($time = 'now', $timezone = 'UTC')
    {
        parent::__construct($time, new DateTimeZone($timezone));
    }

    #[\ReturnTypeWillChange]
    public function getTimestamp()
    {
        return method_exists('DateTime', 'getTimestamp') ? parent::getTimestamp() : $this->format('U');
    }

    #[\ReturnTypeWillChange]
    public function setUtcOffset($offset)
    {
        $this->utcOffset = intval($offset);
    }

    #[\ReturnTypeWillChange]
    public function getOffset()
    {
        return $this->utcOffset ? $this->utcOffset : parent::getOffset();
    }

    #[\ReturnTypeWillChange]
    public function setTimezone($timezone)
    {
        $this->utcOffset = 0;
        parent::setTimezone($timezone);

        return $this;
    }

    #[\ReturnTypeWillChange]
    public function getOffsetTimestamp()
    {
        return $this->getTimestamp() + $this->getOffset();
    }
}
