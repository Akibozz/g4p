<?php

namespace ADP\BaseVersion\Includes\Debug;

use ADP\BaseVersion\Includes\Context;
use ADP\BaseVersion\Includes\Core\Cart\Cart;
use ADP\BaseVersion\Includes\Core\Cart\Coupon;
use ADP\BaseVersion\Includes\Core\Cart\CouponCart;
use ADP\BaseVersion\Includes\Core\Cart\CouponInterface;
use ADP\BaseVersion\Includes\Core\Cart\Fee;
use ADP\BaseVersion\Includes\Core\RuleProcessor\Listener;
use ADP\BaseVersion\Includes\Core\RuleProcessor\RuleProcessor;
use ADP\BaseVersion\Includes\WC\WcCartItemFacade;
use ADP\BaseVersion\Includes\WC\WcCustomerSessionFacade;
use ADP\BaseVersion\Includes\WC\WcShippingRateFacade;
use ADP\BaseVersion\Includes\WC\WcTotalsFacade;
use WC_Cart;
use WC_Product;
use WC_Session;
use WC_Session_Handler;

defined('ABSPATH') or exit;

class CartCalculatorListener implements Listener
{
    /**
     * @var Context
     */
    protected $context;

    /**
     * @var array
     */
    protected $totals;

    /**
     * @var array
     */
    protected $currentTotals;

    /**
     * @param null $deprecated
     */
    public function __construct($deprecated = null)
    {
        $this->context       = adp_context();
        $this->totals        = array();
        $this->currentTotals = array();
    }

    public function withContext(Context $context)
    {
        $this->context = $context;
    }

    /**
     * @param WC_Cart|null $wcCart
     * @param WC_Session_Handler|null $wcSession
     */
    public function processStarted($wcCart, $wcSession)
    {
        $this->currentTotals['processStarted'] = $this->fetchWcCartData($wcCart, $wcSession);
    }

    /**
     * @param Cart $cart
     */
    public function cartCreated($cart)
    {

    }

    /**
     * @param Cart $cart
     */
    public function cartCompleted($cart)
    {

    }

    public function calcProcessStarted()
    {

    }

    /**
     * @param RuleProcessor $proc
     */
    public function ruleCalculated($proc)
    {
        if ( ! isset($this->currentTotals['rules'])) {
            $this->currentTotals['rules'] = array();
        }

        $this->currentTotals['rules'][] = array(
            'id'        => $proc->getRule()->getId(),
            'status'    => $proc->getStatus(),
            'exec_time' => $proc->getLastExecTime(),
        );
    }

    /**
     * @param bool $result
     */
    public function processResult($result)
    {
        $this->currentTotals['processResult'] = $result;
    }

    /**
     * @param WC_Cart $wcCart
     * @param WC_Session_Handler|null $wcSession
     */
    public function processFinished($wcCart, $wcSession)
    {
        $this->currentTotals['processFinished'] = $this->fetchWcCartData($wcCart, $wcSession);
        $this->totals[]                         = $this->currentTotals;
        $this->currentTotals                    = array();
    }

    /**
     * @param WC_Cart|null $wcCart
     * @param WC_Session_Handler|null $wcSession
     *
     * @return array
     */
    protected function fetchWcCartData($wcCart, $wcSession)
    {
        /**
         * Do not use @see WC_Cart::is_empty
         * It causes 'Get basket should not be called before the wp_loaded action.' error during REST API request
         */
        if ( ! ($wcCart instanceof WC_Cart) || count(array_filter($wcCart->get_cart_contents())) === 0) {
            return array();
        }

        $cartContentsData = array();
        $wcSessionFacade = new WcCustomerSessionFacade($wcSession);

        $couponReplacedItemRuleId = array();
        $replacedFreeItemRuleId   = array();
        $feeReplacedItemRuleId    = array();
        foreach ($wcSessionFacade->getSingleCoupons() as $coupon) {
            if ($coupon instanceof Coupon) {
                if ($coupon->isType($coupon::TYPE_ITEM_DISCOUNT)) {
                    $couponReplacedItemRuleId[$coupon->getRuleId()] = $coupon;
                }

                if ($coupon->isType($coupon::TYPE_FREE_ITEM)) {
                    $replacedFreeItemRuleId[$coupon->getRuleId()] = $coupon;
                }
            }
        }

        foreach ($wcSessionFacade->getGroupedCoupons() as $code => $coupons) {
            foreach ($coupons as $coupon) {
                if ($coupon instanceof Coupon) {
                    if ($coupon->isType($coupon::TYPE_ITEM_DISCOUNT)) {
                        $couponReplacedItemRuleId[$coupon->getRuleId()] = $coupon;
                    }

                    if ($coupon->isType($coupon::TYPE_FREE_ITEM)) {
                        $replacedFreeItemRuleId[$coupon->getRuleId()] = $coupon;
                    }
                }
            }
        }

        foreach ($wcSessionFacade->getFees() as $fee) {
            if ($fee->isType($fee::TYPE_ITEM_OVERPRICE)) {
                $feeReplacedItemRuleId[$fee->getRuleId()] = $fee;
            }
        }

        foreach ($wcCart->cart_contents as $key => $cart_content) {
            $cartItem = new WcCartItemFacade($this->context, $cart_content, $key);

            $clearData = $cartItem->getClearData();
            /** @var WC_Product $product */
            $product                           = $clearData[$cartItem::KEY_PRODUCT];
            $clearData[$cartItem::KEY_PRODUCT] = array(
                'id'         => $product->get_id(),
                'parent_id'  => $product->get_parent_id(),
                'name'       => $product->get_name(),
                'changes'    => $product->get_changes(),
                'price_edit' => $product->get_price(''),
                'price_view' => $product->get_price(),
            );

            $couponRepl = array();
            $feeRepl    = array();
            if ($cartItem->getHistory()) {
                $historyKeys = array_keys($cartItem->getHistory());

                if ($cartItem->isFreeItem()) {
                    foreach (array_intersect($historyKeys, array_keys($replacedFreeItemRuleId)) as $ruleId) {
                        $coupon       = $replacedFreeItemRuleId[$ruleId];
                        $couponRepl[] = array(
                            'code'   => $coupon->getCode(),
                            'type'   => $coupon->getType(),
                            'value'  => $coupon->getValue(),
                            'amount' => $wcCart->get_coupon_discount_amount($coupon->getCode(),
                                $wcCart->display_cart_ex_tax),
                            'ruleId' => $coupon->getRuleId(),
                        );
                    }
                } else {
                    foreach (array_intersect($historyKeys, array_keys($couponReplacedItemRuleId)) as $ruleId) {
                        $coupon       = $couponReplacedItemRuleId[$ruleId];
                        $couponRepl[] = array(
                            'code'   => $coupon->getCode(),
                            'type'   => $coupon->getType(),
                            'value'  => $coupon->getValue(),
                            'amount' => $wcCart->get_coupon_discount_amount($coupon->getCode(),
                                $wcCart->display_cart_ex_tax),
                            'ruleId' => $coupon->getRuleId(),
                        );
                    }
                }

                if ($cartItem->getHistory()) {
                    foreach (array_intersect($historyKeys, array_keys($feeReplacedItemRuleId)) as $ruleId) {
                        $fee       = $feeReplacedItemRuleId[$ruleId];
                        $feeRepl[] = array(
                            'name'     => $fee->getName(),
                            'type'     => $fee->getType(),
                            'value'    => $fee->getValue(),
                            'amount'   => $fee->getAmount(),
                            'taxable'  => $fee->isTaxAble(),
                            'taxClass' => $fee->getTaxClass(),
                            'ruleId'   => $fee->getRuleId(),
                        );
                    }
                }
            }

            $cartContentsData[$key] = array(
                'clear'               => $clearData,
                'third_party'         => $cartItem->getThirdPartyData(),
                'our_data'            => $cartItem->getOurData(),
                'coupon_replacements' => $couponRepl,
                'fee_replacements'    => $feeRepl,
            );
        }

        $groupedCoupons = array();
        foreach ($wcSessionFacade->getGroupedCoupons() as $code => $coupons) {
            $groupedCoupons[$code] = array();

            foreach ($coupons as $coupon) {
                $data = array();

                if ($coupon instanceof CouponCart) {
                    $data = array(
                        'code'   => $coupon->getCode(),
                        'type'   => $coupon->getType(),
                        'value'  => $coupon->getValue(),
                        'amount' => $wcCart->get_coupon_discount_amount(
                            $coupon->getCode(), $wcCart->display_cart_ex_tax
                        ),
                        'ruleId' => $coupon->getRuleId(),
                    );
                } elseif ($coupon instanceof Coupon) {
                    $data = array(
                        'code'         => $coupon->getCode(),
                        'type'         => $coupon->getType(),
                        'value'        => $coupon->getValue(),
                        'amount'       => $wcCart->get_coupon_discount_amount(
                            $coupon->getCode(), $wcCart->display_cart_ex_tax
                        ),
                        'ruleId'       => $coupon->getRuleId(),
                        'affectedItem' => $coupon->getAffectedCartItemKey(),
                        'affectedQty'  => $coupon->getAffectedCartItemQty(),
                    );
                }

                if ($data) {
                    $groupedCoupons[$code][] = $data;
                }
            }
        }

        $shippingRates = array();

        if ($this->context->getOption("disable_shipping_calc_during_process", false)) {
            $reflection = new \ReflectionClass($wcCart);
            $property   = $reflection->getProperty('shipping_methods');
            $property->setAccessible(true);
            if ( ! ($shippingRates = $property->getValue($wcCart))) {
                $shippingRates = array();
            }
        } else {
            if ( $wcCart->show_shipping() ) {
                $canCalculateShipping = true;

                /**
                 * Calculate shipping requires "line_total".
                 * It may not exist if the recalculation was not called.
                 * @see WC_Cart::get_shipping_packages()
                 * 'contents_cost'   => array_sum( wp_list_pluck( $this->get_items_needing_shipping(), 'line_total' ) )
                 *
                 */
                foreach ( $wcCart->cart_contents as $cartContent ) {
                    if ( ! isset($cartContent['line_total']) ) {
                        $canCalculateShipping = false;
                        break;
                    }
                }

                if ( $canCalculateShipping ) {
                    $shippingRates = $wcCart->calculate_shipping();
                }
            } else {
                $shippingRates = array();
            }
        }

        return array(
            'items'    => $cartContentsData,
            'coupons'  => array(
                'applied' => $wcCart->get_applied_coupons(),
                'adp'     => array(
                    'single'  => array_filter(
                        array_map(
                            function ($coupon) use ($wcCart) {
                                /** @var CouponInterface $coupon */

                                if ($coupon instanceof CouponCart) {
                                    return array(
                                        'code'   => $coupon->getCode(),
                                        'type'   => $coupon->getType(),
                                        'value'  => $coupon->getValue(),
                                        'amount' => $wcCart->get_coupon_discount_amount(
                                            $coupon->getCode(), $wcCart->display_cart_ex_tax
                                        ),
                                        'ruleId' => $coupon->getRuleId(),
                                    );
                                } elseif ($coupon instanceof Coupon) {
                                    return array(
                                        'code'         => $coupon->getCode(),
                                        'type'         => $coupon->getType(),
                                        'value'        => $coupon->getValue(),
                                        'amount'       => $wcCart->get_coupon_discount_amount(
                                            $coupon->getCode(), $wcCart->display_cart_ex_tax
                                        ),
                                        'ruleId'       => $coupon->getRuleId(),
                                        'affectedItem' => $coupon->getAffectedCartItemKey(),
                                        'affectedQty'  => $coupon->getAffectedCartItemQty(),
                                    );
                                }

                                return null;
                            }, $wcSessionFacade->getSingleCoupons())),
                    'grouped' => $groupedCoupons,
                ),
            ),
            'fees'     => array(
                'applied' => json_decode(json_encode($wcCart->get_fees())),
                'adp'     => array_map(function ($fee) {
                    /** @var Fee $fee */
                    return array(
                        'name'     => $fee->getName(),
                        'type'     => $fee->getType(),
                        'value'    => $fee->getValue(),
                        'amount'   => $fee->getAmount(),
                        'taxable'  => $fee->isTaxAble(),
                        'taxClass' => $fee->getTaxClass(),
                        'ruleId'   => $fee->getRuleId(),
                    );
                }, $wcSessionFacade->getFees()),
            ),
            'shipping' => array(
                'packages' => $wcCart->get_shipping_packages(),
                'methods'  => array_map(function ($rate) {
                    if ( ! $rate) {
                        return null;
                    }

                    $shippingRate = new WcShippingRateFacade($rate);
                    $cost          = (float)$shippingRate->getRate()->get_cost();
                    $meta          = $rate->get_meta_data();
                    $original_cost = $cost;
                    $is_on_sale    = false;
                    $rules         = array();
                    $is_free       = false;

                    if ($shippingRate->getInitialPrice()) {
                        $original_cost = $shippingRate->getInitialPrice();
                        $is_on_sale    = true;
                    }

                    if ($shippingRate->getAdjustments()) {
                        $rules = array_map(function ($adj) {
                            return array(
                                'ruleId' => $adj->getRuleId(),
                                'type'   => $adj->getType(),
                                'value'  => $adj->getValue(),
                                'amount' => $adj->getAmount(),
                            );
                        }, $shippingRate->getAdjustments());
                    }

                    if ($shippingRate->getType() === "free") {
                        $is_free = true;
                    }

                    return array(
                        'label'          => $shippingRate->getRate()->get_label(),
                        'cost'           => $cost,
                        'original_cost'  => $original_cost,
                        'is_on_adp_sale' => $is_on_sale,
                        'rules'          => $rules,
                        'is_adp_free'    => $is_free,
                    );
                }, $shippingRates),
            ),
        );
    }

    /**
     * @param Cart $cart
     *
     * @return array
     */
    protected function fetchCartData($cart)
    {
        return array();
    }

    /**
     * @return array
     */
    public function getTotals()
    {
        return $this->totals;
    }
}
