<?php
	define('MAIN_PATH', realpath('.'));
	$cachedir = MAIN_PATH.'/_cache/';
	define('CACHE_PATH', $cachedir);	
	define('GARBAGE_DAYS', '5');	// number of days to hold cached files for. DO NOT SET THIS TO LESS THAN 1 for any reason
	class Cache {
		var $sFile;
		var $sFileLock;
		var $iCacheTime;
		var $oCacheObject;
		public function __construct($sKey, $iCacheTime) {
			$this->sFile = CACHE_PATH.md5($sKey).".txt";
			$this->sFileLock = "$this->sFile.lock";
			$iCacheTime >= 10 ? $this->iCacheTime = $iCacheTime : $this->iCacheTime = 10;
		}
		public function __destruct(){
			// garbage cleanup, nothing older than the number of days specified by GARBAGE_DAYS
			$result = shell_exec('find '.CACHE_PATH.'*.txt -mtime +'.GARBAGE_DAYS.' -exec rm {} \;');
		}
		function Check() {
			$val = 0;
			if (file_exists($this->sFileLock)) return true;
			$val = (file_exists($this->sFile) && ($this->iCacheTime == -1 || time() - filemtime($this->sFile) <= $this->iCacheTime));
			if( !$val ){
				if (file_exists($this->sFile)) { unlink($this->sFile); }
			}
			return $val;
		}
		function Reset(){
			if (file_exists($this->sFile)) { unlink($this->sFile); }
		}
		function Exists() {
			return (file_exists($this->sFile) || file_exists($this->sFileLock));
		}
		function Set($vContents) {
			if (!file_exists($this->sFileLock)) {
				if (file_exists($this->sFile)) { copy($this->sFile, $this->sFileLock); }
				$oFile = fopen($this->sFile, 'w');
				fwrite($oFile, serialize($vContents));
				fclose($oFile);
				if (file_exists($this->sFileLock)) {unlink($this->sFileLock);}
				return true;
			}     
			return false;
		}
		function Get() {
			if (file_exists($this->sFileLock)) {
				return unserialize(file_get_contents($this->sFileLock));
			} else {
				return unserialize(file_get_contents($this->sFile));
			}
		}
		function ReValidate() {
			touch($this->sFile);
		}
	}
?>
