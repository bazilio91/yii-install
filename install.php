#!/usr/bin/php
<?php
define('BASE_DIR', realpath(__DIR__));
$dirs = array(
	'lib' => 0750,
	'logs' => 0700,
	'www' => 0750,
	'www/assets' => 0770,
	'www/storage' => 0770,
	'protected' => 0770,
);

$modules = array(
	'lib/yii' => array(
		'url' => 'git://github.com/yiisoft/yii.git',
		'tag' => '1.1.3',
	),
	'protected/extensions/yii-localfs' => 'git@github.com:mediasite/yii-LocalFS.git',
);

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
foreach ($modules as $dir => $data) {
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
		exec('git clone --depth 5' . escapeshellarg($url) . ' ' . escapeshellarg($dir) . ' && git submodule add ' . escapeshellarg($url) . ' ' . escapeshellarg($dir), $output, $code);
		InstallHelper::processOutput($output);
		if ($code !== 0) {
			echo "Failed add submodule $url.\n";
			exit (1);
		}
	}

	// checkout specified tag
	if ($tag) {
		echo "Checkout $tag tag for $url\n";
		chdir($dir);
		exec('git fetch --tags && git checkout ' . escapeshellarg($tag), $output, $code);
		InstallHelper::processOutput($output);
		if ($code !== 0) {
			echo "Failed to checkout tag $tag for submodule $url.\n";
			exit (1);
		}
		chdir(BASE_DIR);
	}

	echo "Updating submodules recursively\n";
	exec('git submodule update --init --recursive', $output, $code);
	InstallHelper::processOutput($output);
	if ($code !== 0) {
		echo "Failed to update submodules recursively.\n";
		exit (1);
	}
}

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
}