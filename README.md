#说明
  不定期更新源码解读用于交流学习，源码解析中部分解析来源于网络和自己的见解，欢迎拍砖
  
#源码阅读流程

>从laravel框架的启动开启，注册自动加载到容器到环境变量加载等..

```php
#一切从public/index.php开启

define('LARAVEL_START', microtime(true));
#自动加载
require __DIR__.'/../vendor/autoload.php';
#启动容器
$app = require_once __DIR__.'/../bootstrap/app.php';
#框架核心处理
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);
$response->send();
$kernel->terminate($request, $response);
```
>其中在容器启动过程中会初始化框架的环境变量和配置文件等相关，相关代码如下，我们的源码阅读也是按照这样子的顺序:

```php
#环境变量加载
\Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables::class,
#配置加载
\Illuminate\Foundation\Bootstrap\LoadConfiguration::class,
#自带和自定义异常处理加载
\Illuminate\Foundation\Bootstrap\HandleExceptions::class,
#自带和自定义门面加载
\Illuminate\Foundation\Bootstrap\RegisterFacades::class,
#自带和自定义服务提供者加载
\Illuminate\Foundation\Bootstrap\RegisterProviders::class,
#初始化服务提供者们的
\Illuminate\Foundation\Bootstrap\BootProviders::class,

```

#阅读顺序

---

1. Composer-composer自动加载原理1
2. Autoload-composer自动加载原理2
3. Container-IOC容器
4. Config-环境变量和配置文件的加载
5. Facade-laravel门面的实现
6. Exception-laraval异常处理
7. provider-服务提供者
