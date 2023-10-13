<?php
use WHMCS\Config;
use WHMCS\Product;
use WHMCS\Database\Capsule;
use WHMCS\Config\Setting;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}
function zoho_site24x7_MetaData()
{    
     try {
         if(!Capsule::schema()->hasTable('zoho_site24x7')){
    	       Capsule::schema()->create(
    	                                'zoho_site24x7',
    	                           function ($table) {
    	                                 $table->string('authtoken');
    	                                 $table->string('domain');
    	                                 $table->string('server');
    	                                 $table->string('zoid');
    	                                 $table->string('profileid');
    	                                 $table->string('superAdmin');
    	                               }
    	                        );
        }
        else {
            $pdo = Capsule::connection()->getPdo();
            $pdo->beginTransaction();
        }
	} catch (Exception $e) {
	logModuleCall('zoho_site24x7', __FUNCTION__, $params, $e->getMessage(), $e->getTraceAsString());
    }
    return array(
    	'DisplayName' => 'Zoho Site24x7',
    	'APIVersion' => '1.1',
    	'RequiresServer' => true,
    	'DefaultNonSSLPort' => '1111',
    	'DefaultSSLPort' => '1112',
    	'ServiceSingleSignOnLabel' => 'Login to Panel as User',
    	'AdminSingleSignOnLabel' => 'Login to Panel as Admin',
    );
    
}
function zoho_site24x7_ConfigOptions()
{  
         $patharray = array();
         $patharray = explode('/',$_SERVER['REQUEST_URI']);
         $url = Setting::getValue('SystemURL');
         $patharray[1] = $url;
         $config = array (
            'Provide Zoho API credentials'=>array(
                      'Description'=>
                           '<script type="text/javascript">
                           var tabval = window.location.hash;
                           document.getElementById("zm_tab_value").value = tabval.toString();
                           </script>
                           <form action=../modules/servers/zoho_site24x7/zsite24x7_oauthgrant.php method=post>
                           <label>Domain</label><br>
                           <select name="zm_dn" required>
                           <option value="com">com</option>
                           <option value="eu">eu</option>
                           <option value="in">in</option>
                           <option value="cn">cn</option>
                           </select><br><br>
                           <label>Client ID</label><br>
                           <input type="text" size="60" name="zm_ci" required/><br>
                           For CN DC, Generated from <a href="https://accounts.zoho.com.cn/developerconsole" target=_blank>Zoho Developer Console</a><br>
                           For other DCs, Generated from <a href="https://accounts.zoho.com/developerconsole" target=_blank>Zoho Developer Console</a><br><br>
                           <label>Client Secret</label><br>
                           <input type="text" size="60" name="zm_cs" required/><br>
                            For CN DC, Generated from <a href="https://accounts.zoho.com.cn/developerconsole" target=_blank>Zoho Developer Console</a><br>
                            For other DCs, Generated from <a href="https://accounts.zoho.com/developerconsole" target=_blank>Zoho Developer Console</a><br><br>                           
                            <label>Admin folder name</label><br>
                           <input type="text" size="60" name="zm_ad"/><br>
                           If you have a customized WHMCS admin directory name, please enter it here. You will be redirected here after authentication.<a href="https://www.zoho.com/mail/help/partnerportal/whmcs-integration.html" target=_blank>Refer here</a> for instructions.<br><br>
                           <label>Redirect URL</label><br>
                           <input type="text" size="60" name="zm_ru" value='.$_SERVER['REQUEST_SCHEME'].'://'.$_SERVER['SERVER_NAME'].'/modules/servers/zoho_site24x7/zsite24x7_oauthgrant.php required readonly/><br>
                           Redirect URL used to generate Client ID and Client Secret.<br><br>
                           <input type="hidden" id="zm_tab_value" name="zm_tab_value" value=""/>
                           <input type="hidden" name="zm_pi" value='.$_REQUEST['id'].'>
                           <button name="zm_submit" size="15">Authenticate</button>
                           </form>'
                      )
                  );
          try {
            if (Capsule::schema()->hasTable('zoho_site24x7_auth_table')) 
            {
              $count = 0;
              $list = 0;
              foreach (Capsule::table('zoho_site24x7_auth_table')->get() as $client) {
                  if (strpos($client->token, 'tab') == false && strlen($client->token) > 1 ){
                    $list = $list + 1;
                    $count = 1;
                  } 
                }
              if ($count > 0 && $list > 0) { 
              $config = array (
              'Status' => array('Description'=>' <label style="color:green;"> Authenticated Successfully </label>')
              );
            }
            
          } 
         } catch(Exception $e) {

          }
        return $config;
}


function get_site24x7_access_token(array $params) {

        $curl = curl_init();
        $cli = Capsule::table('zoho_site24x7_auth_table')->first();
        $region = $cli->region;
        if($region == 'cn') {
            $urlAT = 'https://accounts.zoho.com.'.$region.'/oauth/v2/token?refresh_token='.$cli->token.'&grant_type=refresh_token&client_id='.$cli->clientId.'&client_secret='.$cli->clientSecret.'&redirect_uri='.$cli->redirectUrl.'&scope=ZohoPayments.partnersubscription.all';
        }
        else {
            $urlAT = 'https://accounts.zoho.'.$region.'/oauth/v2/token?refresh_token='.$cli->token.'&grant_type=refresh_token&client_id='.$cli->clientId.'&client_secret='.$cli->clientSecret.'&redirect_uri='.$cli->redirectUrl.'&scope=ZohoPayments.partnersubscription.all';
        }
        curl_setopt_array($curl, array(
                  CURLOPT_URL => $urlAT,
                  CURLOPT_RETURNTRANSFER => true,
                  CURLOPT_ENCODING => "",
                  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                  CURLOPT_CUSTOMREQUEST => "POST"
                 ));
 
        $response = curl_exec($curl);
        $accessJson = json_decode($response);
        $getInfo = curl_getinfo($curl,CURLINFO_HTTP_CODE);
        curl_close($curl);
        return $accessJson->access_token;

}

function zoho_site24x7_CreateAccount(array $params)
{
	$planid;
	$bodyArr = [];
	$plantype = $params['configoptions']['Plan Type'];
	$planmode = $params['configoptions']['Mode of Plan'];
	try {
    	$curl = curl_init();
    	$arrClient = $params['clientsdetails'];
    	$country = $arrClient['countryname'];
    	
    	if($planmode == "Assign Paid Plan"){
            $bodyArr = array(
        		"serviceid" => 3,
        		"email" => $arrClient['email'],
        		"customer" => array(
        		"companyname" => $arrClient['companyname'],
        		"street" => $arrClient['address1'],
        		"city" => $arrClient['city'],
        		"state" => $arrClient['state'],
        		"country" => $country,
        		"zipcode" => $arrClient['postcode'],
        		"phone" => $arrClient['phonenumber']
        		),
        		"subscription" => array(
        		"plan" => $plantype,
        		"addons" => array(
        		),
        		"payperiod" => "YEAR"
        		)
    	    );
        }else{
            $bodyArr = array(
        		"serviceid" => 3,
        		"email" => $arrClient['email'],
        		"customer" => array(
        		"companyname" => $arrClient['companyname'],
        		"street" => $arrClient['address1'],
        		"city" => $arrClient['city'],
        		"state" => $arrClient['state'],
        		"country" => $country,
        		"zipcode" => $arrClient['postcode'],
        		"phone" => $arrClient['phonenumber']
        		),
        		"subscription" => array(
        		"plan" => $plantype,
        		"addons" => array(
        		),
        		"payperiod" => "YEAR"
        		),
        		"trial" => true
    	    );
        }
    	
	$bodyJson = json_encode($bodyArr);
	$token = get_site24x7_access_token($params);
    $curlOrg = curl_init();
    $cli = Capsule::table('zoho_site24x7_auth_table')->first();
    	$domain = $cli->region;
    	if($domain == 'cn') {
    	    $urlOrg = 'https://store.zoho.com.'.$domain.'/restapi/partner/v1/json/subscription';		   
    	}
        else {
            $urlOrg = 'https://store.zoho.'.$domain.'/restapi/partner/v1/json/subscription';
        }   
    curl_setopt_array($curlOrg, array(
      CURLOPT_URL => $urlOrg,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => "",
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => "POST",
      CURLOPT_POSTFIELDS => array('JSONString'=> $bodyJson),
      CURLOPT_HTTPHEADER => array(
        "Authorization: Zoho-oauthtoken ".$token,
        "content-type: multipart/form-data",
        "origin: Whmcs"
      ),
   ));
		$responseOrg = curl_exec($curlOrg);
		$respOrgJson = json_decode($responseOrg); 
		$getInfo = curl_getinfo($curlOrg,CURLINFO_HTTP_CODE);
		curl_close($curlOrg);
		$result = $respOrgJson->result;
		if(($result == 'success') && ($getInfo == '200')) {
		     $licenseDetails = $respOrgJson->licensedetails;
		    $customid = $respOrgJson->customid;
		    $domain1 = $params['domain'];
		    $region = $cli->region;
		    if($customid != '') {
		        $planmode = $params['configoptions']['Mode of Plan'];
		        if($planmode == "Start Trial Plan"){
		            $profileId = 0;
		        }
		        else{
		            $profileId = $licenseDetails->profileid;
		        }
		        $pdo = Capsule::connection()->getPdo();
		        $pdo->setAttribute(PDO::ATTR_AUTOCOMMIT, 0 );
		        try {
			    $statement = $pdo->prepare('insert into zoho_site24x7 (authtoken,domain,server,zoid,profileid,superAdmin) values (:authtoken, :domain, :server, :zoid, :profileid, :superAdmin)');
		            $statement->execute(
        		     [
        			   ':authtoken' => $token,
        			   ':domain' => $domain1,
        			   ':server' => $region,
        			   ':zoid' => $customid,
        			   ':profileid' => $profileId,
        			   ':superAdmin' => "true"              
        		    ]
        		 );
	 
        		 $pdo->commit();
        		 $pdo->setAttribute(PDO::ATTR_AUTOCOMMIT, 1 );
        		 } catch (\Exception $e) {
        			  return "Uh oh! {$e->getMessage()}".$urlChildPanel;
        			  $pdo->rollBack();
        		  }
    		  return array ('success' => 'Site24x7 Org has been created successfully.');
    		    }
    		    else if(($result == 'success') && (isset($respOrgJson->ERRORMSG))) {
    		        return 'Failed  ->  '.$respOrgJson->ERRORMSG;
    		    }
    		    else if ($getInfo == '400') {
		            $updatedUserCount = Capsule::table('tblproducts')
		            ->where('servertype','zoho_site24x7')
		            ->update(
        			  [
        			   'configoption2' => '',
        			  ]
		            );
			    }
			    else
        		{
        		    return 'Failed -->Description: '.$respOrgJson->status->description.' --->More Information:'.$respOrgJson->data->moreInfo.'--------------'.$getInfo;
        	    }
    		        
    		    
    		}
    		else if($getInfo == '400') {
    		    return 'Failed -->  Invalid Authtoken.';
    		}
    		else{
    		    $errorMsg = $respOrgJson->ERRORMSG;
    		    return 'Failed -->  '.$errorMsg;
    		}
	 
	} catch (Exception $e) {
		logModuleCall('zoho_site24x7', __FUNCTION__, $params, $e->getMessage(), $e->getTraceAsString());
		return $e->getMessage();
	    }
 
}
function zoho_site24x7_TestConnection(array $params)
{
    try {
	// Call the service's connection test function.
	$success = true;
	$errorMsg = '';
    } catch (Exception $e) {
	// Record the error in WHMCS's module log.
	logModuleCall('zoho_site24x7', __FUNCTION__, $params, $e->getMessage(), $e->getTraceAsString());
	$success = false;
	$errorMsg = $e->getMessage();
    }
    return array(
	'success' => $success,
	'error' => $errorMsg,
    );
}
function zoho_site24x7_AdminServicesTabFields(array $params)
{
 try{
    $url;
	    $paymenturl;
	    $cli = Capsule::table('zoho_site24x7')->where('domain',$params['domain'])->first();
	    $domain = $cli->server;
	    if($domain == 'cn') {
    	    $paymenturl = 'https://store.zoho.com.'.$domain.'/store/reseller.do?profileId='.$cli->profileid;
    	}
        else {
            $paymenturl = 'https://store.zoho.'.$domain.'/store/reseller.do?profileId='.$cli->profileid;
        }
        
        $authenticateStatus = '<h2 style="color:red;">UnAuthenticated</h2>';
        if (Capsule::schema()->hasTable('zoho_site24x7_auth_table')) 
            {
              $count = 0;
              $list = 0;
              foreach (Capsule::table('zoho_site24x7_auth_table')->get() as $client) {
                  $list = $list + 1;
                  if ( $client->token =='test'){
                    $count = 1;
                  } 
                }
              if ($count == 0 && $list > 0) { 
                $authenticateStatus = '<h2 style="color:green;">Authenticated</h2>';
              }
            }
	$response = array();
	return array(
	    'Authenticate' => $authenticateStatus,
	    'Super Administrator' => $cli->superAdmin,
	    'ZOID' => $cli->zoid,
        'URL to Manage Customers' => '<a href="'.$paymenturl.'" target=_window>Click here</a>'
	    );
	 
    } catch (Exception $e) {
	logModuleCall('zoho_site24x7', __FUNCTION__, $params, $e->getMessage(), $e->getTraceAsString());
    }
	    return array();
}
function zoho_site24x7_AdminServicesTabFieldsSave(array $params)
{
    // Fetch form submission variables.
    $originalFieldValue = isset($_REQUEST['zoho_site24x7_original_uniquefieldname']) ? $_REQUEST['zoho_site24x7_original_uniquefieldname'] : '';
    $newFieldValue = isset($_REQUEST['zoho_site24x7_uniquefieldname']) ? $_REQUEST['zoho_site24x7_uniquefieldname'] : '';
    if ($originalFieldValue != $newFieldValue) {
	    try {
	    } 
	    catch (Exception $e) {
	        logModuleCall('zoho_site24x7', __FUNCTION__, $params, $e->getMessage(), $e->getTraceAsString());
	    }
    }
}
function zoho_site24x7_ServiceSingleSignOn(array $params)
{
    try {
	$response = array();
	return array(
	    'success' => true,
	    'redirectTo' => $response['redirectUrl'],
	);
    } catch (Exception $e) {
	logModuleCall('zoho_site24x7', __FUNCTION__, $params, $e->getMessage(), $e->getTraceAsString());
	return array(
	    'success' => false,
	    'errorMsg' => $e->getMessage(),
	);
    }
}
function zoho_site24x7_AdminSingleSignOn(array $params)
{
    try {
	// Call the service's single sign-on admin token retrieval function,
	// using the values provided by WHMCS in `$params`.
	$response = array();
	return array(
	    'success' => true,
	    'redirectTo' => $response['redirectUrl'],
	);
    } catch (Exception $e) {
	// Record the error in WHMCS's module log.
	logModuleCall( 'zoho_site24x7', __FUNCTION__, $params, $e->getMessage(), $e->getTraceAsString());
	return array(
	    'success' => false,
	    'errorMsg' => $e->getMessage(),
	);
    }
}
function zoho_site24x7_ClientArea(array $params)
{
    $serviceAction = 'get_stats';
    $templateFile = 'templates/overview.tpl';
   $site24x7url;
   try {
    $cli = Capsule::table('zoho_site24x7')->where('domain',$params['domain'])->first();
    $domain = $cli->server;
    $site24x7url = 'https://site24x7.'.$domain;
	  
	return array(
            'tabOverviewReplacementTemplate' => $templateFile,
	    'templateVariables' => array(
	         'site24x7Url' => $site24x7url
    	     ),
	  );
    } catch (Exception $e) {
	// Record the error in WHMCS's module log.
	logModuleCall('zoho_site24x7',  __FUNCTION__, $params, $e->getMessage(), $e->getTraceAsString());
	// In an error condition, display an error page.
	return array(
	    'tabOverviewReplacementTemplate' => 'error.tpl',
	    'templateVariables' => array(
	        'usefulErrorHelper' => $e->getMessage(),
	    ),
	);
    }
}
