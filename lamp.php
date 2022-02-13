<?php
/** PHP 8.0	**/

$_SERVER['DOCUMENT_ROOT'] = __DIR__;
const SERVER_MAIN_PROXY = 'proxy';

global $wsl;
$wsl = '';

$isWin = shell_exec("uname -s 2>&1");

if (!stristr($isWin, 'Linux') || stristr($isWin, 'cmdlet') || stristr($isWin, '"uname"'))
{
	$wsl = 'wsl ';
}

$command = $argv[1] ?? '';
$server = $argv[2] ?? '';


function createEnv(string $path, array $ar): void
{
	$originalEnv = file_get_contents($_SERVER['DOCUMENT_ROOT'] . "\\.env");

	if (!file_exists($_SERVER['DOCUMENT_ROOT'] . $path))
	{
		if (!file_exists($dir = str_replace('.env', '', $_SERVER['DOCUMENT_ROOT'] . $path)))
		{
			mkdir($dir);
		}

		$str = implode("\n", $ar);
		file_put_contents($_SERVER['DOCUMENT_ROOT'] . $path, $originalEnv . "\n" . $str);
	}
}

function getServersList(): array
{
	global $wsl;

	$dirList = array_filter(scandir($_SERVER['DOCUMENT_ROOT'] . '/sites/'), function($itm) {
		return (($itm != '.') && ($itm != '..') && file_exists($_SERVER['DOCUMENT_ROOT'] . '/sites/' . $itm . '/.env'));
	});

	$str = shell_exec($wsl . 'docker container ls -a --format=\'{{json .}}\' 2>&1');

	$arr = array_filter(explode("\n", $str), function ($itm)
	{
		return $itm;
	});

	$json = json_decode("[\n" . implode(",\n", $arr) . "\n]");

	$result = [];

	foreach ($dirList as $item)
	{
		foreach ($json as $jsonItem)
		{
			$temp = (array)$jsonItem;

			if ($temp['Names'] == $item . "-" . 'db' || $temp['Names'] == $item . "-" . 'httpd')
			{
				$result[$item][$temp['Names']] = [
					'NAME' => $temp['Names'],
					'ID' => $temp['ID'],
					'STATE' => $temp['State'],
					'STATUS' => $temp['Status'],
					'RUNNING_FOR' => $temp['RunningFor']
				];
			}
		}
	}

	return $result;
}

function proxy(string $command)
{
	if ($command)
	{
		shell_exec("docker-compose -f docker-compose.main_proxy.yml -p main_proxy ${command}");
	}
}

function server(string $server, string $command)
{
	if (strlen($server) && strlen($command))
	{
		createEnv("/sites/${server}/.env", ["APP_NAME=${server}"]);
		shell_exec("docker-compose -f docker-compose.server.yml -p ${server} --env-file ./sites/${server}/.env ${command}");
	}
}

function deleteServerFiles(string $server)
{
	if ($server == 'proxy')
	{
		$servers = array_keys(getServersList());
		foreach ($servers as $itm)
		{
			server(server: $itm, command: 'down');
		}
		proxy(command: 'down');

		$str = str_replace('/', '', file_get_contents($_SERVER['DOCUMENT_ROOT'] . "/.gitignore"));
		$dirs = array_filter(explode("\n", $str), function($itm){
			return (trim($itm) != '.idea') && (trim($itm) != '*env');
		});

		foreach ($dirs as $dir)
		{
			delTree($_SERVER['DOCUMENT_ROOT'] . '/' . $dir);
		}
	}
	else
	{
		delTree($_SERVER['DOCUMENT_ROOT'] . "/ext_www/${server}/");
		delTree($_SERVER['DOCUMENT_ROOT'] . "/sites/${server}/");
		delTree($_SERVER['DOCUMENT_ROOT'] . "/databases/${server}/");
	}
}

function delTree($dir): bool
{
	if (is_dir($dir))
	{
		$files = array_filter(scandir($dir) ?? [], function($itm) {
			return (($itm != '.') && ($itm != '..'));
		});

		foreach ($files as $file)
		{
			(is_dir("$dir/$file")) ? delTree("$dir/$file") : unlink("$dir/$file");
		}
		return rmdir($dir);
	}
	else
	{
		return false;
	}
}

switch ($command)
{
	case "up":
		if ($server == SERVER_MAIN_PROXY)
		{
			proxy(command: 'up -d');
		}
		else
		{
			server(server: $server, command: 'up -d');
		}
		break;
	case "down":
		if ($server == SERVER_MAIN_PROXY)
		{
			proxy(command: 'down');
		}
		else
		{
			server(server: $server, command: 'down');
		}
		break;
	case "stop":
		if ($server == SERVER_MAIN_PROXY)
		{
			proxy(command: 'stop');
		}
		else
		{
			server(server: $server, command: 'stop');
		}
		break;
	case "delete":
		if ($server == SERVER_MAIN_PROXY)
		{
			deleteServerFiles('proxy');
		}
		else
		{
			server(server: $server, command: 'down');
			deleteServerFiles($server);
		}
		break;
	case "restart":
		if ($server == SERVER_MAIN_PROXY)
		{
			proxy(command: 'down');
			proxy(command: 'up -d');
		}
		else
		{
			server(server: $server, command: 'down -d');
			server(server: $server, command: 'up -d');
		}
		break;
	case "build":
		if ($server == SERVER_MAIN_PROXY)
		{
			proxy(command: 'build --no-cache');
		}
		else
		{
			server(server: $server, command: 'build --no-cache');
		}
		break;
	case 'list':
		$info = getServersList();

		if ($info)
		{
			$temp = $info[array_key_first($info)];

			$serverList = array_keys($info);
			$columns = array_keys($temp[array_key_first($temp)]);
			echo "\n";
			$mask = "|%-30.30s |%-15.15s |%-15.15s |%-30.30s |%-15.15s |\n";

			foreach ($serverList as $item)
			{
				echo "Server: \033[0;31m${item}\033[0m\n\n";

				$count = printf($mask, ...$columns);
				echo str_repeat('-', $count) . "\n";

				foreach ($info[$item] as $containerName => $containerValues)
				{
					printf($mask, ...array_values($containerValues));
				}
				echo "\n";
			}
		}
		else
		{
			echo "0 servers\n";
		}

		break;
	default:
		break;
}
echo "\033[0m";