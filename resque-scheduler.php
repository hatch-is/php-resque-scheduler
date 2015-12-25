<?php

// Find and initialize Composer
$files = array(
    __DIR__ . '/../../vendor/autoload.php',
    __DIR__ . '/../../../autoload.php',
    __DIR__ . '/../../../../autoload.php',
    __DIR__ . '/../vendor/autoload.php',
);

foreach ($files as $file) {
    if (file_exists($file)) {
        require_once $file;
        break;
    }
}

// Load the user's application if one exists
$APP_INCLUDE = getenv('APP_INCLUDE');
if($APP_INCLUDE) {
	if(!file_exists($APP_INCLUDE)) {
		die('APP_INCLUDE ('.$APP_INCLUDE.") does not exist.\n");
	}

	require_once $APP_INCLUDE;
}

// Look for an environment resque class
$RESQUE_CLASS = getenv('RESQUE_CLASS');
if(!empty($RESQUE_CLASS)) {
	/** @var Resque $resqueClass */
	$resqueClass = $RESQUE_CLASS;
} else {
	// Look for an environment variable with
	$RESQUE_PHP = getenv('RESQUE_PHP');
	if (!empty($RESQUE_PHP)) {
		require_once $RESQUE_PHP;
	}
	// Otherwise, if we have no Resque then assume it is in the include path
	else if (!class_exists('Resque')) {
		throw new \Exception("There is no Resque class to work with");
	}

	/** @var Resque $resqueClass */
	$resqueClass = 'Resque';
}

// Load resque-scheduler
require_once dirname(__FILE__) . '/lib/ResqueScheduler.php';
require_once dirname(__FILE__) . '/lib/ResqueScheduler/Worker.php';

$REDIS_BACKEND = getenv('REDIS_BACKEND');
$REDIS_BACKEND_DB = getenv('REDIS_BACKEND_DB');
if(!empty($REDIS_BACKEND)) {
	if (empty($REDIS_BACKEND_DB))
		$resqueClass::setBackend($REDIS_BACKEND);
	else
		$resqueClass::setBackend($REDIS_BACKEND, $REDIS_BACKEND_DB);
}

// Set log level for resque-scheduler
$logLevel = 0;
$LOGGING = getenv('LOGGING');
$VERBOSE = getenv('VERBOSE');
$VVERBOSE = getenv('VVERBOSE');
if(!empty($LOGGING) || !empty($VERBOSE)) {
	$logLevel = ResqueScheduler_Worker::LOG_NORMAL;
}
else if(!empty($VVERBOSE)) {
	$logLevel = ResqueScheduler_Worker::LOG_VERBOSE;
}

// Check for jobs every $interval seconds
$interval = 5;
$INTERVAL = getenv('INTERVAL');
if(!empty($INTERVAL)) {
	$interval = $INTERVAL;
}

$PREFIX = getenv('PREFIX');
if(!empty($PREFIX)) {
    fwrite(STDOUT, '*** Prefix set to '.$PREFIX."\n");
    Resque_Redis::prefix($PREFIX);
}

$worker = new ResqueScheduler_Worker($resqueClass);
$worker->logLevel = $logLevel;

$PIDFILE = getenv('PIDFILE');
if ($PIDFILE) {
	file_put_contents($PIDFILE, getmypid()) or
		die('Could not write PID information to ' . $PIDFILE);
}

fwrite(STDOUT, "*** Starting scheduler worker\n");
$worker->work($interval);
