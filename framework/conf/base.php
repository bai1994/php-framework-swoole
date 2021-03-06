<?php
/**
 * Created by PhpStorm.
 * User: rxw
 * Date: 17-8-26
 * Time: 下午9:33
 */
return array(
    'composer' => array(
        'Logger' => function (array $params) {
            return new \Monolog\Logger($params[0]);      //这里测试composer的加载
        },
        'meedo' => function (array $params) {
           return new \Medoo\Medoo($params);      //这里测试composer的加载
       }
    ),
    'addComponentsMap' => array(
        'msgTask' => 'blog\\conf\\Task'
    ),
    'components' => array(
        'log' => array(
            'path' => 'runtime/log/',
            'isLog' => true,
            'maxSize' => 2097152,
            'url' => 'url'
        ),
        'url' => array(
            'routerKey' => '',
            'type' => '/',
            'separator' => '/',
            'defaultSystem' => 'application',
            'defaultSystemKey' => 's',
            'controllerKey' => 'm',
            'actionKey' => 'act',
            'defaultController' => 'index',
            'defaultAction' => 'index',
            'systems' => array('application', 'application1', 'blog')
        ),
        'dispatcher' => array(
            'controller' => array(
                'prefix' => '',
                'suffix' => ''
            ),
            'action' => array(
                'prefix' => '',
                'suffix' => 'Api'
            )
        ),
        'resquest' => array(
            'separator' => '/',
            'url' => 'url'
        ),
        'response' => array(
            'defaultType' => 'text',
            'charset' => 'utf-8'
        ),
        'view' => array(
            'templatePath' => 'view',
            'cachePath' => 'runtime/viewCache',
            'compilePath' => 'runtime/compile',
            'viewExt' => '.html',
            'isCache' => false,
            'cacheExpire' => 3600,
            'leftDelimiter' => '{',
            'rightDelimiter' => '}'
        ),
        'server' => array(
            'pid_file' => '/var/www/server.pid',
            'event' => 'blog\\conf\\ServerEvent',
            'ip' => '127.0.0.1',
            'port' => '86',
            'supportHttp' => false,
            'type' => 'http',
            'factory_mode'=>2,
//             'daemonize' => 1,
            'dispatch_mode' => 2,
            'task_worker_num' => 2, //异步任务进程
            "task_max_request"=>10,
            'max_request'=>3000,
            'worker_num'=>4,
            'task_ipc_mode' => 2, 
            'message_queue_key' => '0x72000100', //指定一个消息队列key。如果需要运行多个swoole_server的实例，务必指定。否则会发生数据错乱
            'log_file' => '/tmp/swoole.log',
            'enable_static_handler' => true,
            'document_root' => '/var/www/php/easy-framework-swoole/public/assets/application/images/' //访问链接是 127.0.0.1:81/jpg文件名
        ),
        'upload' => array(
            'maxSize' => 2088960
        ),
        'captcha' => array(
            'height' => 70,
            'width' => 200,
            'num' => 5,
            'type' => 'png',   //png jpg gif,
            'response' => 'response'
        ),
        'page' => array(
            'url' => 'url'
        ),
        'model' => array(
            'db' => 'meedo'
        )
    )
);

