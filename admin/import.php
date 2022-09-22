<?php

/**
 * Dokuwiki Advanced Import/Export Plugin
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Giuseppe Di Terlizzi <giuseppe.diterlizzi@gmail.com>
 */

class admin_plugin_advanced_import extends DokuWiki_Admin_Plugin
{

    /**
     * @return int sort number in admin menu
     */
    public function getMenuSort()
    {
        return 1;
    }

    public function forAdminOnly()
    {
        return false;
    }

    public function getMenuIcon()
    {
        return dirname(__FILE__) . '/../svg/export.svg';
    }

    public function getMenuText($language)
    {
        return $this->getLang('menu_import');
    }

    public function handle()
    {
        global $INPUT;

        if (!$_REQUEST['cmd']) {
            return;
        }

        if (!checkSecurityToken()) {
            return;
        }

        $cmd = $INPUT->extract('cmd')->str('cmd');

        if ($cmd) {
            $cmd = "cmd_$cmd";
            $this->$cmd();
        }

    }

    public function html()
    {

        global $INPUT;
        global $lang;
        global $conf;
        global $ID;

        $lang['toc'] = $this->getLang('menu_import');

        echo '<div id="plugin_advanced_export">';
        echo $this->locale_xhtml('import');
        echo '<p>&nbsp;</p>';

        echo '<form action="" method="post" enctype="multipart/form-data" class="form-inline">';

        $this->steps_dispatcher();

        formSecurityToken();

        echo '<input type="hidden" name="do" value="admin" />';
        echo '<input type="hidden" name="page" value="advanced_import" />';

        echo '</form>';
        echo '</div>';

    }

    private function cmd_import()
    {

        global $INPUT;
        global $conf;

        $extract_dir     = io_mktmpdir();
        $archive_file    = $INPUT->str('file');
        $overwrite_pages = ($INPUT->str('overwrite-existing-pages') == 'on' ? true : false);
        $files           = array_keys($INPUT->arr('files'));
        $ns              = $INPUT->str('ns');
        $imported_pages  = array();

        if ($ns == '(root)') {
            $ns = '';
        }

        if (!file_exists($archive_file)) {
            msg($this->getLang('imp_zip_not_found'), -1);
            return 0;
        }

        $Zip = new \splitbrain\PHPArchive\Zip;
        $Zip->open($archive_file);

        if (!$Zip->extract($extract_dir)) {
            msg($this->getLang('imp_zip_extract_error'), -1);
            return 0;
        }

        if (!count($files)) {
            msg($this->getLang('imp_no_page_selected'), -1);
            return 0;
        }

        foreach ($files as $file) {

            $wiki_page = str_replace('/', ':', preg_replace('/\.txt$/', '', $file));
            $wiki_page = cleanID("$ns:$wiki_page");

            $sum = $this->getLang('imp_page_summary');

            if (page_exists($wiki_page) && !$overwrite_pages) {
                msg(sprintf($this->getLang('imp_page_already_exists'), $wiki_page), 2);
                continue;
            }

            if (!page_exists($wiki_page)) {
                $sum = $lang['created'] . " - $sum";
            }

            $wiki_text = io_readFile("$extract_dir/$file");

            checklock($wiki_page);
            lock($wiki_page);
            saveWikiText($wiki_page, $wiki_text, $sum);
            unlock($wiki_page);
            idx_addPage($wiki_page);

            $imported_pages[] = $wiki_page;

        }

        # Delete import archive
        unlink($archive_file);

        # Delete the extract directory
        io_rmdir($extract_dir, true);

        if (count($imported_pages)) {
            msg($this->getLang('imp_pages_import_success'));
        }

    }

    private function steps_dispatcher()
    {

        global $INPUT;

        $step = $INPUT->extract('import')->str('import');

        if (!$step) {
            return $this->step_upload_form();
        }

        return call_user_func(array($this, "step_$step"));

    }

    private function step_upload_form()
    {

        global $lang;

        echo '<input type="file" name="file" required="required" accept=".zip,application/zip,application/x-zip,application/x-zip-compressed" />';
        echo '<p>&nbsp;</p>';

        echo '<p class="pull-right">';
        echo sprintf('<button type="submit" name="import[upload_backup]" class="btn btn-primary primary">%s &rarr;</button> ', $this->getLang('imp_upload_backup'));
        echo '</p>';

    }

    private function step_upload_backup()
    {

        global $conf;
        global $lang;

        $tmp_name  = $_FILES['file']['tmp_name'];
        $file_name = $_FILES['file']['name'];
        $file_path = $conf['tmpdir'] . "/$file_name";

        move_uploaded_file($tmp_name, $file_path);

        search($namespaces, $conf['datadir'], 'search_namespaces', $options, '');

        echo sprintf('<h3>1. %s</h3>', $this->getLang('imp_select_namespace'));

        echo '<p><select name="ns" class="form-control" required="required">';
        echo sprintf('<option>%s</option>', $this->getLang('imp_select_namespace'));
        echo '<option value="(root)" selected="selected">(root)</option>';

        foreach ($namespaces as $namespace) {
            echo sprintf('<option value="%s">%s</option>', $namespace['id'], $namespace['id']);
        }

        echo '</select></p>';
        echo '<p>&nbsp;</p>';

        echo sprintf('<h3>2. %s</h3>', $this->getLang('imp_select_pages'));

        $Zip = new \splitbrain\PHPArchive\Zip;
        $Zip->open($file_path);

        echo '<table class="table inline pages" width="100%">';
        echo '<thead>
      <tr>
        <th width="10"><input type="checkbox" class="import-all-pages" title="' . $this->getLang('select_all_pages') . '" /></th>
        <th>File</th>
        <th>Size</th>
      </tr>
    </thead>';
        echo '<tbody>';

        foreach ($Zip->contents() as $fileinfo) {
            echo '<tr>';
            echo sprintf('
        <td><input type="checkbox" name="files[%s]" class="import-file" /></td>
        <td>%s</td>
        <td>%s</td>',
                $fileinfo->getPath(),
                $fileinfo->getPath(),
                filesize_h($fileinfo->getSize())
            );
            echo '<tr>';
        }

        echo '</tbody></table>';
        echo '<p>&nbsp;</p>';

        echo '<h3>3. Options</h3>';
        echo '<table class="table inline">';
        echo sprintf('<tbody>
      <tr>
        <td width="10"><input type="checkbox" name="overwrite-existing-pages" /></td>
        <td>%s</td>
      </tr>
    </tbody>', $this->getLang('imp_overwrite_pages'));
        echo '</table>';

        echo '<p>&nbsp;</p>';

        echo '<p class="pull-right">';
        echo sprintf('<button type="submit" name="import[upload_form]" class="btn btn-default">&larr; %s</button> ', $lang['btn_back']);
        echo sprintf('<button type="submit" name="cmd[import]" class="btn btn-primary primary">%s &rarr;</button>', $this->getLang('btn_import'));
        echo '</p>';

        echo sprintf('<input type="hidden" name="file" value="%s">', $file_path);

    }

}
