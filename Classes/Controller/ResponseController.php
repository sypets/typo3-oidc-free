<?php


namespace Miniorange\MiniorangeOidc\Controller;

use Miniorange\Helper\Constants;
use Miniorange\Helper\MoUtilities;
use Miniorange\Helper\Utilities;
use Miniorange\Helper\OAuthHandler;
use Miniorange\MiniorangeOidc\Domain\Repository\ResponseRepository;
use Miniorange\Helper\Actions\TestResultActions;

use ReflectionClass;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;
use \TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Felogin\Controller\FrontendLoginController;
use TYPO3\CMS\Extbase\Domain\Model\FrontendUser;
use TYPO3\CMS\Extbase\Domain\Repository\FrontendUserRepository;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

/**
 * ResponseController
 */
class ResponseController extends ActionController
{
    private $first_name = null;
    private $last_name = null;
    protected $persistenceManager = null;
    protected $frontendUserRepository = null;
    private $ses_id = null;

    function testattrmappingconfig($nestedprefix, $resourceOwnerDetails){
        error_log("In ResponseController : testattrmappingconfig()");
        foreach($resourceOwnerDetails as $key => $resource){
            if(is_array($resource) || is_object($resource)){
                if(!empty($nestedprefix))
                    $nestedprefix .= ".";
                $this->testattrmappingconfig($nestedprefix.$key,$resource);
                $nestedprefix = rtrim($nestedprefix,".");
            } else {
                echo "<tr><td>";
                if(!empty($nestedprefix))
                    echo $nestedprefix.".";
                echo $key."</td><td>".$resource."</td></tr>";
            }
        }
    }
    /**
     * action check
     *
     * @return void
     */
    public function responseAction()
    {
        error_log("In reponseController: checkAction() ");

       GeneralUtility::makeInstance(\TYPO3\CMS\Core\Cache\CacheManager::class)->flushCaches();
       if (array_key_exists('logintype', $_REQUEST) && $_REQUEST['logintype'] == 'logout') {
        error_log("Logout intercepted.");
        $this->logout();
        $logoutUrl = $this->request->getBaseUri();
        header('Location: '.$logoutUrl);
       }
       else if (strpos($_SERVER['REQUEST_URI'], "/oauthcallback") !== false || isset($_GET['code'])) {
            if (session_id() == '' || !isset($_SESSION))
                session_start();

                error_log("session started".print_r($_SERVER,true));

            error_log("code: ".print_r($_GET,true));
            if (!isset($_GET['code'])) {
                if (isset($_GET['error_description']))
                    exit($_GET['error_description']);
                else if (isset($_GET['error']))
                    exit($_GET['error']);
                exit('Invalid response');
            } else {

                try {

                    $currentappname = "";

                    if (isset($_SESSION['appname']) && !empty($_SESSION['appname']))
                        $currentappname = $_SESSION['appname'];
                    else if (isset($_GET['state']) && !empty($_GET['state'])) {
                        $currentappname = base64_decode($_GET['state']);
                    }
                    if (empty($currentappname)) {
                        exit('No request found for this application.');
                    }

                    $username_attr = "";
                    $oidc_object = MoUtilities::fetchFromDb('oidc_object', Constants::TABLE_OIDC);
                    $currentapp = isset($oidc_object) ? json_decode((string)$oidc_object, true) : array();

                    if(!$currentapp)
                        exit('Application not configured.');

                    $mo_oauth_handler = new OAuthHandler();

                    if (!isset($currentapp['set_header_credentials']))
                        $currentapp['set_header_credentials'] = false;
                    if (!isset($currentapp['set_body_credentials']))
                        $currentapp['set_body_credentials'] = false;

                    if (isset($currentapp['app_type']) && $currentapp['app_type'] == Constants::TYPE_OPENID_CONNECT) {
                        // OpenId connect

                        $relayStateUrl = array_key_exists('RelayState', $_REQUEST) ? $_REQUEST['RelayState'] : '/';
                        error_log("relaystate in response: ".$relayStateUrl);
                        $tokenResponse = $mo_oauth_handler->getIdToken($currentapp['token_endpoint'],
                            'authorization_code',
                            $currentapp['client_id'],
                            $currentapp['client_secret'],
                            $_GET['code'],
                            $currentapp['redirect_url'],
                            $currentapp['set_header_credentials'],
                            $currentapp['set_body_credentials']
                        );

                        $idToken = isset($tokenResponse["id_token"]) ? $tokenResponse["id_token"] : $tokenResponse["access_token"];

                        if (!$idToken)
                            exit('Invalid token received.');
                        else
                            $resourceOwner = $mo_oauth_handler->getResourceOwnerFromIdToken($idToken);
        
                            if(isset($resourceOwner['email']))
                            $resourceOwner['NameID'] = ['0' => $resourceOwner['email']];
                            else
                            $resourceOwner['NameID'] = ['0' => $this->findUserEmail($resourceOwner)];

                    } else {
                        // echo "OAuth";
                        $accessTokenUrl = $currentapp['token_endpoint'];
                        if (strpos($accessTokenUrl, "google") !== false) {
                            $accessTokenUrl = "https://www.googleapis.com/oauth2/v4/token";
                        }
                        $accessToken = $mo_oauth_handler->getAccessToken($accessTokenUrl,
                           'authorization_code',
                           $currentapp['client_id'],
                           $currentapp['client_secret'],
                           $_GET['code'],
                           $currentapp['redirect_url'],
                           $currentapp['set_header_credentials'],
                           $currentapp['set_body_credentials']
                        );
                        if (!$accessToken)
                            exit('Invalid token received.');

                        $resourceownerdetailsurl = $currentapp['user_info_endpoint'];
                        if (substr($resourceownerdetailsurl, -1) == "=") {
                            $resourceownerdetailsurl .= $accessToken;
                        }
                        if (strpos($resourceownerdetailsurl, "google") !== false) {
                            $resourceownerdetailsurl = "https://www.googleapis.com/oauth2/v1/userinfo";
                        }
                        $resourceOwner = $mo_oauth_handler->getResourceOwner($resourceownerdetailsurl, $accessToken);
                    }

                    $username = "";

                    $nameId = $this->findUserEmail($resourceOwner);
                    $this->nameId = $nameId;
                    //TEST Configuration
                    if (isset($_COOKIE['mo_oauth_test']) && $_COOKIE['mo_oauth_test']) {
                        echo '<div style="font-family:Calibri;padding:0 3%;">';
                        echo '<style>table{border-collapse:collapse;}th {background-color: #eee; text-align: center; padding: 8px; border-width:1px; border-style:solid; border-color:#212121;}tr:nth-child(odd) {background-color: #f2f2f2;} td{padding:8px;border-width:1px; border-style:solid; border-color:#212121;}</style>';
                        (new TestResultActions($resourceOwner, $nameId))->execute();
                        setcookie('mo_oauth_test',false);
                        exit();
                    }
                    $am_username = MoUtilities::fetchFromDb(Constants::OIDC_ATTRIBUTE_USERNAME, Constants::TABLE_OIDC);
                    if (isset( $am_username) &&  $am_username != "") {
                        $username_attr = $am_username;
                    } else {
                        exit("Attribute Mapping not configured.");
                    }

                    if (!empty($username_attr))
                        $username = $this->getnestedattribute($resourceOwner, $username_attr);
                    
                    if (empty($username) || "" === $username)
                        exit('Username not received. Check your <b>Attribute Mapping</b> configuration.');
                    if (!is_string($username['0'])) {
                        exit('Username is not a string. It is ' . gettype($username));
                    }
                } catch (Exception $e) {
                    // Failed to get the access token or user details.
                    exit($e->getMessage());
                }
            }
            if(is_array($username))
            $this->login_user($username['0']);
            else
            $this->login_user($username);
        }


        else if (isset($_REQUEST['option']) and strpos($_REQUEST['option'], 'mooauth') !== false) {
            //do stuff after returning from oAuth processing
            $access_token = $_POST['access_token'];
            $token_type = $_POST['token_type'];
            $user_email = '';
            if (array_key_exists('email', $_POST))
                $user_email = $_POST['email'];

            $this->login_user($user_email);
        }
    }


    function login_user($username){

        error_log("In ResponseController : login_user()");
        
        $GLOBALS['TSFE']->fe_user->checkPid = 0;
        $info = $GLOBALS['TSFE']->fe_user->getAuthInfoArray();
        $user = Utilities::fetchUserFromUsername($username);
        if ($user == null) {
            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable(Constants::TABLE_OIDC);
            $count = $queryBuilder->select('countuser')->from(Constants::TABLE_OIDC)->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter(1, \PDO::PARAM_INT)))->execute()->fetchColumn(0);
            if($count>0)
            {
                $queryBuilder->update(Constants::TABLE_OIDC)->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter(1, \PDO::PARAM_INT)))->set('countuser', $count-1)->execute();
                $user = $this->create($username);
            }
            else{
                echo "Auto create user limit exceeded...Please upgrade to the premium version";exit;
            }
            $user = Utilities::fetchUserFromUsername($username);
        }
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('fe_sessions');

        $queryBuilder->delete('fe_sessions')->where($queryBuilder->expr()->eq('ses_userid',$queryBuilder->createNamedParameter($user['uid'], \PDO::PARAM_INT)))->execute();
        //$queryBuilder->delete('fe_sessions')->where($queryBuilder->expr()->eq('ses_userid',$queryBuilder->createNamedParameter($user['uid'], \PDO::PARAM_INT)))->executeStatement();   

        $GLOBALS['TSFE']->fe_user->forceSetCookie = TRUE;
        $GLOBALS['TSFE']->fe_user->loginUser = 1;
        $GLOBALS['TSFE']->fe_user->start();
        $GLOBALS['TSFE']->fe_user->createUserSession($user);
        $GLOBALS['TSFE']->initUserGroups();
        $GLOBALS['TSFE']->fe_user->loginSessionStarted = TRUE;
        $GLOBALS['TSFE']->fe_user->user = $user;
        $GLOBALS['TSFE']->fe_user->setKey('user', 'fe_typo_user', $user);
        $GLOBALS['TSFE']->fe_user->setKey('ses', 'fe_typo_user', $user);
        $GLOBALS['TSFE']->fe_user->user = $GLOBALS['TSFE']->fe_user->fetchUserSession();
        $GLOBALS['TSFE']->fe_user->setAndSaveSessionData('user', TRUE);
        $this->ses_id = $GLOBALS['TSFE']->fe_user->fetchUserSession();
        $reflection = new ReflectionClass($GLOBALS['TSFE']->fe_user);
        $setSessionCookieMethod = $reflection->getMethod('setSessionCookie');
        $setSessionCookieMethod->setAccessible(TRUE);
        $setSessionCookieMethod->invoke($GLOBALS['TSFE']->fe_user);
        $GLOBALS['TYPO3_CONF_VARS']['SVCONF']['auth']['setup']['FE_alwaysFetchUser'] = true;
        $GLOBALS['TYPO3_CONF_VARS']['SVCONF']['auth']['setup']['FE_alwaysAuthUser'] = true;
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['felogin']['login_confirmed'] = true;
        $GLOBALS['TSFE']->fe_user->storeSessionData();
        $test = $GLOBALS['TSFE']->fe_user->user;
        if (!isset($_SESSION)) {
            session_id('email');
            session_start();
        }
        $_SESSION['ses_id'] = $user['uid'];
    }


    function getnestedattribute($resource, $key){
        error_log("In ResponseController : getnestedattribute()");
        if($key==="")
            return "";

        $keys = explode(".",$key);

        if(sizeof($keys)>1){
            $current_key = $keys[0];
            if(isset($resource[$current_key]))
                return getnestedattribute($resource[$current_key], str_replace($current_key.".","",$key));
        } else {
            $current_key = $keys[0];
            if(isset($resource[$current_key])) {
                return $resource[$current_key];
            }
        }
        return null;
    }

    /**
     * @param $ses_id
     * @param $ssoemail
     * @return string
     * @throws \Exception
     */
    function logout()
    {
        error_log("In ResponseController : logout()");
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('fe_sessions');
        if(isset($_SESSION['ses_id']))
        {
                //$queryBuilder->delete('fe_sessions')->where($queryBuilder->expr()->eq('ses_userid',$queryBuilder->createNamedParameter($_SESSION['ses_id'], \PDO::PARAM_INT)))->executeStatement();
                $queryBuilder->delete('fe_sessions')->where($queryBuilder->expr()->eq('ses_userid',$queryBuilder->createNamedParameter($_SESSION['ses_id'], \PDO::PARAM_INT)))->execute();
        }
    }

	/**
	 * @param $username
	 * @return FrontendUser
	 */
    function create($username)
    {
        $this->objectManager = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Extbase\\Object\\ObjectManager');

        $objectManager = GeneralUtility::makeInstance('TYPO3\\CMS\\Extbase\\Object\\ObjectManager');
        $frontendUser = new FrontendUser();
        $frontendUser->setUsername($username);
        if (filter_var($username, FILTER_VALIDATE_EMAIL))
        {
            $fname_lname = explode('@',$username);
            $first_name = $fname_lname['0'];
            $last_name = $fname_lname['1'];
            $frontendUser->setFirstName($first_name);
            $frontendUser->setLastName($last_name);
            $frontendUser->setEmail($username);
        }
        $frontendUser->setPassword('demouser');
        $mappedGroupUid = Utilities::fetchUidFromGroupName(Utilities::fetchFromTable(Constants::COLUMN_GROUP_DEFAULT,Constants::TABLE_OIDC));
        $userGroup = $this->objectManager->get('TYPO3\\CMS\\Extbase\\Domain\\Repository\\FrontendUserGroupRepository')->findByUid($mappedGroupUid);

        if(isset($userGroup))
        $frontendUser->addUsergroup($userGroup);
        else
        exit("Group not assigned...Please contact your Administrator!");

        $this->frontendUserRepository = $objectManager->get('TYPO3\\CMS\\Extbase\\Domain\\Repository\\FrontendUserRepository')->add($frontendUser);
        $this->persistenceManager = $objectManager->get('TYPO3\\CMS\\Extbase\\Persistence\\Generic\\PersistenceManager')->persistAll();
        return $frontendUser;
    }

    function findUserEmail($arr)
    {
        error_log("ProcessResponseAction: In findUserEmail");
        if ($arr) {
            foreach ($arr as $value) {
                if (is_array($value) && !empty($value)) {
                    return $this->findUserEmail($value);
                }
                elseif(isset($value) && !empty($value) && $value!=null){
                    if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        return $value;
                    }
                }
                else
                {
                    error_log("null parameter");
                }
            }
        }
    }
}