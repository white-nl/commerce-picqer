<?php


namespace white\commerce\picqer\services;


use craft\base\Component;
use yii\log\Logger;

class Log extends Component
{
    public $systemLoggerCategory = 'commerce-picqer';
    public $systemLogger;

    public function log($message, $level = Logger::LEVEL_INFO)
    {
        $this->getSystemLogger()->log($message, $level, $this->systemLoggerCategory);
    }

    public function error($message, \Exception $exception = null)
    {
        if ($exception !== null) {
            $message .= ' [' . get_class($exception) . '] ' . $exception->getMessage() . ' in ' . $exception->getFile() . ':' . $exception->getLine();
        }
        
        $this->log($message, Logger::LEVEL_ERROR);
    }

    public function warning($message)
    {
        $this->log($message, Logger::LEVEL_WARNING);
    }

    public function trace($message)
    {
        $this->log($message, Logger::LEVEL_TRACE);
    }

    /**
     * @return \yii\log\Logger
     */
    protected function getSystemLogger()
    {
        if ($this->systemLogger === null) {
            $this->systemLogger = \Craft::getLogger();
        }

        return $this->systemLogger;
    }
}
