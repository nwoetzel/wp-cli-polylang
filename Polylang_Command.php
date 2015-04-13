<?php

/**
 * Polylang community package for WP-CLI
 *
 * Polylang_Command class — `wp polylang` commands
 *
 * @package     WP-CLI
 * @subpackage  Polylang
 * @author      Sébastien Santoro aka Dereckson <dereckson@espace-win.org>
 * @license     http://www.opensource.org/licenses/bsd-license.php BSD
 * @filesource
 */

if (!defined('WP_CLI')) {
    return;
}
// define PLL_ADMIN and PLL_SETTINGS so that $polylang->model is initialized properly
define('PLL_ADMIN',true);
define('PLL_SETTINGS',true);

require_once 'PolylangHelperFunctions.php';

/**
 * Implements polylang command, to interact with the Polylang plug-in.
 */
class Polylang_Command extends WP_CLI_Command {

    /**
     * Prints the languages currently available
     *
     * ## EXAMPLES
     *
     *     wp polylang languages
     *
     * @synopsis
     */
    function languages ($args, $assocArgs) {
        $languages = pll_installed_language_list();
        if ( !$languages) {
            WP_CLI::success("No languages are currently configured.");
            return;
        }

        $default = pll_default_language();
        foreach ($languages as $language) {
            $line = "$language->slug — $language->name";

            if ($language->slug == $default) {
                $line .= ' [DEFAULT]';
            }

            WP_CLI::line($line);
        }
    }

    /**
     * Gets the site homepage URL in the specified language
     *
     * ## OPTIONS
     *
     * <language-code>
     * : The language to get the home URL to.
     *
     * ## EXAMPLES
     *
     *   wp polylang home
     *   wp polylang home fr
     *
     * @synopsis [<language-code>]
     */
    function home ($args, $assocArgs) {
        $lang = (count($args) == 1) ?  $args[0] : '';

        $url = pll_home_url($lang);
        WP_CLI::line($url);
    }

    /**
     * Gets a post or a term in the specified language
     *
     * ## OPTIONS
     *
     * <data-type>
     * : 'post' or 'term'
     *
     * <data-id>
     * : the ID of the object to get
     *
     * <language-count>
     * : the language (if omitted, will be returned in the default language)
     *
     * ## EXAMPLES
     *
     *   wp polylang get post 1 fr
     *
     * @synopsis <data-type> <data-id> [<language-code>]
     */
    function get($args, $assocArgs) {
        $lang = (count($args) == 2) ?  '' : $args[2];

        switch ($what = $args[0]) {
            case 'post':
            case 'term':
                $method = 'pll_get_' . $what;
                break;

            default:
                WP_CLI::error("Expected: wp polylang get <post or term> ..., not '$what'");
        }

        $id = $method($args[1], $lang);
        WP_CLI::line($id);
    }

    /**
     * Get the language of a post or a term as slug
     *
     * ## OPTIONS
     *
     * <data-type>
     * : 'post' or 'term'
     *
     * <data-id>
     * : the ID of the object to get the language for
     *
     * ## EXAMPLES
     *
     *   wp polylang getlang post 12
     *   wp polylang getlang term 5
     *
     * @synopsis <data-type> <data-id>
     */
    function getlang($args, $assocArgs) {
        switch ($what = $args[0]) {
            case 'post':
            case 'term':
                $method = 'pll_get_' . $what . '_language';
                break;

            default:
                WP_CLI::error("Expected: wp polylang getlang <post or term> ..., not '$what'");
        }

        // only available since 1.5.4 of polylang
        if( !function_exists($method)) {
            WP_CLI::error("function $method does not exist befor polylang 1.5.4!");
        }

        $lang = $method($args[1]);
        if( !$lang) {
            WP_CLI::error("'$what' $args[1] is not managed yet");
        }
        WP_CLI::line($lang);
    }

    /**
     * Sets a post or a term to the specified language
     *
     * ## OPTIONS
     *
     * <data-type>
     * : 'post' or 'term'
     *
     * <data-id>
     * : the ID of the object to set
     *
     * <language-code>
     * : the language (if omitted, will be set to the default language)
     *
     * ## EXAMPLES
     *
     *   wp polylang set post 1 fr
     *
     * @synopsis <data-type> <data-id> [<language-code>]
     */
    function set($args, $assocArgs) {
        $lang = '';
        // is no language code given - use default
        if( count($args) == 2) {
                $lang =  pll_default_language();
        }
        // use the lang code given - test if the lang is installed
        else {
                $lang = $args[2];
                if( !pll_is_language_installed($lang)) {
                        WP_CLI::error("Language '$lang' is not installed!");
                }
        }

        switch ($what = $args[0]) {
            case 'post':
            case 'term':
                $method = 'pll_set_' . $what . '_language';
                break;

            default:
                WP_CLI::error("Expected: wp polylang set <post or term> ..., not '$what'");
        }

        $method($args[1], $lang);
        WP_CLI::success("language for $what $args[1] saved");
    }

    /**
     * Associate terms or post as translations
     *
     * ## OPTIONS
     *
     * <data-type>
     * : 'post' or 'term'
     *
     * <data-ids>
     * : comma separated list of data IDs that are translations of each other
     *
     * ## EXAMPLES
     *
     *   wp polylang trans post 1,7,9
     *   wp polylang trans term 27,32
     *
     * @synopsis <data-type> <data-ids>
     */
    function trans ($args, $assocArgs) {
        // comma sperated list as array
        $data_ids = explode( ',', $args[1]);

        // two or more ids necessary
        if( count( $data_ids) < 2) {
                WP_CLI::error("need at least two ids for translation");
        }

        // term or post
        switch ($what = $args[0]) {
            case 'post':
            case 'term':
                $method = 'pll_save_' . $what . '_translations';
                $get_lang_method = 'pll_get_' . $what . '_language';
                break;

            default:
                WP_CLI::error("Expected: wp polylang trans <post or term> ..., not '$what'");
        }

        // only available since 1.5.4 of polylang
        if( !function_exists($get_lang_method)) {
            WP_CLI::error("function $get_lang_method does not exist befor polylang 1.5.4 and is necessary for this implementation!");
        }

        // get language of each term or post and build array for the pll_save api function
        $arr = array();
        foreach( $data_ids as $id) {
            $lang = $get_lang_method( $id);

            // is the post or term already managed
            if( !$lang) {
                WP_CLI::error("'$what' $id is not managed yet and cannot be translated");
            }

            // is there a post or term with the same language given?
            if( array_key_exists( $lang, $arr)){
                WP_CLI::error("$lang => $id as well as $lang => $arr[$lang] ar two $what with the same language!");
            }
            $arr[ $lang] = intval($id);
        }

        // save the translation
        $method( $arr);
        WP_CLI::success("translations saved");
    }

    /**
     * Adds, gets information about or removes a language
     *
     * ## OPTIONS
     *
     * <operation>
     * : the language operation (add, info, del)
     *
     * <language-code>
     * : the language code
     *
     * <order>
     * : for add operation, indicates the order of the language
     *
     * ## EXAMPLES
     *
     *   wp polylang language add fr
     *   wp polylang language add nl 2
     *   wp polylang language info vec
     *   wp polylang language del vec
     *
     * @synopsis <operation> <language-code> [<order>]
     */
    function language ($args, $assocArgs) {
        $language_code = $args[1];
        $language_order = (count($args) == 3) ? $args[2] : 0;
        $language_info = pll_get_default_language_information($language_code);
        if ($language_info === null) {
             WP_CLI::error("$language_code isn't a valid language code.");
             return;
        }
        $language_installed = pll_is_language_installed($language_code);

        switch ($args[0]) {
            case 'info':
                WP_CLI::line('Code:      ' . $language_info['code']);
                WP_CLI::line('Locale     ' . $language_info['locale']);
                WP_CLI::line('Name:      ' . $language_info['name']);
                WP_CLI::line('RTL:       ' . ($language_info['rtl'] ? 'yes' : 'no'));
                WP_CLI::line('Installed: ' . ($language_installed ? 'yes' : 'no'));
                break;

            case 'add':
                if ($language_installed) {
                    WP_CLI::warning("This language is already installed.");
                    return;
                }

                if (pll_add_language($language_code, $language_order)) {
                    WP_CLI::success("Language added.");
                    return;
                }

                WP_CLI::error("Can't add the language.");
                break;

            case 'del':
                if (!$language_installed) {
                    WP_CLI::warning("This language isn't installed.");
                    return;
                }

                if (pll_del_language($language_code)) {
                     WP_CLI::success("Language deleted.");
                     return;
                }

                WP_CLI::error("Could not delete language");
                break;

            default:
                WP_CLI::error("Unknown command: polylang language $args[0]. Expected: add/del/info.");
        }
    }

    /**
     * add the language switcher to a menu
     *
     * ## OPTIONS
     *
     * <menu_id>
     * : the menu id, name or slug
     *
     * [--hide_if_no_translation=<boolean>]
     * : hide the switcher, if no translation (for post/page) is available
     * : Accepted values: 0, 1. Default: 0
     *
     * [--hide_current=<boolean>]
     * : hide the current language
     * : Accepted values: 0, 1. Default: 0
     *
     * [--force_home=<boolean>]
     * : link to home (instead of translated post/page)
     * : Accepted values: 0, 1. Default: 0
     *
     * [--show_flags=<boolean>]
     * : show language flags
     * : Accepted values: 0, 1. Default: 0
     *
     * [--show_names=<boolean>]
     * : show language names
     * : Accepted values: 0, 1. Default: 1
     *
     * ## EXAMPLES
     *
     *   wp polylang langswitcher 1
     *
     * @synopsis <menu_id> [--hide_if_no_translation=<boolean>] [--hide_current=<boolean>] [--force_home=<boolean>] [--show_flags=<boolean>] [--show_names=<boolean>]
     */
    function langswitcher ($args, $assocArgs) {
        $menu_id = $args[0];
        // check if the menu exists
        $nav_menu = wp_get_nav_menu_object( $args[0] );

        if( false === $nav_menu) {
            WP_CLI::error( "Invalid menu " . $menu_id);
        }

        // handle options
        $options = wp_parse_args( $assocArgs, array(
                   'hide_if_no_translation' => 0,
                   'hide_current' => 0,
                   'force_home' => 0,
                   'show_flags' => 0,
                   'show_names' => 1
        ));

        // at least one needs to be activated - otherwise use default
        if( !$options['show_flags'] && !$options['show_names']) {
            $options['show_names'] = 1;
        }

        // add the language switcher as new menu item
        $menu_item_db_id = wp_update_nav_menu_item($nav_menu->term_id, 0, array(
                'menu-item-title' => __('Language switcher', 'polylang'),
                'menu-item-url' => '#pll_switcher',
                'menu-item-status' => 'publish'
        ));

        // check for success
        if( is_wp_error($menu_item_db_id)) {
            WP_CLI::error( "could not add language switcher: " . $menu_item_db_id->get_error_message());
        }

        // update the meta data, so that the Language switcher really behaves the desired way
        if( !update_post_meta($menu_item_db_id, '_pll_menu_item', $options)) {
            // cleanup
            wp_delete_post( $menu_item_db_id);
            WP_CLI::error( "error setting options for Language Switcher menu item");
        }

        // success
        WP_CLI::success("Added language switcher with db_id: " . $menu_item_db_id);
    }
}

WP_CLI::add_command('polylang', 'Polylang_Command');
WP_CLI::add_command('pll', 'Polylang_Command'); //alias for the users expecting to use the API shortname.
