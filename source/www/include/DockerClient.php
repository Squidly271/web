<?PHP
/* Copyright 2005-2016, Lime Technology
 * Copyright 2014-2016, Guilherme Jardim, Eric Schultz, Jon Panozzo.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 */
?>
<?PHP
$docroot = $docroot ?: @$_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp';

$dockerManPaths = [
	'plugin'            => '/usr/local/emhttp/plugins/dynamix.docker.manager',
	'autostart-file'    => '/var/lib/docker/unraid-autostart',
	'template-repos'    => '/boot/config/plugins/dockerMan/template-repos',
	'templates-user'    => '/boot/config/plugins/dockerMan/templates-user',
	'templates-storage' => '/boot/config/plugins/dockerMan/templates',
	'images-ram'        => '/usr/local/emhttp/state/plugins/dynamix.docker.manager/images',
	'images-storage'    => '/boot/config/plugins/dockerMan/images',
	'webui-info'        => '/usr/local/emhttp/state/plugins/dynamix.docker.manager/docker.json',
	'update-status'     => '/var/lib/docker/unraid-update-status.json'
];

#load emhttp variables if needed.
/* if (!isset($var)) {
	if (!is_file("$docroot/state/var.ini")) shell_exec("wget -qO /dev/null localhost:$(lsof -nPc emhttp | grep -Po 'TCP[^\d]*\K\d+')");
	$var = @parse_ini_file("$docroot/state/var.ini");
}
if (!isset($eth0) && is_file("$docroot/state/network.ini")) {
	extract(parse_ini_file("$docroot/state/network.ini",true));
} */

// Docker configuration file - guaranteed to exist
/* $docker_cfgfile = "/boot/config/docker.cfg";
$dockercfg = parse_ini_file($docker_cfgfile);
 */
######################################
##   	DOCKERTEMPLATES CLASS       ##
######################################

class DockerTemplates {

	public $verbose = false;

	private function debug($m) {
		if ($this->verbose) echo $m."\n";
	}


	public function download_url($url, $path = "", $bg = false) {
		exec("curl --compressed --max-time 600 --silent --insecure --location --fail ".($path ? " -o ".escapeshellarg($path) : "")." ".escapeshellarg($url)." ".($bg ? ">/dev/null 2>&1 &" : "2>/dev/null"), $out, $exit_code);
		return ($exit_code === 0) ? implode("\n", $out) : false;
	}


	public function listDir($root, $ext = null) {
		$iter = new RecursiveIteratorIterator(
						new RecursiveDirectoryIterator($root,
						RecursiveDirectoryIterator::SKIP_DOTS),
						RecursiveIteratorIterator::SELF_FIRST,
						RecursiveIteratorIterator::CATCH_GET_CHILD);
		$paths = [];
		foreach ($iter as $path => $fileinfo) {
			$fext = $fileinfo->getExtension();
			if ($ext && ($ext != $fext)) continue;
			if ($fileinfo->isFile()) $paths[] = ['path' => $path, 'prefix' => basename(dirname($path)), 'name' => $fileinfo->getBasename(".$fext")];
		}
		return $paths;
	}


	public function getTemplates($type) {
		global $dockerManPaths;
		$tmpls = [];
		$dirs = [];
		if ($type == "all") {
			$dirs[] = $dockerManPaths['templates-user'];
			$dirs[] = $dockerManPaths['templates-storage'];
		} elseif ($type == "user") {
			$dirs[] = $dockerManPaths['templates-user'];
		} elseif ($type == "default") {
			$dirs[] = $dockerManPaths['templates-storage'];
		} else {
			$dirs[] = $type;
		}
		foreach ($dirs as $dir) {
			if (!is_dir($dir)) @mkdir($dir, 0770, true);
			$tmpls = array_merge($tmpls, $this->listDir($dir, "xml"));
		}
		return $tmpls;
	}


	private function removeDir($path) {
		if (is_dir($path)) {
			$files = array_diff(scandir($path), ['.', '..']);
			foreach ($files as $file) {
				$this->removeDir(realpath($path) . '/' . $file);
			}
			return rmdir($path);
		} elseif (is_file($path)) {
			return unlink($path);
		}
		return false;
	}


	public function downloadTemplates($Dest = null, $Urls = null) {
		global $dockerManPaths;
		$Dest = ($Dest) ? $Dest : $dockerManPaths['templates-storage'];
		$Urls = ($Urls) ? $Urls : $dockerManPaths['template-repos'];
		$repotemplates = [];
		$output = "";
		$tmp_dir = "/tmp/tmp-".mt_rand();
		if (!file_exists($dockerManPaths['template-repos'])) {
			@mkdir(dirname($dockerManPaths['template-repos']), 0777, true);
			@file_put_contents($dockerManPaths['template-repos'], "https://github.com/limetech/docker-templates");
		}
		$urls = @file($Urls, FILE_IGNORE_NEW_LINES);
		if (!is_array($urls)) return false;
		$this->debug("\nURLs:\n   " . implode("\n   ", $urls));
		$github_api_regexes = [
			'%/.*github.com/([^/]*)/([^/]*)/tree/([^/]*)/(.*)$%i',
			'%/.*github.com/([^/]*)/([^/]*)/tree/([^/]*)$%i',
			'%/.*github.com/([^/]*)/(.*).git%i',
			'%/.*github.com/([^/]*)/(.*)%i'
		];
		foreach ($urls as $url) {
			$github_api = ['url' => ''];
			foreach ($github_api_regexes as $api_regex) {
				if (preg_match($api_regex, $url, $matches)) {
					$github_api['user']   = (isset($matches[1])) ? $matches[1] : "";
					$github_api['repo']   = (isset($matches[2])) ? $matches[2] : "";
					$github_api['branch'] = (isset($matches[3])) ? $matches[3] : "master";
					$github_api['path']   = (isset($matches[4])) ? $matches[4] : "";
					$github_api['url']    = sprintf("https://github.com/%s/%s/archive/%s.tar.gz", $github_api['user'], $github_api['repo'], $github_api['branch']);
					break;
				}
			}
			// if after above we don't have a valid url, check for GitLab
			if (empty($github_api['url'])) {
				$source = file_get_contents($url);
				// the following should always exist for GitLab Community Edition or GitLab Enterprise Edition
				if (preg_match("/<meta content='GitLab (Community|Enterprise) Edition' name='description'>/", $source) > 0) {
					$parse = parse_url($url);
					$custom_api_regexes = [
						'%/'.$parse['host'].'/([^/]*)/([^/]*)/tree/([^/]*)/(.*)$%i',
						'%/'.$parse['host'].'/([^/]*)/([^/]*)/tree/([^/]*)$%i',
						'%/'.$parse['host'].'/([^/]*)/(.*).git%i',
						'%/'.$parse['host'].'/([^/]*)/(.*)%i',
					];
					foreach ($custom_api_regexes as $api_regex) {
						if (preg_match($api_regex, $url, $matches)) {
							$github_api['user']   = (isset($matches[1])) ? $matches[1] : "";
							$github_api['repo']   = (isset($matches[2])) ? $matches[2] : "";
							$github_api['branch'] = (isset($matches[3])) ? $matches[3] : "master";
							$github_api['path']   = (isset($matches[4])) ? $matches[4] : "";
							$github_api['url']    = sprintf("https://".$parse['host']."/%s/%s/repository/archive.tar.gz?ref=%s", $github_api['user'], $github_api['repo'], $github_api['branch']);
							break;
						}
					}
				}
			}
			if (empty($github_api['url'])) {
				$this->debug("\n Cannot parse URL ".$url." for Templates.");
				continue;
			}
			if ($this->download_url($github_api['url'], "$tmp_dir.tar.gz") === false) {
				$this->debug("\n Download ".$github_api['url']." has failed.");
				@unlink("$tmp_dir.tar.gz");
				return null;
			} else {
				@mkdir($tmp_dir, 0777, true);
				shell_exec("tar -zxf $tmp_dir.tar.gz -C $tmp_dir/ 2>&1");
				unlink("$tmp_dir.tar.gz");
			}
			$tmplsStor = [];
			$this->debug("\n Templates found in ".$github_api['url']);
			foreach ($this->getTemplates($tmp_dir) as $template) {
				$storPath = sprintf("%s/%s", $Dest, str_replace($tmp_dir."/", "", $template['path']));
				$tmplsStor[] = $storPath;
				if (!is_dir(dirname($storPath))) @mkdir(dirname($storPath), 0777, true);
				if (is_file($storPath)) {
					if (sha1_file($template['path']) === sha1_file($storPath)) {
						$this->debug("   Skipped: ".$template['prefix'].'/'.$template['name']);
						continue;
					} else {
						@copy($template['path'], $storPath);
						$this->debug("   Updated: ".$template['prefix'].'/'.$template['name']);
					}
				} else {
					@copy($template['path'], $storPath);
					$this->debug("   Added: ".$template['prefix'].'/'.$template['name']);
				}
			}
			$repotemplates = array_merge($repotemplates, $tmplsStor);
			$output[$url] = $tmplsStor;
			$this->removeDir($tmp_dir);
		}
		// Delete any templates not in the repos
		foreach ($this->listDir($Dest, "xml") as $arrLocalTemplate) {
			if (!in_array($arrLocalTemplate['path'], $repotemplates)) {
				unlink($arrLocalTemplate['path']);
				$this->debug("   Removed: ".$arrLocalTemplate['prefix'].'/'.$arrLocalTemplate['name']."\n");
				// Any other files left in this template folder? if not delete the folder too
				$files = array_diff(scandir(dirname($arrLocalTemplate['path'])), ['.', '..']);
				if (empty($files)) {
					rmdir(dirname($arrLocalTemplate['path']));
					$this->debug("   Removed: ".$arrLocalTemplate['prefix']);
				}
			}
		}
		return $output;
	}


	public function getTemplateValue($Repository, $field, $scope = "all") {
		foreach ($this->getTemplates($scope) as $file) {
			$doc = new DOMDocument();
			$doc->load($file['path']);
			$TemplateRepository = DockerUtil::ensureImageTag($doc->getElementsByTagName("Repository")->item(0)->nodeValue);

			if ($Repository == $TemplateRepository) {
				$TemplateField = $doc->getElementsByTagName($field)->item(0)->nodeValue;
				return trim($TemplateField);
			}
		}
		return null;
	}


	public function getUserTemplate($Container) {
		foreach ($this->getTemplates("user") as $file) {
			$doc = new DOMDocument('1.0', 'utf-8');
			$doc->load($file['path']);
			$Name = $doc->getElementsByTagName("Name")->item(0)->nodeValue;
			if ($Name == $Container) {
				return $file['path'];
			}
		}
		return false;
	}


	public function getControlURL($name) {
		global $var,$eth0;
		$DockerClient = new DockerClient();

		$Repository = "";
		foreach ($DockerClient->getDockerContainers() as $ct) {
			if ($ct['Name'] == $name) {
				$Repository = $ct['Image'];
				$Ports = $ct["Ports"];
				break;
			}
		}

		$WebUI = $this->getTemplateValue($Repository, "WebUI");

		if (preg_match("%\[IP\]%", $WebUI)) {
			$WebUI = preg_replace("%\[IP\]%", $eth0["IPADDR:0"], $WebUI);
		}
		if (preg_match("%\[PORT:(\d+)\]%", $WebUI, $matches)) {
			$ConfigPort = $matches[1];
			if ($ct["NetworkMode"] == "bridge") {
				foreach ($Ports as $key) {
					if ($key["PrivatePort"] == $ConfigPort) {
						$ConfigPort = $key["PublicPort"];
					}
				}
			}
			$WebUI = preg_replace("%\[PORT:\d+\]%", $ConfigPort, $WebUI);
		}
		return $WebUI;
	}


	public function removeContainerInfo($container) {
		global $dockerManPaths;

		$info = DockerUtil::loadJSON($dockerManPaths['webui-info']);
		if (isset($info[$container])) unset($info[$container]);
		DockerUtil::saveJSON($dockerManPaths['webui-info'], $info);
	}


	public function removeImageInfo($image) {
		global $dockerManPaths;
		$image = DockerUtil::ensureImageTag($image);

		$updateStatus = DockerUtil::loadJSON($dockerManPaths['update-status']);
		if (isset($updateStatus[$image])) unset($updateStatus[$image]);
		DockerUtil::saveJSON($dockerManPaths['update-status'], $updateStatus);
	}


	public function getAllInfo($reload = false) {
		global $dockerManPaths;
		$DockerClient = new DockerClient();
		$DockerUpdate = new DockerUpdate();
		$DockerUpdate->verbose = $this->verbose;
		$new_info = [];

		$info = DockerUtil::loadJSON($dockerManPaths['webui-info']);

		$autostart_file = $dockerManPaths['autostart-file'];
		$allAutoStart = @file($autostart_file, FILE_IGNORE_NEW_LINES);
		if ($allAutoStart === false) $allAutoStart = [];

		foreach ($DockerClient->getDockerContainers() as $ct) {
			$name           = $ct['Name'];
			$image          = $ct['Image'];
			$tmp            = (count($info[$name])) ? $info[$name] : [];

			$tmp['running'] = $ct['Running'];
			$tmp['autostart'] = in_array($name, $allAutoStart);

			if (!$tmp['icon'] || $reload) {
				$icon = $this->getIcon($image);
				$tmp['icon'] = ($icon) ? $icon : null;
			}
			if (!$tmp['url'] || $reload) {
				$WebUI = $this->getControlURL($name);
				$tmp['url'] = ($WebUI) ? $WebUI : null;
			}

			$Registry = $this->getTemplateValue($image, "Registry");
			$tmp['registry'] = ($Registry) ? $Registry : null;

			if (!$tmp['updated'] || $reload) {
				if ($reload) $DockerUpdate->reloadUpdateStatus($image);
				$vs = $DockerUpdate->getUpdateStatus($image);
				$tmp['updated'] = ($vs === null) ? null : (($vs === true) ? 'true' : 'false');
			}

			if (!$tmp['template'] || $reload) {
				$tmp['template'] = $this->getUserTemplate($name);
			}

			if ($reload) {
				$DockerUpdate->updateUserTemplate($name);
			}

			$this->debug("\n$name");
			foreach ($tmp as $c => $d) $this->debug(sprintf("   %-10s: %s", $c, $d));
			$new_info[$name] = $tmp;
		}
		DockerUtil::saveJSON($dockerManPaths['webui-info'], $new_info);
		return $new_info;
	}


	public function getIcon($Repository) {
		global $docroot, $dockerManPaths;

		$imgUrl = $this->getTemplateValue($Repository, "Icon");

		preg_match_all("/(.*?):([\w]*$)/i", $Repository, $matches);
		$tempPath    = sprintf("%s/%s-%s-%s.png", $dockerManPaths['images-ram'], preg_replace('%\/|\\\%', '-', $matches[1][0]), $matches[2][0], 'icon');
		$storagePath = sprintf("%s/%s-%s-%s.png", $dockerManPaths['images-storage'], preg_replace('%\/|\\\%', '-', $matches[1][0]), $matches[2][0], 'icon');
		if (!is_dir(dirname($tempPath))) @mkdir(dirname($tempPath), 0770, true);
		if (!is_dir(dirname($storagePath))) @mkdir(dirname($storagePath), 0770, true);
		if (!is_file($tempPath)) {
			if (!is_file($storagePath)) {
				$this->download_url($imgUrl, $storagePath);
			}
			@copy($storagePath, $tempPath);
		}
		return (is_file($tempPath)) ? str_replace($docroot, '', $tempPath) : "";
	}
}


?>
