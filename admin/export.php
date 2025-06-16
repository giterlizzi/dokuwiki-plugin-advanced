<?php

use dokuwiki\Extension\AdminPlugin;
use dokuwiki\File\MediaFile;

/**
 * Dokuwiki Advanced Import/Export Plugin
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Giuseppe Di Terlizzi <giuseppe.diterlizzi@gmail.com>
 */

class admin_plugin_advanced_export extends AdminPlugin
{

    /**
     * @return int sort number in admin menu
     */
    public function getMenuSort()
    {
        return 2;
    }

    public function getMenuIcon()
    {
        return dirname(__FILE__) . '/../svg/export.svg';
    }

    public function forAdminOnly()
    {
        return false;
    }

    public function getMenuText($language)
    {
        return $this->getLang('menu_export');
    }

    public function handle()
    {
        global $INPUT;

        if (!$INPUT->has('cmd')) {
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
        global $lang;

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

    private function steps_dispatcher()
    {
        global $INPUT;

        $step = $INPUT->extract('export')->str('export');

        if (!$step) {
            return $this->step_select_ns();
        }

        return call_user_func(array($this, "step_$step"));
    }

    private function step_select_ns()
    {
        global $conf;

        $namespaces = array();
        $options    = array();

        search($namespaces, $conf['datadir'], 'search_namespaces', $options, '');

        echo sprintf('<h3>%s</h3>', $this->getLang('exp_select_namespace'));

        echo '<input list="plugin__advanced-ns-list" name="ns" id="plugin__advanced-ns-input" class="form-control" />';

        echo '<datalist id="plugin__advanced-ns-list">';

        echo '<option value="(root)"></option>';

        foreach ($namespaces as $namespace) {
            echo sprintf('<option value="%s"></option>', $namespace['id']);
        }

        echo '</datalist>';
        echo '<p>&nbsp;</p>';

        echo '<input type="hidden" name="step" value="select-ns" />';

        echo '<p class="pull-right">';
        echo sprintf('<label><input type="checkbox" name="include-sub-ns" checked /> %s</label> ', $this->getLang('exp_include_sub_namespaces'));
        echo sprintf('<label><input type="checkbox" name="include-media" checked /> %s</label> ', $this->getLang('exp_include_media'));
        echo sprintf('<button type="submit" name="cmd[export]" class="btn btn-default">%s &rarr;</button> ', $this->getLang('exp_export_all_pages_in_namespace'));
        echo sprintf('<button type="submit" name="export[select_pages]" class="btn btn-primary primary">%s &rarr;</button> ', $this->getLang('exp_select_pages'));
        echo '</p>';
    }

    private function getNamespaceContent($ns, $follow_ns = false, $include_media = false)
    {
        global $conf;

        if ($ns == '(root)') {
            $ns = '';
        }

        $ns = str_replace(':', '/', $ns);

        $pages = [];
        search($pages, $conf['datadir'], 'search_allpages', ['depth' => self::getSearchDepth($ns, $follow_ns)], $ns);

        $media = [];
        if ($include_media) {
            search($media, $conf['mediadir'], 'search_media', ['depth' => self::getSearchDepth($ns, $follow_ns, 'media')], $ns);
        }

        return [$pages, $media];
    }

    /**
     * Depth parameter for search functions,
     * takes recursion and type (pages or media) into account
     *
     * @param string $ns
     * @param bool $followNs
     * @param string $type
     *
     * @return int
     */
    public static function getSearchDepth($ns, $followNs, $type = 'pages')
    {
        // recursive search with no depth limit
        if ($followNs) return 0;

        // search methods for pages and media have slightly different concepts of depth
        // to prevent recursion in search_media() we need to pass 1 as depth
        if ($type === 'media') {
            return 1;
        }


        if ($ns === '') {
            $nsDepth = 0;
        } else {
            $nsDepth = substr_count($ns, '/') + 1;
        }

        // + 1 level deeper for pages
        // start has nsDepth of 0 and page depth of 1
        // ns1:ns2:ns3:start has nsDepth of 3 and page depth of 4
        $nsDepth += 1;

        return $nsDepth;
    }

    private function step_select_pages()
    {
        global $INPUT;
        global $lang;
        global $conf;

        $namespace = str_replace(':', '/', $INPUT->str('ns'));

        if (!$namespace) {
            msg($this->getLang('exp_no_namespace_selected'), -1);
            $this->step_select_ns();
            return 0;
        }

        $nsDir = $conf['datadir'] . '/' . str_replace('(root)', '', $namespace);
        if (!is_dir($nsDir)) {
            msg(sprintf($this->getLang('exp_namespace_invalid'), $nsDir), -1);
            $this->step_select_ns();
            return 0;
        }

        [$pages, $media] = $this->getNamespaceContent($INPUT->str('ns'), (bool)$INPUT->str('include-sub-ns'), (bool)$INPUT->str('include-media'));

        echo sprintf('<h3>%s</h3>', $this->getLang('exp_select_pages'));
        echo sprintf('<input type="hidden" value="%s" name="ns" />', $INPUT->str('ns'));

        /**
         * Pages table
         */
        echo '<table class="table inline pages" width="100%">';
        echo '<thead>
      <tr>
        <th width="10"><input type="checkbox" class="export-all-pages" title="' . $this->getLang('select_all_pages') . '" /></th>
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
            $page_size     = filesize_h($page['size']);
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
          <td>%s</td>
        </tr>',
                $page_id,
                $page_id, $page_title,
                $create_user, $create_date,
                $modified_user, $modified_date,
                $page_size);
        }
        echo '</tbody>';
        echo '</table>';

        /**
         * Media table
         */
        echo '<table class="table inline media" width="100%">';

        echo '<thead><tr>
        <th width="10"><input type="checkbox" class="export-all-media" title="' . $this->getLang('select_all_media') . '" /></th>
        <th>Media</th>
        <th>Extension</th>
        <th>Modified</th>
        <th>Size</th>
      </tr></thead>';
        echo '<tbody>';

        foreach ($media as $info) {
            $file = new MediaFile($info['id']);

            echo sprintf('
        <tr>
          <td><input type="checkbox" name="media[%s]" class="export-page" /></td>
          <td>%s</td>
          <td>%s</td>
          <td>%s</td>
          <td>%s</td>
        </tr>',
            $info['id'],
            $info['id'],
            $file->getExtension(),
            dformat($file->getLastModified()),
            filesize_h($file->getFileSize()));
        }

        echo '</tbody>';
        echo '</table>';

        echo '<p>&nbsp;</p>';
        echo '<input type="hidden" name="step" value="select-pages" />';

        echo '<p class="pull-right">';
        echo sprintf('<button type="submit" name="export[select_ns]" class="btn btn-default">&larr; %s</button> ', $lang['btn_back']);
        echo sprintf('<button type="submit" name="cmd[export]" class="btn btn-primary primary">%s &rarr;</button>', $this->getLang('btn_export'));
        echo '</p>';
    }

    private function cmd_export()
    {
        global $INPUT;
        global $conf;

        $pages = [];
        $media = [];

        $nsDir = $conf['datadir'] . '/' . str_replace(':', '/', $INPUT->str('ns'));
        if (!is_dir($nsDir)) {
            msg(sprintf($this->getLang('exp_namespace_invalid'), $nsDir), -1);
            return 0;
        }

        switch ($INPUT->str('step')) {
            case 'select-ns':
                [$pageInfo, $mediaInfo] = $this->getNamespaceContent($INPUT->str('ns'), (bool)$INPUT->str('include-sub-ns'), (bool)$INPUT->str('include-media'));
                foreach ($pageInfo as $page) {
                    $pages[] = $page['id'];
                }
                foreach ($mediaInfo as $info) {
                    $media[] = $info['id'];
                }
                break;

            case 'select-pages':
                $pages = array_keys($INPUT->arr('pages'));
                $media = array_keys($INPUT->arr('media'));
                break;
        }

        if (!count($pages) && !count($media)) {
            msg('Nothing selected for export!', -1);
            return 0;
        }

        $namespace = str_replace(':', '-', str_replace('(root)', 'ROOT', $INPUT->str('ns')));
        $timestamp = date('Ymd-His');

        $Zip = new \splitbrain\PHPArchive\Zip;
        $Zip->create();

        foreach ($pages as $page) {
            $file_fullpath = wikiFN($page);
            $file_path     = \helper_plugin_advanced::PAGES_DIR . str_replace($conf['datadir'], '', $file_fullpath);
            $file_content  = io_readFile($file_fullpath);
            $Zip->addData($file_path, $file_content);
        }

        foreach ($media as $file) {
            $file_fullpath = mediaFN($file);
            $file_path     = \helper_plugin_advanced::MEDIA_DIR . str_replace($conf['mediadir'], '', $file_fullpath);
            $file_content  = io_readFile($file_fullpath, false);
            $Zip->addData($file_path, $file_content);
        }

        header("Content-Type: application/zip");
        header("Content-Transfer-Encoding: Binary");
        header("Content-Disposition: attachment; filename=DokuWiki-export-$namespace-$timestamp.zip");

        echo $Zip->getArchive();
        die();
    }
}
