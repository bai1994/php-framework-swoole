<?php
/**
 * Created by PhpStorm.
 * User: rxw
 * Date: 17-9-16
 * Time: 下午8:54
 */
namespace framework\server;
use framework\base\Container;

class WebSocketServer extends BaseServer
{
    protected function init()
    {
        $this->_server = new  \swoole_websocket_server($this->_appConf['ip'], $this->_appConf['port']);
        parent::init(); // TODO: Change the autogenerated stub
        $this->onHandShake();
        $this->onOpen();
        if ($this->getValueFromConf('supportHttp', false)) {
            $this->onRequest();
        }
        $this->onMessage();
        $this->onCLose();
    }

    protected function onOpen()
    {
        $this->_server->on('open', function (\swoole_websocket_server $server, $request)
        {
            if (empty($this->_event)) return false;
            try
            {
                $this->_event->onOpen($server, $request);
            }
            catch (\Exception $e)
            {
                Container::getInstance()->getComponent('exception')->handleException($e);
            }
            catch (\Error $e)
            {
                Container::getInstance()->getComponent('exception')->handleException($e);
            }
        });
    }

    protected function onHandShake()
    {
        $this->_server->on('handshake', function (\swoole_http_request $request, \swoole_http_response $response)
        {
            if (!isset($request->header['sec-websocket-key']))
            {
                //'Bad protocol implementation: it is not RFC6455.'
                $response->end();
                return false;
            }
            if (0 === preg_match('#^[+/0-9A-Za-z]{21}[AQgw]==$#', $request->header['sec-websocket-key'])
                || 16 !== strlen(base64_decode($request->header['sec-websocket-key']))
            )
            {
                //Header Sec-WebSocket-Key is illegal;
                $response->end();
                return false;
            }
            $key = base64_encode(sha1($request->header['sec-websocket-key']
                . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11',
                true));
            $headers = array(
                'Upgrade'               => 'websocket',
                'Connection'            => 'Upgrade',
                'Sec-WebSocket-Accept'  => $key,
                'Sec-WebSocket-Version' => '13',
                'KeepAlive'             => 'off',
            );
            foreach ($headers as $key => $val)
            {
                $response->header($key, $val);
            }
            $response->status(101);
            $response->end();
            return true;
        });
    }

    protected function onRequest()
    {
        $this->_server->on("request", function (\swoole_http_request $request,\swoole_http_response $response)
        {
            if (!empty($this->_event))
            {
                $this->_event->onRequest($request,$response);
            }
            $container = Container::getInstance();
            if (!empty($request->get)) {
                $_GET = $request->get;
            }
            if (!empty($request->post)) {
                $_POST = $request->post;
            }
            if (!empty($request->files)) {
                $_FILES = $request->files;
//                $container->getComponent('upload')->save('file'); 上传文件测试
            }

            $hasEnd = false;
            try
            {
                $request->server['host'] = $request->header['host'];
                $urlInfo = $container->getComponent('url')->run($request->server);
                if ($urlInfo !== false) {
                    $result = $container->getComponent('dispatcher')->run($urlInfo);
                    $hasEnd = $container->getComponent('response')->send($response, $result);
                }
                if (!empty($this->_event))
                {
                    $this->_event->onResponse($request,$response);
                }
                $container->finish();
            }
            catch (\Exception $exception)
            {
                $response->status(404);
                $response->write($exception->getMessage());
                $container->getComponent('exception')->handleException($exception);
            }
            catch (\Error $e)
            {
                $response->status(404);
                $response->write($e->getMessage());
                $container->getComponent('exception')->handleException($e);
            }
            if (!$hasEnd)
            {
                $response->end();
            }

            $_GET = null;
            $_POST = null;
            $_FILES = null;
            unset($container,$request,$response);
        });
    }

    protected function onMessage()
    {
        $this->_server->on('message', function (\swoole_websocket_server $server, $frame)
        {
//            目前不支持过大消息和二进制数据
            if (!$frame->finish || $frame->opcode === 2) {
                $server->push($frame->fd, '');
                return false;
            }

            $frame->data = json_decode($frame->data, true);
            if (empty($frame->data['controller']) || empty($frame->data['action'])) {
                $server->push($frame->fd, 'bad request');
                return false;
            }

            if (!empty($this->_event))
            {
                $this->_event->onMessage($server, $frame);
            }

            $container = Container::getInstance();

            try
            {
                if (!empty($frame->data['data'])) {
                    $_GET = $frame->data['data'];
                }
                $result = $container->getComponent('dispatcher')->run(array(
                    'controller' => $frame->data['controller'],
                    'action' => $frame->data['action']
                ));

                if (is_array($result)) {
                    $result = json_encode($result);
                }
                $server->push($frame->fd, $result);
                unset($result);
            }
            catch (\Exception $exception)
            {
                $server->push($frame->fd, $exception->getMessage());
                $container->getComponent('exception')->handleException($exception);
            }
            catch (\Error $e)
            {
                $server->push($frame->fd, $e->getMessage());
                $container->getComponent('exception')->handleException($e);
            }
            unset($container);
            return false;
        });
    }

    protected function onCLose()
    {
        $this->_server->on('close', function (\swoole_websocket_server $server, $fd) {
            if (empty($this->_event)) return false;
            try
            {
                $this->_event->onClose($server, $fd);
            }
            catch (\Exception $e)
            {
                Container::getInstance()->getComponent('exception')->handleException($e);
            }
            catch (\Error $e)
            {
                Container::getInstance()->getComponent('exception')->handleException($e);
            }
        });
    }
}