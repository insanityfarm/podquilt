<?php

namespace Podquilt;

class Error
{

	public function __construct(\Podquilt\App $app)
	{
		$this->app = $app;
		$app->error = $this;
		return $this;
	}

	public function show($exception)
    {
        echo '<pre>' . $exception . '</pre>';
		$this->app->log->write($exception, Log::LOG_LEVEL_ERROR);
        die;
    }

}