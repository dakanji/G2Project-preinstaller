<?php
/*
 * Gallery - a web based photo album viewer and editor
 * Copyright (C) 2000-2008 Bharat Mediratta
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either Version 2 of the License, or (at
 * your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street - Fifth Floor, Boston, MA  02110-1301, USA.
 */

/**
 * Script to:
 *  - download the gallery2.zip / tar.gz from a known server directly to the
 *  server where the script is running.
 *  - extract the gallery2.zip / tar.gz archive directly on the server
 * @package Preinstaller
 * @author: Andy Staudacher <ast@gmx.ch>
 * @version $Revision: 20999 $
 * @versionId 2.3.2
 */

/**
 * Refactored for PHP 7.3 (Backward Compatible with PHP 5.6) by Dayo Akanji
 * Date: 29 June 2018
 */
error_reporting(E_ALL);
set_time_limit(900);

// ---------------------- P A S S P H R A S E
// Enter a one time passphrase with at least 6 characters
$passPhrase = '';


// ---------------------- C L A S S E S
class preInstallerConfig {
	// Pre-Installer Version
	public $scriptVersion = '2.0.0';

	// General Gallery 2 Release Tags
	public $stableReleaseTag;
	public $releaseCandidateTag;

	// Gallery 2 Repository
	public $g2Repo = 'dakanji/G2Project-main';

	// Local name of the gallery2 archive (without extension )
	public $archiveBaseName = 'gallery2';

	// A list of folder permissions available for chmodding
	public $folderPermissionList = array('777', '755', '555');

	// Archive extensions available for download
	public $availableExtensions = array('zip', 'tar.gz');

	// Available codebases of Gallery 2
	public $availableVersions = array('official', 'stable', 'tag', 'dev', 'master');

	// Last Official Version Tag Name
	public $officialReleaseTag = 'v2.3.2';

	// PHP allow_url_fopen status
	public $allowFOpen = false;

	// Implementation Stage
	public $stuckOnTwo = false;

	public function __construct() {
		if (ini_get('allow_url_fopen')) {
			$this->allowFOpen = true;

			// Get Releases from Github Repo
			$url      = "https://api.github.com/repos/$this->g2Repo/releases";
			$opts     = array(
				'http' => array(
					'method' => 'GET',
					'header' => array(
						'User-Agent: G2Project-Preinstaller',
					),
				),
			);
			$json     = file_get_contents($url, false, stream_context_create($opts));
			$releases = json_decode($json);
		}

		if ($this->allowFOpen && $releases) {
			$arrSubsetAll    = array();
			$arrSubsetStable = array();

			foreach ($releases as $release) {
				// Put a Subset of Stable Releases into an Array
				if ($release->prerelease === false) {
					$arrSubsetStable[] = array(
						'created_at'  => $release->created_at,
						'zipball_url' => $release->zipball_url,
						'tag_name'    => $release->tag_name,
					);
				}

				// Put a Subset of All Releases into an Array
				$arrSubsetAll[] = array(
					'created_at'  => $release->created_at,
					'zipball_url' => $release->zipball_url,
					'tag_name'    => $release->tag_name,
				);
			}

			// Sort Subset of All Releases by Date (In Desecnding Order - Latest First)
			// Then Get Path of First Item in Sorted Array
			$arrSubsetAll  = $this->arraySorter($arrSubsetAll, 'created_at');
			$latestPathAll = $arrSubsetAll[0]['zipball_url'];

			// Sort Subset of Stable Releases by Date (In Desecnding Order - Latest First)
			// Then Get Path of First Item in Sorted Array
			$arrSubsetStable  = $this->arraySorter($arrSubsetStable, 'created_at');
			$latestPathStable = $arrSubsetStable[0]['zipball_url'];
		} else {
			// Default to master
			$latestPathAll    = "https://github.com/$this->g2Repo/archive/master";
			$latestPathStable = $latestPathAll;
		}

		// Last Stable Release Tag Name.
		// Default to Last Official Release Tag
		$this->stableReleaseTag = $arrSubsetStable[0]['tag_name'] ? $arrSubsetStable[0]['tag_name'] : $this->officialReleaseTag;

		// Last Release Candidate Tag Name.
		// Default to Last Stable Release Tag
		$this->releaseCandidateTag = $arrSubsetAll[0]['tag_name'] ? $arrSubsetAll[0]['tag_name'] : $this->stableReleaseTag;

		// Paths to Codebase Releases
		$this->downloadUrls = array(
			'official' => "https://github.com/$this->g2Repo/archive/v2.3.2",
			'stable'   => $latestPathStable,
			'tag'      => $latestPathAll,
			'master'   => "https://github.com/$this->g2Repo/archive/master",
			'dev'      => "https://github.com/$this->g2Repo/archive/dev",
		);
	}

	protected function arraySorter($array, $on, $order = SORT_DESC) {
		$new_array      = array();
		$sortable_array = array();

		if (count($array) > 0) {
			foreach ($array as $k => $v) {
				if (is_array($v)) {
					foreach ($v as $k2 => $v2) {
						if ($k2 == $on) {
							$sortable_array[$k] = $v2;
						}
					}
				} else {
					$sortable_array[$k] = $v;
				}
			}

			switch ($order) {
				case SORT_ASC:
					asort($sortable_array);

					break;

				case SORT_DESC:
					arsort($sortable_array);

					break;
			}

			foreach ($sortable_array as $k => $v) {
				$new_array[$k] = $array[$k];
			}
		}

		return $new_array;
	}
}

class preInstaller {
	private $_extractMethods;
	private $_transferMethods;

	public function main() {
		global $config, $server, $page;

		// Authentication
		$this->authenticate();

		// Register Tranfer Methods
		$this->_transferMethods = array(
			new CurlDownloader(),
			new WgetDownloader(),
			new FopenDownloader(),
			new FsockopenDownloader(),
		);

		// Register Extraction Methods
		$this->_extractMethods = array(
			new UnzipExtractor(),
			new PhpUnzipExtractor(),
			new TarGzExtractor(),
			new PhpTarGzExtractor(),
		);

		// Make sure we can write to the current server folder
		if (!$server->isDirectoryWritable()) {
			$page->render(
				'results',
				array(
					'failure' => 'Server Folder: <em>' . __DIR__ . '</em> is not writeable',
					'fix'     => 'ftp > chmod 777 ' . basename(__DIR__),
				)
			);
		}

		// Handle the request
		if (empty($_POST['command'])) {
			$command = '';
		} else {
			$command = trim($_POST['command']);
		}

		switch ($command) {
			case 'download':
				// Input validation / sanitation
				if (empty($_POST['method'])
					|| !preg_match('/^[a-z]+downloader$/', $_POST['method'])
				) {
					$downloader = '';
				} else {
					$downloader = trim($_POST['method']);
				}

				// ... archive extension
				if (empty($_POST['extension'])) {
					$extension = '';
				} else {
					$extension = trim($_POST['extension']);
				}

				if (!preg_match('/^([a-z]{2,4}\.)?[a-z]{2,4}$/', $extension)) {
					$page->render(
						'results',
						array(
							'failure' => 'Filetype for download not defined. Please retry',
						)
					);
				}

				if (!in_array($extension, $config->availableExtensions)) {
					$extension = 'zip';
				}

				// Gallery 2 Codebase ('official', 'stable', 'tag', 'master', 'dev')
				if (empty($_POST['codebase']) || !in_array($_POST['codebase'], $config->availableVersions)) {
					$codebase = 'stable';
				} else {
					$codebase = trim($_POST['codebase']);
				}

				// Handle the request
				if (class_exists($downloader)) {
					$method = new $downloader();

					if ($method->isSupported()) {
						$archiveName = __DIR__ . '/' . $config->archiveBaseName . '.' . $extension;

						// Delete previous versions with the same file name
						// Just in case someone tries to download multiple times
						if (file_exists($archiveName)) {
							unlink($archiveName);
						}

						// Assemble the downlod URL
						$url     = $this->getDownloadUrl($codebase, $extension);
						$results = $method->download($url, $archiveName);

						if ($results === true) {
							if (file_exists($archiveName)) {
								@chmod($archiveName, 0777);
								$page->render(
									'results',
									array(
										'success' => 'Gallery 2 Archive File Successfully Transfered to this Webserver',
									)
								);
							} else {
								$page->render(
									'results',
									array(
										'failure' => "Transfer Failed. Local Gallery 2 Archive File <em>$archiveName</em> Does not Exist",
									)
								);
							}
						} else {
							$page->render(
								'results',
								array(
									'failure' => $results,
								)
							);
						}
					} else {
						$page->render(
							'results',
							array(
								'failure' => "File Transfer Method: <em>$method</em> is not supported by this webserver",
							)
						);
					}
				} else {
					$page->render(
						'results',
						array(
							'failure' => 'File Transfer Method is not defined or does not exist!',
						)
					);
				}

				break;

			case 'extract':
				// Input validation / sanitation
				if (empty($_POST['method'])
					|| !preg_match('/^[a-z]+extractor$/', $_POST['method'])
				) {
					$extractor = '';
				} else {
					$extractor = trim($_POST['method']);
				}

				// Handle the request
				if (class_exists($extractor)) {
					$method = new $extractor();

					if ($method->isSupported()) {
						$archiveName = __DIR__ . '/' .
						$config->archiveBaseName . '.' . $method->getSupportedExtension();

						if (file_exists($archiveName)) {
							$results = $method->extract($archiveName);

							if ($results === true) {
								// Make sure the dirs and files were extracted successfully
								if (!$this->integrityCheck()) {
									$page->render(
										'results',
										array(
											'failure' => '<h4>Archive Successfully Extracted</h4> Basic integrity check failed however',
										)
									);
								} else {
									// Get the name of the Gallery 2 folder
									$folderName = $this->findGallery2Folder();

									if ($folderName) {
										// Get the php_sapi
										$sapi_type = php_sapi_name();

										if ((substr($sapi_type, 0, 3) == 'cgi')
											|| (substr($sapi_type, 0, 3) == 'fpm')
										) {
											// We are using PHP CGI/FPM
											// So set permissions to 0755
											$folderPerm = '0755';
											$infoString = 'Folder permissions have been set to <em>0755</em> (Read/Write for "Owner" and Read Only for Others) as we are using PHP CGI/FPM';
										} else {
											// We are NOT using PHP CGI/FPM
											// So set permissions to 0777
											$folderPerm = '0777';
											$infoString = 'Folder permissions have been set to <em>0777</em> (Read/Write for Everybody) as we are NOT using PHP CGI/FPM.<br>Please use the provided convenience function to change permissions to <em>0755</em> or <em>0555</em> to secure your installation once Gallery 2 is up and running';
										}

										// Set the permissions
										@chmod(__DIR__ . '/' . $folderName, $folderPerm);
									}

									// Render the results
									$page->render(
										'results',
										array(
											'success' => '<h4>Archive Successfully Extracted</h4>' . $infoString,
										)
									);
								}
							} else {
								$page->render(
									'results',
									array(
										'failure' => $results,
									)
								);
							}
						} else {
							$page->render(
								'results',
								array(
									'failure' => "Archive <em>$archiveName</em> does not exist in the current server folder",
								)
							);
						}
					} else {
						$page->render(
							'results',
							array(
								'failure' => "Extraction Method: <em>$method</em> is not supported by this webserver",
							)
						);
					}
				} else {
					$page->render(
						'results',
						array(
							'failure' => 'Extraction Method not defined or does not exist!',
						)
					);
				}

				break;

			case 'chmod':
				// Input validation / sanitation
				if (empty($_POST['folderName'])) {
					$folderName = '';
				} else {
					$folderName = trim($_POST['folderName']);
				}
				// Remove trailing / leading slashes
				$folderName = str_replace(array('/', '\\', '..'), '', $folderName);

				if (!$folderName) {
					$page->render(
						'results',
						array(
							'failure' => 'Please type in a folder name',
						)
					);
				}

				if (!preg_match('/^[\w-]+$/', $folderName)) {
					$page->render(
						'results',
						array(
							'failure' => "Folder <em>$folderName</em> has invalid characters. Can only change the permissions of folders in the current server folder.",
						)
					);
				}
				$folderName = __DIR__ . '/' . $folderName;

				if (!file_exists($folderName)) {
					$page->render(
						'results',
						array(
							'failure' => "Folder <em>$folderName</em> does not exist!",
						)
					);
				}

				if (empty($_POST['folderPermissions'])) {
					$folderPermissions = '';
				} else {
					$folderPermissions = trim($_POST['folderPermissions']);
				}

				// Handle the request
				if (in_array($folderPermissions, $config->folderPermissionList)) {
					$permissionsLong = (string)('0' . (int)$folderPermissions);

					if ($permissionsLong === substr(sprintf('%o', fileperms($folderName)), -4)) {
						$page->render(
							'results',
							array(
								'info' => "Folder $folderName already has <em>$folderPermissions</em> permissions",
							)
						);
					}
					$success = @chmod($folderName, octdec($permissionsLong));

					if (!$success) {
						$page->render(
							'results',
							array(
								'failure' => "Attempt to change permissions of folder $folderName to <em>$folderPermissions</em> failed!",
							)
						);
					} else {
						$page->render(
							'results',
							array(
								'success' => "Successfully changed permissions of $folderName to <em>$folderPermissions</em>",
							)
						);
					}
				} else {
					$page->render(
						'results',
						array(
							'failure' => "Invalid permissions $folderPermissions",
						)
					);
				}

				break;

			case 'rename':
				if (empty($_POST['folderName'])) {
					$folderName = '';
				} else {
					$folderName = trim($_POST['folderName']);
				}

				// Remove trailing / leading slashes
				$folderName = str_replace(array('/', '\\', '.'), '', $folderName);

				if (!preg_match('/^[\w-]+$/', $folderName)) {
					$page->render(
						'results',
						array(
							'failure' => "Folder name <em>$folderName</em> has invalid characters. Can only rename within the current server folder.",
						)
					);
				}

				$folderNamePath    = __DIR__ . '/' . $folderName;
				$oldFolderName     = $this->findGallery2Folder();
				$oldFolderNamePath = __DIR__ . '/' . $oldFolderName;

				if (empty($oldFolderName) || !file_exists($oldFolderNamePath)) {
					$page->render(
						'results',
						array(
							'failure' => 'Gallery 2 folder not found in current server folder',
						)
					);
				}

				if ($oldFolderName == $folderName) {
					$page->render(
						'results',
						array(
							'info' => "Current Gallery 2 folder:<br><br>$oldFolderNamePath<br>already has <em>$folderName</em> as folder name!",
						)
					);
				}

				$success = @rename($oldFolderNamePath, $folderNamePath);

				if (!$success) {
					$page->render(
						'results',
						array(
							'failure' => "Attempt to Rename <br> $oldFolderNamePath <br>to<br> <em>$folderNamePath</em> <br>Failed",
						)
					);
				} else {
					$page->render(
						'results',
						array(
							'success' => "Successfully Renamed <br> $oldFolderNamePath <br>to<br>  <em>$folderNamePath</em>",
						)
					);
				}

				break;

			default:
				// Discover the capabilities of this PHP installation / platform
				$capabilities                       = $this->discoverCapabilities();
				$capabilities['gallery2FolderName'] = $this->findGallery2Folder();

				if (!empty($capabilities['gallery2FolderName'])) {
					$statusMessage = 'Ready for installation (Gallery 2 Folder: <em>' . $capabilities['gallery2FolderName'] . '</em> found)';
				} elseif (!empty($capabilities['anyArchiveExists'])) {
					$statusMessage      = 'Gallery 2 Archive Detected in Current Server Folder<br><br>Please Continue with Implementation Step 2: <em>Extraction Methods</em>';
					$config->stuckOnTwo = true;
				} else {
					$statusMessage = 'Gallery 2 Archive not Detected in Current Server Folder<br><br>Please Start with Implementation Step 1: <em>Transfer Methods</em>';
				}
				$capabilities['statusMessage'] = $statusMessage;

				// Are we in RC stage?
				if (!empty($capabilities['transferMethods'])) {
					foreach ($capabilities['transferMethods'] as $dMethod) {
						if ($dMethod['isSupported']) {
							$capabilities['showTagRelease']    = $this->shouldShowTagRelease();
							$capabilities['showStableRelease'] = $this->shouldShowStableRelease();

							break;
						}
					}
				}
				$page->render('options', $capabilities);
		}
	}

	public function authenticate() {
		global $passPhrase, $page;

		// Check authentication
		if (empty($passPhrase)) {
			$page->render('missingPassword');
		}

		if (strlen($passPhrase) < 6) {
			$page->render('passwordTooShort');
		}

		if (!empty($_COOKIE['G2PREINSTALLER'])
			&& trim($_COOKIE['G2PREINSTALLER']) == md5($passPhrase)
		) {
			// Already logged in, got a cookie
			return true;
		}

		if (!empty($_POST['g2_password'])) {
			// Login attempt
			if ($_POST['g2_password'] == $passPhrase) {
				setcookie('G2PREINSTALLER', md5($passPhrase), 0);

				return true;
			}
			$page->render(
				'passwordForm',
				array(
					'incorrectPassword' => 1,
				)
			);
		}
		$page->render('passwordForm');
	}

	public function discoverCapabilities() {
		$capabilities = array();
		global $config;

		$extractMethods        = array();
		$extensions            = array();
		$anyExtensionSupported = 0;
		$anyArchiveExists      = 0;

		foreach ($this->_extractMethods as $method) {
			$archiveName      = $config->archiveBaseName . '.' . $method->getSupportedExtension();
			$archiveExists    = file_exists(__DIR__ . '/' . $archiveName);
			$isSupported      = $method->isSupported();
			$extractMethods[] = array(
				'isSupported'   => $isSupported,
				'name'          => $method->getName(),
				'command'       => strtolower(get_class($method)),
				'archiveExists' => $archiveExists,
				'archiveName'   => $archiveName,
			);

			if (empty($extensions[$method->getSupportedExtension()])) {
				$extensions[$method->getSupportedExtension()] = (int)$isSupported;
			}

			if ($isSupported) {
				$anyExtensionSupported = 1;
			}

			if ($archiveExists) {
				$anyArchiveExists = 1;
			}
		}
		$capabilities['extractMethods']        = $extractMethods;
		$capabilities['extensions']            = $extensions;
		$capabilities['anyExtensionSupported'] = $anyExtensionSupported;
		$capabilities['anyArchiveExists']      = $anyArchiveExists;

		$transferMethods = array();

		foreach ($this->_transferMethods as $method) {
			$transferMethods[] = array(
				'isSupported' => $method->isSupported(),
				'name'        => $method->getName(),
				'command'     => strtolower(get_class($method)),
			);
		}
		$capabilities['transferMethods'] = $transferMethods;

		return $capabilities;
	}

	public function findGallery2Folder() {
		global $server;

		// Search in the current folder for a gallery2 folder
		$latestPathAll = __DIR__ . '/';

		if (file_exists($latestPathAll . 'gallery2')
			&& file_exists($latestPathAll . 'gallery2/install/index.php')
		) {
			return 'gallery2';
		}

		if (!$server->isPhpFunctionSupported('opendir')
			|| !$server->isPhpFunctionSupported('readdir')
		) {
			return false;
		}

		$handle = opendir($latestPathAll);

		if (!$handle) {
			return false;
		}

		while (($fileName = readdir($handle)) !== false) {
			if ($fileName == '.' || $fileName == '..') {
				continue;
			}

			if (file_exists($latestPathAll . $fileName . '/install/index.php')) {
				return $fileName;
			}
		}
		closedir($handle);

		return false;
	}

	public function integrityCheck() {
		// TODO, check for the existence of modules, lib, themes, main.php
		return true;
	}

	public function getDownloadUrl($codebase, $extension) {
		global $config;

		// Get defined codebases
		$url = $config->downloadUrls[$codebase];

		// Try to get the latest codebase string
		if ($codebase != 'stable' && $codebase != 'tag') {
			$url .= '.' . $extension;
		} elseif ($extension == 'tar.gz') {
			str_replace('/zipball/', '/tarball/', $url);
		}

		return $url;
	}

	public function shouldShowTagRelease() {
		global $config;

		// Only show if we are in a release candidate stage
		return $config->stableReleaseTag != $config->releaseCandidateTag;
	}

	public function shouldShowStableRelease() {
		global $config;

		// Only show if not same as official release
		return $config->stableReleaseTag != $config->officialReleaseTag;
	}
}

class serverPlatform {
	// Check if a specific php function is available
	public function isPhpFunctionSupported($functionName) {
		if (in_array($functionName, preg_split('/,\s*/', ini_get('disable_functions'))) || !function_exists($functionName)) {
			return false;
		}

		return true;
	}

	// Check if a specific command line tool is available
	public function isBinaryAvailable($binaryName) {
		$binaryPath = $this->getBinaryPath($binaryName);

		return !empty($binaryPath);
	}

	// Return the path to a binary or false if it's not available
	public function getBinaryPath($binaryName) {
		if (!$this->isPhpFunctionSupported('exec')) {
			return false;
		}

		// First try 'which'
		$ret = array();
		exec('which ' . $binaryName, $ret);

		if (strpos(join(' ', $ret), $binaryName) !== false && is_executable(join('', $ret))) {
			return $binaryName;// it's in the path
		}

		// Try a bunch of likely seeming paths to see if any of them work.
		$paths = array();

		if (!strncasecmp(PHP_OS, 'win', 3)) {
			$separator = ';';
			$slash     = '\\';
			$extension = '.exe';
			$paths[]   = "C:\\Program Files\\$binaryName\\";
			$paths[]   = 'C:\\apps$binaryName\\';
			$paths[]   = "C:\\$binaryName\\";
		} else {
			$separator = ':';
			$slash     = '/';
			$extension = '';
			$paths[]   = '/usr/bin/';
			$paths[]   = '/usr/local/bin/';
			$paths[]   = '/bin/';
			$paths[]   = '/sw/bin/';
		}
		$paths[] = './';

		foreach (explode($separator, getenv('PATH')) as $path) {
			$path = trim($path);

			if (empty($path)) {
				continue;
			}

			if ($path[strlen($path) - 1] != $slash) {
				$path .= $slash;
			}
			$paths[] = $path;
		}

		// Now try each path in turn to see which ones work
		foreach ($paths as $path) {
			$execPath = $path . $binaryName . $extension;

			if (file_exists($execPath) && is_executable($execPath)) {
				// We have a winner
				return $execPath;
			}
		}

		return false;
	}

	// Check if we can write to this directory (download, extract)
	public function isDirectoryWritable() {
		return is_writable(__DIR__);
	}

	public function extendTimeLimit() {
		if (function_exists('apache_reset_timeout')) {
			@apache_reset_timeout();
		}
		@set_time_limit(600);
	}
}

class DownloadMethod {
	public function download($url, $outputFile) {
		return false;
	}

	public function isSupported() {
		return false;
	}

	public function getName() {
		return '';
	}
}

class WgetDownloader extends DownloadMethod {
	public function download($url, $outputFile) {
		global $server;

		$status = 0;
		$output = array();
		$wget   = $server->getBinaryPath('wget');
		exec("$wget -O$outputFile $url ", $output, $status);

		if ($status) {
			$msg  = 'exec returned an error status ';
			$msg .= is_array($output) ? implode('<br>', $output) : '';

			return $msg;
		}

		return true;
	}

	public function isSupported() {
		global $server;

		return $server->isBinaryAvailable('wget');
	}

	public function getName() {
		return 'Transfer using Wget';
	}
}

class FopenDownloader extends DownloadMethod {
	public function download($url, $outputFile) {
		global $server;

		if (!$server->isDirectoryWritable()) {
			return 'Unable to write to current server folder';
		}
		$start = time();

		$server->extendTimeLimit();

		$fh = fopen($url, 'rb');

		if (empty($fh)) {
			return 'Unable to open url';
		}
		$ofh = fopen($outputFile, 'wb');

		if (!$ofh) {
			fclose($fh);

			return 'Unable to open output file in writing mode';
		}

		$failed = $results = false;

		while (!feof($fh) && !$failed) {
			$buf = fread($fh, 4096);

			if (!$buf) {
				$results = 'Error during download';
				$failed  = true;

				break;
			}

			if (fwrite($ofh, $buf) != strlen($buf)) {
				$failed  = true;
				$results = 'Error during writing';

				break;
			}

			if (time() - $start > 55) {
				$server->extendTimeLimit();
				$start = time();
			}
		}
		fclose($ofh);
		fclose($fh);

		if ($failed) {
			return $results;
		}

		return true;
	}

	public function isSupported() {
		global $server;

		$actual = ini_get('allow_url_fopen');

		if (in_array($actual, array(1, 'On', 'on')) && $server->isPhpFunctionSupported('fopen')) {
			return true;
		}

		return false;
	}

	public function getName() {
		return 'Transfer using PHP fopen()';
	}
}

class FsockopenDownloader extends DownloadMethod {
	public function download($url, $outputFile, $maxRedirects = 10) {
		global $server;

		// Code from WebHelper_simple.class

		if ($maxRedirects < 0) {
			return "Error too many redirects. Last URL: $url";
		}

		$components = parse_url($url);
		$port       = empty($components['port']) ? 80 : $components['port'];

		$errno = $errstr = null;
		$fd    = @fsockopen($components['host'], $port, $errno, $errstr, 2);

		if (empty($fd)) {
			return "Error $errno: '$errstr' retrieving $url";
		}

		$get = $components['path'];

		if (!empty($components['query'])) {
			$get .= '?' . $components['query'];
		}

		$start = time();

		// Read the web file into a buffer
		$ok = fwrite(
			$fd,
			sprintf(
				"GET %s HTTP/1.0\r\n" .
				"Host: %s\r\n" .
				"\r\n",
				$get,
				$components['host']
			)
		);

		if (!$ok) {
			return 'Transfer request failed (fwrite)';
		}
		$ok = fflush($fd);

		if (!$ok) {
			return 'Transfer request failed (fflush)';
		}

		/*
		 * Read the response code. fgets stops after newlines.
		 * The first line contains only the status code (200, 404, etc.).
		 */
		$headers  = array();
		$response = trim(fgets($fd, 4096));

		// Jump over the headers but follow redirects
		while (!feof($fd)) {
			$line = trim(fgets($fd, 4096));

			if (empty($line)) {
				break;
			}

			// Normalize the line endings
			$line              = str_replace("\r", '', $line);
			list($key, $value) = explode(':', $line, 2);

			if (trim($key) == 'Location') {
				fclose($fd);

				return $this->download(trim($value), $outputFile, --$maxRedirects);
			}
		}

		$success = false;
		$ofd     = fopen($outputFile, 'wb');

		if ($ofd) {
			// Read the body
			$failed = false;

			while (!feof($fd) && !$failed) {
				$buf = fread($fd, 4096);

				if (fwrite($ofd, $buf) != strlen($buf)) {
					$failed = true;

					break;
				}

				if (time() - $start > 55) {
					$server->extendTimeLimit();
					$start = time();
				}
			}
			fclose($ofd);

			if (!$failed) {
				$success = true;
			}
		} else {
			return "Could not open $outputFile in write mode";
		}
		fclose($fd);

		// if the HTTP response code did not begin with a 2 this request was not successful
		if (!preg_match('/^HTTP\/\d+\.\d+\s2\d{2}/', $response)) {
			return "Download failed with HTTP status: $response";
		}

		return true;
	}

	public function isSupported() {
		global $server;

		return $server->isPhpFunctionSupported('fsockopen');
	}

	public function getName() {
		return 'Transfer using PHP fsockopen()';
	}
}

class CurlDownloader extends DownloadMethod {
	public function download($url, $outputFile) {
		$ch  = curl_init();
		$ofh = fopen($outputFile, 'wb');

		if (!$ofh) {
			fclose($ch);

			return 'Unable to open output file in writing mode';
		}

		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_FILE, $ofh);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
		curl_setopt($ch, CURLOPT_FAILONERROR, true);
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_TIMEOUT, 20 * 60);
		curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);

		curl_exec($ch);

		$errorString = curl_error($ch);
		$errorNumber = curl_errno($ch);
		curl_close($ch);

		if ($errorNumber != 0) {
			if (!empty($errorString)) {
				return $errorString;
			}

			return 'CURL download failed';
		}

		return true;
	}

	public function isSupported() {
		global $server;

		foreach (array('curl_init', 'curl_setopt', 'curl_exec', 'curl_close', 'curl_error') as $functionName) {
			if (!$server->isPhpFunctionSupported($functionName)) {
				return false;
			}
		}

		return true;
	}

	public function getName() {
		return 'Transfer using PHP cURL()';
	}
}

class UnzipExtractor {
	public function extract($fileName) {
		global $server;

		$output = array();
		$status = 0;
		$unzip  = $server->getBinaryPath('unzip');
		exec($unzip . ' ' . $fileName, $output, $status);

		if ($status) {
			$msg  = 'exec returned an error status ';
			$msg .= is_array($output) ? implode('<br>', $output) : '';

			return $msg;
		}

		return true;
	}

	public function getSupportedExtension() {
		return 'zip';
	}

	public function isSupported() {
		global $server;

		return $server->isBinaryAvailable('unzip');
	}

	public function getName() {
		return 'Extract .zip with unzip';
	}
}

class TargzExtractor {
	public function extract($fileName) {
		global $server;

		$output = array();
		$status = 0;
		$tar    = $server->getBinaryPath('tar');
		exec($tar . ' -xzf' . $fileName, $output, $status);

		if ($status) {
			$msg  = 'exec returned an error status ';
			$msg .= is_array($output) ? implode('<br>', $output) : '';

			return $msg;
		}

		return true;
	}

	public function getSupportedExtension() {
		return 'tar.gz';
	}

	public function isSupported() {
		global $server;

		return $server->isBinaryAvailable('tar');
	}

	public function getName() {
		return 'Extract .tar.gz with tar';
	}
}

class PhpTargzExtractor {
	public function extract($fileName) {
		$tarArchiveHandler = new tarArchiveHandler();

		return $tarArchiveHandler->PclTarExtract($fileName);
	}

	public function getSupportedExtension() {
		return 'tar.gz';
	}

	public function isSupported() {
		global $server;

		foreach (array(
			'gzopen',
			'gzclose',
			'gzseek',
			'gzread',
			'touch',
			'gzeof',
		) as $functionName) {
			if (!$server->isPhpFunctionSupported($functionName)) {
				return false;
			}
		}

		return true;
	}

	public function getName() {
		return 'Extract .tar.gz with PHP functions';
	}
}

class PhpUnzipExtractor {
	public function extract($fileName) {
		global $server;

		$baseFolder = dirname($fileName);

		if (!($zip = zip_open($fileName))) {
			return "Could not open the zip archive $fileName";
		}
		$start = time();

		while ($zip_entry = zip_read($zip)) {
			if (zip_entry_open($zip, $zip_entry, 'r')) {
				$buf        = zip_entry_read($zip_entry, zip_entry_filesize($zip_entry));
				$entry_name = zip_entry_name($zip_entry);
				$dir_name   = dirname($entry_name);

				if ($dir_name != '.') {
					$dir = $baseFolder . '/';

					foreach (explode('/', $dir_name) as $folderName) {
						$dir .= $folderName;

						if (is_file($dir)) {
							unlink($dir);
						}

						if (!is_dir($dir)) {
							mkdir($dir);
						}
						$dir .= '/';
					}
				}
				$fp = fopen(rtrim($baseFolder . '/' . $entry_name, '/'), 'w');

				if (!$fp) {
					return 'Error during php unzip: trying to open a file for writing';
				}

				if (fwrite($fp, $buf) != strlen($buf)) {
					return 'Error during php unzip: could not write the whole buffer length';
				}
				fclose($fp);
				zip_entry_close($zip_entry);

				if (time() - $start > 55) {
					$server->extendTimeLimit();
					$start = time();
				}
			} else {
				return false;
			}
		}
		zip_close($zip);

		return true;
	}

	public function getSupportedExtension() {
		return 'zip';
	}

	public function isSupported() {
		global $server;

		foreach (array(
			'mkdir',
			'zip_open',
			'zip_entry_name',
			'zip_read',
			'zip_entry_read',
			'zip_entry_filesize',
			'zip_entry_close',
			'zip_close',
			'zip_entry_close',
		)
 as $functionName) {
			if (!$server->isPhpFunctionSupported($functionName)) {
				return false;
			}
		}

		return true;
	}

	public function getName() {
		return 'Extract .zip with PHP functions';
	}
}

class htmlPage {
	public function render($renderType, $args = array()) {
		global $config;
		$self          = basename(__FILE__);
		$deleteWarning = '
		<div class="alert alert-danger">
			<h4>Delete this Script File when Done</h4>
			<p>
				This script file can be used to compromise your Gallery 2 installation
			</p>
		</div>';

		echo '
<!DOCTYPE html>
<html>
	<head>
		<title>Gallery 2 Pre-Installer</title>';
		echo $this->getCSS();
		echo $this->getJS();

		echo '
	<body>
		<div class="container">
			<h1>The Gallery 2 Pre-Installer</h1>
			<span class="text-muted">v' . $config->scriptVersion . '</span><br><br>';

		if ($renderType == 'missingPassword') {
			echo '
			<div class="panel panel-danger">
				<div class="panel-heading">
					<h3 class="panel-title">Please complete the security check to proceed.</h3>
				</div>
				<div class="panel-body">
					You must enter a setup passphrase to run the preinstaller.
					<br>
					This is in the "PASSPHRASE" section at the top of this script file.
				</div>
			</div>';
		} elseif ($renderType == 'passwordTooShort') {
			echo '
			<div class="panel panel-danger">
				<div class="panel-heading">
					<h3 class="panel-title">Password Too Short</h3>
				</div>
				<div class="panel-body">
					The setup passphrase entered in this script file is too short. It must be at least 6 characters long.
				</div>
			</div>';
		} elseif ($renderType == 'passwordForm') {
			if (!empty($args['incorrectPassword'])) {
				echo '
			<div class="alert alert-danger">
				Password incorrect!
			</div>';
			}
			echo '
			<div class="panel panel-info">
				<div class="panel-heading">
					<h3 class="panel-title">Please enter the setup passphrase</h3>
					This is stored in the "PASSPHRASE" section at the top of this script file.
				</div>
				<div class="panel-body">
					<form class="form-horizontal" id="loginForm" method="post">
						<fieldset>
							<legend>Verification Form</legend>
							<div class="form-group">
								<label for="g2_password" class="col-xs-6 col-sm-4 control-label">PassPhrase:</label>
								<div class="col-xs-6 col-sm-8">
									<input class="form-control" name="g2_password" id="g2_password" placeholder="' . $folderName . '" type="password">
								</div>
							</div>
							<div class="form-group">
								<div class="col-xs-10 col-sm-offset-2">
									<input class="btn btn-primary" type="submit" value="Verify Me" onclick="this.disabled=true;this.form.submit();"/>
								</div>
							</div>
						</fieldset>
					</form>
				</div>
			</div>';
		} elseif ($renderType == 'options') {
			echo '
			<!-- Show available and unavailable options -->';

			if (empty($args['anyExtensionSupported'])) {
				echo '
			<div class="panel panel-danger">
				<div class="panel-heading">
					<h3 class="panel-title">
						This server is unable to extract any of our archive types!
					</h3>
				</div>
				<div class="panel-body">';

				$first = true;

				foreach ($args['extensions'] as $ext => $supported) {
					$textClass = $supported ? ' class="text-primary"' : ' class="text-muted"';

					echo "
					<span$textClass>";

					if (!$first) {
						echo ', ';
					} else {
						$first = false;
					}

					echo $ext;

					echo '
					</span>';
				}

				echo '
				</div>
			</div>';
			}
			echo '
			<h2>[A] General Preamble</h2>';
			echo $deleteWarning;
			echo '
			<div class="panel panel-info">
				<div class="panel-heading">
					<h3 class="panel-title">Instructions</h3>
				</div>
				<div class="panel-body">
					<span id="instructions-toggle" class="btn btn-default" onclick="BlockToggle(\'instructions\', \'instructions-toggle\', \'instructions\')">
						Show instructions
					</span>
					<div id="instructions" style="display: none;">
						<br>
						<h4>Introduction</h4>
						<p>
							Gallery 2 is a web application with thousands of files and hundreds of folders. Uploading these files and folders with an FTP program can take a very long time and can be error prone. This script puts Gallery 2 software on your server as an alternative to uploading the files and/or extracting an archive manually.
						</p>
						<p>
							The core capabilities are:
						</p>
						<ol>
							<li>
								<b>Transfer</b> the Gallery 2 codebase from the community repository directly to your webserver. No need to download / upload the file yourself
							</li>
							<li>
								<b>Extract</b> the Gallery 2 archive file directly on your server. No need to manually upload several files.
							</li>
							<li>
								<b>Install</b> Gallery 2 by providing a link to the Gallery 2 installation wizard which will guide you through steps involved in getting Gallery 2 up and running on your server.
							</li>
						</ol>
						<p>
							Once Gallery 2 is extracted, you can also use these convenience functions:
						</p>
						<ul>
							<li>
								<b>Change permissions</b>: When the Gallery 2 archive file has been extracted using this script, the files and folders may not be "owned" by you. That means that you may not be able to rename the Gallery 2 folder or to do much else with it unless you change the permissions as required. This convenience function allows this to be done easily.
							</li>
							<li>
								<b>Rename</b> the Gallery 2 folder if you want it to be &quot;photos/&quot; or &quot;gallery/&quot;, etc. rather than the default folder name which is &quot;gallery2/&quot;.
							</li>
						</ul>
						<h4>Upgrade Notes</h4>
						<p>
							To upgrade a Gallery 2 installation that was extracted with this script, your Gallery 2 folder may be "owned" by the webserver. If this is the case, make sure the Gallery 2 folder is named &quot;gallery2&quot; and that it has <em>755</em> or <em>777</em> permissions. Then download the latest release and extract it. This will extract the new files over the existing installation and you can then run the Gallery 2 upgrader.
						</p>
						<h4>Deleting Gallery 2</h4>
						<p>
							To delete a Gallery 2 installation that was extracted using this script (If you want to lose all your albums and remove Gallery 2 from your webserver), use the cleanup script which can be found at: <a href="http://codex.gallery2.org/index.php/Downloads:Cleanup_Script">Gallery Codex:Downloads:Cleanup_Script</a>.
						</p>
					</div>
				</div>
			</div>';

			if (!empty($args['statusMessage'])) {
				if (strpos($args['statusMessage'], 'Gallery 2 Archive not Detected') !== false) {
					$startPoint = 'Step 1';
					echo '
			<div class="panel panel-warning">';
				} elseif (strpos($args['statusMessage'], 'Gallery 2 Archive Detected') !== false) {
					$startPoint = 'Step 2';
					echo '
			<div class="panel panel-info">';
				} else {
					$startPoint = 'Step 3';
					echo '
			<div class="panel panel-success">';
				}
				echo '
				<div class="panel-heading">
					<h3 class="panel-title">Status</h3>
				</div>
				<div class="panel-body">' .
					$args['statusMessage'] .
				'</div>
			</div>';
			}

			echo '
			<h2>[B] Implementation Steps</h2>';
			echo $deleteWarning;
			echo '
			<!-- DOWNLOAD SECTION -->';
			$arbiter = $startPoint == 'Step 1' ? true : false;
			$label   = $arbiter ? 'Hide ' : 'Show ';
			$display = $arbiter ? '' : 'style="display: none;"';

			echo '
			<div class="panel panel-default">
				<div class="panel-heading">
					<h3>[1] Transfer Methods</h3>
					<span class="lead">Transfer Gallery 2 to this webserver</span>
					<br><br>
					<button id="download-toggle" class="btn btn-default" onclick="BlockToggle(\'download\', \'download-toggle\', \'transfer methods\')">' . $label . 'transfer methods
					</button>
					<br><br>
				</div>
				<div class="panel-body" id="download" ' . $display . '>';

			if (!empty($args['transferMethods']) && !empty($args['anyExtensionSupported'])) {
				echo '
				<!-- "stable", "tag", "master", "dev" -->
				<form class="form-horizontal" id="downloadForm" method="post">
					<fieldset>
						<legend>Transfer Gallery 2 to this Webserver</legend>';

				if ($config->allowFOpen === false) {
					echo '
						<div class="alert alert-warning">
							<h1>PHP\'s "allow_url_fopen" parameter is disabled.</h1>
							<p>
								You can continue with limited defaults as the Gallery 2 Pre-Installer is unable to interact with the Repository to suggest the best codebase for use.
								<br><br>
								Alternatively, enable "allow_url_fopen" before continuing.
							<p>
						</div>';
				}

				echo '
						<span class="text-info">Codebase:</span>
						<table class="table">
							<tr>
								<td>
									<select name="codebase">
										<option value="stable">
											Last Official Release - v2.3.2&nbsp;&nbsp;&nbsp;
										</option>';

				if ($args['showTagRelease'] === false
					&& $args['showStableRelease'] === true
				) {
					echo '
										<option value="stable" selected="selected">
											Community Stable Version - ' . $config->stableReleaseTag . ' (Recommended)&nbsp;&nbsp;&nbsp;
										</option>';
				} elseif ($args['showTagRelease'] === true
					&& $args['showStableRelease'] === false
				) {
					echo '
										<option value="tag" selected="selected">
											Community Release Candidate - ' . $config->releaseCandidateTag . ' (Recommended)&nbsp;&nbsp;&nbsp;
										</option>';
				} elseif ($args['showTagRelease'] === true
					&& $args['showStableRelease'] === true
				) {
					echo '
										<option value="stable">
											Community Stable Version - ' . $config->stableReleaseTag . '
										</option>
										<option value="tag" selected="selected">
											Community Release Candidate - ' . $config->releaseCandidateTag . ' (Recommended)&nbsp;&nbsp;&nbsp;
										</option>';
				}

				echo '
										<option value="master">
											Community Development Version - Github Master Branch&nbsp;&nbsp;&nbsp;
										</option>
										<option value="dev">
											Community Experimental Version - Github Dev Branch&nbsp;&nbsp;&nbsp;
										</option>
									</select>
								</td>
							</tr>
						</table>
						<span class="text-info">Type:</span>
						<table class="table table-striped table-hover">';

				$first = true;

				foreach ($args['extensions'] as $ext => $supported) {
					$disabled = empty($supported) ? "disabled='true'" : '';
					$message  = empty($supported) ? '<span class="label label-danger">Not Supported</span>' : '&nbsp;';
					$checked  = '';

					if ($first && $supported) {
						$checked = 'checked';
						$first   = false;
					}
					printf(
						'<tr><td><input type="radio" name="extension" value="%s" %s %s/></td><td>%s</td><td>%s</td></tr>',
						$ext,
						$disabled,
						$checked,
						strtoupper($ext) . ' File',
						$message
					);
				}

				echo '
						</table>
						<span class="text-info">Method:</span>
						<table class="table table-striped table-hover">';

				$first = true;

				foreach ($args['transferMethods'] as $method) {
					$disabled     = empty($method['isSupported']) ? "disabled='true'" : '';
					$notSupported = empty($method['isSupported']) ? '<span class="label label-danger">Not Supported</span>' : '&nbsp;';
					$checked      = '';

					if ($first && !empty($method['isSupported'])) {
						$checked = 'checked';
						$first   = false;
					}
					printf(
						'<tr><td><input type="radio" name="method" %s value="%s" %s/></td><td>%s</td><td>%s</td></tr>',
						$disabled,
						$method['command'],
						$checked,
						$method['name'],
						$notSupported
					);
				}

				echo '
						</table>
						<input type="hidden" name="command" value="download"/>
						<br>
						<input type="submit" class="btn btn-primary" value="Transfer" onclick="this.disabled=true;this.form.submit();"/>
					</fieldset>
				</form>';
			} elseif (!empty($args['anyExtensionSupported'])) {
				echo '
				<div class="alert alert-warning">
					<h4>This webserver does not support any of our file transfer methods</h4>
					<p>
						Please manually upload the gallery2.tar.gz or gallery2.zip files via FTP and continue from Step 2.
					</p>
				</div>';
			} elseif (!empty($args['transferMethods'])) {
				echo '
				<div class="alert alert-danger">
					<h4>This webserver cannot extract archives</h4>
					<p>
						Unfortunately, it is not possible to use the Gallery 2 Pre-Installer on this webserver.
						<br>
						Manually download the archive and extract it on your local computer.
						<br>
						Once done, upload the files and folders to your site via FTP.
					</p>
				</div>';
			} else {
				echo '
				<div class="alert alert-danger">
					<h4>This platform does not support any of our file handling methods</h4>
					<p>
						Unfortunately, it is not possible to use the Gallery 2 Pre-Installer on this webserver.
						<br>
						Manually download the archive and extract it on your local computer.
						<br>
						Once done, upload the files and folders to your site via FTP.
					</p>
				</div>';
			}

			echo '
			</div>
		</div>
		<!-- EXTRACTION METHODS -->';

			$arbiter = $startPoint == 'Step 2' ? true : false;
			$label   = $arbiter ? 'Hide ' : 'Show ';
			$display = $arbiter ? '' : 'style="display: none;"';

			echo '
		<div class="panel panel-default">
			<div class="panel-heading">
				<h3>[2] Extraction Methods</h3>
				<span class="lead">Extract Gallery 2 Archive File</span>
				<br><br>
				<button id="extract-toggle" class="btn btn-default" onclick="BlockToggle(\'extract\', \'extract-toggle\', \'extraction methods\')">' . $label . 'extraction methods
				</button>
				<br><br>
			</div>
			<div class="panel-body" id="extract" ' . $display . '>';

			if (!empty($args['anyExtensionSupported'])) {
				$first       = true;
				$available   = false;
				$extractData = array();

				foreach ($args['extractMethods'] as $method) {
					$disabled = "disabled='true'";

					if (!$method['archiveExists']) {
						$message = "<span class='label label-danger'>Get " . $method['archiveName'] . '</span>';
					} elseif (empty($method['isSupported'])) {
						$message   = '<span class="label label-danger">Not Supported</span>';
						$available = true;
					} else {
						$message   = "<span class='label label-success'>Available</span>";
						$disabled  = '';
						$available = true;
					}
					$checked = '';

					if ($first && empty($disabled) && !empty($method['isSupported'])) {
						$checked = 'checked';
						$first   = false;
					}

					$extractData[] = sprintf(
						'<tr><td><input type="radio" name="method" %s value="%s" %s/></td><td>%s</td><td class="align-middle">%s</td></tr>',
						$disabled,
						$method['command'],
						$checked,
						$method['name'],
						$message
					);

					$i++;
				}

				if (!$available) {
					echo '
				<div class="alert alert-info">
					Please Transfer the Gallery 2 Archive First (Step 1)
				</div>';
				} else {
					echo '
				<form class="form-horizontal" id="extractForm" method="post">
					<fieldset>
						<legend>Select Extraction Method</legend>
						<table class="table table-striped table-hover">';

					for ($i = 0; $i <= 3; $i++) {
						echo $extractData[$i];
					}

					echo '
						</table>
						<input type="hidden" name="command" value="extract"/>
						<br>
						<input type="submit" class="btn btn-primary" value="Extract Archive" onclick="this.disabled=true;this.form.submit();"/>
					</fieldset>
				</form>';
				}
			} else {
				echo '
				<div class="alert alert-warning">
					<h4>This Platform Cannot Extract Archives</h4>
					Ask your web hosting provider to extract the archive for you
					or if that is not an option, you will have to extract the archive on your local
					computer and upload the files and folders to your site via FTP.
				</div>';
			}
			$arbiter    = $startPoint == 'Step 3' ? true : false;
			$label      = $arbiter ? 'Hide ' : 'Show ';
			$display    = $arbiter ? '' : 'style="display: none;"';
			$folderName = empty($args['gallery2FolderName']) ? 'gallery2' : $args['gallery2FolderName'];

			echo '
			</div>
		</div>
		<!-- LINK TO INSTALLER -->
		<div class="panel panel-default">
			<div class="panel-heading">
				<h3>[3] Install Gallery 2</h3>
				<span class="lead">Run the Gallery 2 Installation Wizard</span>
				<br><br>
				<button id="install-toggle" class="btn btn-default" onclick="BlockToggle(\'install\', \'install-toggle\', \'link to install wizard\')">' . $label . 'Link to Install Wizard
				</button>
				<br><br>
			</div>
			<div class="panel-body" id="install" ' . $display . '>';

			if (file_exists($folderName . '/install/index.php')) {
				echo '
				<div class="col-sm-8">
					<span class = "lead">Click to Start the Gallery 2 Installation Wizard</span>
					<br>
					<span class = "lead">You may wish to first change the folder name</span>
					<br>
					<span class = "lead">(Use the  provided convenience function below)</span>
					<br>
				</div>
				<div class="col-sm-4">
					<br>
					<a href="' . $folderName . '/install/index.php" class="btn btn-primary">Install Gallery 2</a>
				</div>';
			} else {
				if ($config->stuckOnTwo) {
					echo '
				<div class="alert alert-info">
					Please Extract the Gallery 2 Archive First (Step 2)
				</div>';
				} else {
					echo '
				<div class="alert alert-info">
					Please Transfer and Extract the Gallery 2 Archive First (Steps 1 and 2)
				</div>';
				}
			}

			echo '
			</div>
		</div>';
			echo '
		<h2>[C] Convenience Functions</h2>';
			echo $deleteWarning;
			echo '
		<!-- CHANGE PERMISSIONS -->
		<div class="panel panel-default">
			<div class="panel-heading">
				<h3>Change Gallery 2 Folder Permissions</h3>
				<span class="lead">Change folder permissions if required</span>
				<br><br>
				<button id="chmod-toggle" class="btn btn-default" onclick="BlockToggle(\'chmod\', \'chmod-toggle\', \'change permissions form\')">' . $label . 'change permissions
				</button>
				<br><br>
			</div>
			<div class="panel-body" id="chmod" ' . $display . '>';

			if ($folderName) {
				echo '
				<div class="alert alert-info">
					<p>
						NB: If you are running PHP-CGI, you might already have the required permissions and no further changes are required. Your web hosting provider will be able to advise.
					</p>
					<br>
					<ul>
						<li>
							<em>777</em> permissions makes the folder writeable and readable for everybody.
						</li>
						<li>
							<em>755</em> permissions makes it writeable and readable for the "Owner" and only readable for others.
						</li>
						<li>
							<em>555</em> permissions makes it readable for everybody but disallows amendments by anyone.
						</li>
					</ul>
					<p>
						To improve <em>security</em>, change the folder permissions to <em>755</em> or <em>555</em> once Gallery 2 is up and running.
					</p>
				</div>
				<br>
				<form class="form-horizontal" id="chmodForm" method="post">
					<fieldset>
						<legend>Change Permissions</legend>
						<div class="form-group">
							<label for="folderName" class="col-xs-6 col-sm-4 control-label">
								Folder Name:
							</label>
							<div class="col-xs-6 col-sm-8">
								<input class="form-control" name="folderName" id="folderName" placeholder="' . $folderName . '" value="' . $folderName . '" type="text">
							</div>
						</div>
						<div class="form-group">
							<label for="folderPermissions" class="col-xs-6 col-sm-4 control-label">
								Permissions:
							</label>
							<div class="col-xs-6 col-sm-8">
								<select class="form-control" name="folderPermissions" id="folderPermissions">';

				foreach ($config->folderPermissionList as $perm) {
					echo '
									<option value="' . $perm . '">' . $perm . '</option>';
				}

				echo '
								</select>
							</div>
						</div>
						<input type="hidden" name="command" value="chmod"/>
						<div class="form-group">
							<div class="col-xs-10 col-sm-offset-2">
								<input class="btn btn-primary" type="submit" value="Change Permissions" onclick="this.disabled=true;this.form.submit();"/>
							</div>
						</div>
					</fieldset>
				</form>';
			} else {
				echo '
				<div class="alert alert-warning">
					There is no Gallery 2 folder in the current server folder.
				</div>';
			}

			echo '
			</div>
		</div>
		<!-- RENAME FOLDER -->
		<div class="panel panel-default">
			<div class="panel-heading">
				<h3>Rename Gallery 2 Folder</h3>
				<span class="lead">
					Rename folder if required
				</span>
				<br><br>
				<button id="rename-toggle" class="btn btn-default" onclick="BlockToggle(\'rename\', \'rename-toggle\', \'rename folder form\')">' . $label . 'rename folder
				</button>
				<br><br>
			</div>
			<div class="panel-body" id="rename" ' . $display . '>';

			if (!empty($args['gallery2FolderName'])) {
				echo '
				<form class="form-horizontal" id="renameForm" method="post">
					<fieldset>
						<legend>Rename Folder</legend>
						<div class="form-group">
							<label for="folderName" class="col-sm-3 control-label">
								Rename folder to:
							</label>
							<div class="col-sm-9">
								<input class="form-control" name="folderName" id="folderName" placeholder="Type New Name Here" type="text">
							</div>
						</div>
						<input type="hidden" name="command" value="rename"/>
						<br><br>
						<div class="form-group">
							<div class="col-xs-10 col-sm-offset-2">
								<input class="btn btn-primary" type="submit" value="Rename Folder" onclick="this.disabled=true;this.form.submit();"/>
							</div>
						</div>
					</fieldset>
				</form>';
			} else {
				echo '
				<div class="alert alert-info">
					Gallery 2 Folder not Detected in Current Server Folder
				</div>';
			}

			echo '
			</div>
		</div>';
		} elseif ($renderType == 'results') {
			echo '
			<h2> Results </h2>';

			if (!empty($args['failure'])) {
				echo '
			<div class="alert alert-danger">
				<div>' . $args['failure'] . '</div>';

				if (!empty($args['fix'])) {
					echo '
				<div><h4> Suggested fix: </h4>' . $args['fix'] . '</div>';
				}
				echo '</div>';
			}

			if (!empty($args['success'])) {
				echo '
			<div class="alert alert-success">' . $args['success'] . '</div>';
			}

			if (!empty($args['info'])) {
				echo '
			<div class="alert alert-info">' . $args['info'] . '</div>';
			}

			echo '
			<div>
				<a class="btn btn-primary" href="' . $self . '">Back to Overview</a>
			</div>';
		}
		echo '
		</div>
	</body>
</html>';

		// We always exit after rendering
		exit;
	}

	public function getCSS() {
		return '
		<style type="text/css">
			/*!
			 * bootswatch v3.3.7
			 * Homepage: http://bootswatch.com
			 * Copyright 2012-2016 Thomas Park
			 * Licensed under MIT
			 * Based on Bootstrap
			 */
			/*!
			 * Bootstrap v3.3.7 (http://getbootstrap.com)
			 * Copyright 2011-2016 Twitter, Inc.
			 * Licensed under MIT (https://github.com/twbs/bootstrap/blob/master/LICENSE)
			 */
			*,
			*:before,
			*:after {
				box-sizing: inherit;
				vertical-align: baseline;
			}

			html {
				box-sizing: border-box;
				font: 16px/1.25 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol";
				font-weight: 200;
				-webkit-tap-highlight-color: rgba(0, 0, 0, 0);
			}

			body {
				background: #ffffff;
				color: #77777;
				margin: 10px auto;
			}

			/*! normalize.css v3.0.3 | MIT License | github.com/necolas/normalize.css */
			a {
				background-color: transparent;
			}

			a:active,
			a:hover {
				outline: 0;
			}

			b {
				font-weight: bold;
			}

			button,
			input,
			select {
				color: inherit;
				font: inherit;
				margin: 0;
			}

			button {
				overflow: visible;
			}

			button,
			select {
				text-transform: none;
			}

			button,
			input[type="submit"] {
				-webkit-appearance: button;
				cursor: pointer;
			}

			html input[disabled] {
				cursor: default;
			}

			button::-moz-focus-inner,
			input::-moz-focus-inner {
				border: 0;
				padding: 0;
			}

			input {
				line-height: normal;
			}

			input[type="radio"] {
				-webkit-box-sizing: border-box;
				-moz-box-sizing: border-box;
				box-sizing: border-box;
				padding: 0;
			}

			fieldset {
				border: 1px solid #c0c0c0;
				margin: 0 2px;
				padding: 0.35em 0.625em 0.75em;
			}

			legend {
				border: 0;
				padding: 0;
			}

			table {
				border-collapse: collapse;
				border-spacing: 0;
			}

			td {
				padding: 0;
			}

			/*! Source: https://github.com/h5bp/html5-boilerplate/blob/master/src/css/main.css */
			@media print {
				*,
				*:before,
				*:after {
					background: transparent !important;
					color: #000 !important;
					-webkit-box-shadow: none !important;
					box-shadow: none !important;
					text-shadow: none !important;
				}

				a,
				a:visited {
					text-decoration: underline;
				}

				a[href]:after {
					content: " ("attr(href) ")";
				}

				tr {
					page-break-inside: avoid;
				}

				p,
				h2,
				h3 {
					orphans: 3;
					widows: 3;
				}

				h2,
				h3 {
					page-break-after: avoid;
				}

				.table {
					border-collapse: collapse !important;
				}

				.table td {
					background-color: #fff !important;
				}
			}

			input,
			button,
			select {
				font-family: inherit;
				font-size: inherit;
				line-height: inherit;
			}

			a:hover,
			a:focus {
				color: #0a6ebd;
				text-decoration: underline;
			}

			a:focus {
				outline: 5px auto -webkit-focus-ring-color;
				outline-offset: -2px;
			}

			p {
				margin: 0 0 11.5px;
			}

			.lead {
				font-size: 14px;
				font-weight: 100;
				margin: 0 0 2.25rem;
				padding: 0 0 1.5rem;
				color: #888 !Important;
			}

			.text-success {
				color: #4caf50;
			}

			.text-info {
				color: #6c757d;
			}

			.text-warning {
				color: #ff9800;
			}

			.text-muted {
				color: #bbbbbb;
			}

			.text-primary {
				color: #2196f3;
			}

			.text-justify {
			  text-align: justify;
			}

			ul,
			ol {
				margin-top: 0;
				margin-bottom: 11.5px;
			}

			.container {
				margin-right: auto;
				margin-left: auto;
				padding-left: 15px;
				padding-right: 15px;
			}

			@media (min-width: 768px) {
				.container {
					width: 750px;
				}
			}

			@media (min-width: 992px) {
				.container {
					width: 970px;
				}
			}

			@media (min-width: 1200px) {
				.container {
					width: 1170px;
				}
			}

			.col-sm-2,
			.col-sm-3,
			.col-sm-4,
			.col-sm-8,
			.col-sm-9,
			.col-sm-10,
			.col-xs-4
			.col-xs-6
			.col-xs-8,
			.col-xs-10 {
				position: relative;
				min-height: 1px;
				padding-left: 15px;
				padding-right: 15px;
			}

			.col-xs-4,
			.col-xs-6,
			.col-xs-8,
			.col-xs-10 {
				float: left;
			}

			.col-xs-10 {
				width: 83.33333333%;
			}

			.col-xs-8 {
				width: 66.66666667%;
			}

			.col-xs-6 {
				width: 50%;
			}

			.col-xs-4 {
				width: 33.33333333%;
			}

			@media (min-width: 768px) {

				.col-sm-2,
				.col-sm-3,
				.col-sm-4,
				.col-sm-8,
				.col-sm-9,
				.col-sm-10 {
					float: left;
				}

				.col-sm-10 {
					width: 83.33333333%;
				}

				.col-sm-9 {
					width: 75%;
				}

				.col-sm-8 {
					width: 66.66666667%;
				}

				.col-sm-4 {
					width: 33.33333333%;
				}

				.col-sm-3 {
					width: 25%;
				}

				.col-sm-2 {
					width: 16.66666667%;
				}

				.col-sm-offset-2 {
					margin-left: 16.66666667%;
				}
			}

			table {
				background-color: transparent;
			}

			.table {
				width: 100%;
				max-width: 100%;
				margin-bottom: 23px;
			}

			.table>tbody>tr>td {
				padding: 8px;
				line-height: 1.846;
				vertical-align: top;
			}

			.table-striped>tbody>tr:nth-of-type(odd) {
				background-color: #f9f9f9;
			}

			.table-hover>tbody>tr:hover {
				background-color: #f5f5f5;
			}

			fieldset {
				padding: 0;
				margin: 0;
				border: 0;
				min-width: 0;
			}

			legend {
				display: block;
				width: 100%;
				padding: 0;
				margin-bottom: 23px;
				font-size: 19.5px;
				line-height: inherit;
				color: #212121;
				border: 0;
				border-bottom: 1px solid #e5e5e5;
			}

			label {
				display: inline-block;
				max-width: 100%;
				margin-bottom: 5px;
				font-weight: bold;
			}

			input[type="radio"] {
				margin: 4px 0 0;
				margin-top: 1px \9;
				line-height: normal;
			}

			input[type="radio"]:focus {
				outline: 5px auto -webkit-focus-ring-color;
				outline-offset: -2px;
			}

			.form-control {
				display: block;
				width: 100%;
				height: 37px;
				padding: 6px 16px;
				font-size: 13px;
				line-height: 1.846;
				color: #666666;
				background-color: transparent;
				background-image: none;
				border: 1px solid transparent;
				border-radius: 3px;
				-webkit-box-shadow: inset 0 1px 1px rgba(0, 0, 0, 0.075);
				box-shadow: inset 0 1px 1px rgba(0, 0, 0, 0.075);
				-webkit-transition: border-color ease-in-out .15s, -webkit-box-shadow ease-in-out .15s;
				-o-transition: border-color ease-in-out .15s, box-shadow ease-in-out .15s;
				transition: border-color ease-in-out .15s, box-shadow ease-in-out .15s;
			}

			.form-control:focus {
				border-color: #66afe9;
				outline: 0;
				-webkit-box-shadow: inset 0 1px 1px rgba(0, 0, 0, .075), 0 0 8px rgba(102, 175, 233, 0.6);
				box-shadow: inset 0 1px 1px rgba(0, 0, 0, .075), 0 0 8px rgba(102, 175, 233, 0.6);
			}

			.form-control::-moz-placeholder {
				color: #bbbbbb;
				opacity: 1;
			}

			.form-control:-ms-input-placeholder {
				color: #bbbbbb;
			}

			.form-control::-webkit-input-placeholder {
				color: #bbbbbb;
			}

			.form-control::-ms-expand {
				border: 0;
				background-color: transparent;
			}

			.form-group {
				margin-bottom: 15px;
			}

			input[type="radio"][disabled] {
				cursor: not-allowed;
			}

			.form-horizontal .form-group {
				margin-left: -15px;
				margin-right: -15px;
			}

			@media (min-width: 768px) {
				.form-horizontal .control-label {
					text-align: right;
					margin-bottom: 0;
					padding-top: 7px;
				}
			}

			.btn {
				display: inline-block;
				margin-bottom: 0;
				font-weight: normal;
				text-align: center;
				vertical-align: middle;
				-ms-touch-action: manipulation;
				touch-action: manipulation;
				cursor: pointer;
				background-image: none;
				border: 1px solid transparent;
				white-space: nowrap;
				padding: 6px 16px;
				font-size: 13px;
				line-height: 1.846;
				border-radius: 3px;
				-webkit-user-select: none;
				-moz-user-select: none;
				-ms-user-select: none;
				user-select: none;
			}

			.btn:focus,
			.btn:active:focus {
				outline: 5px auto -webkit-focus-ring-color;
				outline-offset: -2px;
			}

			.btn:hover,
			.btn:focus {
				color: #444444;
				text-decoration: none;
			}

			.btn:active {
				outline: 0;
				background-image: none;
				-webkit-box-shadow: inset 0 3px 5px rgba(0, 0, 0, 0.125);
				box-shadow: inset 0 3px 5px rgba(0, 0, 0, 0.125);
			}

			.btn-default {
				color: #444444;
				background-color: #ffffff;
				border-color: transparent;
			}

			.btn-default:focus {
				color: #444444;
				background-color: #e6e6e6;
				border-color: rgba(0, 0, 0, 0);
			}

			.btn-default:hover {
				color: #444444;
				background-color: #e6e6e6;
				border-color: rgba(0, 0, 0, 0);
			}

			.btn-default:active {
				color: #444444;
				background-color: #e6e6e6;
				border-color: rgba(0, 0, 0, 0);
			}

			.btn-default:active:hover,
			.btn-default:active:focus {
				color: #444444;
				background-color: #d4d4d4;
				border-color: rgba(0, 0, 0, 0);
			}

			.btn-default:active {
				background-image: none;
			}

			.btn-primary {
				color: #ffffff;
				background-color: #2196f3;
				border-color: transparent;
			}

			.btn-primary:focus {
				color: #ffffff;
				background-color: #0c7cd5;
				border-color: rgba(0, 0, 0, 0);
			}

			.btn-primary:hover {
				color: #ffffff;
				background-color: #0c7cd5;
				border-color: rgba(0, 0, 0, 0);
			}

			.btn-primary:active {
				color: #ffffff;
				background-color: #0c7cd5;
				border-color: rgba(0, 0, 0, 0);
			}

			.btn-primary:active:hover,
			.btn-primary:active:focus {
				color: #ffffff;
				background-color: #0a68b4;
				border-color: rgba(0, 0, 0, 0);
			}

			.btn-primary:active {
				background-image: none;
			}

			.label {
				display: inline;
				padding: .2em .6em .3em;
				font-size: 75%;
				font-weight: bold;
				line-height: 1;
				color: #ffffff;
				text-align: center;
				white-space: nowrap;
				vertical-align: baseline;
				border-radius: .25em;
			}

			.label-success {
				background-color: #4caf50;
			}

			.label-danger {
				background-color: #e51c23;
			}

			.alert {
				padding: 15px;
				margin-bottom: 23px;
				border: 1px solid transparent;
				border-radius: 3px;
			}

			.alert h4 {
				margin-top: 0;
				color: inherit;
			}

			.alert>p {
				margin-bottom: 0;
			}

			.alert>p+p {
				margin-top: 5px;
			}

			.alert-success {
				background-color: #dff0d8;
				border-color: #d6e9c6;
				color: #4caf50;
			}

			.alert-danger {
				background-color: #e51c23;
				border-color: #f7a4af;
				color: #e51c23;
			}

			.alert-info {
				background-color: #e1bee7;
				border-color: #cba4dd;
				color: #6c757d;
			}

			.alert-warning {
				background-color: #ffe0b2;
				border-color: #ffc599;
				color: #ff9800;
			}

			.panel {
				margin-bottom: 23px;
				background-color: #ffffff;
				border: 1px solid transparent;
				border-radius: 3px;
				-webkit-box-shadow: 0 1px 1px rgba(0, 0, 0, 0.05);
				box-shadow: 0 1px 1px rgba(0, 0, 0, 0.05);
			}

			.panel-body {
				padding: 15px;
			}

			.panel-heading {
				padding: 10px 15px;
				border-bottom: 1px solid transparent;
				border-top-right-radius: 2px;
				border-top-left-radius: 2px;
			}

			.panel-title {
				margin-top: 0;
				margin-bottom: 0;
				font-size: 15px;
				color: inherit;
			}

			.panel-default {
				border-color: #dddddd;
			}

			.panel-default>.panel-heading {
				color: #212121;
				background-color: #f5f5f5;
				border-color: #dddddd;
			}

			.panel-success {
				border-color: #d6e9c6;
			}

			.panel-success>.panel-heading {
				color: #ffffff;
				background-color: #4caf50;
				border-color: #d6e9c6;
			}

			.panel-info {
				border-color: #cba4dd;
			}

			.panel-info>.panel-heading {
				color: #ffffff;
				background-color: #6c757d;
				border-color: #cba4dd;
			}

			.panel-warning {
				border-color: #ffc599;
			}

			.panel-warning > .panel-heading {
				color: #ffffff;
				background-color: #ff9800;
				border-color: #ffc599;
			}

			.panel-danger {
				border-color: #f7a4af;
			}

			.panel-danger>.panel-heading {
				color: #ffffff;
				background-color: #e51c23;
				border-color: #f7a4af;
			}

			.container:before,
			.container:after,
			.form-horizontal .form-group:before,
			.form-horizontal .form-group:after,
			.panel-body:before,
			.panel-body:after {
				content: " ";
				display: table;
			}

			.container:after,
			.form-horizontal .form-group:after,
			.panel-body:after {
				clear: both;
			}

			@-ms-viewport {
				width: device-width;
			}

			.btn-default {
				position: relative;
			}

			.btn-default:focus {
				background-color: #ffffff;
			}

			.btn-default:hover,
			.btn-default:active:hover {
				background-color: #f0f0f0;
			}

			.btn-default:active {
				-webkit-box-shadow: 2px 2px 4px rgba(0, 0, 0, 0.4);
				box-shadow: 2px 2px 4px rgba(0, 0, 0, 0.4);
			}

			.btn-default:after {
				content: "";
				display: block;
				position: absolute;
				width: 100%;
				height: 100%;
				top: 0;
				left: 0;
				background-image: -webkit-radial-gradient(circle, #444444 10%, transparent 10.01%);
				background-image: -o-radial-gradient(circle, #444444 10%, transparent 10.01%);
				background-image: radial-gradient(circle, #444444 10%, transparent 10.01%);
				background-repeat: no-repeat;
				-webkit-background-size: 1000% 1000%;
				background-size: 1000% 1000%;
				background-position: 50%;
				opacity: 0;
				pointer-events: none;
				-webkit-transition: background .5s, opacity 1s;
				-o-transition: background .5s, opacity 1s;
				transition: background .5s, opacity 1s;
			}

			.btn-default:active:after {
				-webkit-background-size: 0% 0%;
				background-size: 0% 0%;
				opacity: .2;
				-webkit-transition: 0s;
				-o-transition: 0s;
				transition: 0s;
			}

			.btn-primary {
				position: relative;
			}

			.btn-primary:focus {
				background-color: #2196f3;
			}

			.btn-primary:hover,
			.btn-primary:active:hover {
				background-color: #0d87e9;
			}

			.btn-primary:active {
				-webkit-box-shadow: 2px 2px 4px rgba(0, 0, 0, 0.4);
				box-shadow: 2px 2px 4px rgba(0, 0, 0, 0.4);
			}

			.btn-primary:after {
				content: "";
				display: block;
				position: absolute;
				width: 100%;
				height: 100%;
				top: 0;
				left: 0;
				background-image: -webkit-radial-gradient(circle, #ffffff 10%, transparent 10.01%);
				background-image: -o-radial-gradient(circle, #ffffff 10%, transparent 10.01%);
				background-image: radial-gradient(circle, #ffffff 10%, transparent 10.01%);
				background-repeat: no-repeat;
				-webkit-background-size: 1000% 1000%;
				background-size: 1000% 1000%;
				background-position: 50%;
				opacity: 0;
				pointer-events: none;
				-webkit-transition: background .5s, opacity 1s;
				-o-transition: background .5s, opacity 1s;
				transition: background .5s, opacity 1s;
			}

			.btn-primary:active:after {
				-webkit-background-size: 0% 0%;
				background-size: 0% 0%;
				opacity: .2;
				-webkit-transition: 0s;
				-o-transition: 0s;
				transition: 0s;
			}

			.btn {
				text-transform: uppercase;
				border: none;
				-webkit-box-shadow: 1px 1px 4px rgba(0, 0, 0, 0.4);
				box-shadow: 1px 1px 4px rgba(0, 0, 0, 0.4);
				-webkit-transition: all 0.4s;
				-o-transition: all 0.4s;
				transition: all 0.4s;
			}

			body {
				-webkit-font-smoothing: antialiased;
				letter-spacing: .1px;
			}

			p {
				margin: 0 0 1em;
			}

			input,
			button {
				-webkit-font-smoothing: antialiased;
				letter-spacing: .1px;
			}

			a {
				-webkit-transition: all 0.2s;
				-o-transition: all 0.2s;
				transition: all 0.2s;
			}

			.table-hover>tbody>tr,
			.table-hover>tbody>tr>td {
				-webkit-transition: all 0.2s;
				-o-transition: all 0.2s;
				transition: all 0.2s;
			}

			label {
				font-weight: normal;
			}

			input.form-control,
			input[type=text],
			[type=text].form-control {
				padding: 0;
				border: none;
				border-radius: 0;
				-webkit-appearance: none;
				-webkit-box-shadow: inset 0 -1px 0 #dddddd;
				box-shadow: inset 0 -1px 0 #dddddd;
				font-size: 16px;
			}

			input.form-control:focus,
			input[type=text]:focus,
			[type=text].form-control:focus {
				-webkit-box-shadow: inset 0 -2px 0 #2196f3;
				box-shadow: inset 0 -2px 0 #2196f3;
			}

			select,
			select.form-control {
				border: 0;
				border-radius: 0;
				-webkit-appearance: none;
				-moz-appearance: none;
				appearance: none;
				padding-left: 0;
				padding-right: 0\9;
				background-image: url(data:image/png;
				base64, iVBORw0KGgoAAAANSUhEUgAAABoAAAAaCAMAAACelLz8AAAAJ1BMVEVmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmaP/QSjAAAADHRSTlMAAgMJC0uWpKa6wMxMdjkoAAAANUlEQVR4AeXJyQEAERAAsNl7Hf3X6xt0QL6JpZWq30pdvdadme+0PMdzvHm8YThHcT1H7K0BtOMDniZhWOgAAAAASUVORK5CYII=);
				-webkit-background-size: 13px 13px;
				background-size: 13px;
				background-repeat: no-repeat;
				background-position: right center;
				-webkit-box-shadow: inset 0 -1px 0 #dddddd;
				box-shadow: inset 0 -1px 0 #dddddd;
				font-size: 16px;
				line-height: 1.5;
			}

			select::-ms-expand,
			select.form-control::-ms-expand {
				display: none;
			}

			select:focus,
			select.form-control:focus {
				-webkit-box-shadow: inset 0 -2px 0 #2196f3;
				box-shadow: inset 0 -2px 0 #2196f3;
				background-image: url(data:image/png;
				base64, iVBORw0KGgoAAAANSUhEUgAAABoAAAAaCAMAAACelLz8AAAAJ1BMVEUhISEhISEhISEhISEhISEhISEhISEhISEhISEhISEhISEhISEhISF8S9ewAAAADHRSTlMAAgMJC0uWpKa6wMxMdjkoAAAANUlEQVR4AeXJyQEAERAAsNl7Hf3X6xt0QL6JpZWq30pdvdadme+0PMdzvHm8YThHcT1H7K0BtOMDniZhWOgAAAAASUVORK5CYII=);
			}

			input[type="radio"] {
				position: relative;
				margin-top: 6px;
				margin-right: 4px;
				vertical-align: top;
				border: none;
				background-color: transparent;
				-webkit-appearance: none;
				appearance: none;
				cursor: pointer;
			}

			input[type="radio"]:focus {
				outline: none;
			}

			input[type="radio"]:before,
			input[type="radio"]:after {
				content: "";
				display: block;
				width: 18px;
				height: 18px;
				border-radius: 50%;
				-webkit-transition: 240ms;
				-o-transition: 240ms;
				transition: 240ms;
			}

			input[type="radio"]:before {
				position: absolute;
				left: 0;
				top: -3px;
				background-color: #2196f3;
				-webkit-transform: scale(0);
				-ms-transform: scale(0);
				-o-transform: scale(0);
				transform: scale(0);
			}

			input[type="radio"]:after {
				position: relative;
				top: -3px;
				border: 2px solid #666666;
			}

			input[type="radio"]:checked:before {
				-webkit-transform: scale(0.5);
				-ms-transform: scale(0.5);
				-o-transform: scale(0.5);
				transform: scale(0.5);
			}

			input[type="radio"]:disabled:checked:before {
				background-color: #bbbbbb;
			}

			input[type="radio"]:checked:after {
				border-color: #2196f3;
			}

			input[type="radio"]:disabled:after,
			input[type="radio"]:disabled:checked:after {
				border-color: #bbbbbb;
			}

			.alert {
				border: none;
				color: #fff;
			}

			.alert-success {
				background-color: #4caf50;
			}

			.alert-info {
				background-color: #6c757d;
			}

			.alert-warning {
				background-color: #ff9800;
			}

			.panel {
				border: none;
				border-radius: 2px;
				-webkit-box-shadow: 0 1px 4px rgba(0, 0, 0, 0.3);
				box-shadow: 0 1px 4px rgba(0, 0, 0, 0.3);
			}

			.panel-heading {
				border-bottom: none;
			}

			.align-middle {
			  vertical-align: middle !important;
			}

			/*!
			 * Hydrogen CSS
			 * Copyright 2018 Pim Brouwers
			 * Licensed under MIT
			 *https://github.com/pimbrouwers/hydrogen
			 */
			em {
				font-weight: 700;
				font-style: italic;
			}

			em::before,
			em::after {
				content: "\""
			}

			ol,
			p,
			ul {
				margin: 0 0 1rem 0;
			}

			ol,
			p,
			ul {
				line-height: 1.5;
			}

			ol,
			ul {
				padding: 0;
			}

			ol li,
			ul li {
				margin-left: 1.125rem;
			}

			a {
				color: #0080ff;
				font-weight: 300;
				text-decoration: none;
			}

			a:hover,
			a:focus {
				color: #0a6ebd;
				text-decoration: underline;
			}

			a:focus {
				outline: 5px auto -webkit-focus-ring-color;
				outline-offset: -2px;
			}


			ul {
				list-style-type: square;
			}

			ol {
				list-style-type: upper-roman;
			}

			h1 {
				font-size: 4.0rem;
				font-weight: 100;
				color: #000;
				letter-spacing: 1px;
				margin: 3rem 0 1.5rem;
				border-bottom: 1px solid #ddd;
			}

			h2 {
				font-size: 2rem;
				font-weight: 200;
				margin: 2.25rem 0 1rem;
				padding: 0.5rem 0 1rem;
			}

			h3 {
				font-size: 1.5rem;
				font-weight: 300;
				color: #707070;
				margin: 1.75rem 0 0.25rem 0;
			}

			h4 {
				font-size: 1.25rem;
				font-weight: 400;
				margin: 1.25rem 0 0.25rem 0;
				color: maroon;
				font-variant: small-caps;
			}

			@media (max-width: 991px) {
				h1 {
					font-size: 3.5rem;
				}

				h2 {
					font-size: 1.75rem;
				}
			}

			@media (max-width: 767px) {
				h1 {
					font-size: 2.25rem;
				}

				h2 {
					font-size: 1.5rem;
				}

				h3 {
					font-size: 1.25rem;
				}

				h4 {
					font-size: 1.125rem;
				}
			}
		</style>
		';
	}

	public function getJS() {
		return '
		<script type="text/javascript">
			function BlockToggle(objId, togId, text) {
				var o = document.getElementById(objId), t = document.getElementById(togId);
				if (o.style.display == "none") {
					o.style.display = "block";
					t.innerHTML = "Hide " + text;
				} else {
					o.style.display = "none";
					t.innerHTML = "Show " + text;
				}
			}
		</script>
	</head>
		';
	}
}

	// ---------- START 3rd Party code for tar.gz extraction ---------------------
class tarArchiveHandler {
	// --------------------------------------------------------------------------------
	// PhpConcept Library - Tar Module 1.3
	// --------------------------------------------------------------------------------
	// License GNU/GPL - Vincent Blavet - August 2001
	// http://www.phpconcept.net
	// --------------------------------------------------------------------------------
	// Note:
	//  Small changes have been made by Andy Staudacher <ast@gmx.ch> to incorporate
	//  the code in this script. Code to create new archives has been removed,
	//  we only need to extract archives. Date: 03 Feb 2006
	//
	//  Additional changes by Dayo Akanji to wrap this in a class. Date: 29 June 2018
	// --------------------------------------------------------------------------------

	// --------------------------------------------------------------------------------
	// Function : PclTarExtract()
	// Description :
	//   Extract all the files present in the archive $p_tarname, in the directory
	//   $p_path. The relative path of the archived files are kept and become
	//   relative to $p_path.
	//   If a file with the same name already exists, it will be replaced.
	//   If the path to the file does not exist, it will be created.
	//   Depending on the $p_tarname extension (.tar, .tar.gz or .tgz) the
	//   function will determine the type of the archive.
	// Parameters :
	//   $p_tarname : Name of an existing tar file.
	//   $p_path : Path where the files will be extracted. The files will use
	//          their memorized path from $p_path.
	//          If $p_path is "", files will be extracted in "./".
	//   $p_remove_path : Path to remove (from the file memorized path) while writing the
	//                  extracted files. If the path does not match the file path,
	//                  the file is extracted with its memorized path.
	//                  $p_path and $p_remove_path are commulative.
	//   $p_mode : 'tar' or 'tgz', if not set, will be determined by $p_tarname extension
	// Return Values :
	//   Same as PclTarList()
	// --------------------------------------------------------------------------------
	public function PclTarExtract($p_tarname, $p_path = './', $p_remove_path = '', $p_mode = '') {
		$v_result = 1;

		// ----- Extract the tar format from the extension
		if (($p_mode == '') || (($p_mode != 'tar') && ($p_mode != 'tgz'))) {
			if (($p_mode = $this->PclTarHandleExtension($p_tarname)) == '') {
				return 'Extracting tar/gz failed, cannot handle extension';
			}
		}

		// ----- Call the extracting fct
		$p_list = array();

		if (($v_result = $this->PclTarHandleExtract($p_tarname, 0, $p_list, 'complete', $p_path, $p_mode, $p_remove_path)) != 1) {
			return 'Extracting tar.gz failed';
		}

		return true;
	}

	// --------------------------------------------------------------------------------
	// Function : PclTarHandleExtract()
	// Description :
	// Parameters :
	//   $p_tarname : Filename of the tar (or tgz) archive
	//   $p_file_list : An array which contains the list of files to extract, this
	//              array may be empty when $p_mode is 'complete'
	//   $p_list_detail : An array where will be placed the properties of  each extracted/listed file
	//   $p_mode : 'complete' will extract all files from the archive,
	//          'partial' will look for files in $p_file_list
	//          'list' will only list the files from the archive without any extract
	//   $p_path : Path to add while writing the extracted files
	//   $p_tar_mode : 'tar' for GNU TAR archive, 'tgz' for compressed archive
	//   $p_remove_path : Path to remove (from the file memorized path) while writing the
	//                  extracted files. If the path does not match the file path,
	//                  the file is extracted with its memorized path.
	//                  $p_remove_path does not apply to 'list' mode.
	//                  $p_path and $p_remove_path are commulative.
	// Return Values :
	// --------------------------------------------------------------------------------
	private function PclTarHandleExtract($p_tarname, $p_file_list, &$p_list_detail, $p_mode, $p_path, $p_tar_mode, $p_remove_path) {
		global $server;

		$v_result      = 1;
		$v_nb          = 0;
		$v_extract_all = true;
		$v_listing     = false;

		// ----- Check the path
		/*
		 * if (($p_path == "") || ((substr($p_path, 0, 1) != "/") && (substr($p_path, 0, 3) != "../")))
		 * $p_path = "./".$p_path;
		 */

		$isWin = (substr(PHP_OS, 0, 3) == 'WIN');

		if (!$isWin) {
			if (($p_path == '') || ((substr($p_path, 0, 1) != '/') && (substr($p_path, 0, 3) != '../'))) {
				$p_path = './' . $p_path;
			}
		}
		// ----- Look for path to remove format (should end by /)
		if (($p_remove_path != '') && (substr($p_remove_path, -1) != '/')) {
			$p_remove_path .= '/';
		}
		$p_remove_path_size = strlen($p_remove_path);

		// ----- Study the mode
		switch ($p_mode) {
			case 'complete':
				// ----- Flag extract of all files
				$v_extract_all = true;
				$v_listing     = false;

				break;

			case 'partial':
				// ----- Flag extract of specific files
				$v_extract_all = false;
				$v_listing     = false;

				break;

			case 'list':
				// ----- Flag list of all files
				$v_extract_all = false;
				$v_listing     = true;

				break;

			default:
				return false;
		}

		// ----- Open the tar file
		if ($p_tar_mode == 'tar') {
			$v_tar = fopen($p_tarname, 'rb');
		} else {
			$v_tar = @gzopen($p_tarname, 'rb');
		}

		// ----- Check that the archive is open
		if ($v_tar == 0) {
			return false;
		}

		$start = time();

		// ----- Read the blocks
		while (!($v_end_of_file = ($p_tar_mode == 'tar' ? feof($v_tar) : gzeof($v_tar)))) {
			// ----- Clear cache of file infos
			clearstatcache();

			if (time() - $start > 55) {
				$server->extendTimeLimit();
				$start = time();
			}

			// ----- Reset extract tag
			$v_extract_file       = false;
			$v_extraction_stopped = 0;

			// ----- Read the 512 bytes header
			if ($p_tar_mode == 'tar') {
				$v_binary_data = fread($v_tar, 512);
			} else {
				$v_binary_data = gzread($v_tar, 512);
			}

			// ----- Read the header properties
			$v_header = array();

			if (($v_result = $this->PclTarHandleReadHeader($v_binary_data, $v_header)) != 1) {
				// ----- Close the archive file
				if ($p_tar_mode == 'tar') {
					fclose($v_tar);
				} else {
					gzclose($v_tar);
				}

				// ----- Return
				return $v_result;
			}

			// ----- Look for empty blocks to skip
			if ($v_header['filename'] == '') {
				continue;
			}

			// ----- Look for partial extract
			if ((!$v_extract_all) && (is_array($p_file_list))) {
				// ----- By default no unzip if the file is not found
				$v_extract_file = false;

				// ----- Look into the file list
				for ($i = 0; $i < sizeof($p_file_list); $i++) {
					// ----- Look if it is a directory
					if (substr($p_file_list[$i], -1) == '/') {
						// ----- Look if the directory is in the filename path
						if ((strlen($v_header['filename']) > strlen($p_file_list[$i])) && (substr($v_header['filename'], 0, strlen($p_file_list[$i])) == $p_file_list[$i])) {
							// ----- The file is in the directory, so extract it
							$v_extract_file = true;

							// ----- End of loop
							break;
						}
					} elseif ($p_file_list[$i] == $v_header['filename']) { // ----- It is a file, so compare the file names
						// ----- File found
						$v_extract_file = true;

						// ----- End of loop
						break;
					}
				}

				// ----- Trace
				if (!$v_extract_file) {
				}
			} else {
				// ----- All files need to be extracted
				$v_extract_file = true;
			}

			// ----- Look if this file need to be extracted
			if (($v_extract_file) && (!$v_listing)) {
				// ----- Look for path to remove
				if (($p_remove_path != '')
					&& (substr($v_header['filename'], 0, $p_remove_path_size) == $p_remove_path)
				) {
					// ----- Remove the path
					$v_header['filename'] = substr($v_header['filename'], $p_remove_path_size);
				}

				// ----- Add the path to the file
				if (($p_path != './') && ($p_path != '/')) {
					// ----- Look for the path end '/'
					while (substr($p_path, -1) == '/') {
						$p_path = substr($p_path, 0, strlen($p_path) - 1);
					}

					// ----- Add the path
					if (substr($v_header['filename'], 0, 1) == '/') {
						$v_header['filename'] = $p_path . $v_header['filename'];
					} else {
						$v_header['filename'] = $p_path . '/' . $v_header['filename'];
					}
				}

				// ----- Check that the file does not exists
				if (file_exists($v_header['filename'])) {
					// ----- Look if file is a directory
					if (is_dir($v_header['filename'])) {
						// ----- Change the file status
						$v_header['status'] = 'already_a_directory';

						// ----- Skip the extract
						$v_extraction_stopped = 1;
						$v_extract_file       = 0;
					} elseif (!is_writeable($v_header['filename'])) { // ----- Look if file is write protected
						// ----- Change the file status
						$v_header['status'] = 'write_protected';

						// ----- Skip the extract
						$v_extraction_stopped = 1;
						$v_extract_file       = 0;
					} elseif (filemtime($v_header['filename']) > $v_header['mtime']) { // ----- Look if the extracted file is older
						// ----- Change the file status
						$v_header['status'] = 'newer_exist';

						// ----- Skip the extract
						$v_extraction_stopped = 1;
						$v_extract_file       = 0;
					}
				} else { // ----- Check the directory availability and create it if necessary
					if ($v_header['typeflag'] == '5') {
						$v_dir_to_check = $v_header['filename'];
					} elseif (!strstr($v_header['filename'], '/')) {
						$v_dir_to_check = '';
					} else {
						$v_dir_to_check = dirname($v_header['filename']);
					}

					if (($v_result = $this->PclTarHandlerDirCheck($v_dir_to_check)) != 1) {
						// ----- Change the file status
						$v_header['status'] = 'path_creation_fail';

						// ----- Skip the extract
						$v_extraction_stopped = 1;
						$v_extract_file       = 0;
					}
				}

				// ----- Do the extraction
				if (($v_extract_file) && ($v_header['typeflag'] != '5')) {
					// ----- Open the destination file in write mode
					if (($v_dest_file = @fopen($v_header['filename'], 'wb')) == 0) {
						// ----- Change the file status
						$v_header['status'] = 'write_error';

						// ----- Jump to next file
						if ($p_tar_mode == 'tar') {
							fseek($v_tar, ftell($v_tar) + (ceil(($v_header['size'] / 512)) * 512));
						} else {
							gzseek($v_tar, gztell($v_tar) + (ceil(($v_header['size'] / 512)) * 512));
						}
					} else {
						// ----- Read data
						$n = floor($v_header['size'] / 512);

						for ($i = 0; $i < $n; $i++) {
							if ($p_tar_mode == 'tar') {
								$v_content = fread($v_tar, 512);
							} else {
								$v_content = gzread($v_tar, 512);
							}
							fwrite($v_dest_file, $v_content, 512);
						}

						if (($v_header['size'] % 512) != 0) {
							if ($p_tar_mode == 'tar') {
								$v_content = fread($v_tar, 512);
							} else {
								$v_content = gzread($v_tar, 512);
							}
							fwrite($v_dest_file, $v_content, ($v_header['size'] % 512));
						}

						// ----- Close the destination file
						fclose($v_dest_file);

						// ----- Change the file mode, mtime
						@touch($v_header['filename'], $v_header['mtime']);
						//chmod($v_header[filename], DecOct($v_header[mode]));
					}

					// ----- Check the file size
					clearstatcache();

					if (filesize($v_header['filename']) != $v_header['size']) {
						// ----- Close the archive file
						if ($p_tar_mode == 'tar') {
							fclose($v_tar);
						} else {
							gzclose($v_tar);
						}

						// ----- Return
						return false;
					}
				} else {
					// ----- Jump to next file
					if ($p_tar_mode == 'tar') {
						fseek($v_tar, ftell($v_tar) + (ceil(($v_header['size'] / 512)) * 512));
					} else {
						gzseek($v_tar, gztell($v_tar) + (ceil(($v_header['size'] / 512)) * 512));
					}
				}
			} else { // ----- Look for file that is not to be unzipped
				// ----- Jump to next file
				if ($p_tar_mode == 'tar') {
					fseek($v_tar, ($p_tar_mode == 'tar' ? ftell($v_tar) : gztell($v_tar)) + (ceil(($v_header[size] / 512)) * 512));
				} else {
					gzseek($v_tar, gztell($v_tar) + (ceil(($v_header[size] / 512)) * 512));
				}
			}

			if ($p_tar_mode == 'tar') {
				$v_end_of_file = feof($v_tar);
			} else {
				$v_end_of_file = gzeof($v_tar);
			}

			// ----- File name and properties are logged if listing mode or file is extracted
			if ($v_listing || $v_extract_file || $v_extraction_stopped) {
				// ----- Log extracted files
				if (($v_file_dir = dirname($v_header['filename'])) == $v_header['filename']) {
					$v_file_dir = '';
				}

				if ((substr($v_header['filename'], 0, 1) == '/') && ($v_file_dir == '')) {
					$v_file_dir = '/';
				}

				// ----- Add the array describing the file into the list
				$p_list_detail[$v_nb] = $v_header;

				// ----- Increment
				$v_nb++;
			}
		}

		// ----- Close the tarfile
		if ($p_tar_mode == 'tar') {
			fclose($v_tar);
		} else {
			gzclose($v_tar);
		}

		// ----- Return
		return $v_result;
	}

	// --------------------------------------------------------------------------------

	// --------------------------------------------------------------------------------
	// Function : PclTarHandleReadHeader()
	// Description :
	// Parameters :
	// Return Values :
	// --------------------------------------------------------------------------------
	private function PclTarHandleReadHeader($v_binary_data, &$v_header) {
		$v_result = 1;

		// ----- Read the 512 bytes header
		/*
		if ($p_tar_mode == "tar")
		$v_binary_data = fread($p_tar, 512);
		else
		$v_binary_data = gzread($p_tar, 512);
		*/

		// ----- Look for no more block
		if (strlen($v_binary_data) == 0) {
			$v_header['filename'] = '';
			$v_header['status']   = 'empty';

			return $v_result;
		}

		// ----- Look for invalid block size
		if (strlen($v_binary_data) != 512) {
			$v_header['filename'] = '';
			$v_header['status']   = 'invalid_header';

			// ----- Return
			return false;
		}

		// ----- Calculate the checksum
		$v_checksum = 0;
		// ..... First part of the header
		for ($i = 0; $i < 148; $i++) {
			$v_checksum += ord(substr($v_binary_data, $i, 1));
		}
		// ..... Ignore the checksum value and replace it by ' ' (space)
		for ($i = 148; $i < 156; $i++) {
			$v_checksum += ord(' ');
		}
		// ..... Last part of the header
		for ($i = 156; $i < 512; $i++) {
			$v_checksum += ord(substr($v_binary_data, $i, 1));
		}

		// ----- Extract the values
		$v_data = unpack('a100filename/a8mode/a8uid/a8gid/a12size/a12mtime/a8checksum/a1typeflag/a100link/a6magic/a2version/a32uname/a32gname/a8devmajor/a8devminor', $v_binary_data);

		// ----- Extract the checksum for check
		$v_header['checksum'] = octdec(trim($v_data['checksum']));

		if ($v_header['checksum'] != $v_checksum) {
			$v_header['filename'] = '';
			$v_header['status']   = 'invalid_header';

			// ----- Look for last block (empty block)
			if (($v_checksum == 256) && ($v_header['checksum'] == 0)) {
				$v_header['status'] = 'empty';
				// ----- Return
				return $v_result;
			}

			// ----- Return
			return false;
		}
		// ----- Extract the properties
		$v_header['filename'] = trim($v_data['filename']);
		$v_header['mode']     = octdec(trim($v_data['mode']));
		$v_header['uid']      = octdec(trim($v_data['uid']));
		$v_header['gid']      = octdec(trim($v_data['gid']));
		$v_header['size']     = octdec(trim($v_data['size']));
		$v_header['mtime']    = octdec(trim($v_data['mtime']));

		if (($v_header['typeflag'] = $v_data['typeflag']) == '5') {
			$v_header['size'] = 0;
		}
		/* ----- All these fields are removed form the header because they do not carry interesting info
		$v_header[link] = trim($v_data[link]);
		TrFctMessage(__FILE__, __LINE__, 2, "Linkname : $v_header[linkname]");
		$v_header[magic] = trim($v_data[magic]);
		TrFctMessage(__FILE__, __LINE__, 2, "Magic : $v_header[magic]");
		$v_header[version] = trim($v_data[version]);
		TrFctMessage(__FILE__, __LINE__, 2, "Version : $v_header[version]");
		$v_header[uname] = trim($v_data[uname]);
		TrFctMessage(__FILE__, __LINE__, 2, "Uname : $v_header[uname]");
		$v_header[gname] = trim($v_data[gname]);
		TrFctMessage(__FILE__, __LINE__, 2, "Gname : $v_header[gname]");
		$v_header[devmajor] = trim($v_data[devmajor]);
		TrFctMessage(__FILE__, __LINE__, 2, "Devmajor : $v_header[devmajor]");
		$v_header[devminor] = trim($v_data[devminor]);
		TrFctMessage(__FILE__, __LINE__, 2, "Devminor : $v_header[devminor]");
		*/

		// ----- Set the status field
		$v_header['status'] = 'ok';

		// ----- Return
		return $v_result;
	}

	// --------------------------------------------------------------------------------

	// --------------------------------------------------------------------------------
	// Function : PclTarHandlerDirCheck()
	// Description :
	//   Check if a directory exists, if not it creates it and all the parents directory
	//   which may be useful.
	// Parameters :
	//   $p_dir : Directory path to check (without / at the end).
	// Return Values :
	//  1 : OK
	//   -1 : Unable to create directory
	// --------------------------------------------------------------------------------
	private function PclTarHandlerDirCheck($p_dir) {
		$v_result = 1;

		// ----- Check the directory availability
		if ((is_dir($p_dir)) || ($p_dir == '')) {
			return 1;
		}

		// ----- Look for file alone
		/*
		 *if (!strstr("$p_dir", "/")) {
		 *  TrFctEnd(__FILE__, __LINE__,  "'$p_dir' is a file with no directory");
		 *  return 1;
		 *}
		 */

		// ----- Extract parent directory
		$p_parent_dir = dirname($p_dir);

		// ----- Just a check
		if ($p_parent_dir != $p_dir) {
			// ----- Look for parent directory
			if ($p_parent_dir != '') {
				if (($v_result = $this->PclTarHandlerDirCheck($p_parent_dir)) != 1) {
					return $v_result;
				}
			}
		}

		// ----- Create the directory
		if (!@mkdir($p_dir, 0777)) {
			// ----- Return
			return false;
		}

		// ----- Return
		return $v_result;
	}

	// --------------------------------------------------------------------------------

	// --------------------------------------------------------------------------------
	// Function : PclTarHandleExtension()
	// Description :
	// Parameters :
	// Return Values :
	// --------------------------------------------------------------------------------
	private function PclTarHandleExtension($p_tarname) {
		// ----- Look for file extension
		if ((substr($p_tarname, -7) == '.tar.gz') || (substr($p_tarname, -4) == '.tgz')) {
			$v_tar_mode = 'tgz';
		} elseif (substr($p_tarname, -4) == '.tar') {
			$v_tar_mode = 'tar';
		} else {
			$v_tar_mode = '';
		}

		return $v_tar_mode;
	}

	// --------------------------------------------------------------------------------
}

// ---------------------- M A I N
$config     = new preInstallerConfig();
$page       = new htmlPage();
$server     = new serverPlatform();
$preInstall = new preInstaller();
$preInstall->main();
