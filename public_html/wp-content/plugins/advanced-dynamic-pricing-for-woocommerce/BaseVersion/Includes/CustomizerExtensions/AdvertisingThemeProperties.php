<?php

namespace ADP\BaseVersion\Includes\CustomizerExtensions;

use ADP\BaseVersion\Includes\CustomizerExtensions\AdvertisingThemeProperties\CartMenu;
use ADP\BaseVersion\Includes\CustomizerExtensions\AdvertisingThemeProperties\CheckoutMenu;
use ADP\BaseVersion\Includes\CustomizerExtensions\AdvertisingThemeProperties\GlobalMenu;
use ADP\BaseVersion\Includes\CustomizerExtensions\AdvertisingThemeProperties\MiniCartMenu;

defined('ABSPATH') or exit;

class AdvertisingThemeProperties
{
    const KEY = "wdp_discount_message";
    const SHORT_KEY = "discount_message";

    /**
     * @var GlobalMenu
     */
    public $global;

    /**
     * @var CartMenu
     */
    public $cart;

    /**
     * @var MiniCartMenu
     */
    public $miniCart;

    /**
     * @var CheckoutMenu
     */
    public $checkout;

    public function __construct()
    {
        $this->global       = new GlobalMenu();
        $this->cart         = new CartMenu();
        $this->miniCart     = new MiniCartMenu();
        $this->checkout = new CheckoutMenu();
    }
}
