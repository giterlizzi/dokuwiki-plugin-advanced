/*!
 * DokuWiki Advanced Plugin
 *
 * Home      http://dokuwiki.org/plugin:advanced
 * Author    Giuseppe Di Terlizzi <giuseppe.diterlizzi@gmail.com>
 * License   GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * Copyright (C) 2016-2020, Giuseppe Di Terlizzi
 */

jQuery(document).ready(function () {

    var $adv = jQuery('#plugin_advanced_config');

    dw_page.makeToggle('.config_default h3', '.config_default > div', -1);
    dw_page.makeToggle('.config_protected h3', '.config_protected > div', -1);

    $adv.find('.purge-cache').on('click', function (e) {
        var $btn = jQuery(this);
        jQuery.get(DOKU_BASE + 'lib/exe/' + $btn.data('purgeType') + '.php?purge=true').done(function () {
            alert($btn.data('purgeMsg'));
        });
    });

    var $advanced_forms = jQuery('#plugin_advanced_export, #plugin_advanced_import');

    $advanced_forms.find('.export-all-pages, .import-all-pages').on('click', function () {

        var $pages = $advanced_forms.find('table.pages tbody input[type=checkbox]');

        if (jQuery(this).prop('checked')) {
            $pages.prop('checked', true);
        } else {
            $pages.prop('checked', false);
        }

    });

});
