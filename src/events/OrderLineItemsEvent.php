<?php


namespace white\commerce\picqer\events;

use craft\base\Event;

class OrderLineItemsEvent extends OrderEvent
{
    /**
     * @var \craft\commerce\models\LineItem[]
     */
    public array $lineItems = [];
}
