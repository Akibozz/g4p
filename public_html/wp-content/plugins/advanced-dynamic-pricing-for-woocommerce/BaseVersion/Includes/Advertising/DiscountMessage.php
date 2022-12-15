<?php

namespace ADP\BaseVersion\Includes\Advertising;

use ADP\BaseVersion\Includes\Context;
use ADP\BaseVersion\Includes\Core\Cart\CouponCart;
use ADP\BaseVersion\Includes\CustomizerExtensions\CustomizerExtensions;
use ADP\BaseVersion\Includes\Database\Repository\OrderRepository;
use ADP\BaseVersion\Includes\Database\Repository\OrderRepositoryInterface;
use ADP\BaseVersion\Includes\TemplateLoader;
use ADP\BaseVersion\Includes\WC\WcCartItemFacade;
use ADP\BaseVersion\Includes\WC\WcCustomerSessionFacade;

defined('ABSPATH') or exit;

class DiscountMessage
{
    const PANEL_KEY = 'discount_message';

    const CONTEXT_CART = 'cart';
    const CONTEXT_MINI_CART = 'mini-cart';
    const CONTEXT_CHECKOUT = 'checkout';

    protected $amountSavedLabel;

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @param null $deprecated
     */
    public function __construct($deprecated = null)
    {
        $this->context          = adp_context();
        $this->orderRepository  = new OrderRepository();
        $this->amountSavedLabel = __("Amount Saved", 'advanced-dynamic-pricing-for-woocommerce');
    }

    public function withContext(Context $context)
    {
        $this->context = $context;
    }

    public function withOrderRepository(OrderRepositoryInterface $repository)
    {
        $this->orderRepository = $repository;
    }

    /**
     * @param CustomizerExtensions $customizer
     */
    public function setThemeOptionsEmail($customizer)
    {
        return;
    }

    /**
     * @param CustomizerExtensions $customizer
     */
    public function setThemeOptions($customizer)
    {
        // wait until filling get_theme_mod()
        add_action('wp_loaded', function () use ($customizer) {
            $contexts = array(
                self::CONTEXT_CART      => array($this, 'outputCartAmountSaved'),
                self::CONTEXT_MINI_CART => array($this, 'outputMiniCartAmountSaved'),
                self::CONTEXT_CHECKOUT  => array($this, 'outputCheckoutAmountSaved'),
            );

            $this->installMessageHooks($customizer, $contexts);
        });
    }

    /**
     * @param CustomizerExtensions $customizer
     * @param array $contexts
     *
     */
    protected function installMessageHooks($customizer, $contexts)
    {
        $themeOptions = $customizer->getThemeOptions()->advertisingThemeProperties;

        if ( $amountSavedLabel = $themeOptions->global->amountSavedLabel ) {
            $this->amountSavedLabel = _x(
                $amountSavedLabel,
                "theme option 'amount saved label",
                'advanced-dynamic-pricing-for-woocommerce'
            );
        }

        foreach ($contexts as $context => $callback) {

            if ( $context === self::CONTEXT_CART ) {
                $enable = $themeOptions->cart->isEnableAmountSaved;
                $position = $themeOptions->cart->positionAmountSavedAction;
            } elseif ( $context === self::CONTEXT_MINI_CART ) {
                $enable = $themeOptions->miniCart->isEnableAmountSaved;
                $position = $themeOptions->miniCart->positionAmountSavedAction;
            } elseif ( $context === self::CONTEXT_CHECKOUT ) {
                $enable = $themeOptions->checkout->isEnableAmountSaved;
                $position = $themeOptions->checkout->positionAmountSavedAction;
            } else {
                continue;
            }

            if ($enable) {
                if (has_action("wdp_{$context}_discount_message_install")) {
                    do_action(
                        "wdp_{$context}_discount_message_install",
                        $this,
                        $position
                    );
                } else {
                    add_action($position, $callback, 10);
                }
            }
        }
    }

    public function getOption($option, $default = false)
    {
        return $this->context->getOption($option);
    }

    public function outputCartAmountSaved()
    {
        $includeTax   = 'incl' === $this->context->getTaxDisplayCartMode();
        $amount_saved = $this->getAmountSaved($includeTax);

        if ($amount_saved > 0) {
            $this->outputAmountSaved(self::CONTEXT_CART, $amount_saved);
        }
    }

    public function outputMiniCartAmountSaved()
    {
        $includeTax  = 'incl' === $this->context->getTaxDisplayCartMode();
        $amountSaved = $this->getAmountSaved($includeTax);

        if ($amountSaved > 0) {
            $this->outputAmountSaved(self::CONTEXT_MINI_CART, $amountSaved);
        }
    }

    public function outputCheckoutAmountSaved()
    {
        $includeTax  = 'incl' === $this->context->getTaxDisplayCartMode();
        $amountSaved = $this->getAmountSaved($includeTax);

        if ($amountSaved > 0) {
            $this->outputAmountSaved(self::CONTEXT_CHECKOUT, $amountSaved);
        }
    }

    public function outputAmountSaved($context, $amountSaved)
    {
        switch ($context) {
            case self::CONTEXT_CART:
                $template = 'cart-totals.php';
                break;
            case self::CONTEXT_MINI_CART:
                $template = 'mini-cart.php';
                break;
            case self::CONTEXT_CHECKOUT:
                $template = 'cart-totals-checkout.php';
                break;
            default:
                $template = null;
                break;
        }

        if (is_null($template)) {
            return;
        }

        echo TemplateLoader::wdpGetTemplate($template, array(
            'amount_saved' => $amountSaved,
            'title'        => $this->amountSavedLabel,
        ), 'amount-saved');
    }

    public function getAmountSaved($includeTax)
    {
        $cartItems    = WC()->cart->cart_contents;
        $wcSessionFacade = new WcCustomerSessionFacade(WC()->session);

        $amount_saved = floatval(0);

        foreach ($cartItems as $cartItemKey => $cartItem) {
            $facade = new WcCartItemFacade($this->context, $cartItem, $cartItemKey);

            if ($includeTax) {
                $original = ($facade->getOriginalPriceWithoutTax() + $facade->getOriginalPriceTax()) * $facade->getQty();
                $current  = $facade->getSubtotal() + $facade->getExactSubtotalTax();
            } else {
                $original = $facade->getOriginalPriceWithoutTax() * $facade->getQty();
                $current  = $facade->getSubtotal();
            }

            $amount_saved += $original - $current;
        }

        foreach (WC()->cart->get_coupons() as $wcCoupon) {
            $code    = $wcCoupon->get_code();
            $adpData = $wcCoupon->get_meta('adp', true, 'edit');
            $coupon  = isset($adpData['parts']) ? reset($adpData['parts']) : null;

            if ($coupon) {
                /** @var $coupon CouponCart */
                $amount_saved += WC()->cart->get_coupon_discount_amount($code, ! $includeTax);
            }
        }

        foreach ($wcSessionFacade->getFees() as $fee) {
            foreach (WC()->cart->get_fees() as $cartFee) {
                if ($fee->getName() === $cartFee->name) {
                    if ($includeTax) {
                        $amount_saved -= $cartFee->total + $cartFee->tax;
                    } else {
                        $amount_saved -= $cartFee->total;
                    }
                }
            }
        }

        return floatval(apply_filters('wdp_amount_saved', $amount_saved, $cartItems));
    }

}
