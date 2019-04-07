<?php

namespace Illuminate\Foundation\Bootstrap;

use Exception;
use ErrorException;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Foundation\Application;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Debug\Exception\FatalErrorException;
use Symfony\Component\Debug\Exception\FatalThrowableError;

class HandleExceptions
{
    /**
     * The application instance.
     *
     * @var \Illuminate\Contracts\Foundation\Application
     */
    protected $app;

    /**
     * Bootstrap the given application.
     *
     * @param  \Illuminate\Contracts\Foundation\Application  $app
     * @return void
     */
    public function bootstrap(Application $app)
    {
        $this->app = $app;

        #表示显示所有PHP错误报告，包括将来PHP加入的新的错误级别。 至PHP5.4，E_ALL有同样的行为
        #0表示关闭所有PHP错误报告;7表示显示 E_ERROR(1) | E_WARING(2) | E_PARSE(4) == (1+2+4)
        error_reporting(-1);
        #注册错误处理函数handleError
        set_error_handler([$this, 'handleError']);
        #注册异常处理函数handleException
        set_exception_handler([$this, 'handleException']);
        #注册一个会在php中止时执行的函数
        #函数可实现当程序执行完成后执行的函数，其功能为可实现程序执行完成的后续操作。程序在运行的时候可能存在执行超时，或强制关闭等情况，
        #但这种情况下默认的提示是非常不友好的，如果使用register_shutdown_function()函数捕获异常，就能提供更加友好的错误展示方式，
        #同时可以实现一些功能的后续操作，如执行完成后的临时数据清理，包括临时文件等
        register_shutdown_function([$this, 'handleShutdown']);
        #对于不致命的错误，例如notice级别的错误，handleError即可截取，laravel将错误转化为了异常，交给了handleException去处理。
        #对于致命错误，例如 E_PARSE解析错误，handleShutdown将会启动，并且判断当前代码运行结束是否是由于致命错误，如果是致命错误，
        #将会将其转化为FatalErrorException, 交给了 handleException 作为异常去处理

        if (! $app->environment('testing')) {
            #不是testing环境的时候关闭错误显示
            ini_set('display_errors', 'Off');
        }
    }

    /**
     * Convert PHP errors to ErrorException instances.
     *
     * @param  int  $level
     * @param  string  $message
     * @param  string  $file
     * @param  int  $line
     * @param  array  $context
     * @return void
     * 错误处理方法，错误转换成异常，直接返回错误异常ErrorException
     * @throws \ErrorException
     */
    public function handleError($level, $message, $file = '', $line = 0, $context = [])
    {
        if (error_reporting() & $level) {
            throw new ErrorException($message, 0, $level, $file, $line);
        }
    }

    /**
     * Handle an uncaught exception from the application.
     *
     * Note: Most exceptions can be handled via the try / catch block in
     * the HTTP and Console kernels. But, fatal error exceptions must
     * be handled differently since they are not normal exceptions.
     * 异常处理函数
     * @param  \Throwable  $e
     * @return void
     */
    public function handleException($e)
    {
        #是否是异常，不是转为FatalThrowableError，FatalThrowableError的类是vendor/symfony/debug/Exception/FatalThrowableError.php
        #追踪到最后是继承自\ErrorException的
        if (! $e instanceof Exception) {
            $e = new FatalThrowableError($e);
        }

        try {
            #获取容器中异常处理类，report方法会判断是否要做日志记录，如果要调用日志处理类，记录异常日志信息
            #$this->getExceptionHandler()获取到的异常处理类为vendor/laravel/framework/src/Illuminate/Foundation/Exceptions/Handler.php
            #为什么？在getExceptionHandler()方法中有注解
            $this->getExceptionHandler()->report($e);
        } catch (Exception $e) {
            //
        }
        #记录错误后将异常转化为页面向开发者展示异常的信息，提示给开发者
        #异常返回分为CLI模式和web模式的，根据不同的模式，返回对于格式化后错误内容
        if ($this->app->runningInConsole()) {
            $this->renderForConsole($e);
        } else {
            #如果是web的请求：会根据其错误的状态码，选取不同的错误页面模板，若不存在相关的模板，则会通过 SymfonyResponse 来构造异常展示页面
            #这里将不做深入，代码也不复杂，有兴趣的可以追踪看看
            $this->renderHttpResponse($e);
        }
    }

    /**
     * Render an exception to the console.
     *
     * @param  \Exception  $e
     * @return void
     */
    protected function renderForConsole(Exception $e)
    {
        $this->getExceptionHandler()->renderForConsole(new ConsoleOutput, $e);
    }

    /**
     * Render an exception as an HTTP response and send it.
     *
     * @param  \Exception  $e
     * @return void
     */
    protected function renderHttpResponse(Exception $e)
    {
        $this->getExceptionHandler()->render($this->app['request'], $e)->send();
    }

    /**
     * Handle the PHP shutdown event.
     *
     * @return void
     */
    public function handleShutdown()
    {
        if (! is_null($error = error_get_last()) && $this->isFatal($error['type'])) {
            $this->handleException($this->fatalExceptionFromError($error, 0));
        }
    }

    /**
     * Create a new fatal exception instance from an error array.
     *
     * @param  array  $error
     * @param  int|null  $traceOffset
     * @return \Symfony\Component\Debug\Exception\FatalErrorException
     */
    protected function fatalExceptionFromError(array $error, $traceOffset = null)
    {
        return new FatalErrorException(
            $error['message'], $error['type'], 0, $error['file'], $error['line'], $traceOffset
        );
    }

    /**
     * Determine if the error type is fatal.
     *
     * @param  int  $type
     * @return bool
     */
    protected function isFatal($type)
    {
        return in_array($type, [E_COMPILE_ERROR, E_CORE_ERROR, E_ERROR, E_PARSE]);
    }

    /**
     * Get an instance of the exception handler.
     *
     * @return \Illuminate\Contracts\Debug\ExceptionHandler
     */
    protected function getExceptionHandler()
    {
        #该异常处理类在框架中在/bootstrap/app.php中有注册到容器中
        /*
         $app->singleton(
            Illuminate\Contracts\Debug\ExceptionHandler::class,
            App\Exceptions\Handler::class
        );
        其中App\Exceptions\Handler::class是来自app/Exceptions/Handler.php类，该类继承于Illuminate\Foundation\Exceptions\Handler
        */
        return $this->app->make(ExceptionHandler::class);
    }
}
