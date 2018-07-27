<?php
namespace Chack1172\Core;

class Install
{
    const CORE_VERSION = '1.0.1'; 

    private $pluginCode = '';
    private $prefix = '';
    private $pluginInfo = [];
    private $resourceDir = '';

    public function __construct(array $pluginInfo)
    {
        $this->pluginInfo = $pluginInfo;
        $this->pluginCode = $pluginInfo['plugin_code'];
        $this->prefix = $pluginInfo['codename'];
        $this->resourceDir = MYBB_ROOT . 'inc/plugins/Chack1172/' . $this->pluginCode . '/resources';
    }

    /**
     * Verify if the core version is correct. Checks only major and minor version.
     * 
     * @return bool Wether the version is valid.
     */
    function isValidVersion() : bool
    {
        $coreVersion = explode('.', self::CORE_VERSION);
        $pluginVersion = explode('.', $this->pluginInfo['core_version']);
        if (count($coreVersion) >= 2 && count($pluginVersion) >= 2) {
            if ($coreVersion[0] > $pluginVersion[0]) {
                return true;
            } elseif ($coreVersion[0] == $pluginVersion[0] && $coreVersion[1] >= $pluginVersion[1]) {
                return true;
            }
        }
        return false;
    }

    public function checkRequirements(array $data = [], array $errors = [])
    {
        if (!$this->isValidVersion()) {
            $errors[] = 'You need PluginsCore version ' . $this->pluginInfo['core_version'];
        } elseif (count($data) > 0) {
            if (in_array('templates', $data)) {
                $path = $this->resourceDir . '/templates';
                if (!file_exists($path) || !is_dir($path)) {
                    $errors[] = 'Template folder is missing';
                }
            }
        }

        // Error found, redirect the user
        if (!empty($errors)) {
            $message = 'Please, fix these errors and try again:<ul>';
            foreach ($errors as $error) {
                $message .= '<li>' . $error . '</li>';
            }
            $message .= '</li>';
            flash_message($message, 'error');
            admin_redirect('index.php?module=config-plugins');
        }
    }

    public function addTemplates(string $groupTitle)
    {
        global $db;

        $db->insert_query('templategroups', [
            'prefix'    => $db->escape_string($this->prefix),
            'title'     => $db->escape_string($groupTitle),
            'isdefault' => 0
        ]);

        $path = $this->resourceDir . '/templates';
        $directory = new \DirectoryIterator($path);
        foreach ($directory as $file) {
            if (!$file->isDot() && !$file->isDir()) {
                $templateName = basename($file->getPathname(), '.html');
                $templateName = $this->prefix . '_' . $templateName;
                $templateContent = file_get_contents($file->getPathname());

                $db->insert_query('templates', [
                    'title'     => $db->escape_string($templateName),
                    'template'  => $db->escape_string($templateContent),
                    'sid'       => -2,
                    'version'   => '',
                    'dateline'  => TIME_NOW
                ]);
            }
        }
    }

    public function deleteTemplates()
    {
        global $db;
        $db->delete_query('templates', "title LIKE '{$this->prefix}_%'");
        $db->delete_query('templategroups', "prefix = '{$this->prefix}'");
    }
}