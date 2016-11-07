<?php
/**
 * Dokuwiki Advanced Import/Export Plugin
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Giuseppe Di Terlizzi <giuseppe.diterlizzi@gmail.com>
 */

class admin_plugin_advanced_export extends DokuWiki_Admin_Plugin {

  /**
   * @return int sort number in admin menu
   */
  public function getMenuSort() {
      return 1;
  }


  public function forAdminOnly() {
      return true;
  }


  public function getMenuText($language) {
    return $this->getLang('menu_export');
  }


  public function handle() {

//     if ($_REQUEST['step'] && !checkSecurityToken()) {
//       unset($_REQUEST['step']);
//     }

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

    $lang['toc'] = $this->getLang('menu_export');

    echo '<div id="plugin_advanced_export">';
    echo $this->locale_xhtml('export');

    echo '<form action="" method="post" class="form-inline">';

    $this->steps_dispatcher();

    formSecurityToken();

    echo '<input type="hidden" name="do" value="admin" />';
    echo '<input type="hidden" name="page" value="advanced_export" />';

    echo '</form>';
    echo '</div>';

  }


  private function steps_dispatcher() {

    global $INPUT;

    $step = $INPUT->extract('export')->str('export');

    if (! $step) {
      return $this->step_select_ns();
    }

    return call_user_func(array($this, "step_$step"));

  }


  private function step_select_ns() {

    global $conf;
    global $lang;

    $namespaces = array();
    $options    = array();

    search($namespaces, $conf['datadir'], 'search_namespaces', $options, '');

    echo sprintf('<h3>%s</h3>', $this->getLang('export_select_namespace'));

    echo '<p><select name="ns" class="form-control">';
    echo '<option value="">Select the namespace</option>';
    echo '<option value="(root)">(root)</option>';

    foreach ($namespaces as $namespace) {
      echo sprintf('<option value="%s">%s</option>', $namespace['id'], $namespace['id']);
    }

    echo '</select></p>';
    echo '<p>&nbsp;</p>';

    echo '<input type="hidden" name="step" value="select-ns" />';

    echo '<p class="pull-right">';
    echo sprintf('<label><input type="checkbox" name="include-sub-ns" /> %s</label> ', 'Include sub-namespaces');
    echo sprintf('<button type="submit" name="cmd[export]" class="btn btn-default">%s &rarr;</button> ', 'Export all Pages in Namespace');
    echo sprintf('<button type="submit" name="export[select_pages]" class="btn btn-primary primary">%s &rarr;</button> ', $this->getLang('export_select_pages'));
    echo '</p>';

  }


  private function getPagesFromNamespace($ns, $follow_ns = 0) {

    global $conf;

    $depth = ($follow_ns ? 0 : 2);

    if ($ns == '(root)') {
      $ns    = '';
      $depth = ($follow_ns ? 2 : 1);
    }

    $pages     = array();
    $namespace = str_replace(':', '/', $ns);
    $options   = array('depth' => $depth);

    search($pages, $conf['datadir'], 'search_allpages', $options, $namespace);

    return $pages;

  }


  private function step_select_pages() {

    global $INPUT;
    global $conf;
    global $lang;

    $pages     = array();
    $namespace = str_replace(':', '/', $INPUT->str('ns'));

    if (! $namespace) {
      msg('No namespace selected', -1);
      $this->step_select_ns();
      return 0;
    }

    $pages = $this->getPagesFromNamespace($INPUT->str('ns'), ($INPUT->str('include-sub-ns') ? 1 : 0));

    echo sprintf('<h3>%s</h3>', $this->getLang('export_select_pages'));
    echo sprintf('<input type="hidden" value="%s" name="ns" />', $INPUT->str('ns'));

    echo '<table class="table inline" width="100%">';
    echo '<thead>
      <tr>
        <th><input type="checkbox" class="export-all-pages" /></th>
        <th>Page</th>
        <th>Created</th>
        <th>Modified</th>
        <th>Size</th>
      </tr>
    </thead>';
    echo '<tbody>';

    foreach ($pages as $page) {

      $page_id       = $page['id'];
      $page_title    = p_get_first_heading($page_id);
      $page_size     = round(($page['size'] / 1024), 1);
      $create_user   = editorinfo(p_get_metadata($page_id, 'user'));
      $modified_user = editorinfo(p_get_metadata($page_id, 'last_change user'));
      $create_date   = dformat(p_get_metadata($page_id, 'date created'));
      $modified_date = dformat(p_get_metadata($page_id, 'date modified'));

      echo sprintf('
        <tr>
          <td><input type="checkbox" name="pages[%s]" class="export-page" /></td>
          <td>%s<br/><small>%s</small></td>
          <td>%s<br/>%s</td>
          <td>%s<br/>%s</td>
          <td>%s Kb</td>
        </tr>',
        $page_id,
        $page_id, $page_title,
        $create_user, $create_date,
        $modified_user, $modified_date,
        $page_size);

    }

    echo '</tbody>';
    echo '</table>';

    echo '<p>&nbsp;</p>';
    echo '<input type="hidden" name="step" value="select-pages" />';

    echo '<p class="pull-right">';
    echo sprintf('<button type="submit" name="export[select_ns]" class="btn btn-default">&larr; %s</button> ', $lang['btn_back']);
    echo sprintf('<button type="submit" name="cmd[export]" class="btn btn-primary primary">%s &rarr;</button>', 'Export');
    echo '</p>';

  }


  private function cmd_export() {

    global $INPUT;
    global $conf;

    $pages = array();

    switch ($INPUT->str('step')) {

      case 'select-ns':

        foreach ($this->getPagesFromNamespace($INPUT->str('ns'), ($INPUT->str('include-sub-ns') ? 1 : 0)) as $page) {
          $pages[] = $page['id'];
        }

        break;

      case 'select-pages':
        $pages = array_keys($INPUT->arr('pages'));
        break;

    }

    if (! count($pages)) {
      msg('No page selected for export!', -1);
      return 0;
    }

    $namespace = str_replace(':', '-', str_replace('(root)', 'ROOT', $INPUT->str('ns')));
    $timestamp = date('YmdHis');

    $Zip = new \splitbrain\PHPArchive\Zip;
    $Zip->create();

    foreach ($pages as $page) {

      $file     = wikiFN($page);
      $fileinfo = str_replace($conf['datadir'], '', $file);

      $Zip->addFile($file, $fileinfo);

    }

    header("Content-Type: application/zip");
    header("Content-Transfer-Encoding: Binary");
    header("Content-Disposition: attachment; filename=DokuWiki-export-$namespace-$timestamp.zip");

    echo $Zip->getArchive();
    die();

  }


}
