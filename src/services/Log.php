<?php


namespace white\commerce\picqer\services;

use craft\base\Component;
use yii\log\Logger;

class Log extends Component
{
    public string $systemLoggerCategory = 'commerce-picqer';
    public Logger $systemLogger;

    /**
     * @param string $message
     * @param int|null $level
     * @return void
     */
    public function log(string $message, ?int $level = Logger::LEVEL_INFO): void
    {
        $this->getSystemLogger()->log($message, $level, $this->systemLoggerCategory);
    }

    /**
     * @param string $message
     * @param \Exception|null $exception
     * @return void
     */
    public function error(string $message, \Exception $exception = null): void
    {
        if ($exception !== null) {
            $message .= ' [' . $exception::class . '] ' . $exception->getMessage() . ' in ' . $exception->getFile() . ':' . $exception->getLine();
        }
        
        $this->log($message, Logger::LEVEL_ERROR);
    }

    /**
     * @param string $message
     * @return void
     */
    public function warning(string $message): void
    {
        $this->log($message, Logger::LEVEL_WARNING);
    }

    /**
     * @param string $message
     * @return void
     */
    public function trace(string $message): void
    {
        $this->log($message, Logger::LEVEL_TRACE);
    }

    /**
     * @return Logger
     */
    protected function getSystemLogger(): Logger
    {
        if (!isset($this->systemLogger)) {
            $this->systemLogger = \Craft::getLogger();
        }

        return $this->systemLogger;
    }
}
