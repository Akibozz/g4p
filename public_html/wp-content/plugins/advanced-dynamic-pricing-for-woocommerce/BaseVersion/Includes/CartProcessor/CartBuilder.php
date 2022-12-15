<?php

namespace ADP\BaseVersion\Includes\CartProcessor;

use ADP\BaseVersion\Includes\Compatibility\WcSubscriptionsCmp;
use ADP\BaseVersion\Includes\Compatibility\WpcBundleCmp;
use ADP\BaseVersion\Includes\Core\Cart\Cart;
use ADP\BaseVersion\Includes\Core\Cart\CartContext;
use ADP\BaseVersion\Includes\Core\Cart\CartCustomer;
use ADP\BaseVersion\Includes\Context;
use ADP\BaseVersion\Includes\Compatibility\SomewhereWarmBundlesCmp;
use ADP\BaseVersion\Includes\Compatibility\SomewhereWarmCompositesCmp;
use ADP\BaseVersion\Includes\Database\Repository\OrderRepository;
use ADP\BaseVersion\Includes\Database\Repository\RuleRepository;
use ADP\BaseVersion\Includes\WC\WcCartItemFacade;
use ADP\BaseVersion\Includes\WC\WcCouponFacade;
use ADP\BaseVersion\Includes\WC\WcCustomerConverter;
use ADP\BaseVersion\Includes\WC\WcCustomerSessionFacade;
use ADP\Factory;
use WC_Cart;
use WC_Coupon;
use WC_Customer;

defined('ABSPATH') or exit;

class CartBuilder
{
    /**
     * @var Context
     */
    protected $context;

    /**
     * @var SomewhereWarmBundlesCmp
     */
    protected $bundlesCmp;

    /**
     * @var WpcBundleCmp
     */
    protected $wpcBundlesCmp;

    /**
     * @var SomewhereWarmCompositesCmp
     */
    protected $compositeCmp;

    /**
     * @param null $deprecated
     */
    public function __construct($deprecated = null)
    {
        $this->context = adp_context();
        $this->bundlesCmp = new SomewhereWarmBundlesCmp();
        $this->wpcBundlesCmp = new WpcBundleCmp();
        $this->compositeCmp = new SomewhereWarmCompositesCmp();
    }

    public function withContext(Context $context)
    {
        $this->context = $context;
        $this->bundlesCmp->withContext($context);
        $this->wpcBundlesCmp->withContext($context);
        $this->compositeCmp->withContext($context);
    }

    /**
     * @param WC_Customer|null $wcCustomer
     * @param \WC_Session_Handler|null $wcSession
     *
     * @return Cart
     */
    public function create($wcCustomer, $wcSession)
    {
        $context = $this->context;
        /** @var WcCustomerConverter $converter */
        $converter = Factory::get("WC_WcCustomerConverter", $context);
        $customer  = $converter->convertFromWcCustomer($wcCustomer, $wcSession);
        $userMeta = get_user_meta($customer->getId());
        $customer->setMetaData($userMeta ? $userMeta : array());

        $cartContext = new CartContext($customer, $context);
        /** @var WcCustomerSessionFacade $wcSessionFacade */
        $wcSessionFacade = Factory::get("WC_WcCustomerSessionFacade", $wcSession);
        $cartContext->withSession($wcSessionFacade);

        /** @var Cart $cart */
        $cart = Factory::get('Core_Cart_Cart', $cartContext);

        return $cart;
    }

    /**
     *
     * @param Cart $cart
     * @param WC_Cart $wcCart
     */
    public function populateCart($cart, $wcCart)
    {
        $pos = 0;

        foreach ($wcCart->cart_contents as $cartKey => $wcCartItem) {
            $wrapper = new WcCartItemFacade($this->context, $wcCartItem, $cartKey);
            $wrapper->withContext($this->context);

            if ($wrapper->isClone()) {
                continue;
            }

            $item = $wrapper->createItem();
            if ($item) {
                $item->setPos($pos);

                if ($this->bundlesCmp->isBundled($wrapper)) {
                    $item->addAttr($item::ATTR_IMMUTABLE);
                }

                if ($this->wpcBundlesCmp->isBundled($wrapper)) {
                    $item->addAttr($item::ATTR_IMMUTABLE);
                }

                if ($this->wpcBundlesCmp->isSmartBundle($wrapper)) {
                    $item->addAttr($item::ATTR_IMMUTABLE);
                }

                if ((new WcSubscriptionsCmp())->isRenewalSubscription($wrapper)) {
                    $item->addAttr($item::ATTR_IMMUTABLE);
                }

                if ($this->compositeCmp->isCompositeItem($wrapper)) {
                    if ($this->compositeCmp->isAllowToProcessPricedIndividuallyItems()) {
                        if ($this->compositeCmp->isCompositeItemNotPricedIndividually($wrapper, $wcCart)) {
                            $item->addAttr($item::ATTR_IMMUTABLE);
                        }
                    } else {
                        $item->addAttr($item::ATTR_IMMUTABLE);
                    }
                }

                $cart->addToCart($item);
            }

            $pos++;
        }

        /** Save applied coupons. It needs for detect free (gifts) products during current calculation and notify about them. */
        $this->addOriginCoupons($cart, $wcCart);
    }

    /**
     * @param Cart $cart
     * @param WC_Cart $wcCart
     */
    public function addOriginCoupons($cart, $wcCart)
    {
        if ( ! ($wcCart instanceof WC_Cart)) {
            return;
        }

        $adpCoupons = $cart->getContext()->getSession()->getAdpCoupons();

        foreach ($wcCart->get_coupons() as $code => $coupon) {
            /** @var $coupon WC_Coupon */

            if ($coupon->is_valid()) {
                if ($coupon->get_discount_type('edit') === WcCouponFacade::TYPE_ADP_RULE_TRIGGER) {
                    $cart->addRuleTriggerCoupon($coupon->get_code('edit'));
                } elseif (!$coupon->get_meta('adp', true) && !in_array($code, $adpCoupons)) {
                    $cart->addOriginCoupon($code);
                }
            }
        }
    }
}
