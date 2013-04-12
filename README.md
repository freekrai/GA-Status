GA-Status
=========

Google Analytics code to output a graph for use with Panic's StatusBoard iPad app

Upload the files to your server, and open up index.php

You'll see a function call to googleAnalytics():

		googleAnalytics("YOUR EMAIL ADDRESS","YOUR PASSWORD",'ga:[ID OF THE PROFILE YOU ARE DISPLAYING FROM GA]');

Populate this with your email address and password you use for Google Analytics and then replace [ID OF THE PROFILE YOU ARE DISPLAYING FROM GA] with the profile ID for the site you wish to view.

Then add the URL to your statusboard and you will start seeing Visits and Page Views displayed pretty quickly..

### To get your profile ID,

Log in to your google analytics account and click the site you want to generate a report for.
	
In the URL you will see:
		
		https://www.google.com/analytics/web/?#report/visitors-overview/a123456w7890pMORENUMBERS/

Grab the numbers after the p in the URL, represented here by MORENUMBERS and copy that into the function below, so it would be:

		googleAnalytics("YOUR EMAIL ADDRESS","YOUR PASSWORD",'ga:MORENUMBERS');
