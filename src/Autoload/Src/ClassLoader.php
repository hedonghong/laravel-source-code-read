<?php

/*
 * This file is part of Composer.
 *
 * (c) Nils Adermann <naderman@naderman.de>
 *     Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Composer\Autoload;

/**
 * ClassLoader implements a PSR-0, PSR-4 and classmap class loader.
 *
 *     $loader = new \Composer\Autoload\ClassLoader();
 *
 *     // register classes with namespaces
 *     $loader->add('Symfony\Component', __DIR__.'/component');
 *     $loader->add('Symfony',           __DIR__.'/framework');
 *
 *     // activate the autoloader
 *     $loader->register();
 *
 *     // to enable searching the include path (eg. for PEAR packages)
 *     $loader->setUseIncludePath(true);
 *
 * In this example, if you try to use a class in the Symfony\Component
 * namespace or one of its children (Symfony\Component\Console for instance),
 * the autoloader will first look for the class under the component/
 * directory, and it will then fallback to the framework/ directory if not
 * found before giving up.
 *
 * This class is loosely based on the Symfony UniversalClassLoader.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Jordi Boggiano <j.boggiano@seld.be>
 * @see    http://www.php-fig.org/psr/psr-0/
 * @see    http://www.php-fig.org/psr/psr-4/
 */
class ClassLoader
{
    // PSR-4
    private $prefixLengthsPsr4 = array();
    private $prefixDirsPsr4 = array();
    private $fallbackDirsPsr4 = array();

    // PSR-0
    private $prefixesPsr0 = array();
    private $fallbackDirsPsr0 = array();

    private $useIncludePath = false;
    private $classMap = array();
    private $classMapAuthoritative = false;
    private $missingClasses = array();

    public function getPrefixes()
    {
        if (!empty($this->prefixesPsr0)) {
            return call_user_func_array('array_merge', $this->prefixesPsr0);
        }

        return array();
    }

    public function getPrefixesPsr4()
    {
        return $this->prefixDirsPsr4;
    }

    public function getFallbackDirs()
    {
        return $this->fallbackDirsPsr0;
    }

    public function getFallbackDirsPsr4()
    {
        return $this->fallbackDirsPsr4;
    }

    public function getClassMap()
    {
        return $this->classMap;
    }

    /**
     * @param array $classMap Class to filename map
     */
    public function addClassMap(array $classMap)
    {
        if ($this->classMap) {
            $this->classMap = array_merge($this->classMap, $classMap);
        } else {
            $this->classMap = $classMap;
        }
    }

    /**
     * Registers a set of PSR-0 directories for a given prefix, either
     * appending or prepending to the ones previously set for this prefix.
     *
     * @param string       $prefix  The prefix
     * @param array|string $paths   The PSR-0 root directories
     * @param bool         $prepend Whether to prepend the directories
     */
    public function add($prefix, $paths, $prepend = false)
    {
        if (!$prefix) {
            if ($prepend) {
                $this->fallbackDirsPsr0 = array_merge(
                    (array) $paths,
                    $this->fallbackDirsPsr0
                );
            } else {
                $this->fallbackDirsPsr0 = array_merge(
                    $this->fallbackDirsPsr0,
                    (array) $paths
                );
            }

            return;
        }

        $first = $prefix[0];
        if (!isset($this->prefixesPsr0[$first][$prefix])) {
            $this->prefixesPsr0[$first][$prefix] = (array) $paths;

            return;
        }
        if ($prepend) {
            $this->prefixesPsr0[$first][$prefix] = array_merge(
                (array) $paths,
                $this->prefixesPsr0[$first][$prefix]
            );
        } else {
            $this->prefixesPsr0[$first][$prefix] = array_merge(
                $this->prefixesPsr0[$first][$prefix],
                (array) $paths
            );
        }
    }

    /**
     * Registers a set of PSR-4 directories for a given namespace, either
     * appending or prepending to the ones previously set for this namespace.
     *
     * @param string       $prefix  The prefix/namespace, with trailing '\\'
     * @param array|string $paths   The PSR-4 base directories
     * @param bool         $prepend Whether to prepend the directories
     *
     * @throws \InvalidArgumentException
     */
    public function addPsr4($prefix, $paths, $prepend = false)
    {
        if (!$prefix) {
            // Register directories for the root namespace.
            if ($prepend) {
                $this->fallbackDirsPsr4 = array_merge(
                    (array) $paths,
                    $this->fallbackDirsPsr4
                );
            } else {
                $this->fallbackDirsPsr4 = array_merge(
                    $this->fallbackDirsPsr4,
                    (array) $paths
                );
            }
        } elseif (!isset($this->prefixDirsPsr4[$prefix])) {
            // Register directories for a new namespace.
            $length = strlen($prefix);
            if ('\\' !== $prefix[$length - 1]) {
                throw new \InvalidArgumentException("A non-empty PSR-4 prefix must end with a namespace separator.");
            }
            $this->prefixLengthsPsr4[$prefix[0]][$prefix] = $length;
            $this->prefixDirsPsr4[$prefix] = (array) $paths;
        } elseif ($prepend) {
            // Prepend directories for an already registered namespace.
            $this->prefixDirsPsr4[$prefix] = array_merge(
                (array) $paths,
                $this->prefixDirsPsr4[$prefix]
            );
        } else {
            // Append directories for an already registered namespace.
            $this->prefixDirsPsr4[$prefix] = array_merge(
                $this->prefixDirsPsr4[$prefix],
                (array) $paths
            );
        }
    }

    /**
     * Registers a set of PSR-0 directories for a given prefix,
     * replacing any others previously set for this prefix.
     *
     * @param string       $prefix The prefix
     * @param array|string $paths  The PSR-0 base directories
     */
    public function set($prefix, $paths)
    {
        if (!$prefix) {
            $this->fallbackDirsPsr0 = (array) $paths;
        } else {
            $this->prefixesPsr0[$prefix[0]][$prefix] = (array) $paths;
        }
    }

    /**
     * Registers a set of PSR-4 directories for a given namespace,
     * replacing any others previously set for this namespace.
     *
     * @param string       $prefix The prefix/namespace, with trailing '\\'
     * @param array|string $paths  The PSR-4 base directories
     *
     * @throws \InvalidArgumentException
     */
    public function setPsr4($prefix, $paths)
    {
        if (!$prefix) {
            $this->fallbackDirsPsr4 = (array) $paths;
        } else {
            $length = strlen($prefix);
            if ('\\' !== $prefix[$length - 1]) {
                throw new \InvalidArgumentException("A non-empty PSR-4 prefix must end with a namespace separator.");
            }
            $this->prefixLengthsPsr4[$prefix[0]][$prefix] = $length;
            $this->prefixDirsPsr4[$prefix] = (array) $paths;
        }
    }

    /**
     * Turns on searching the include path for class files.
     *
     * @param bool $useIncludePath
     */
    public function setUseIncludePath($useIncludePath)
    {
        $this->useIncludePath = $useIncludePath;
    }

    /**
     * Can be used to check if the autoloader uses the include path to check
     * for classes.
     *
     * @return bool
     */
    public function getUseIncludePath()
    {
        return $this->useIncludePath;
    }

    /**
     * Turns off searching the prefix and fallback directories for classes
     * that have not been registered with the class map.
     *
     * @param bool $classMapAuthoritative
     */
    public function setClassMapAuthoritative($classMapAuthoritative)
    {
        $this->classMapAuthoritative = $classMapAuthoritative;
    }

    /**
     * Should class lookup fail if not found in the current class map?
     *
     * @return bool
     */
    public function isClassMapAuthoritative()
    {
        return $this->classMapAuthoritative;
    }

    /**
 * Registers this instance as an autoloader.
 * 注册自动加载核心类对象
 * @param bool $prepend Whether to prepend the autoloader or not
 */
    public function register($prepend = false)
    {
        spl_autoload_register(array($this, 'loadClass'), true, $prepend);
    }

    /**
     * Unregisters this instance as an autoloader.
     */
    public function unregister()
    {
        spl_autoload_unregister(array($this, 'loadClass'));
    }

    /**
     * Loads the given class or interface.
     * 一切加载的开始
     * @param  string    $class The name of the class
     * @return bool|null True if loaded, null otherwise
     */
    public function loadClass($class)
    {
        #findFile查找对应的被加载文件的所在磁盘目录路径
        if ($file = $this->findFile($class)) {
            #就是一个include而已，为什么include？程序不中断，并且高效
            includeFile($file);

            return true;
        }
    }

    /**
     * Finds the path to the file where the class is defined.
     * 找出被加载文件对应的真是磁盘路径
     * @param string $class The name of the class
     *
     * @return string|false The path if found, false otherwise
     * 通过classMap和findFileWithExtension()函数发现被加载文件路径
     * 1、classMap直接看命名空间是否在映射数组中即可
     *
     * 2、findFileWithExtension()这个函数包含了PSR0和PSR4标准的实现。还有个值得我们注意的是查找路径成功后includeFile()仍然类外面的函数，
     * 并不是ClassLoader的成员函数，原理跟上面一样，防止有用户写$this或self。
     * 3、命名空间是以\开头的，要去掉\然后再匹配
     *
     */
    public function findFile($class)
    {
        #命名空间是以\开头的，要去掉\然后再匹配
        // work around for PHP 5.3.0 - 5.3.2 https://bugs.php.net/50731
        if ('\\' == $class[0]) {
            $class = substr($class, 1);
        }

        // class map lookup
        #直接看命名空间是否在映射数组中即可
        if (isset($this->classMap[$class])) {
            return $this->classMap[$class];
        }
        #当发现之前已经加载不了的类，直接返回False
        if ($this->classMapAuthoritative || isset($this->missingClasses[$class])) {
            return false;
        }
        #利用PSR4标准尝试解析目录文件，如果文件不存在则继续用PSR0标准解析，如果解析出来的目录文件仍然不存在。
        #下面用命名空间phpDocumentor\Reflection\example作为栗子说明
        $file = $this->findFileWithExtension($class, '.php');

        #但是环境是HHVM虚拟机，继续用后缀名为hh再次调用findFileWithExtension函数
        // Search for Hack files if we are running on HHVM
        if (false === $file && defined('HHVM_VERSION')) {
            $file = $this->findFileWithExtension($class, '.hh');
        }

        #如果不存在，说明此命名空间无法加载，放到missingClasses中设为true，以便以后更快地加载
        if (false === $file) {
            // Remember that this class does not exist.
            $this->missingClasses[$class] = true;
        }

        return $file;
    }

    private function findFileWithExtension($class, $ext)
    {
        // PSR-4 lookup
        #把$class中的\替换为系统的分隔符DIRECTORY_SEPARATOR，并加入扩展名
        #将\转为文件分隔符/，加上后缀php或hh，得到$logicalPathPsr4即phpDocumentor//Reflection//example.php(hh);
        $logicalPathPsr4 = strtr($class, '\\', DIRECTORY_SEPARATOR) . $ext;

        #取得顶级命名空间的首个字母
        $first = $class[0];
        #利用命名空间第一个字母p作为前缀索引搜索prefixLengthsPsr4数组，查到数组，回看上面介绍prefixLengthsPsr4是以顶级命名空间作为键的数组
        #这里可能会找到多个以P开头的命名空间，用这些顶层命名空间与phpDocumentor\Reflection\example相比较，
        #可以得到phpDocumentor\Reflection\这个顶层命名空间
        /*
         'p' =>
          array (
            'phpDocumentor\\Reflection\\' => 25,
          ),
         */
        if (isset($this->prefixLengthsPsr4[$first])) {
            #在prefixLengthsPsr4映射数组中得到phpDocumentor\Reflection\长度为25。
            #在prefixDirsPsr4映射数组中得到phpDocumentor\Reflection\的目录映射为：
            /*
            'phpDocumentor\\Reflection\\' =>
            array (
                0 => __DIR__ . '/..' . '/phpdocumentor/reflection-common/src',
                1 => __DIR__ . '/..' . '/phpdocumentor/type-resolver/src',
                2 => __DIR__ . '/..' . '/phpdocumentor/reflection-docblock/src',
            ),
            */
            foreach ($this->prefixLengthsPsr4[$first] as $prefix => $length) {
                if (0 === strpos($class, $prefix)) {
                    foreach ($this->prefixDirsPsr4[$prefix] as $dir) {
                        #遍历这个映射数组，得到三个目录映射；
                        #查看 “目录+文件分隔符//+substr($logicalPathPsr4, $length)”文件是否存在，存在即返回。
                        #这里就是'_DIR_/../phpdocumentor/reflection-common/src + /+ substr(phpDocumentor/Reflection/example.php(hh),25)'
                        if (file_exists($file = $dir . DIRECTORY_SEPARATOR . substr($logicalPathPsr4, $length))) {
                            return $file;
                        }
                    }
                }
            }
        }

        // PSR-4 fallback dirs
        #如果上面找文件失败，则利用fallbackDirsPsr4数组里面的目录继续判断是否存在文件，具体方法是“目录+文件分隔符//+$logicalPathPsr4”
        foreach ($this->fallbackDirsPsr4 as $dir) {
            if (file_exists($file = $dir . DIRECTORY_SEPARATOR . $logicalPathPsr4)) {
                return $file;
            }
        }

        #如果PSR4标准加载失败，则要进行PSR0标准加载
        #找到phpDocumentor\Reflection\example最后“\”的位置
        #如果最后example为example_e则要将其后面文件名中'_'字符转为文件分隔符'/'
        #得到$logicalPathPsr0即phpDocumentor/Reflection/example.php(hh)或者phpDocumentor/Reflection/example/e.php(hh)
        # 利用命名空间第一个字母p作为前缀索引搜索prefixesPsr0数组，查到下面这个数组（我这里没有phpDocumentor这个命名空间）
        /*
        'P' =>
        array (
            'Prophecy\\' =>
                array (
                    0 => __DIR__ . '/..' . '/phpspec/prophecy/src',
                ),
            'Parsedown' =>
                array (
                    0 => __DIR__ . '/..' . '/erusev/parsedown',
                ),
        ),
        */
        // PSR-0 lookup
        if (false !== $pos = strrpos($class, '\\')) {
            // namespaced class name
            #如果最后example为example_e则要将其后面文件名中'_'字符转为文件分隔符'/'
            $logicalPathPsr0 = substr($logicalPathPsr4, 0, $pos + 1)
                . strtr(substr($logicalPathPsr4, $pos + 1), '_', DIRECTORY_SEPARATOR);
        } else {
            // PEAR-like class name
            $logicalPathPsr0 = strtr($class, '_', DIRECTORY_SEPARATOR) . $ext;
        }
        /*
         如果存在就会这样子
          'phpDocumentor' =>
            array (
                0 => __DIR__ . '/..' . '/erusev/parsedown',
            ),
         */
        #循环这个数组，得到的顶层命名空间phpDocumentor、Prophecy和Parsedown
        #用这些顶层命名空间与phpDocumentor\Reflection\example相比较，可以得到phpDocumentor这个顶层命名空间
        #在映射数组中得到phpDocumentor目录映射为'_DIR_ . '/..' . '/erusev/parsedown'
        #查看 “目录+文件分隔符//+$logicalPathPsr0”文件是否存在，存在即返回。
        #这里就是 “_DIR_ . '/..' . '/erusev/parsedown + //+ phpDocumentor//Reflection//example/e.php(hh)”
        if (isset($this->prefixesPsr0[$first])) {
            #循环这个数组，得到的顶层命名空间phpDocumentor、Prophecy和Parsedown
            foreach ($this->prefixesPsr0[$first] as $prefix => $dirs) {
                if (0 === strpos($class, $prefix)) {
                    foreach ($dirs as $dir) {
                        #查看 “目录+文件分隔符//+$logicalPathPsr0”文件是否存在，存在即返回。
                        if (file_exists($file = $dir . DIRECTORY_SEPARATOR . $logicalPathPsr0)) {
                            return $file;
                        }
                    }
                }
            }
        }

        // PSR-0 fallback dirs
        #如果失败，则利用fallbackDirsPsr0数组里面的目录继续判断是否存在文件，具体方法是“目录+文件分隔符//+$logicalPathPsr0”
        foreach ($this->fallbackDirsPsr0 as $dir) {
            if (file_exists($file = $dir . DIRECTORY_SEPARATOR . $logicalPathPsr0)) {
                return $file;
            }
        }

        // PSR-0 include paths.
        #如果仍然找不到，则利用stream_resolve_include_path()，在当前include目录寻找该文件，如果找到返回绝对路径
        #stream_resolve_include_path如果文件存在则返回改文件的绝对路径
        if ($this->useIncludePath && $file = stream_resolve_include_path($logicalPathPsr0)) {
            return $file;
        }

        return false;
    }
}

/**
 * Scope isolated include.
 *
 * Prevents access to $this/self from included files.
 */
function includeFile($file)
{
    include $file;
}
