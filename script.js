/*!
 * DokuWiki Advanced Plugin
 *
 * Home      http://dokuwiki.org/plugin:advanced
 * Author    Giuseppe Di Terlizzi <giuseppe.diterlizzi@gmail.com>
 * License   GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * Copyright (C) 2016, Giuseppe Di Terlizzi
 */

jQuery(document).ready(function() {

  var $adv = jQuery('#plugin_advanced_config');

  $adv.find('.expand-reduce').on('click', function(e) {
    $adv.find('.default-config').toggle();
    jQuery(this).text((jQuery(this).text() == '-') ? '+' : '-');
  });

  $adv.find('.purge-cache').on('click', function(e) {
    var $btn = jQuery(this);
    jQuery.get(DOKU_BASE + 'lib/exe/'+ $btn.data('purgeType') +'.php?purge=true').done(function(){
      alert($btn.data('purgeMsg'));
    });
  });

});
