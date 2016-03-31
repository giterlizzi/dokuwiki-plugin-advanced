<?php
/**
 * Dokuwiki Advanced Config Plugin
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Giuseppe Di Terlizzi <giuseppe.diterlizzi@gmail.com>
 */

class admin_plugin_advanced extends DokuWiki_Admin_Plugin {

  private $allowedFiles = array();
  private $fileInfo     = array();

  /**
   * @return int sort number in admin menu
   */
  public function getMenuSort() {
      return 1;
  }

  /**
   * @return bool true if only access for superuser, false is for superusers and moderators
   */
  public function forAdminOnly() {
      return true;
  }

  /**
    * handle user request
    */
  public function handle() {

    global $INPUT;

    if (! $_REQUEST['cmd'])    return;
    if (!checkSecurityToken()) return;

    $cmd = $INPUT->extract('cmd')->str('cmd');

    if ($cmd) {
      $cmd = "cmd_$cmd";
      $this->$cmd();
    }

  }


  private function getFileInfo() {

    global $INPUT;
    global $conf;
    global $config_cascade;

    $file = $INPUT->str('file');
    $type = $INPUT->str('type');

    $file_local   = null;
    $file_default = null;

    if (! $file || ! $type) return array();

    switch($type) {

      case 'config':
        $configs      = $config_cascade[$file];
        $file_default = @$configs['default'][0];
        $file_local   = @$configs['local'][0];
        break;

      case 'userstyle':
      case 'userscript':
        $configs      = $config_cascade[$type][$file];
        $file_local   = @$configs[0];
        break;

      case 'hook':
        $file_local   = DOKU_CONF . "$file.html";
        $file_default = tpl_incdir() . "$file.html";
        break;

    }

    if ($type == 'other') {
      switch ($file) {
        case 'htaccess':
          $file_default = DOKU_INC . '.htaccess.dist';
          $file_local   = DOKU_INC . '.htaccess';
          break;
        case 'userscript':
          $configs      = $config_cascade['userscript'];
          $file_local   = @$configs['default'][0];
      }
    }

    $file_info = array(
      'type'            => $type,
      'file'            => $file,
      'default'         => $file_default,
      'local'           => $file_local,
      'localName'       => basename($file_local),
      'defaultName'     => basename($file_default),
      'localLastModify' => (file_exists($file_local) ? strftime($conf['dformat'], filemtime($file_local)) : ''),
    );

    return $file_info;

  }


  public function cmd_save() {

    global $INPUT;

    $file_info = $this->getFileInfo();

    if (io_saveFile($file_info['local'], $INPUT->post->str('content'))) {
      msg(sprintf('File %s saved successfull!', $file_info['localName']), 1);
    } else {
      msg(sprintf('Unable to save %s file!', $file_info['localName']), -1);
    }

  }


  public function cmd_wordblock_update() {

    $file_info = $this->getFileInfo();

    $http = new DokuHTTPClient();
    $blacklist = $http->get('https://meta.wikimedia.org/wiki/Spam_blacklist?action=raw');
    $blacklist = trim(preg_replace('/#(.*)$/m', '', $blacklist));  # Remove all comments from file
    $blacklist = trim(preg_replace('/[\n]+/m', "\n", $blacklist)); # Remove multiple new line

    if (io_saveFile($file_info['local'], $blacklist)) {
      msg('Blacklist update successfull!', 1);
    } else {
      msg('Blacklist update failed!', -1);
    }

  }


  private function help() {

    echo '<div class="help">';
    echo $this->locale_xhtml((($this->fileInfo['type'] == 'hook') ? 'hooks' : $this->fileInfo['file']));
    echo '</div>';
    echo '<p>&nbsp;</p>';

    return true;

  }


  private function getDefault() {

    $file_info = $this->fileInfo;

    if (! $file_info['default'] || ! file_exists($file_info['default'])) return;

    $file_name = $file_info['defaultName'];
    $file_path = $file_info['default'];

    echo "<h3>Default $file_name <small><a href=\"javascript:void(0)\" onclick=\"jQuery('.default-config').toggle(); jQuery(this).text(jQuery(this).text() == '[-]' ? '[+]' : '[-]')\">[+]</a></small></h3>";
    echo '<div class="default-config" style="display:none">';
    echo '<textarea class="edit" rows="15" cols="" disabled="disabled">';
    echo io_readFile($file_path);
    echo '</textarea>';
    echo '<p class="docInfo small pull-right">'. $file_path .'</p>';
    echo '</div>';

    return true;

  }


  private function editForm() {

    global $lang;

    $file_info    = $this->fileInfo;
    $file_path    = $file_info['local'];
    $file_data    = (file_exists($file_path) ? io_readFile($file_path) : '');
    $file_lastmod = $file_info['localLastModify'];
    $file_name    = $file_info['localName'];

    echo "<h3>Edit $file_name file</h3>";

    echo '<form action="" method="post">';
    echo '<textarea name="content" class="edit" rows="15" cols="">';
    echo $file_data;
    echo '</textarea>';

    echo '<p class="docInfo small pull-right">';
    echo $file_path;
    echo (file_exists($file_path) ? ' · '. $lang['lastmod'] . ' ' . $file_lastmod : '');
    echo '</p>';

    echo '<p>&nbsp;</p>';

    formSecurityToken();

    echo '<input type="hidden" name="do" value="admin" />';
    echo '<input type="hidden" name="page" value="advanced" />';

    echo '<button type="submit" name="cmd[save]" class="btn btn-primary primary">'. $lang['btn_save'] .'</button> ';

    if ($file_info['file'] == 'wordblock') {
      echo '<button type="submit" name="cmd[wordblock_update]" class="btn btn-default">Update Blacklist from MediaWiki</button> ';
    }

    echo '<button type="submit" class="btn btn-default">'. $lang['btn_cancel'] .'</button>';
    echo '</form>';

    return true;

  }


  /**
    * output appropriate html
    */
  public function html() {

    global $INPUT;
    global $lang;
    global $conf;
    global $ID;

    $lang['toc'] = $this->getLang('menu');

    $this->fileInfo = $file_info = $this->getFileInfo();

    echo sprintf('<div id="plugin_%s">', $this->getPluginName());
    echo $this->locale_xhtml('intro');
    echo '<p>&nbsp;</p>';

    if (isset($file_info) && in_array($file_info['file'], $this->allowedFiles)) {

      $this->help();
      $this->getDefault();
      $this->editForm();

    }

    echo '</div>';

  }


  public function getTOC() {

    global $INPUT;
    global $conf;

    $current_section = $INPUT->str('type');

    // TOC Sections
    $toc_sections = array(
      'config'     => 'Config',
      'userstyle'  => 'Style',
      'hook'       => 'Template Hooks',
      'other'      => 'Others',
    );

    // DokuWiki config
    $toc_configs = array(
      'acronyms'  => 'Abbreviations and Acronyms',
      'entities'  => 'Entities',
      'interwiki' => 'InterWiki Links',
      'mime'      => 'MIME',
      'smileys'   => 'Smileys',
      'scheme'    => 'URL Schemes',
      'wordblock' => 'Blacklist',
    );

    // User Style
    $toc_styles = array(
      'style' => 'Screen',
      'print' => 'Print',
      'feed'  => 'Feed',
      'all'   => 'All',
    );

    // Template Hooks
    $toc_hooks = array(
      'meta'          => 'Meta',
      'sidebarheader' => 'Sidebar (Header)',
      'sidebarfooter' => 'Sidebar (Footer)',
      'pageheader'    => 'Page (Header)',
      'pagefooter'    => 'Page (Footer)',
      'header'        => 'Header',
      'footer'        => 'Footer',
    );

    // Other config
    $toc_others = array(
      'userscript' => 'User JavaScript',
      'htaccess'   => '.htaccess',
    );

    // Specific Template Hooks
    switch ($conf['template']) {

      case 'bootstrap3':

        $toc_hooks['topheader']          = 'Top Header';
        $toc_hooks['rightsidebarheader'] = 'Right Sidebar (Header)';
        $toc_hooks['rightsidebarfooter'] = 'Right Sidebar (Footer)';
        $toc_hooks['social']             = 'Social';

        break;

    }


    $toc_items = array(
      'config'     => $toc_configs,
      'userstyle'  => $toc_styles,
      'userscript' => $toc_js,
      'hook'       => $toc_hooks,
      'other'      => $toc_others,
    );

    if ($current_section) {
      $this->allowedFiles = array_keys($toc_items[$current_section]);
    }

    foreach ($toc_sections as $section => $section_title) {

      $toc[] = array(
        'link'  => wl($ID, array(
          'do'   => 'admin',
          'page' => $this->getPluginName(),
          'type' => $section)
        ),
        'title' => $section_title,
        'level' => 1,
        'type'  => 'ul',
      );

      foreach ($toc_items[$section] as $file => $title) {

        if ($current_section == $section) {

          $toc[] = array(
            'link'  => wl($ID, array(
              'do' => 'admin',
              'page'   => $this->getPluginName(),
              'type'   => $section,
              'file'   => $file,
              'sectok' => getSecurityToken())
            ),
            'title' => $title,
            'level' => 2,
            'type'  => 'ul',
          );

        }

      }

    }

    return $toc;

  }

}
