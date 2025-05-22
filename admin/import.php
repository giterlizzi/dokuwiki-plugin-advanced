<?php

use dokuwiki\Extension\AdminPlugin;

/**
 * Dokuwiki Advanced Import/Export Plugin
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Giuseppe Di Terlizzi <giuseppe.diterlizzi@gmail.com>
 */

class admin_plugin_advanced_import extends AdminPlugin
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

        $extract_dir     = io_mktmpdir();
        $archive_file    = $INPUT->str('file');
        $overwrite_pages = ($INPUT->str('overwrite-existing-pages') == 'on' ? true : false);
        $pages           = array_keys($INPUT->arr('pages'));
        $media           = array_keys($INPUT->arr('media'));
        $ns              = $INPUT->str('ns');

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

        if (!count($pages) && !count($media)) {
            msg($this->getLang('imp_no_page_selected'), -1);
            return 0;
        }

        $errors = [];

        $this->importPages($ns, $pages, $extract_dir, $overwrite_pages, $errors);
        $this->importMedia($ns, $media, $extract_dir, $overwrite_pages, $errors);

        # Delete import archive
        unlink($archive_file);

        # Delete the extract directory
        io_rmdir($extract_dir, true);

        if (!count($errors)) {
            msg($this->getLang('imp_pages_import_success'));
            return;
        }

        foreach ($errors as $error) {
            msg($error, -1);
        }
    }

    /**
     * Import pages from uploaded file
     *
     * @param string $ns
     * @param array $pages
     * @param string $extract_dir
     * @param bool $overwrite_pages
     * @param array $errors
     */
    protected function importPages($ns, $pages, $extract_dir, $overwrite_pages, &$errors)
    {
        global $lang;

        foreach ($pages as $srcFile) {
            // strip pages directory from the beginning of path
            $targetFile = preg_replace('$^'. \helper_plugin_advanced::PAGES_DIR . '$', '', $srcFile);

            $wiki_page = str_replace('/', ':', preg_replace('/\.txt$/', '', $targetFile));
            $wiki_page = cleanID("$ns:$wiki_page");

            $sum = $this->getLang('imp_page_summary');

            if (page_exists($wiki_page) && !$overwrite_pages) {
                $errors[] = sprintf($this->getLang('imp_page_already_exists'), $wiki_page);
                continue;
            }

            if (!page_exists($wiki_page)) {
                $sum = $lang['created'] . " - $sum";
            }

            $wiki_text = io_readFile("$extract_dir/$srcFile");

            checklock($wiki_page);
            lock($wiki_page);
            saveWikiText($wiki_page, $wiki_text, $sum);
            unlock($wiki_page);
            idx_addPage($wiki_page);
        }
    }

    /**
     * Import media from uploaded file
     *
     * @param string $ns
     * @param array $media
     * @param string $extract_dir
     * @param bool $overwrite
     * @param array $errors
     */
    protected function importMedia($ns, $media, $extract_dir, $overwrite, &$errors)
    {
        foreach ($media as $srcFile) {
            // strip media directory from the beginning of path
            $targetFile = preg_replace('$^'. \helper_plugin_advanced::MEDIA_DIR . '$', '', $srcFile);

            $mediaId = str_replace('/', ':', $targetFile);
            $mediaId = cleanID("$ns:$mediaId");

            $res = media_save(['name' => "$extract_dir/$srcFile"], $mediaId, $overwrite, AUTH_ADMIN, 'copy');

            if (is_array($res)) {
                $errors[] = $res[0] . " ($mediaId)";
            }
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

    /**
     * Displays upload form for the Zip archive
     *
     * @return void
     */
    private function step_upload_form()
    {
        echo '<input type="file" name="file" required="required" accept=".zip,application/zip,application/x-zip,application/x-zip-compressed" />';
        echo '<p>&nbsp;</p>';

        echo '<p class="pull-right">';
        echo sprintf('<button type="submit" name="import[upload_backup]" class="btn btn-primary primary">%s &rarr;</button> ', $this->getLang('imp_upload_backup'));
        echo '</p>';
    }

    /**
     * Displays import selection and options
     *
     * @return void
     * @throws \splitbrain\PHPArchive\ArchiveIOException
     */
    private function step_upload_backup()
    {
        global $conf;
        global $lang;

        $tmp_name  = $_FILES['file']['tmp_name'];
        $file_name = $_FILES['file']['name'];
        $file_path = $conf['tmpdir'] . "/$file_name";

        move_uploaded_file($tmp_name, $file_path);

        search($namespaces, $conf['datadir'], 'search_namespaces', []);

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
        <th>Type</th>
        <th>Import</th>
        <th>Size</th>
      </tr>
    </thead>';
        echo '<tbody>';

        $type = 'pages';

        foreach ($Zip->contents() as $fileinfo) {
            // change type for media files
            if (str_starts_with($fileinfo->getPath(), \helper_plugin_advanced::MEDIA_DIR)) {
                $type = 'media';
            }

            echo '<tr>';
            echo sprintf(
                '<td><input type="checkbox" name="%s[%s]" class="import-file" /></td>
                <td>%s</td>
                <td>%s</td>
                <td>%s</td>',
                $type, // store the file to be imported in appropriate variable
                $fileinfo->getPath(), // full import path
                $type,
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
            </tbody>',
            $this->getLang('imp_overwrite_pages'));
        echo '</table>';

        echo '<p>&nbsp;</p>';

        echo '<p class="pull-right">';
        echo sprintf('<button type="submit" name="import[upload_form]" class="btn btn-default">&larr; %s</button> ', $lang['btn_back']);
        echo sprintf('<button type="submit" name="cmd[import]" class="btn btn-primary primary">%s &rarr;</button>', $this->getLang('btn_import'));
        echo '</p>';

        echo sprintf('<input type="hidden" name="file" value="%s">', $file_path);
    }
}
