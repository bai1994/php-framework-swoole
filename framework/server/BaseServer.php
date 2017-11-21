<?php
/**
 * Created by PhpStorm.
 * User: rxw
 * Date: 17-9-16
 * Time: 下午8:07
 */
namespace framework\server;
use framework\base\Base;
use framework\base\Container;
use framework\task\BaseTask;

abstract class BaseServer extends Base implements ServerInterface
{
    /**
     * @var null
     * 使用trait 添加triggerException 方法
     */
//    use ExceptionTrait;

    protected $_event = null;
    protected $_server;
    protected $_maxTickStep = 86400000;
    protected $_isStart = false;
    protected $_workerNum = -1;
    protected $_taskWorkerNum = -1;

    protected function init()
    {
//        防止重新启动
        if ($this->_isStart) return false;
        $this->_server->set($this->_conf);
        $this->setEvent($this->getValueFromConf('event'));
        $this->onConnect();
        $this->onWorkStart();
        $this->onWorkStop();
        $this->onTask();
        $this->onWorkerError();
        $this->onStart();
        $this->onShutDown();
        $this->onFinish();
    }

    public function setEvent($event)
    {
        if (empty($event))
        {
            return false;
        }

        $event = new $event();
        // TODO: Implement setEvent() method.
        if (!($event instanceof \framework\server\SwooleEvent))
        {
            unset($event);
            throw new \Error('swoole event have implement SwooleEvent', 500);
        }
        $this->_event = $event;
    }

    public function start()
    {
        // TODO: Implement start() method.

//        防止重新启动
        if ($this->_isStart) return false;
        $this->_isStart = true;
        $this->_server->start();
    }

    public function onConnect()
    {
        // TODO: Implement onConnect() method.
        $this->_server->on("Connect",function (\swoole_server $server, $client_id, $from_id)
        {
            try
            {
                if (!empty($this->_event)) {
                    $this->_event->onConnect($server, $client_id, $from_id);
                }
            }
            catch (\Exception $e)
            {
                $this->triggerException($e);
            }
            catch (\Error $e)
            {
                $this->triggerException($e);
            }
        });
    }

    public function onStart()
    {
        // TODO: Implement onStart() method.
        $this->_server->on("start",function (\swoole_server $server)
        {
            try
            {
                if (!empty($this->_event)) {
                    $this->_event->onStart($server);
                }
            }
            catch (\Exception $e)
            {
                $this->triggerException($e);
            }
            catch (\Error $e)
            {
                $this->triggerException($e);
            }
        });
    }

    public function onWorkStart()
    {
        // TODO: Implement onWorkStart() method.
        $this->_server->on("workerStart",function (\swoole_server $server, $workerId)
        {
            try
            {
                if (!empty($this->_event)) {
                    $this->_event->onWorkerStart($server,$workerId);
                }
            }
            catch (\Exception $e)
            {
                $this->triggerException($e);
            }
            catch (\Error $e)
            {
                $this->triggerException($e);
            }
//            开启数据库将断开的检测   8小时检测
            $this->addTimer(28800000, function ($timer_id, $params) {
                Container::getInstance()->getComponent('Pdo')->heartBeat();
            });
        });
    }

    public function onWorkStop()
    {
        // TODO: Implement onWorkStop() method.
        $this->_server->on("workerStop",function (\swoole_server $server, $workerId)
        {
            try
            {
                if (!empty($this->_event)) {
                    $this->_event->onWorkStop($server,$workerId);
                }
            }
            catch (\Exception $e)
            {
                $this->triggerException($e);
            }
            catch (\Error $e)
            {
                $this->triggerException($e);
            }
        });
    }

    public function onWorkerError()
    {
        // TODO: Implement onError() method.
        $this->_server->on("workererror",function (\swoole_server $server,$worker_id, $worker_pid, $exit_code)
        {
            Container::getInstance()->getComponent('log')->save('workerid: ' . $worker_id . '  workerpid: ' . $worker_pid . ' code: ' . $exit_code);
            if (!empty($this->_event)) {
                $this->_event->onWorkerError($server, $worker_id, $worker_pid, $exit_code);
            }
        });
    }

    public function onTask()
    {
        // TODO: Implement onTask() method.
        $num = $this->getTaskWorkerNum();
        if(!empty($num))
        {
            $this->_server->on("task",function (\swoole_server $server, $taskId, $fromId,$taskObj)
            {
                try
                {
                    if (!empty($this->_event)) {
                        $this->_event->onTask($server, $taskId, $fromId, $taskObj);
                    }
                    if (is_array($taskObj))
                    {
                        if (!empty($taskObj['class']) && !empty($taskObj['func']))
                        {
                            $obj = Container::getInstance()->getComponent($taskObj['class']);

                            if ($obj && $obj instanceof BaseTask)
                            {
                                $obj->run($taskObj['func'], $taskObj['params'], $server, $taskId, $fromId);
                                unset($obj);
                            }
                            else
                            {
                                throw new \Exception('task at do: id: ' . $taskId . ' class: ' . $taskObj['class'] . 'not found or not instance BaseTask'.
                                    ' or action: ' .$taskObj['func'] . ' not found', 500);
                            }
                        }
                    }

                    if($taskObj instanceof \Closure)
                    {
                        return $taskObj($server, $taskId, $fromId);
                    }

                    return $taskObj;
                }
                catch (\Exception $e)
                {
                    $this->triggerException($e);
                    return false;
                }
                catch (\Error $e)
                {
                    $this->triggerException($e);
                    return false;
                }
            });
        }
    }

    public function onShutdown()
    {
        // TODO: Implement onShutDown() method.
        $this->_server->on("shutdown",function (\swoole_server $server){

            try
            {
                if (!empty($this->_event)) {
                    $this->_event->onShutdown($server);
                }
            }
            catch (\Exception $e)
            {
                $this->triggerException($e);
            }
            catch (\Error $e)
            {
                $this->triggerException($e);
            }
        });
    }

    public function onFinish()
    {
        // TODO: Implement onFinish() method.
        $num = $this->getTaskWorkerNum();
        if(!empty($num))
        {
            $this->_server->on("finish", function (\swoole_server $server, $taskId, $taskObj)
            {
                try
                {
                    if (!empty($this->_event)) {
                        $this->_event->onFinish($server, $taskId, $taskId,$taskObj);
                    }
                    if (is_array($taskObj))
                    {
                        if (!empty($taskObj['class']) && !empty($taskObj['func']))
                        {
                            $obj = Container::getInstance()->getComponent($taskObj['class']);

                            if ($obj && $obj instanceof BaseTask)
                            {
                                $obj->run($taskObj['func'].'Finish', $taskObj['params'],  $server, $taskId, -1);
                                unset($obj);
                                Container::getInstance()->destroyComponentsInstance($taskObj['class']);
                            }
                            else
                            {
                                throw new \Exception('task at finish: id: ' . $taskId . ' class: ' . $taskObj['class'] . 'not found or not instance BaseTask'.
                                ' or action: ' .$taskObj['func'] . ' not found', 500);
                            }
                        }
                    }

                    return false;
                }
                catch (\Exception $e)
                {
                    $this->triggerException($e);
                    return false;
                }
                catch (\Error $e)
                {
                    $this->triggerException($e);
                    return false;
                }
            });
        }
    }

    public function addTask($data, $taskId)
    {
        $num = $this->getTaskWorkerNum();
        if ($num <= 0)
        {
            return false;
        }
        return $this->_server->task($data, $taskId);
    }

    public function addAsyncTask($data, $taskId)
    {
        $num = $this->getTaskWorkerNum();
        if ($num <= 0)
        {
            return false;
        }
        return $this->_server->taskwait($data, $taskId);
    }

    public function addTimer($timeStep, callable $callable, $params= array())
    {
        if (!is_integer($timeStep)) return false;
        if ($timeStep === 0) return false;
        if ($timeStep > $this->_maxTickStep) return false;
        return swoole_timer_tick($timeStep, $callable, $params);
    }

    public function addTimerAfter($timeStep, callable $callable, $params= array())
    {
        if (!is_integer($timeStep)) return false;
        if ($timeStep === 0) return false;
        if ($timeStep > $this->_maxTickStep) return false;
        return swoole_timer_after($timeStep, $callable, $params);
    }

    protected function triggerException ($e)
    {
        Container::getInstance()->getComponent('exception')->handleException($e);
    }

    public function getWorkerNum()
    {
        if ($this->_workerNum < 0) {
            $this->_workerNum = $this->getValueFromConf('worker_num', 0);
        }
        return $this->_workerNum;
    }

    public function getTaskWorkerNum()
    {
        if ($this->_taskWorkerNum < 0) {
            $this->_taskWorkerNum = $this->getValueFromConf('task_worker_num', 0);
        }
        return $this->_taskWorkerNum;
    }
}