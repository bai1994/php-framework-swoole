<?php
/**
 * Created by PhpStorm.
 * User: rxw
 * Date: 17-8-26
 * Time: 下午8:55
 */
define('APP_ROOT', dirname(dirname(__FILE__)).'/');

include __DIR__.'/autoloader.php';

\framework\web\Application::run($argv[1] ?? 'start');
