<?php
/*
	To get your profile ID,
	Log in to your google analytics account and click the site you want to generate a report for.
	
	In the URL you will see:
		
		https://www.google.com/analytics/web/?#report/visitors-overview/a123456w7890pMORENUMBERS/

	Grab the numbers after the p in the URL, represented here by MORENUMBERS and copy that into the function below, so it would be

		googleAnalytics("YOUR EMAIL ADDRESS","YOUR PASSWORD",'ga:MORENUMBERS');

*/

	require 'analytics.class.php';
	googleAnalytics("YOUR EMAIL ADDRESS","YOUR PASSWORD",'ga:[ID OF THE PROFILE YOU ARE DISPLAYING FROM GA]');
	
	$vs = array();
	$pvs = array();


	$visits = ($_SESSION['visitors']);
	$pageviews = ($_SESSION['pageviews']);
	foreach($visits as $key=>$num){
		$vs[] = array(
			'title'=>milli($key),
			'value'=>$num
		);
	}
	$list[0]['title'] = 'Visits';
	$list[0]['color'] = 'red';
	$list[0]['datapoints'] = $vs;

	foreach($pageviews as $key=>$num){
		$pvs[] = array(
			'title'=>milli($key),
			'value'=>$num
		);
	}
	$list[1]['title'] = 'Page Views';
	$list[1]['color'] = 'blue';
	$list[1]['datapoints'] = $pvs;

	$output = array();
	$output['graph']['title'] = "Google Analytics";
	$output['graph']['total'] = true;
	$output['graph']['type'] = 'line';
	$output['graph']['datasequences'] = $list;
	echo json_encode( $output );
	
	exit;
	function milli($k){
		$s = strtotime("-".$k." days");
		$s = date("m-d-Y",$s);
		return $s;
	}

	function googleAnalytics($user,$pass,$site){
		$analytics = new analytics($user, $pass);
		$analytics->setProfileById($site);
		$analytics->setDateRange(date("Y-m-d",strtotime("30 days ago")), date("Y-m-d",strtotime("today")) );
		$_SESSION['visitors'] = $analytics->getVisitors();
		$_SESSION['pageviews'] = $analytics->getPageviews();
	}

?>