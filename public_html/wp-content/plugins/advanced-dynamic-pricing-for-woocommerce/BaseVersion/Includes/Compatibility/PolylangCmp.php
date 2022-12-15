<?php

namespace ADP\BaseVersion\Includes\Compatibility;

use ADP\BaseVersion\Includes\Context;

defined('ABSPATH') or exit;

/**
 * Plugin Name: Polylang
 * Author: WP SYNTEX
 *
 * @see https://polylang.pro
 */
class PolylangCmp
{
    /**
     * @return bool
     */
    public function isActive()
    {
        return defined("POLYLANG_VERSION");
    }

    public function modifyContext(Context $context)
    {
        if (function_exists("pll_current_language")) {
            $context->setLanguage(new Context\Language(pll_current_language('locale')));
        }
    }

    public function addFilterPreloadedListLanguages()
    {
        if ( ! has_action('wdp_preloaded_list_languages', [$this, 'preloadedListLanguages'])) {
            add_filter('wdp_preloaded_list_languages', [$this, 'preloadedListLanguages'], 10, 1);
        }
    }

    public function preloadedListLanguages($list)
    {
        if ( ! method_exists("\PLL_Settings", "get_predefined_languages")) {
            return $list;
        }

        $list = [];

        foreach (\PLL_Settings::get_predefined_languages() as $key => $data) {
            if ($locale = $data['locale'] ?? "") {
                $list[] = [
                    'id'   => $locale,
                    'text' => $data['name'] . " ($locale)" ?? $locale,
                ];
            }
        }

        return $list;
    }
}
