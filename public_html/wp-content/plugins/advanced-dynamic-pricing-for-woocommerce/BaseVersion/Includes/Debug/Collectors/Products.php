<?php

namespace ADP\BaseVersion\Includes\Debug\Collectors;

use ADP\BaseVersion\Includes\PriceDisplay\Processor;

defined('ABSPATH') or exit;

class Products
{
    /**
     * @var Processor
     */
    protected $processor;

    /**
     * @param $listener Processor
     */
    public function __construct($listener)
    {
        $this->processor = $listener;
    }

    /**
     * @return array
     */
    public function collect()
    {
        return $this->processor->getListener()->getTotals();
    }

}
