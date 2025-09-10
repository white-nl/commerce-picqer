<?php


namespace white\commerce\picqer\events;

use craft\base\Event;
use craft\commerce\elements\Order;

class OrderEvent extends Event
{
    public Order $order = null;
}
