<?php

namespace Illuminate\Foundation\Bootstrap;

use Dotenv\Dotenv;
use Dotenv\Exception\InvalidFileException;
use Dotenv\Exception\InvalidPathException;
use Symfony\Component\Console\Input\ArgvInput;
use Illuminate\Contracts\Foundation\Application;

class LoadEnvironmentVariables
{
    /**
     * Bootstrap the given application.
     *
     * @param  \Illuminate\Contracts\Foundation\Application  $app
     * @return void
     */
    public function bootstrap(Application $app)
    {
        #是否存在配置文件的缓存文件
        if ($app->configurationIsCached()) {
            return;
        }

        #根据APP_ENV设置对应环境的.env文件路径
        $this->checkForSpecificEnvironmentFile($app);

        try {
            #laravel中对.env文件的读取是采用vlucas/phpdotenv，是一个php读取配置的开源扩展包
            #vendor/vlucas/phpdotenv/src/Dotenv.php
            #可以看下源码注释src/Config/Src/Dotenv.php 从构造方法和load方法开始
            (new Dotenv($app->environmentPath(), $app->environmentFile()))->load();
        } catch (InvalidPathException $e) {
            //
        } catch (InvalidFileException $e) {
            echo 'The environment file is invalid: '.$e->getMessage();
            die(1);
        }
    }

    /**
     * Detect if a custom environment file matching the APP_ENV exists.
     * 环境变量中设置了APP_ENV变量，根据APP_ENV环境加载不同的 env 文件
     * @param  \Illuminate\Contracts\Foundation\Application  $app
     * @return void
     */
    protected function checkForSpecificEnvironmentFile($app)
    {
        #判断是否是命令行脚本模式，并且脚本带有--env参数
        if ($app->runningInConsole() && ($input = new ArgvInput)->hasParameterOption('--env')) {
            #设置--env的值对应参数的.env.xxx的路径
            if ($this->setEnvironmentFilePath(
                $app, $app->environmentFile().'.'.$input->getParameterOption('--env')
            )) {
                return;
            }
        }

        #调用php内置方法获取getenv
        if (! env('APP_ENV')) {
            return;
        }
        #设置env('APP_ENV')的值的.env.xxx的路径
        $this->setEnvironmentFilePath(
            $app, $app->environmentFile().'.'.env('APP_ENV')
        );
    }

    /**
     * Load a custom environment file.
     *
     * @param  \Illuminate\Contracts\Foundation\Application  $app
     * @param  string  $file
     * @return bool
     */
    protected function setEnvironmentFilePath($app, $file)
    {
        if (file_exists($app->environmentPath().'/'.$file)) {
            $app->loadEnvironmentFrom($file);

            return true;
        }

        return false;
    }
}
