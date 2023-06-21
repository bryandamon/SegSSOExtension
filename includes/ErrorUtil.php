<?php

function processException($message = '', Exception $e)
{
	ob_start();
	var_dump($e->getTraceAsString());
	$stackTrace = ob_get_contents();
	ob_end_clean();

	$logMsg = $message . "\nException: " . $e->getMessage() . "\n" . $stackTrace;

	error_log($logMsg);
}
