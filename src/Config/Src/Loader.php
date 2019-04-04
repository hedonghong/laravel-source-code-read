<?php

namespace Dotenv;

use Dotenv\Exception\InvalidPathException;

/**
 * This is the loaded class.
 *
 * It's responsible for loading variables by reading a file from disk and:
 * - stripping comments beginning with a `#`,
 * - parsing lines that look shell variable setters, e.g `export key = value`, `key="value"`.
 */
class Loader
{
    /**
     * The file path.
     *
     * @var string
     */
    protected $filePath;

    /**
     * Are we immutable?
     *
     * @var bool
     */
    protected $immutable;

    /**
     * The list of environment variables declared inside the 'env' file.
     *
     * @var array
     */
    public $variableNames = array();

    /**
     * Create a new loader instance.
     *
     * @param string $filePath
     * @param bool   $immutable
     *
     * @return void
     */
    public function __construct($filePath, $immutable = false)
    {
        $this->filePath = $filePath;
        $this->immutable = $immutable;
    }

    /**
     * Set immutable value.
     *
     * @param bool $immutable
     * @return $this
     */
    public function setImmutable($immutable = false)
    {
        $this->immutable = $immutable;

        return $this;
    }

    /**
     * Get immutable value.
     *
     * @return bool
     */
    public function getImmutable()
    {
        return $this->immutable;
    }

    /**
     * Load `.env` file in given directory.
     *
     * @throws \Dotenv\Exception\InvalidPathException|\Dotenv\Exception\InvalidFileException
     *
     * 判断env文件是否可读
     * 读取整个env文件，并将文件按行存储
     * 循环读取每一行，略过注释
     * 进行环境变量赋值
     * @return array
     */
    public function load()
    {
        #判断env文件是否可读
        $this->ensureFileIsReadable();

        $filePath = $this->filePath;
        #读取整个env文件，并将文件按行存储
        $lines = $this->readLinesFromFile($filePath);
        foreach ($lines as $line) {
            #循环读取每一行，略过注释
            if (!$this->isComment($line) && $this->looksLikeSetter($line)) {
                #进行环境变量赋值，跳到setEnvironmentVariable函数去
                $this->setEnvironmentVariable($line);
            }
        }

        return $lines;
    }

    /**
     * Ensures the given filePath is readable.
     *
     * @throws \Dotenv\Exception\InvalidPathException
     *
     * @return void
     */
    protected function ensureFileIsReadable()
    {
        if (!is_readable($this->filePath) || !is_file($this->filePath)) {
            throw new InvalidPathException(sprintf('Unable to read the environment file at %s.', $this->filePath));
        }
    }

    /**
     * Normalise the given environment variable.
     *
     * Takes value as passed in by developer and:
     * - ensures we're dealing with a separate name and value, breaking apart the name string if needed,
     * - cleaning the value of quotes,
     * - cleaning the name of quotes,
     * - resolving nested variables.
     *
     * @param string $name
     * @param string $value
     *
     * @throws \Dotenv\Exception\InvalidFileException
     *
     * @return array
     */
    protected function normaliseEnvironmentVariable($name, $value)
    {
        #进一步处理配置文件中每一行配置信息
        list($name, $value) = $this->processFilters($name, $value);

        $value = $this->resolveNestedVariables($value);

        return array($name, $value);
    }

    /**
     * Process the runtime filters.
     *
     * Called from `normaliseEnvironmentVariable` and the `VariableFactory`, passed as a callback in `$this->loadFromFile()`.
     *
     * @param string $name
     * @param string $value
     *
     * @throws \Dotenv\Exception\InvalidFileException
     *
     * @return array
     */
    public function processFilters($name, $value)
    {
        #用于将赋值语句转化为环境变量名name和环境变量值value
        list($name, $value) = $this->splitCompoundStringIntoParts($name, $value);
        #用于格式化环境变量名，过来一些特殊字符
        list($name, $value) = $this->sanitiseVariableName($name, $value);
        #用于格式化环境变量值，这个处理起来会复杂点，总的来说如下：跳到sanitiseVariableValue
        list($name, $value) = $this->sanitiseVariableValue($name, $value);

        return array($name, $value);
    }

    /**
     * Read lines from the file, auto detecting line endings.
     *
     * @param string $filePath
     *
     * @return array
     */
    protected function readLinesFromFile($filePath)
    {
        // Read file into an array of lines with auto-detected line endings
        #默认为0，当设置为 "1" 时，PHP 将检查通过 fgets() 和 file() 取得的数据中的行结束符号是符合 Unix、MS-Dos 还是 Mac 的习惯。
        #（PHP 4.3 版以后可用）
        $autodetect = ini_get('auto_detect_line_endings');
        ini_set('auto_detect_line_endings', '1');
        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        ini_set('auto_detect_line_endings', $autodetect);

        return $lines;
    }

    /**
     * Determine if the line in the file is a comment, e.g. begins with a #.
     *
     * @param string $line
     *
     * @return bool
     */
    protected function isComment($line)
    {
        $line = ltrim($line);

        return isset($line[0]) && $line[0] === '#';
    }

    /**
     * Determine if the given line looks like it's setting a variable.
     *
     * @param string $line
     *
     * @return bool
     */
    protected function looksLikeSetter($line)
    {
        return strpos($line, '=') !== false;
    }

    /**
     * Split the compound string into parts.
     *
     * If the `$name` contains an `=` sign, then we split it into 2 parts, a `name` & `value`
     * disregarding the `$value` passed in.
     *
     * @param string $name
     * @param string $value
     *
     * @return array
     */
    protected function splitCompoundStringIntoParts($name, $value)
    {
        #根据=号进行分割
        if (strpos($name, '=') !== false) {
            #explode使用第三个参数，就是返回第一个=号前的做为键，后面的所有作为值，不管是否还包含等号
            #所以配置是要一行行的呀，最后使用trim清除下左右的键和值
            list($name, $value) = array_map('trim', explode('=', $name, 2));
        }

        return array($name, $value);
    }

    /**
     * Strips quotes from the environment variable value.
     *
     * @param string $name
     * @param string $value
     *
     * @throws \Dotenv\Exception\InvalidFileException
     *
     * @return array
     */
    protected function sanitiseVariableValue($name, $value)
    {
        $value = trim($value);
        if (!$value) {
            return array($name, $value);
        }
        #Parser::parseValue中处理三种情况:1、空字符串 2、处理有"号或者'号的 3、其他
        return array($name, Parser::parseValue($value));
        /*
         #vendor/vlucas/phpdotenv/src/Parser.php源码
        public static function parseValue($value)
        {
            if ($value === '') {
                return '';
            } elseif ($value[0] === '"' || $value[0] === '\'') {
                #处理带有"号或者'号的的配置，比如XXX="454"或XXX='454'或XXX='454'#注释
                #处理结果分别是454、454、454
                #在parseQuotedValue通过array_reduce(str_split($value), function ($data, $char) use ($value) {
                #},['', 0]);
                #意思是循环处理"454"、'454'、'454'#注释的每一个字符，排除"和'号作为返回值，并且从#部分开始算作注释不作为值返回
                #parseQuotedValue看起来比较绕，但是可以单独调试，断点调试之后就很好理解了
                #$str = '"xx#x"#a';
                #echo Parser::parseQuotedValue($str);
                return Parser::parseQuotedValue($value);
            } else {
                #parseUnquotedValue处理不带"和'号的，比较简单
                return Parser::parseUnquotedValue($value);
            }
        }
        */
    }

    /**
     * Resolve the nested variables.
     *
     * Look for ${varname} patterns in the variable value and replace with an
     * existing environment variable.
     *
     * @param string $value
     *
     * @return mixed
     */
    protected function resolveNestedVariables($value)
    {
        #查找含有$的变量型配置项进行获取原全局环境变量中的值，没有直接返回
        if (strpos($value, '$') !== false) {
            $loader = $this;
            /*
             * 栗子来源：http://www.runoob.com/php/php-preg_replace_callback.html
            #函数执行一个正则表达式搜索并且使用一个回调进行替换
            // 将文本中的年份增加一年.
            $text = "April fools day is 04/01/2002\n";
            $text.= "Last christmas was 12/24/2001\n";
            // 回调函数
            function next_year($matches)
            {
                // 通常: $matches[0]是完成的匹配
                // $matches[1]是第一个捕获子组的匹配
                // 以此类推
                return $matches[1].($matches[2]+1);
            }
            echo preg_replace_callback(
                "|(\d{2}/\d{2}/)(\d{4})|",
                "next_year",
                $text);
            //结果：
            April fools day is 04/01/2003
            Last christmas was 12/24/2002
            */
            $value = preg_replace_callback(
                '/\${([a-zA-Z0-9_.]+)}/',
                function ($matchedPatterns) use ($loader) {
                    $nestedVariable = $loader->getEnvironmentVariable($matchedPatterns[1]);
                    if ($nestedVariable === null) {
                        #获取符合'/\${([a-zA-Z0-9_.]+)}/'的环境变量值，并返回
                        return $matchedPatterns[0];
                    } else {
                        return $nestedVariable;
                    }
                },
                $value
            );
        }
        #返回
        return $value;
    }

    /**
     * Strips quotes and the optional leading "export " from the environment variable name.
     *
     * @param string $name
     * @param string $value
     *
     * @return array
     */
    protected function sanitiseVariableName($name, $value)
    {
        return array(Parser::parseName($name), $value);
    }

    /**
     * Search the different places for environment variables and return first value found.
     *
     * @param string $name
     *
     * @return string|null
     */
    public function getEnvironmentVariable($name)
    {
        switch (true) {
            case array_key_exists($name, $_ENV):
                return $_ENV[$name];
            case array_key_exists($name, $_SERVER):
                return $_SERVER[$name];
            default:
                $value = getenv($name);
                return $value === false ? null : $value; // switch getenv default to null
        }
    }

    /**
     * Set an environment variable.
     *
     * This is done using:
     * - putenv,
     * - $_ENV,
     * - $_SERVER.
     *
     * The environment variable value is stripped of single and double quotes.
     *
     * @param string      $name
     * @param string|null $value
     *
     * @throws \Dotenv\Exception\InvalidFileException
     *
     * @return void
     */
    public function setEnvironmentVariable($name, $value = null)
    {
        #解析出环境变量的键和值，跳到normaliseEnvironmentVariable方法中
        list($name, $value) = $this->normaliseEnvironmentVariable($name, $value);

        $this->variableNames[] = $name;
        #后面的就不解读了，挺简单的了，可以看的懂了，最终设置到$_ENV、$_SERVER全局变量中
        // Don't overwrite existing environment variables if we're immutable
        // Ruby's dotenv does this with `ENV[key] ||= value`.
        if ($this->immutable && $this->getEnvironmentVariable($name) !== null) {
            return;
        }

        // If PHP is running as an Apache module and an existing
        // Apache environment variable exists, overwrite it
        if (function_exists('apache_getenv') && function_exists('apache_setenv') && apache_getenv($name) !== false) {
            apache_setenv($name, $value);
        }

        if (function_exists('putenv')) {
            putenv("$name=$value");
        }

        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }

    /**
     * Clear an environment variable.
     *
     * This is not (currently) used by Dotenv but is provided as a utility
     * method for 3rd party code.
     *
     * This is done using:
     * - putenv,
     * - unset($_ENV, $_SERVER).
     *
     * @param string $name
     *
     * @see setEnvironmentVariable()
     *
     * @return void
     */
    public function clearEnvironmentVariable($name)
    {
        // Don't clear anything if we're immutable.
        if ($this->immutable) {
            return;
        }

        if (function_exists('putenv')) {
            putenv($name);
        }

        unset($_ENV[$name], $_SERVER[$name]);
    }
}
