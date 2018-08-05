<?php
namespace Chack1172\Core;

class Install
{
    const CORE_VERSION = '1.1.0'; 

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

    protected function validateSettings(array $settings) : bool
    {
        if (count($settings) == 0) {
            return false;
        }
        foreach ($settings as $group) {
            if (!isset($group['title'])) {
                return false;
            } elseif (!isset($group['settings']) || !is_array($group['settings'])) {
                return false;
            } elseif (count($group['settings']) > 0) {
                foreach ($group['settings'] as $setting) {
                    if (!isset($setting['title'])) {
                        return false;
                    } elseif (!isset($setting['optionscode'])) {
                        return false;
                    } elseif (!isset($setting['value'])) {
                        return false;
                    }
                }
            }
        }
        return true;
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
            if (in_array('settings', $data)) {
                $path = $this->resourceDir . '/settings.json';
                if (!file_exists($path)) {
                    $errors[] = 'Settings file is missing';
                } else {
                    $settings = json_decode(file_get_contents($path), true);
                    if (json_last_error() != JSON_ERROR_NONE) {
                        $error[] = 'Settings file is not a valid JSON file.';
                    } elseif (!$this->validateSettings($settings))  {
                        $error[] = 'Settings file is not valid.';
                    }
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

    protected function loadSettings() : array
    {
        $path = $this->resourceDir . '/settings.json';
        if (file_exists($path)) {
            $settings = json_decode(file_get_contents($path), true);
            if (json_last_error() != JSON_ERROR_NONE) {
                $settings = [];
            } elseif (!$this->validateSettings($settings)) {
                $settings = [];
            }
        } else {
            $settings = [];
        }
        return $settings;
    }

    public function addSettings()
    {
        global $db;
        $groups = $this->loadSettings();
        foreach ($groups as $groupKey => $group) {
            $group['name'] = $db->escape_string($groupKey);
            $group['title'] = $db->escape_string($group['title']);
            $group['description'] = isset($group['description']) ? $db->escape_string($group['description']) : '';
            $settings = $group['settings'];
            unset($group['settings']);
            $gid = $db->insert_query('settinggroups', $group);
            $disporder = 0;
            foreach ($settings as $key => $setting) {
                $setting['name'] = $db->escape_string($groupKey . '_' . $key);
                $setting['title'] = $db->escape_string($setting['title']);
                $setting['description'] = isset($setting['description']) ? $db->escape_string($setting['description']) : '';
                $setting['optionscode'] = $db->escape_string($setting['optionscode']);
                $setting['value'] = $db->escape_string($setting['value']);
                $setting['disporder'] = $disporder++;
                $setting['gid'] = $gid;
                $db->insert_query('settings', $setting);
            }
        }
        rebuild_settings();
    }

    public function deleteSettings()
    {
        global $db;
        $settings = $this->loadSettings();
        $keys = array_keys($settings);
        if (count($keys) > 0) {
            $names = '';
            foreach ($keys as $name) {
                $names .= '"' . $db->escape_string($name) . '",';
            }
            $names = rtrim($names, ',');
            $query = $db->simple_select('settinggroups', 'gid', "`name` IN ({$names})");
            while ($group = $db->fetch_array($query)) {
                $db->delete_query('settings', '`gid` = ' . $group['gid']);
            }
            $db->delete_query('settinggroups', "`name` IN ({$names})");
        }
        rebuild_settings();
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