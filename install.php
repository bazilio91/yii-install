#!/usr/bin/php
<?php
define('BASE_DIR', realpath(__DIR__));

/**
 * List of directories to create
 */
$dirs = array(
	'lib' => 0750,
	'logs' => 0700,
	'www' => 0750,
	'www/assets' => 0770,
	'www/storage' => 0770,
	'protected' => 0770,
);
/**
 * List of git submodules
 */
$gitModules = array(
	'lib/yii' => array(
		'url' => 'git://github.com/yiisoft/yii.git',
		'tag' => '1.1.3',
	),
	'protected/extensions/yii-localfs' => 'git@github.com:mediasite/yii-LocalFS.git',
);
/**
 * DB
 */
$db = array(
	'db' => array(
		'class' => 'CDbConnection',
		'connectionString' => 'mysql:host=127.0.0.1;dbname=decor',
		'emulatePrepare' => true,
		'username' => 'root',
		'password' => '31415',
		'charset' => 'utf8',
		'schemaCachingDuration' => 0,
		'enableProfiling' => true,
		'enableParamLogging' => true,
		'tablePrefix' => '',
	),
);
/**
 * Params
 */
$params = array(
	'name' => 'Your app name',
	'id' => 'app-id',
	'debug' => true,
	'domain' => 'localhost'
);
/**
 * App main config
 */
$config = array(
	'params' => '{{$params}}',
	'id' => '{{$params["id"]}}',
	'name' => '{{$params["name"]}}',
	'basePath' => realpath(__DIR__ . '/protected'),
	// preloading 'log' component
	'preload' => array('log'),
	// autoloading model and component classes
	'language' => 'ru',
	'import' => array(
		'application.models.*',
		'application.components.*',
		'application.behaviors.*',
	),
	'controllerMap' => array(
		'file' => array(
			'class' => 'ext.yii-localfs.UploadController',
		),
	),
	'modules' => '{{require(__DIR__."/modules.php")}}',
	'components' => array(
		'authManager' => array(
			'class' => 'CDbAuthManager',
			'connectionID' => 'db',
		),
		'user' => array(
			// enable cookie-based authentication
			'allowAutoLogin' => true,
			'loginUrl' => array('/account/login'),
		),
		'urlManager' => array(
			"urlFormat" => "path",
			"urlSuffix" => "/",
			"showScriptName" => false,
			"useStrictParsing" => false,
			'rules' => '{{include("routes.php")}}',
		),
		'db' => '{{include("db.php")}}',
		'errorHandler' => array(
			'errorAction' => 'site/error',
		),
		'fs' => array(
			'class' => 'ext.yii-localfs.LocalFS',
			'nestedFolders' => 5,
		),
		'cache' => array(
			'class' => 'CMemCache',
			'useMemcached' => true,
			'servers' => array(
				array(
					'host' => '127.0.0.1',
					'port' => 11211,
				),
			),
		),
	),
);

/**
 * Script start
 */
foreach ($dirs as $dir => $mode) {
	$dir = __DIR__ . '/' . $dir;
	if (!is_dir($dir)) {
		if (!mkdir($dir, $mode)) {
			echo "Failed to create dir $dir\n";
			exit(0);
		}
	}
}

if (!file_exists(__DIR__ . '/.git/config')) {
	echo "No repo found, initializing new git repo...\n";
	exec('git init', $output, $code);
	InstallHelper::processOutput($output);
	if ($code !== 0) {
		echo "Failed to initialize root git repo.\n";
		exit (1);
	}
}

$gitConfig = file_get_contents(__DIR__ . '/.git/config');
foreach ($gitModules as $dir => $data) {
	$url = null;
	$tag = null;

	if (is_string($data)) {
		$url = $data;
	} elseif (is_array($data) && !empty($data['url'])) {
		$url = $data['url'];
		if (isset($data['tag'])) {
			$tag = $data['tag'];
		}
	} else {
		echo "Failed to parse submodule info:\n";
		var_dump($data);
		echo "\n";
		exit (1);
	}

	if (strpos($gitConfig, $url) === false) {
		echo "Adding new submodule $url to $dir\n";
		exec('git clone --depth 5 ' . escapeshellarg($url) . ' ' . escapeshellarg($dir) . ' && git submodule add ' . escapeshellarg($url) . ' ' . escapeshellarg($dir), $output, $code);
		if ($code !== 0) {
			InstallHelper::processOutput($output);
			echo "Failed add submodule $url.\n";
			exit (1);
		}
	}

	// checkout specified tag
	if ($tag) {
		echo "Checkout $tag tag for $url\n";
		chdir($dir);
		exec('git fetch --tags && git checkout ' . escapeshellarg($tag), $output, $code);
		if ($code !== 0) {
			InstallHelper::processOutput($output);
			echo "Failed to checkout tag $tag for submodule $url.\n";
			exit (1);
		}
		chdir(BASE_DIR);
	}
}

echo "Updating submodules recursively\n";
exec('git submodule update --init --recursive', $output, $code);
if ($code !== 0) {
	InstallHelper::processOutput($output);
	echo "Failed to update submodules recursively.\n";
	exit (1);
}

if (!is_file('www/index.php')) {
	echo "Creating yii webapp\n";
	exec('echo "yes" | ./lib/yii/framework/yiic webapp ' . __DIR__ . '/www git', $output, $code);
	if ($code !== 0) {
		InstallHelper::processOutput($output);
		echo "Failed to create yii webapp.\n";
		exit (1);
	}
	exec('cp -af www/protected/* protected && rm www/protected -rf', $output, $code);
	if ($code !== 0) {
		InstallHelper::processOutput($output);
		echo "Failed to move protected folder.\n";
		exit (1);
	}

	exec('mv www/themes themes', $output, $code);
	if ($code !== 0) {
		InstallHelper::processOutput($output);
		echo "Failed to move themes folder.\n";
		exit (1);
	}
}


file_put_contents('protected/config/.gitignore', "params.php\ndb.php\n");
$configText = var_export($config, true);
$configText = "<?php\n\$params = include 'params.php';\nreturn " . str_replace(array('\'{{', '}}\''), '', $configText) . ';';

InstallHelper::createConfig('main', $config, "\$params = include 'params.php';\n");
InstallHelper::createConfig('console', $config, "\$params = include 'params.php';\n");
InstallHelper::createConfig('params', $params);
InstallHelper::createConfig('db', $db);
InstallHelper::createConfig('modules');

class InstallHelper
{
	public static function processOutput($output)
	{
		if (is_array($output)) {
			foreach ($output as $string) {
				echo "$string\n";
			}
			return;
		}
		echo (string)$output . "\n";
	}

	public static function createConfig($name, $config = array(), $pre = '')
	{
		$configText = var_export($config, true);
		$configText = "<?php\n" . $pre . "return " . str_replace(array('\'{{', '}}\''), '', $configText) . ';';
		file_put_contents("protected/config/$name.php", $configText);
	}
}