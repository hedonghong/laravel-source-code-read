# 阅读须知
> 后面源码有用到很多hash作为类名或者数组键明，在我的理解中有如下作用：
```php
1、防止重复命名
2、这些类或者键值不用复用或不想用户使用
```
部分解析记录在Src目录的源码中，这些源码带有注释，并且有文档readme.md没有细及的地方，可以结合起来阅读
#1、composer自动加载
>在介绍laravel的自动加载前，看看composer的自动加载，因为laravel的自动加载就是建立在composer的自动加载只上的

对应文件夹为/vendor/composer/*.php
```php
autoload_real.php: 自动加载功能的引导类。
    任务是composer加载类的初始化(顶级命名空间与文件路径映射初始化)和注册(spl_autoload_register())。
ClassLoader.php: composer加载类。
    composer自动加载功能的核心类。
autoload_static.php: 顶级命名空间初始化类，
    用于给核心类初始化顶级命名空间。
autoload_classmap.php: 自动加载的最简单形式，
    有完整的命名空间和文件目录的映射；
autoload_files.php: 用于加载全局函数的文件，
    存放各个全局函数所在的文件路径名；
autoload_namespaces.php: 符合PSR0标准的自动加载文件，
    存放着顶级命名空间与文件的映射；
autoload_psr4.php: 符合PSR4标准的自动加载文件，
    存放着顶级命名空间与文件的映射；
```

#2、laravel框架下Composer的自动加载源码
## 2.1、laravel框架入口架设
```php
laravel-
    |-public
    |   |-index.php #web端入口文件
    |-bootstrap
    |   |-app.php
    |-server.php #用于测试简单地搭建web服务入口，提供类似重写，你不开启apache和nginx
```
> 本次教程主要集中在public/index.php,下面看下public/index.php源码情况
```php
define('LARAVEL_START', microtime(true));
#注册自动加载相关（集中在这里）
require __DIR__.'/../vendor/autoload.php';
#初始化laravel框架
$app = require_once __DIR__.'/../bootstrap/app.php';
#运行laravel，接收请求和返回
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);
$response->send();
$kernel->terminate($request, $response);
```
> 下面看下vendor/autoload.php的源码情况，对应本次主要介绍源码解析请看
src/Autoload/Src/autoload_real.php、src/Autoload/Src/autoload_static.php
```php
require_once __DIR__ . '/composer' . '/autoload_real.php';
return ComposerAutoloaderInitf98e46180a57e99b3e258e69ac1b8f28::getLoader();
```
# 3、如何加载类
> 对应源码为带注释源码为src/Autoload/Src/ClassLoader.php，根据上面的准备和了解，我们知道在autoload_real.php中有如下代码：
```php
        #注册自动加载核心类对象，实际就是调用spl_autoload_register函数
        $loader->register(true);

        #自动加载全局函数
        #全局函数自动加载也分为两种：静态初始化和普通初始化，静态加载只支持PHP5.6以上并且不支持HHVM。
        #vendor/composer/autoload_static.php的$files数组部分，认真看下就知道和autoload_files.php的一样
        if ($useStaticLoader) {
            $includeFiles = Composer\Autoload\ComposerStaticInitf98e46180a57e99b3e258e69ac1b8f28::$files;
        } else {
            $includeFiles = require __DIR__ . '/autoload_files.php';
        }
        #获取到全局函数文件数组后，循环一一加载
        foreach ($includeFiles as $fileIdentifier => $file) {
            #composerRequiref98e46180a57e99b3e258e69ac1b8f28这个代码就不说了，就是一个require
            composerRequiref98e46180a57e99b3e258e69ac1b8f28($fileIdentifier, $file);
        }
```
> 好吧，在这个$loader->register(true);源码为:
```php
    /**
     * Registers this instance as an autoloader.
     * 注册自动加载核心类对象
     * @param bool $prepend Whether to prepend the autoloader or not
     */
    public function register($prepend = false)
    {
        spl_autoload_register(array($this, 'loadClass'), true, $prepend);
    }
```

> 天呀，是不是很简单~认识PHP或者认真看过之前上面述说的人都知道spl_autoload_register这个函数的作用，更知道上面方法是在php在遇到未加载的外部文件时会通过"loadClass"进行尝试加载进来！
整个后面的生命周期中PHP会自动调用注册到spl_autoload_register里面的函数堆栈，运行其中的每个函数，直到找到命名空间对应的文件路径，并且加载

> 除了上面加载外部文件外，PHP还会加载全局函数，主要对应的文件在autoload_files.php，里面全部是一些文件路径，通过后面的循环一一加载

## 3.1、loadClass注释

```php
    #具体代码解析请到src/Autoload/Src/ClassLoader.php查看
    public function loadClass($class)
    {
        if ($file = $this->findFile($class)) {
            includeFile($file);

            return true;
        }
    }

```