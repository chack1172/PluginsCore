<?php
namespace Chack1172\Core;

class Loader
{

    private static $registeredNamespaces = [];

    public static function register()
    {
        spl_autoload_register([self, 'loadClass'], true);
    }

    public static function registerNamespace(string $namespace = '', string $path)
    {
        $namespace = rtrim($namespace, '\\');

        if (!isset(static::$registeredNamespaces[$namespace])) {
            static::$registeredNamespaces[$namespace] = $path;
        }
    }

    public static function loadClass(string $name = '') : bool
    {
        $file = static::findFile($name);
        if ($file != '') {
            require_once $file;
            return true;
        }
        return false;
    }

    private static function findFile(string $class) : string
    {
        $classParts = explode('\\', $class);
		$className = array_pop($classParts);
		if (count($classParts) >= 2) {
			$baseNameSpace = $classParts[0] . '\\' . $classParts[1];
			if (isset(static::$registeredNamespaces[$baseNameSpace])) {
				$classParts = array_slice($classParts, 2);
                $classPath = static::$registeredNamespaces[$baseNameSpace] . '/' . implode(
                        '/',
                        $classParts
                    ) . '/' . $className . '.php';
                if (file_exists($classPath)) {
                    return $classPath;
                }
			}
        }
        return '';
    }
}