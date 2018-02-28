<?php
/**
 * Dokuwiki Advanced Config Plugin
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Giuseppe Di Terlizzi <giuseppe.diterlizzi@gmail.com>
 */

class admin_plugin_advanced_maintenance extends DokuWiki_Admin_Plugin {

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

  public function getMenuText($language) {
    return $this->getLang('menu_maintenance');
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

  public function html() {

    global $INPUT;
    global $lang;
    global $conf;
    global $ID;

    $tab = $INPUT->extract('tab')->str('tab');

    if (! $tab) $tab = 'directory';

    echo '<div id="plugin_advanced_maintenance '. (($tab) ? "tab-$tab" : '') .'">';
    echo '<p>' . $this->locale_xhtml('maintenance') . '</p>';

    echo '<ul class="tabs nav nav-tabs" role="tablist">';
    echo '<li class="'. (($tab == 'directory') ? 'active' : '') .'">';
    echo '<a href="'. wl($ID, array('do' => 'admin', 'page' => 'advanced_maintenance', 'tab' => 'directory')) .'">Directory</a>';
    echo '</li><li class="'. (($tab == 'other') ? 'active' : '') .'">';
    echo '<a href="'. wl($ID, array('do' => 'admin', 'page' => 'advanced_maintenance', 'tab' => 'other')) .'">Other</a>';
    echo '</li></ul>';

    echo '<div class="tab-content">';
    echo '<div class="tab-pane fade in active">';
    echo '<p>' . $this->locale_xhtml("maintenance_$tab") . '</p>';
    echo '<p>&nbsp;</p>';
    echo $this->getMaintenanceTab($tab);
    echo '</div>';
    echo '</div>';
    echo '</div>';

  }


  protected function getMaintenanceTab($tab) {

    switch($tab) {

      case 'directory':
        return $this->tabDirectory();

      case 'other':
        return $this->tabOther();

    }

  }


  protected function tabDirectory() {

    global $conf;

    $directories = array('tmpdir', 'cachedir', 'datadir', 'olddir', 'mediadir', 'mediaolddir', 'metadir', 'mediametadir', 'indexdir');
    sort($directories);

    echo '<table class="table table-compress inline">';
    foreach ($directories as $dir) {
      echo '<tr><th>' . $dir . '</th>' .
           '<td><code>' . $conf[$dir] . '</code></td>' .
           '<td>' . filesize_h($this->getDirectorySize($conf[$dir])) . '</td></tr>';
    }
    echo '</table>';

  }


  protected function tabOther() {

    $report_e_all = false;

    if (file_exists(DOKU_CONF . 'report_e_all')) {
      $report_e_all = true;
    }

    echo '<table class="table table-compress inline">';
    echo '<tr><td><strong>Enable <code>report_e_all</code> file</strong><p>ASDADADASD</p></td>'.
        '<td><input type="checkbox" name="report_e_all" '. (($report_e_all) ? ' checked="checked"' : '') .' />';
    echo '</table>';

  }


  private function getDirectorySize($dir) {

    $size = 0;

    foreach (glob(rtrim($dir, '/').'/*', GLOB_NOSORT) as $each) {
      $size += is_file($each) ? filesize($each) : $this->getDirectorySize($each);
    }

    return $size;

  }

}
