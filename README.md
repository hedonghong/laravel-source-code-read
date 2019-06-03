#说明
  不定期更新源码解读用于交流学习，源码解析中部分解析来源于网络和自己的见解，欢迎拍砖
  
  为什么学习laravel？
  
  >PHP是一个学习成本非常低的语言，可以过程也可以面向对象，当然了越是没有“方圆”限制的语言越是容易乱，但如果是一个大型的web应用
  ，如果越是写的随意越难维护和扩展，往往公司从小型成长到大型，会经历重构的痛楚。好吧，有很多人想，到重构的时候又不知道是谁的锅咯！
  一个好的web应用应该从头到尾有一套编码规范和一个可支持拔插式的框架，有人说laravel很膨大，ORM写性能很低等等，但是不要忘记她的优雅的地方：高内聚、低耦合、模块化可拔插；
  正是因为有了这些特性，我们可以在重构的时候加入不同的模块解决不同的业务问题。当然了代码会越来越多不可否认！
  
  >所以即使你不用laravel框架也可以学习她框架中的编程思想或者其中一个模块，用于自己的系统也许会起到令人眼前一亮的效果！
  
  没有最好的语言，只有合适的语言 -- 鲁迅(我证明他没有说过)
  
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
8. Event-事件的实现

#推荐读物

1、《面向对象开发参考手册》-黄磊

2、PHP设计模式-姜海强（百度即可，作者在CSDN有博文）
