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


class analytics{
	
	private $_sUser;
	private $_sPass;
	private $_sAuth;
	private $_sProfileId;
	
	private $_sStartDate;
	private $_sEndDate;
	
	private $_bUseCache;
	private $_iCacheAge;
	public function __construct($sUser, $sPass){
		$this->_sUser = $sUser;
		$this->_sPass = $sPass;
		$this->_bUseCache = false;
		$this->auth();
	}
	private function auth(){
		if (isset($_SESSION['auth'])){
			$this->_sAuth = $_SESSION['auth'];
			return;
		}
		$aPost = array ( 'accountType'   => 'GOOGLE', 
			'Email'         => $this->_sUser,
			'Passwd'        => $this->_sPass,
			'service'       => 'analytics',
			'source'        => 'SWIS-Webbeheer-4.0');
		$sResponse = $this->getUrl('https://www.google.com/accounts/ClientLogin', $aPost);
		$_SESSION['auth'] = '';
		if (strpos($sResponse, "\n") !== false){
			$aResponse = explode("\n", $sResponse);
			foreach ($aResponse as $sResponse){
				if (substr($sResponse, 0, 4) == 'Auth'){
					$_SESSION['auth'] = trim(substr($sResponse, 5));
				}
			}
		}
		if ($_SESSION['auth'] == ''){
			unset($_SESSION['auth']);
			throw new Exception('Retrieving Auth hash failed!');
		}
		$this->_sAuth = $_SESSION['auth']; 
	}
	public function useCache($bCaching = true, $iCacheAge = 600){
		$this->_bUseCache = $bCaching;
		$this->_iCacheAge = $iCacheAge;
		if ($bCaching && !isset($_SESSION['cache'])){
			$_SESSION['cache'] = array();     
		}
	}
	private function getXml($sUrl){
		return $this->getUrl($sUrl, array(), array('Authorization: GoogleLogin auth=' . $this->_sAuth));
	}
	public function setProfileById($sProfileId){
		$this->_sProfileId = $sProfileId; 
	}
	public function setProfileByName($sAccountName){
		if (isset($_SESSION['profile'])){
			$this->_sProfileId = $_SESSION['profile'];
			return;
		}
		$this->_sProfileId = '';
		$sXml = $this->getXml('https://www.google.com/analytics/feeds/accounts/default');
		$aAccounts = $this->parseAccountList($sXml);
		foreach($aAccounts as $aAccount){
			if (isset($aAccount['accountName']) && $aAccount['accountName'] == $sAccountName){
				if (isset($aAccount['tableId'])){
					$this->_sProfileId =  $aAccount['tableId'];
				}
			}    
		}
		if ($this->_sProfileId == ''){
			throw new Exception('No profile ID found!');
		}
		$_SESSION['profile'] = $this->_sProfileId;
	}
	public function getProfileList(){
		$sXml = $this->getXml('https://www.google.com/analytics/feeds/accounts/default');
		$aAccounts = $this->parseAccountList($sXml);
		$aReturn = array();
		foreach($aAccounts as $aAccount){ 
			$aReturn[$aAccount['tableId']] =  $aAccount['title'];
		}       
		return $aReturn;
	}
	private function getCache($sKey){
		if ($this->_bUseCache === false){
			return false;
		}
		if (!isset($_SESSION['cache'][$this->_sProfileId])){
			$_SESSION['cache'][$this->_sProfileId] = array();
		}  
		if (isset($_SESSION['cache'][$this->_sProfileId][$sKey])){
			if (time() - $_SESSION['cache'][$this->_sProfileId][$sKey]['time'] < $this->_iCacheAge){
				return $_SESSION['cache'][$this->_sProfileId][$sKey]['data'];
			} 
		}
		return false;
	}
	private function setCache($sKey, $mData){
		if ($this->_bUseCache === false){
			return false;
		}
		if (!isset($_SESSION['cache'][$this->_sProfileId])){
			$_SESSION['cache'][$this->_sProfileId] = array();
		}  
		$_SESSION['cache'][$this->_sProfileId][$sKey] = array(  'time'  => time(),'data'  => $mData);
	}
	public function getData($aProperties = array()){
		$aParams = array();
		foreach($aProperties as $sKey => $sProperty){
		$aParams[] = $sKey . '=' . $sProperty;
		}
		
		$sUrl = 'https://www.google.com/analytics/feeds/data?ids=' . $this->_sProfileId . 
		'&start-date=' . $this->_sStartDate . 
		'&end-date=' . $this->_sEndDate . '&' . 
		implode('&', $aParams);
		$aCache = $this->getCache($sUrl);
		if ($aCache !== false){
		return $aCache;
		}
		
		$sXml = $this->getXml($sUrl);
		
		$aResult = array();
		
		$oDoc = new DOMDocument();
		$oDoc->loadXML($sXml);
		$oEntries = $oDoc->getElementsByTagName('entry');
		foreach($oEntries as $oEntry){
			$oTitle = $oEntry->getElementsByTagName('title');
			$sTitle = $oTitle->item(0)->nodeValue;
			
			$oMetric = $oEntry->getElementsByTagName('metric'); 
			
			// Fix the array key when multiple dimensions are given
			if (strpos($sTitle, ' | ') !== false && strpos($aProperties['dimensions'], ',') !== false){
			
			$aDimensions = explode(',', $aProperties['dimensions']);
			$aDimensions[] = '|';
			$aDimensions[] = '=';
			$sTitle = preg_replace('/\s\s+/', ' ', trim(str_replace($aDimensions, '', $sTitle)));  
			
			}
			$sTitle = str_replace($aProperties['dimensions'] . '=', '', $sTitle);
			$aResult[$sTitle] = $oMetric->item(0)->getAttribute('value');
		}
		$this->setCache($sUrl, $aResult);
		return $aResult;
	}
	private function parseAccountList($sXml){
		$oDoc = new DOMDocument();
		$oDoc->loadXML($sXml);
		$oEntries = $oDoc->getElementsByTagName('entry');
		$i = 0;
		$aProfiles = array();
		foreach($oEntries as $oEntry){
			$aProfiles[$i] = array();
			$oTitle = $oEntry->getElementsByTagName('title');
			$aProfiles[$i]["title"] = $oTitle->item(0)->nodeValue;
			$oEntryId = $oEntry->getElementsByTagName('id');
			$aProfiles[$i]["entryid"] = $oEntryId->item(0)->nodeValue;
			$oProperties = $oEntry->getElementsByTagName('property');
			foreach($oProperties as $oProperty){
				if (strcmp($oProperty->getAttribute('name'), 'ga:accountId') == 0){
					$aProfiles[$i]["accountId"] = $oProperty->getAttribute('value');
				}    
				if (strcmp($oProperty->getAttribute('name'), 'ga:accountName') == 0){
					$aProfiles[$i]["accountName"] = $oProperty->getAttribute('value');
				}
				if (strcmp($oProperty->getAttribute('name'), 'ga:profileId') == 0){
					$aProfiles[$i]["profileId"] = $oProperty->getAttribute('value');
				}
				if (strcmp($oProperty->getAttribute('name'), 'ga:webPropertyId') == 0){
					$aProfiles[$i]["webPropertyId"] = $oProperty->getAttribute('value');
				}
			}
			$oTableId = $oEntry->getElementsByTagName('tableId');
			$aProfiles[$i]["tableId"] = $oTableId->item(0)->nodeValue;
			$i++;
		}
		return $aProfiles;
	}
	private function getUrl($sUrl, $aPost = array(), $aHeader = array()){
		$oCache = new Cache(md5($sUrl), (60*60*24));
		if (!$oCache->Check()) {
			if (count($aPost) > 0){
				// build POST query
				$sMethod = 'POST'; 
				$sPost = http_build_query($aPost);    
				$aHeader[] = 'Content-type: application/x-www-form-urlencoded';
				$aHeader[] = 'Content-Length: ' . strlen($sPost);
				$sContent = $aPost;
			} else {
				$sMethod = 'GET';
				$sContent = null;
			}
			if (function_exists('curl_init')){
				// If Curl is installed, use it!
				$rRequest = curl_init();
				curl_setopt($rRequest, CURLOPT_URL, $sUrl);
				curl_setopt($rRequest, CURLOPT_RETURNTRANSFER, 1);
				if ($sMethod == 'POST'){
					curl_setopt($rRequest, CURLOPT_POST, 1); 
					curl_setopt($rRequest, CURLOPT_POSTFIELDS, $aPost); 
				} else {
					curl_setopt($rRequest, CURLOPT_HTTPHEADER, $aHeader);
				}
				$sOutput = curl_exec($rRequest);
				if ($sOutput === false){
					throw new Exception('Curl error (' . curl_error($rRequest) . ')');    
				}
				$aInfo = curl_getinfo($rRequest);
				if ($aInfo['http_code'] != 200){
					if ($aInfo['http_code'] == 400){
						throw new Exception('Bad request (' . $aInfo['http_code'] . ') url: ' . $sUrl);     
					}
					if ($aInfo['http_code'] == 403){
						throw new Exception('Access denied (' . $aInfo['http_code'] . ') url: ' . $sUrl);     
					}
					throw new Exception('Not a valid response (' . $aInfo['http_code'] . ') url: ' . $sUrl);
				}
				curl_close($rRequest);
			} else {
				$aContext = array('http' => array ( 'method' => $sMethod,
				'header'=> implode("\r\n", $aHeader) . "\r\n",
				'content' => $sContent));
				$rContext = stream_context_create($aContext);
				$sOutput = @file_get_contents($sUrl, 0, $rContext);
				if (strpos($http_response_header[0], '200') === false){
					throw new Exception('Not a valid response (' . $http_response_header[0] . ') url: ' . $sUrl);       
				}
			}
			$oCache->Set($sOutput);
		}
		$sOutput = $oCache->Get();
		return $sOutput;
	}   
	public function setDateRange($sStartDate, $sEndDate){
		$this->_sStartDate = $sStartDate; 
		$this->_sEndDate   = $sEndDate;
	}
	public function setMonth($iMonth, $iYear){  
		$this->_sStartDate = date('Y-m-d', strtotime($iYear . '-' . $iMonth . '-01')); 
		$this->_sEndDate   = date('Y-m-d', strtotime($iYear . '-' . $iMonth . '-' . date('t', strtotime($iYear . '-' . $iMonth . '-01'))));
	}
	public function getVisitors(){
		return $this->getData(array( 'dimensions' => 'ga:day','metrics'    => 'ga:visits','sort'       => 'ga:day'));
	}
	public function getPageviews(){
		return $this->getData(array( 'dimensions' => 'ga:day','metrics'    => 'ga:pageviews','sort'       => 'ga:day'));
	}
	public function getVisitsPerHour(){
		return $this->getData(array( 'dimensions' => 'ga:hour','metrics'    => 'ga:visits','sort'       => 'ga:hour'));
	}
	public function getBrowsers(){
		$aData = $this->getData(array(  'dimensions' => 'ga:browser,ga:browserVersion','metrics'    => 'ga:visits','sort'       => 'ga:visits'));             
		arsort($aData);
		return $aData;                                                                                                                                                                           
	}
	public function getOperatingSystem(){
		$aData = $this->getData(array(   'dimensions' => 'ga:operatingSystem','metrics'    => 'ga:visits','sort'       => 'ga:visits'));
		arsort($aData);
		return $aData; 
	}
	public function getScreenResolution(){
		$aData = $this->getData(array(   'dimensions' => 'ga:screenResolution','metrics'    => 'ga:visits','sort'       => 'ga:visits'));
		arsort($aData);
		return $aData; 
	}
	public function getReferrers(){
		$aData = $this->getData(array(   'dimensions' => 'ga:source','metrics'    => 'ga:visits','sort'       => 'ga:source'));
		arsort($aData);
		return $aData; 
	}
	public function getSearchWords(){
		$aData = $this->getData(array(   'dimensions' => 'ga:keyword','metrics'    => 'ga:visits','sort'       => 'ga:keyword'));
		arsort($aData);
		return $aData; 
	}
}