<?php

/**
 * Dokuwiki Advanced Config Plugin
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Giuseppe Di Terlizzi <giuseppe.diterlizzi@gmail.com>
 */

class admin_plugin_advanced_config extends DokuWiki_Admin_Plugin
{

    private $allowedFiles = array();
    private $fileInfo     = array();

    /**
     * @return int sort number in admin menu
     */
    public function getMenuSort()
    {
        return 1;
    }

    /**
     * @return bool true if only access for superuser, false is for superusers and moderators
     */
    public function forAdminOnly()
    {
        return true;
    }

    public function getMenuIcon()
    {
        return dirname(__FILE__) . '/../svg/cogs.svg';
    }

    public function getMenuText($language)
    {
        return $this->getLang('menu_config');
    }

    /**
     * handle user request
     */
    public function handle()
    {

        global $INPUT;

        if (!isset($_REQUEST['cmd'])) {
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

    /**
     * Get configuration file info
     *
     * @return array
     */
    private function getFileInfo()
    {

        global $INPUT;
        global $conf;
        global $config_cascade;

        $file = $INPUT->str('file');
        $tab  = $INPUT->str('tab');

        $file_local     = null;
        $file_default   = null;
        $file_protected = null;

        if (!$file || !$tab) {
            return array();
        }

        switch ($tab) {

            case 'config':

                $configs = $config_cascade[$file];

                $file_default   = @$configs['default'][0];
                $file_local     = @$configs['local'][0];
                $file_protected = @$configs['protected'][0];
                break;

            case 'userstyle':
            case 'userscript':

                $configs = $config_cascade[$tab][$file];

                # Detect new DokuWiki release config (css, less)
                if (is_array(@$configs)) {
                    $file_local = @$configs[0];
                } else {
                    $file_local = $configs;
                }

                break;

            case 'hook':
                $file_local   = DOKU_CONF . "$file.html";
                $file_default = tpl_incdir() . "$file.html";
                break;

            case 'plugin':
                $file_local = DOKU_CONF . $file;
                break;

        }

        switch ($file) {

            case 'styleini':
                $file_local   = str_replace('%TEMPLATE%', $conf['template'], $file_local);
                $file_default = str_replace('%TEMPLATE%', $conf['template'], $file_default);
                break;
            case 'acl':
                $file_local   = DOKU_CONF . 'acl.auth.php';
                $file_default = DOKU_CONF . 'acl.auth.php.dist';
                break;

            case 'users':
                $file_local   = DOKU_CONF . 'users.auth.php';
                $file_default = DOKU_CONF . 'users.auth.php.dist';
                break;

            case 'htaccess':
                $file_default = DOKU_INC . '.htaccess.dist';
                $file_local   = DOKU_INC . '.htaccess';
                break;

            case 'userscript':
                $configs = $config_cascade['userscript'];
                if (is_array(@$configs['default'])) {
                    $file_local = @$configs['default'][0];
                } else {
                    $file_local = @$configs['default'];
                }
                $file_default = null;
                break;

        }

        $file_info = array(
            'tab'                   => $tab,
            'file'                  => $file,
            'default'               => $file_default,
            'local'                 => $file_local,
            'protected'             => $file_protected,
            'local_name'            => basename($file_local),
            'default_name'          => basename($file_default),
            'protected_name'        => basename($file_protected),
            'local_last_modify'     => (file_exists($file_local) ? dformat(filemtime($file_local)) : ''),
            'protected_last_modify' => (file_exists($file_protected) ? dformat(filemtime($file_protected)) : ''),
            'default_last_modify'   => (file_exists($file_default) ? dformat(filemtime($file_default)) : ''),
            'help'                  => 'config/' . $file,
        );

        return $file_info;

    }

    public function cmd_save()
    {

        global $INPUT;

        $file_info = $this->getFileInfo();

        $file_path   = $file_info['local'];
        $file_name   = $file_info['localName'];
        $file_backup = sprintf('%s.%s.gz', $file_path, date('YmdHis'));

        $content_old = io_readFile($file_path);
        $content_new = cleanText($INPUT->post->str('content'));

        if (md5($content_old) === md5($content_new)) {
            return false;
        }

        if (io_saveFile($file_path, $content_new)) {

            if ($this->getConf('backup')) {
                io_saveFile($file_backup, $content_old);
            }
            // Create a backup
            msg(sprintf($this->getLang('conf_file_save_success'), $file_name), 1);

        } else {
            msg(sprintf($this->getLang('conf_file_save_fail'), $file_name), -1);
        }

    }

    public function cmd_wordblock_update()
    {

        $file_info     = $this->getFileInfo();
        $blacklist_url = 'https://meta.wikimedia.org/wiki/Spam_blacklist?action=raw';

        $http             = new DokuHTTPClient();
        $http->timeout    = 25;
        $http->keep_alive = false;

        $blacklist = $http->get($blacklist_url);
        $blacklist = trim(preg_replace('/#(.*)$/m', '', $blacklist)); # Remove all comments from file
        $blacklist = trim(preg_replace('/[\n]+/m', "\n", $blacklist)); # Remove multiple new line

        if (io_saveFile($file_info['local'], $blacklist)) {
            msg($this->getLang('conf_blacklist_update'), 1);
        } else {
            msg($this->getLang('conf_blacklist_failed'), -1);
        }

    }

    private function help($file)
    {
        echo $this->locale_xhtml($file);
        return true;
    }

    private function getDefault()
    {

        $this->getDefaultConfig('default');
        $this->getDefaultConfig('protected');

    }

    private function getDefaultConfig($file)
    {
        global $lang;

        $file_info = $this->fileInfo;

        if (!$file_info[$file]) {
            return;
        }

        $file_name    = $file_info[$file . '_name'];
        $file_path    = $file_info[$file];
        $file_lastmod = $file_info[$file . '_last_modify'];

        echo '<div class="config_' . $file . '">';
        echo '<h3>' . "$file_name</h3>";
        echo '<div class="content">';
        echo '<textarea class="edit" rows="15" cols="" disabled="disabled">';
        echo hsc(io_readFile($file_path));
        echo '</textarea>';
        echo '<p class="docInfo small pull-right">';
        echo $file_path;
        echo (file_exists($file_path) ? ' · ' . $lang['lastmod'] . ' ' . $file_lastmod : '');
        echo '</p>';
        echo '</div>';
        echo '</div>';

        return true;

    }

    private function editForm()
    {

        global $lang;

        $file_info    = $this->fileInfo;
        $file_path    = $file_info['local'];
        $file_data    = (file_exists($file_path) ? io_readFile($file_path) : '');
        $file_lastmod = $file_info['local_last_modify'];
        $file_name    = $file_info['local_name'];

        $lng_edit = $this->getLang('conf_edit');
        $lng_upd  = $this->getLang('conf_blacklist_download');

        echo "<h3>$lng_edit $file_name</h3>";

        echo '<form action="" method="post">';
        echo '<textarea name="content" class="edit" rows="15" cols="">';
        echo $file_data;
        echo '</textarea>';

        echo '<p class="docInfo small pull-right">';
        echo $file_path;
        echo (file_exists($file_path) ? ' · ' . $lang['lastmod'] . ' ' . $file_lastmod : '');
        echo '</p>';

        echo '<p>&nbsp;</p>';

        formSecurityToken();

        echo '<input type="hidden" name="do" value="admin" />';
        echo '<input type="hidden" name="page" value="advanced_config" />';

        echo '<button type="submit" name="cmd[save]" class="btn btn-primary primary">' . $lang['btn_save'] . '</button> ';

        if ($file_info['tab'] == 'userstyle' || $file_info['file'] == 'userscript') {

            $purge_type = (($file_info['tab'] == 'userstyle') ? 'css' : 'js');

            echo '<button type="button" class="primary btn btn-default purge-cache" data-purge-msg="' . $this->getLang('conf_cache_purged') . '" data-purge-type="' . $purge_type . '">' . $this->getLang("btn_purge_$purge_type") . '</button> ';

        }

        if ($file_info['file'] == 'wordblock') {
            echo '<button type="submit" name="cmd[wordblock_update]" class="btn btn-default">' . $lng_upd . '</button> ';
        }

        echo '<button type="submit" class="btn btn-default">' . $lang['btn_cancel'] . '</button>';
        echo '</form>';

        return true;

    }

    /**
     * output appropriate html
     */
    public function html()
    {

        global $INPUT;
        global $lang;
        global $conf;
        global $ID;

        $lang['toc'] = $this->getLang('menu_config');

        $this->fileInfo = $file_info = $this->getFileInfo();

        echo '<div id="plugin_advanced_config">';

        echo $this->locale_xhtml('config/intro');
        echo '<p>&nbsp;</p>';

        if ($current_tab = $this->currentTab()) {

            $tab_label = $this->getTabs();
            echo '<h2>' . $tab_label[$current_tab] . '</h2>';
            echo '<p><ul class="tabs">';

            foreach ($this->getTab($current_tab) as $file => $title) {

                $file_class = '';

                if ($INPUT->str('file') == $file) {
                    $file_class = 'active';
                }

                echo '<li class="' . $file_class . '"><a href="' . $this->tabURL($current_tab, array('file' => $file, 'sectok' => getSecurityToken())) . '">' . $title . '</a></li>';
            }

            echo '</ul></p>';

        }

        if ($current_tab == 'config' && !isset($file_info['file'])) {
            $this->help('config');
        }

        if ($current_tab == 'userstyle' && !isset($file_info['file'])) {
            $this->help('config/userstyle');
        }

        if ($current_tab == 'hook' && !isset($file_info['file'])) {
            $this->help('config/hooks');
        }

        if (isset($file_info['file']) && in_array($file_info['file'], $this->allowedFiles)) {

            $this->help($file_info['help']);
            echo '<p>&nbsp;</p>';
            $this->getDefaultConfig('default');
            $this->getDefaultConfig('protected');
            $this->editForm();

        }

        echo '</div>';

    }

    public function getTabs()
    {

        return array(
            'config'    => $this->getLang('conf_tab_configurations'),
            'userstyle' => $this->getLang('conf_tab_styles'),
            'hook'      => $this->getLang('conf_tab_hooks'),
            'other'     => $this->getLang('conf_tab_others'),
        );
    }

    public function getTab($tab)
    {
        global $conf;
        global $plugin_controller;

        $current_section = $this->currentTab();

        // DokuWiki config
        $toc_configs = array(
            'acronyms'   => $this->getLang('conf_abbrev'),
            'entities'   => $this->getLang('conf_entities'),
            'interwiki'  => $this->getLang('conf_iwiki'),
            'mime'       => $this->getLang('conf_mime'),
            'smileys'    => $this->getLang('conf_smiley'),
            'scheme'     => $this->getLang('conf_scheme'),
            'wordblock'  => $this->getLang('conf_blacklist'),
            'license'    => $this->getLang('conf_license'),
            'main'       => $this->getLang('conf_main'),
            'manifest'   => $this->getLang('conf_manifest'),
            'plugins'    => $this->getLang('conf_plugins'),
            'styleini'   => $this->getLang('conf_styleini'),
            'userscript' => $this->getLang('conf_ujs'),
        );

        // User Style
        $toc_styles = array(
            'screen' => 'Screen',
            'print'  => 'Print',
            'feed'   => 'Feed',
            'all'    => 'All',
        );

        // Template Hooks
        $toc_hooks = array(
            'meta'          => 'Meta',
            'sidebarheader' => $this->getLang('conf_sidebar') . ' (' . $this->getLang('conf_header') . ')',
            'sidebarfooter' => $this->getLang('conf_sidebar') . ' (' . $this->getLang('conf_footer') . ')',
            'pageheader'    => 'Page (' . $this->getLang('conf_header') . ')',
            'pagefooter'    => 'Page (' . $this->getLang('conf_footer') . ')',
            'header'        => $this->getLang('conf_header'),
            'footer'        => $this->getLang('conf_footer'),
        );

        // Other config
        $toc_others = array(
            'htaccess' => '.htaccess',
        );

        if ($conf['useacl']) {
            $toc_configs['acl'] = 'ACL';
        }

        if ($conf['authtype'] == 'authplain') {
            $toc_configs['users'] = 'Users';
        }

        // Specific Template Hooks
        switch ($conf['template']) {

            case 'bootstrap3':

                $toc_hooks['topheader']          = $this->getLang('conf_topheader');
                $toc_hooks['rightsidebarheader'] = $this->getLang('conf_rsidebar') . ' (' . $this->getLang('conf_header') . ')';
                $toc_hooks['rightsidebarfooter'] = $this->getLang('conf_rsidebar') . ' (' . $this->getLang('conf_footer') . ')';
                $toc_hooks['social']             = 'Social';

                $toc_others['bootstrap3.themes.conf'] = 'Bootstrap3 NS Themes';

                break;

        }

        $plugin_list = $plugin_controller->getList('', true);

        if (!is_array($plugin_list)) {
            $plugin_list = array();
        }

        $toc_plugins = array();

        foreach ($plugin_list as $plugin) {

            switch ($plugin) {
                case 'explain':
                    $toc_plugins['explain.conf'] = 'Explain';
                    break;
            }

        }

        $toc_items = array(
            'config'    => $toc_configs,
            'userstyle' => $toc_styles,
            'hook'      => $toc_hooks,
            'other'     => $toc_others,
            'plugin'    => $toc_plugins,
        );

        if ($current_section) {
            $this->allowedFiles = array_keys($toc_items[$current_section]);
        }

        if (!isset($toc_items[$tab])) {
            return array();
        }

        return $toc_items[$tab];
    }

    /**
     * Create an URL
     *
     * @param string $tab      tab to load, empty for current tab
     * @param array  $params   associative array of parameter to set
     * @param string $sep      seperator to build the URL
     * @param bool   $absolute create absolute URLs?
     * @return string
     */
    public function tabURL($tab = '', $params = array(), $sep = '&amp;', $absolute = false)
    {
        global $ID;

        $defaults = array(
            'do'   => 'admin',
            'page' => 'advanced_config',
            'tab'  => $tab,
        );

        return wl($ID, array_merge($defaults, $params), $absolute, $sep);
    }

    public function currentTab()
    {
        global $INPUT;

        $tab = $INPUT->str('tab');

        if (in_array($tab, array_keys($this->getTabs()))) {
            return $tab;
        }

        return null;
    }

    public function getTOC()
    {
        global $ID;

        foreach ($this->getTabs() as $section => $section_title) {

            $toc[] = array(
                'link'  => wl($ID, array(
                    'do'   => 'admin',
                    'page' => 'advanced_config',
                    'tab'  => $section)
                ),
                'title' => $section_title,
                'level' => 1,
                'type'  => 'ul',
            );

        }

        return $toc;
    }

}
