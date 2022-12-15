<?php

namespace ADP\BaseVersion\Includes\Shortcodes;

use ADP\BaseVersion\Includes\Cache\CacheHelper;
use ADP\BaseVersion\Includes\Context;
use ADP\BaseVersion\Includes\Core\Rule\Rule;
use ADP\BaseVersion\Includes\Core\Rule\SingleItemRule;
use ADP\BaseVersion\Includes\Database\Database;
use ADP\BaseVersion\Includes\Database\Repository\RuleRepository;
use ADP\BaseVersion\Includes\Database\RuleStorage;
use ADP\BaseVersion\Includes\Enums\RuleTypeEnum;
use ADP\Factory;

defined('ABSPATH') or exit;

class OnSaleProducts extends Products
{
    const NAME = 'adp_products_on_sale';
    const STORAGE_KEY = 'wdp_products_onsale';
    const STORAGE_WITH_RULES_KEY = 'wdp_rules_products_onsale';

    protected function set_adp_products_on_sale_query_args(&$queryArgs)
    {
        if ($this->attributes['rule_id'] !== false) {
            $productIds = static::getCachedProductsIdsByRule($this->attributes['rule_id']);
        } else {
            $productIds = static::getCachedProductsIds();
        }

        if ($this->attributes["show_wc_onsale_products"])
            $queryArgs['post__in'] = array_unique(array_merge(array(0), $productIds, wc_get_product_ids_on_sale()));
        else
            $queryArgs['post__in'] = array_merge(array(0), $productIds);
    }

    /**
     * @param null $deprecated
     *
     * @return array
     */
    public static function getProductsIds($from = null, $count = null, $deprecated = null)
    {
        global $wpdb;

        $context         = adp_context();
        $rulesCollection = CacheHelper::loadActiveRules($context);
        $rulesArray      = $context->getOption('rules_apply_mode') !== "none" ? $rulesCollection->getRules() : array();

        /** @var RuleStorage $storage */
        $storage                   = Factory::get("Database_RuleStorage");
        $ruleRepository            = new RuleRepository();
        $rows                      = $ruleRepository->getRules(
            array(
                'active_only' => true,
                'rule_types'  => array(RuleTypeEnum::PERSISTENT()->getValue())
            )
        );
        $persistentRulesCollection = $storage->buildPersistentRules($rows);
        $persistentRulesArray      = $context->getOption('rules_apply_mode') !== "none" ? $persistentRulesCollection->getRules() : array();

        $rulesArray = array_merge($rulesArray, $persistentRulesArray);

        /** @var $sqlGenerator SqlGenerator */
        $sqlGenerator = Factory::get("Shortcodes_SqlGenerator");

        foreach ($rulesArray as $rule) {
            if (self::isSimpleRule($rule)) {
                $sqlGenerator->applyRuleToQuery($rule);
            }
        }

        if ($sqlGenerator->isEmpty()) {
            return array();
        }

        $sql_joins    = $sqlGenerator->getJoin();
        $sql_where    = $sqlGenerator->getWhere();
        $excludeWhere = $sqlGenerator->getExcludeWhere();

        $sql = "SELECT post.ID as id, post.post_parent as parent_id FROM `$wpdb->posts` AS post
			" . implode(" ", $sql_joins) . "
			WHERE post.post_type IN ( 'product', 'product_variation' )
				AND post.post_status = 'publish'
			" . ($sql_where ? " AND " : "") . implode(" OR ", array_map(function ($v) {
                return "(" . $v . ")";
            }, $sql_where)) . ($excludeWhere ? " AND " : "") . implode(" AND ", array_map(function ($v) {
                return "(" . $v . ")";
            }, $excludeWhere)) . "
			GROUP BY post.ID";
        if (isset($from) && isset($count)) {
            $sql .= " LIMIT $from, $count";
        }

        $bogoProducts = $wpdb->get_results($sql);

        $productIdsBogo = wp_parse_id_list(array_merge(wp_list_pluck($bogoProducts, 'id'),
            array_diff(wp_list_pluck($bogoProducts, 'parent_id'), array(0))));

        return $productIdsBogo;
    }

    public static function getProductsIdsPerRule($from = null, $count = null) {
        global $wpdb;

        $context         = adp_context();
        $rulesCollection = CacheHelper::loadActiveRules($context);
        $rulesArray      = $context->getOption('rules_apply_mode') !== "none" ? $rulesCollection->getRules() : array();

        /** @var RuleStorage $storage */
        $storage                   = Factory::get("Database_RuleStorage");
        $ruleRepository            = new RuleRepository();
        $rows                      = $ruleRepository->getRules(
            array(
                'active_only' => true,
                'rule_types'  => array(RuleTypeEnum::PERSISTENT()->getValue())
            )
        );
        $persistentRulesCollection = $storage->buildPersistentRules($rows);
        $persistentRulesArray      = $context->getOption('rules_apply_mode') !== "none" ? $persistentRulesCollection->getRules() : array();

        $rulesArray = array_merge($rulesArray, $persistentRulesArray);

        /** @var $sqlGenerator SqlGenerator */
        $sqlGenerator = Factory::get("Shortcodes_SqlGenerator");

        $productsOnSalePerRule = array();

        foreach ($rulesArray as $rule) {
            if (self::isSimpleRule($rule)) {
                $sqlGenerator->clear();
                $sqlGenerator->applyRuleToQuery($rule);

                $sql_joins    = $sqlGenerator->getJoin();
                $sql_where    = $sqlGenerator->getWhere();
                $excludeWhere = $sqlGenerator->getExcludeWhere();

                $sql = "SELECT post.ID as id, post.post_parent as parent_id FROM `$wpdb->posts` AS post
                    " . implode(" ", $sql_joins) . "
                    WHERE post.post_type IN ( 'product', 'product_variation' )
                        AND post.post_status = 'publish'
                    " . ($sql_where ? " AND " : "") . implode(" OR ", array_map(function ($v) {
                        return "(" . $v . ")";
                    }, $sql_where)) . ($excludeWhere ? " AND " : "") . implode(" AND ", array_map(function ($v) {
                        return "(" . $v . ")";
                    }, $excludeWhere)) . "
                    GROUP BY post.ID";
                if (isset($from) && isset($count)) {
                    $sql .= " LIMIT $from, $count";
                }

                $onSaleProducts = $wpdb->get_results($sql);

                $productIdsOnSale = wp_parse_id_list(array_merge(wp_list_pluck($onSaleProducts, 'id'),
                    array_diff(wp_list_pluck($onSaleProducts, 'parent_id'), array(0))));

                $productsOnSalePerRule[$rule->getId()] = $productIdsOnSale;
            }
        }

        return $productsOnSalePerRule;
    }

    /**
     * @param array $ruleId
     * @return array
     */
    public static function getCachedProductsIdsByRule($ruleIds)
    {
        $productIdsPerRule = static::getCachedProductsIdsPerRule();

        $productIdsByRuleIds = array();
        foreach ($ruleIds as $ruleId) {
            $productIdsByRuleIds = array_merge($productIdsByRuleIds, $productIdsPerRule[$ruleId]);
        }

        return array_unique($productIdsByRuleIds);
    }

    /**
     * @return mixed
     */
    public static function getCachedProductsIdsPerRule()
    {

        // Load from cache.
        $productIdsPerRule = get_transient(static::STORAGE_WITH_RULES_KEY);

        // Valid cache found.
        if (false !== $productIdsPerRule) {
            return $productIdsPerRule;
        }

        return static::updateCachedProductsIdsPerRule();
    }

    /**
     * @return mixed
     */
    public static function updateCachedProductsIdsPerRule()
    {

        $productIdsPerRule = static::getProductsIdsPerRule();

        set_transient(static::STORAGE_WITH_RULES_KEY, $productIdsPerRule, DAY_IN_SECONDS * 30);

        return $productIdsPerRule;
    }

    public static function clearCache() {
        parent::clearCache();
        delete_transient(self::STORAGE_WITH_RULES_KEY);
    }

    public static function partialUpdateCachedProductsIds($from, $count)
    {
        $result     = static::getProductsIdsPerRule($from, $count);
        $productIdsPerRule = get_transient(static::STORAGE_WITH_RULES_KEY);
        if ($productIdsPerRule) {
            foreach ($productIdsPerRule as $ruleId => $productIdsForRule) {
                if (isset($result[$ruleId])) {
                    $productIdsPerRule[$ruleId] = array_merge($productIdsForRule, $result[$ruleId]);
                }
            }
        } else {
            $productIdsPerRule = $result;
        }

        set_transient(static::STORAGE_WITH_RULES_KEY, $productIdsPerRule, DAY_IN_SECONDS * 30);
        return parent::partialUpdateCachedProductsIds($from, $count);
    }

    /**
     * @param Rule $rule
     *
     * @return bool
     */
    protected static function isSimpleRule($rule)
    {
        return
            $rule instanceof SingleItemRule &&
            $rule->getProductAdjustmentHandler() &&
            ! $rule->getProductRangeAdjustmentHandler() &&
            ! $rule->getRoleDiscounts() &&
            count($rule->getGifts()) === 0 &&
            count($rule->getItemGiftsCollection()->asArray()) === 0 &&
            adp_functions()->isRuleMatchedCart($rule) &&
            count($rule->getLimits()) === 0;
    }

    /**
     * Parse attributes.
     *
     * @since  3.2.0
     * @param  array $attributes Shortcode attributes.
     * @return array
     */
    protected function parse_attributes( $attributes ) {
        $parsed_attributes = parent::parse_attributes( $attributes );
        //parse own attrubutes
        $parsed_attributes['show_wc_onsale_products'] = false;
        if ( isset($attributes['show_wc_onsale_products']) )
            $parsed_attributes['show_wc_onsale_products'] = wc_string_to_bool($attributes['show_wc_onsale_products']);
        $parsed_attributes['rule_id'] = false;
        if (isset($attributes['rule_id'])) {
            $parsed_attributes['rule_id'] = explode(',', $attributes['rule_id']);
        }
        return $parsed_attributes;
    }

}
