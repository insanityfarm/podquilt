<?php

namespace Podquilt;

class Log
{

	const LOG_LEVEL_ERROR   = 1;
	const LOG_LEVEL_WARN    = 2;
	const LOG_LEVEL_NOTICE  = 3;
	const LOG_LEVEL_INFO    = 4;

    public function __construct(\Podquilt\App $app)
    {
    	$this->app = $app;
		$app->log = $this;
        return $this;
    }

    public function write($message, $logLevel = self::LOG_LEVEL_INFO)
    {
    	$success = false;
	    if($this->app->config->logs->enabled === true)
	    {
	    	if($this->app->config->logs->level >= $logLevel)
		    {

		    	// TODO: Add some cleanup for old logs

		    	$message = date(DATE_ATOM) . ": " . strtoupper($this->getLevelLabel($logLevel)) . ': ' . $message . "\n";
			    $handle = fopen($this->app->config->logs->path, 'a');
			    $success = !!fwrite($handle, $message);
			    fclose($handle);
		    }
	    }
	    return $success;
    }

    public function getLevelLabel($logLevel)
	{
		$logLevels = [
			self::LOG_LEVEL_ERROR   => 'error',
			self::LOG_LEVEL_WARN    => 'warning',
			self::LOG_LEVEL_NOTICE  => 'notice',
			self::LOG_LEVEL_INFO    => 'info'
		];
		return array_key_exists($logLevel, $logLevels) ? $logLevels[$logLevel] : 'unknown';
	}

}