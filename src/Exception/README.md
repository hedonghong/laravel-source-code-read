#laravel的异常与错误处理
>代码的世界中有很多异常和错误，异常和错误并不可怕，可怕是我们不知道在哪里错了，一个nb的框架会很好的暴露错误给开发人员，提供给开发者一个溯源错误的提示！下面看看laravel是如何做的？

##1、PHP异常处理

### 1.1、PHP中提供的处理异常错误方法
```php

#try, throw 和 catch

try {
    throw new Exception('code', 'message');

} catch(Exception $e) {

} finally {

}


```

### 1.2、异常配置
```php
display_errors = Off
error_reporting = E_ALL & ~E_NOTICE
```
### 1.3、错误异常处理注册函数
```php
set_error_handler();
set_exception_handler();
register_shutdown_function();
```

### 1.4、php7的错误异常处理

```
PHP7改变了大多数错误的报告方式。不同于PHP5的传统错误报告机制，现在大多数错误被作为 Error 异常抛出。
这种 Error 异常可以像普通异常一样被 try / catch 块所捕获。如果没有匹配的 try / catch 块， 则调用异常处理函数（由 set_exception_handler() 注册）进行处理。 
如果尚未注册异常处理函数，则按照传统方式处理：被报告为一个致命错误（Fatal Error）。
Error 类并不是从 Exception 类 扩展出来的，所以用 catch (Exception $e) { ... } 这样的代码是捕获不 到 Error 的。
可以用 catch (Error $e) { ... } 这样的代码，或者通过注册异常处理函数（ set_exception_handler()）来捕获 Error。
```
>PHP7错误异常整体架构如下：

- throwable
   - Error
      - ArithmeticError
      - AssertionError
      - DivisionByZeroError
      - ParseError
      - TypeError
   - Exceptio
      - LogicException
      - RunTimeException
      - ...

>在php7之前的我们只能捕捉到代码中出现的逻辑异常,php7之后大多数fatal错误都可以被捕抓到
>在php7里，无论是Exception还是Error，都实现了一个共同的接口Throwable。因此，遇到非Exception类型的异常，首先就要将其转化为FatalThrowableError类型

##laravel的异常错误处理
>由类 \Illuminate\Foundation\Bootstrap\HandleExceptions::class 完成，详细注释看src/Exception/Src/HandleExceptions.php，主要代码入口如下：
>laravel中分别注册了handleError、handleException、handleShutdown三个类处理方法，并且会将错误转异常处理，记录异常日志、呈现给开发者
```php
public function bootstrap(Application $app)
{
    $this->app = $app;

    #表示显示所有PHP错误报告，包括将来PHP加入的新的错误级别。 至PHP5.4，E_ALL有同样的行为
    error_reporting(-1);
    #注册错误处理函数handleError
    set_error_handler([$this, 'handleError']);
    #注册异常处理函数handleException
    set_exception_handler([$this, 'handleException']);
    #注册一个会在php中止时执行的函数
    register_shutdown_function([$this, 'handleShutdown']);

    if (! $app->environment('testing')) {
        ini_set('display_errors', 'Off');
    }
}
```
