<?php

namespace Miniorange\MiniorangeOidc\Controller;

use Exception;

use Miniorange\MiniorangeOidc\Domain\Model\Beoidc;
use Miniorange\Helper\MoUtilities;
use Miniorange\Helper\CustomerMo;
use Miniorange\Helper\Constants;
use Miniorange\Helper\Actions\TestResultActions;

use PDO;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;
use \TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Tstemplate\Controller\TypoScriptTemplateModuleController;
use TYPO3\CMS\Extbase\Object\ObjectManager;

use Miniorange\Helper\Utilities;
/**
 *BeoidcController
 */
class BeoidcController extends ActionController
{

    protected $tab = "";

    /**
     * @throws Exception
     */
    public function requestAction()
    {
        error_log("BeoidcController.php : In requestAction");
        $util=new Utilities();
        $baseurl= $util->currentPageUrl();
        $_SESSION['base_url']=$baseurl;
        if(isset($_POST['option'])){
            if(empty($_POST['app_type'])){
                $_POST['app_type'] = Constants::TYPE_OPENID_CONNECT;
            }
        }

//------------ OPENID CONNECT SETTINGS---------------
        if(isset($_POST['option']) and $_POST['option']=="oidc_settings"){
            if(empty($_POST['set_body_credentials'])){
                $_POST['set_body_credentials'] = 'false';
            }
            if(empty($_POST['set_header_credentials'])){
                $_POST['set_header_credentials'] = 'false';
            }
            $value1 = $this->validateURL($_POST['feoidc']);
            $value2 = $this->validateURL($_POST['redirect_url']);
            $value3 = $this->validateURL($_POST['auth_endpoint']);
            $value4 = $this->validateURL($_POST['token_endpoint']);
            if($value1 == 1 && $value2 == 1 && $value3 == 1 && $value4 == 1)
            {

               $this->defaultSettings($_POST);
               MoUtilities::showSuccessFlashMessage('Settings saved successfully!');
                //$this->storeToDatabase($_POST);
            }
            else
            {
                MoUtilities::showErrorFlashMessage('Incorrect configurations!');
            }
        }

        //------------ ATTRIBUTE MAPPING---------------
        if (isset( $_POST['option'] ) and $_POST['option'] == "attribute_mapping"){

            $username = $_POST['oidc_am_username'];

            if(!MoUtilities::isEmptyOrNull($username))
            {
                if($this->fetchFromOidc('uid') == null){
                    MoUtilities::showErrorFlashMessage('Please configure OAuth / OpenID Connect client first.');
                }else{
                    $this->updateOidc(Constants::OIDC_ATTRIBUTE_USERNAME,$username);
                    MoUtilities::showSuccessFlashMessage('Attribute Mapping saved successfully.');
                }
            }else{
                MoUtilities::showErrorFlashMessage('Please provide valid input.');
            }
        }

        //------------ GROUP MAPPING---------------
        if(isset( $_POST['option']) and $_POST['option'] == 'group_mapping')
        {
            $this->updateOidc(Constants::COLUMN_GROUP_DEFAULT,$_POST['defaultUserGroup']);
            MoUtilities::showSuccessFlashMessage('Group Mapping saved successfully!');
        }

        //------------ VERIFY CUSTOMER---------------
        if ( isset( $_POST['option'] ) and $_POST['option'] == "mo_oidc_verify_customer" ) {
            $this->account($_POST);
        }

        //------------ HANDLE LOG OUT ACTION---------------
        if(isset($_POST['option']) and $_POST['option']=='logout'){
            $this->remove_cust();
                MoUtilities::showSuccessFlashMessage('You account is removed successfully.');
                $this->view->assign('status','not_logged');
        }

        //------------ Support---------------
        if(isset($_POST['option']) and $_POST['option'] == 'mo_oauth_contact_us_query_option') {
            if (isset($_POST['option']) and $_POST['option'] == "mo_oauth_contact_us_query_option") {
                error_log('Received support query.  ');
                $this->support();
            }
        }

//------------ CHANGING TABS---------------
        if(!empty($_POST['option']))
        {
           if ($_POST['option'] == 'oidc_settings' || $_POST['option'] == '')
            {
                $this->tab = "OIDC_Settings";
            }
            elseif ($_POST['option'] == 'attribute_mapping')
            {
                $this->tab = "Attribute_Mapping";
            }
            elseif($_POST['option'] == 'Group_Mapping')
            {
                $this->tab = "Group_Mapping";
            }
            else
            {
                $this->tab = "Account";
            }
        }

        $this->objectManager = GeneralUtility::makeInstance('TYPO3\\CMS\\Extbase\\Object\\ObjectManager');
        $allUserGroups= $this->objectManager->get('TYPO3\\CMS\\Extbase\\Domain\\Repository\\FrontendUserGroupRepository')->findAll();
        GeneralUtility::makeInstance(\TYPO3\CMS\Core\Cache\CacheManager::class)->flushCaches();
        $oidc_object = $this->fetchFromOidc('oidc_object');
        $oidc_object = json_decode((string)$oidc_object,true);
        $allUserGroups->getQuery()->getQuerySettings()->setRespectStoragePage(false);
        $this->view->assign('allUserGroups', $allUserGroups);
        $this->view->assign('defaultGroup',Utilities::fetchFromTable(Constants::COLUMN_GROUP_DEFAULT,Constants::TABLE_OIDC));

//------------ LOADING SAVED SETTINGS OBJECTS TO BE USED IN VIEW---------------
        $this->view->assign('conf', $oidc_object);
        $this->view->assign('am_username', $this->fetchFromOidc(Constants::OIDC_ATTRIBUTE_USERNAME));

        $this->view->assign('tab', $this->tab);

        if(isset($oidc_object) && !empty($oidc_object))
        {
            $feoidc_validated = $this->validateURL($oidc_object['feoidc']);
            $redirect_validated = $this->validateURL($oidc_object['redirect_url']);
            $authorize_validated = $this->validateURL($oidc_object['auth_endpoint']);
            $token_validated = $this->validateURL($oidc_object['token_endpoint']);
            if($oidc_object['app_type'] == 'OAuth')
            {
                $value = $this->validateURL($oidc_object['user_info_endpoint']);
    
                if($value == 1 && $feoidc_validated == 1 && $redirect_validated == 1 && $authorize_validated == 1 && $token_validated == 1)
                {
                    $this->view->assign('test_enabled', '');
                }
                else
                {
                    $this->view->assign('test_enabled', 'disabled');
                }
                $this->view->assign('userinfo', 'display:block');
            }
            else
            {
                if($feoidc_validated == 1 && $redirect_validated == 1 && $authorize_validated == 1 && $token_validated == 1)
                {
                    $this->view->assign('test_enabled', '');
                }
                else
                {
                    $this->view->assign('test_enabled', 'disabled');
                }
                $this->view->assign('userinfo', 'display:none');
            }
        }
        else
        {
            $this->view->assign('test_enabled', 'disabled');
            $this->view->assign('userinfo', 'display:none');
        }

        //------------ LOADING VARIABLES TO BE USED IN VIEW---------------
        if($this->fetch_cust(Constants::CUSTOMER_REGSTATUS) == 'logged'){
            $this->view->assign('status','logged');
            $this->view->assign('log', '');
            $this->view->assign('nolog', 'display:none');
            $this->view->assign('email',$this->fetch_cust('cust_email'));
            $this->view->assign('key',$this->fetch_cust('cust_key'));
            $this->view->assign('token',$this->fetch_cust('cust_token'));
            $this->view->assign('api_key',$this->fetch_cust('cust_api_key'));
        }else{
            $this->view->assign('log', 'disabled');
            $this->view->assign('nolog', 'display:block');
            $this->view->assign('status','not_logged');
        }
        GeneralUtility::makeInstance(\TYPO3\CMS\Core\Cache\CacheManager::class)->flushCaches();
    }

// FETCH CUSTOMER
    public function fetchFromCustomer($col)
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable(Constants::TABLE_CUSTOMER);
        $variable = $queryBuilder->select($col)->from(Constants::TABLE_CUSTOMER)->where($queryBuilder->expr()->eq('id', $queryBuilder->createNamedParameter(1, PDO::PARAM_INT)))->execute()->fetchColumn(0);
        return $variable;
    }

// ---- UPDATE CUSTOMER Details
    public function updateCustomer($column, $value)
    {
        error_log("In BeoidcController : updateCustomer()");
        if($this->fetchFromCustomer('id') == null)
        {
            $this->insertCustomerRow();
        }
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable(Constants::TABLE_CUSTOMER);
        $queryBuilder->update(Constants::TABLE_CUSTOMER)->where($queryBuilder->expr()->eq('id', $queryBuilder->createNamedParameter(1, PDO::PARAM_INT)))->set($column, $value)->execute();
    }

    // FETCH OIDC VALUES
    public function fetchFromOidc($col){
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('mo_oidc');
        $variable = $queryBuilder->select($col)->from('mo_oidc')->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter(1, PDO::PARAM_INT)))->execute()->fetchColumn(0);
        return $variable;
    }

// ---- UPDATE OIDC Settings
    public function updateOidc($column, $value)
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable(Constants::TABLE_OIDC);
        $queryBuilder->update(Constants::TABLE_OIDC)->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter(1, PDO::PARAM_INT)))->set($column, $value)->execute();
    }

    public function insertCustomerRow()
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable(Constants::TABLE_CUSTOMER);
        $affectedRows = $queryBuilder->insert(Constants::TABLE_CUSTOMER)->values([  'id' => '1' ])->execute();
    }

    /**
     * @param $col
     * @param string $table
     * @return bool|string
     */

    /**
     * @param $postArray
     */
    public function defaultSettings($postArray)
    {

        error_log("In BeoidcController : defaultSettings: ");
        $this->oidc_object = json_encode($postArray);
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable(Constants::TABLE_OIDC);
        $uid=$queryBuilder->select('uid')->from(Constants::TABLE_OIDC)
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter(1, PDO::PARAM_INT)))
            ->execute()->fetchColumn(0);
        if($uid== null)
        {
            $affectedRows = $queryBuilder
            ->insert(Constants::TABLE_OIDC)
            ->values([
                'oidc_object' => $this->oidc_object])
            ->execute();
        }
        else{
            $queryBuilder->update('mo_oidc')->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter(1, PDO::PARAM_INT)))->set('oidc_object', $this->oidc_object)->execute();
    }
    }

    //   HANDLE LOGIN FORM
    public function account($post){
        $email = $post['email'];
        $password = $post['password'];
        $customer = new CustomerMo();
        $customer->email = $email;
        MoUtilities::update_cust('cust_email',$email);
        $check_customer = $customer->check_customer($email,$password);
        $check_content = isset($check_customer) && !empty($check_customer) ? json_decode((string)$check_customer, true) : array();
        if($check_content['status'] == 'CUSTOMER_NOT_FOUND'){
            error_log("CUSTOMER_NOT_FOUND.. Creating ...");
            $create_customer = $customer->create_customer($email,$password);
            $result = isset($create_customer) && !empty($create_customer) ? json_decode((string)$create_customer, true) : array();
        	   if($result['status']== 'SUCCESS' ){
                    $get_customer_key = $customer->get_customer_key($email,$password);
                    $key_content = isset($get_customer_key) && !empty($get_customer_key) ? json_decode((string)$get_customer_key, true) : array();
							 if($key_content['status'] == 'SUCCESS'){
								 $this->saveCustomer($key_content,$email);
								 Utilities::showSuccessFlashMessage('User created successfully.');
							 }else{
								 Utilities::showErrorFlashMessage('It seems like you have entered the incorrect password');
							 }
        	   }
        }elseif ($check_content['status'] == 'SUCCESS'){
            $get_customer_key = $customer->get_customer_key($email,$password);
            $key_content = isset($get_customer_key) && !empty($get_customer_key) ? json_decode((string)$get_customer_key, true) : array();

            if($key_content['status']){
                $this->saveCustomer($key_content,$email);
                MoUtilities::showSuccessFlashMessage('User retrieved successfully.');
            }
            else{
                MoUtilities::showErrorFlashMessage('It seems like you have entered the incorrect password');
            }
        }
    }

    //  SAVE CUSTOMER
    public function saveCustomer($content, $email){
        $this->updateCustomer(Constants::CUSTOMER_KEY,$content['id']);
        $this->updateCustomer(Constants::CUSTOMER_API_KEY,$content['apiKey']);
        $this->updateCustomer(Constants::CUSTOMER_TOKEN,$content['token']);
        $this->updateCustomer(Constants::CUSTOMER_REGSTATUS, 'logged');
        $this->updateCustomer(Constants::CUSTOMER_EMAIL,$email);
    }

    // FETCH CUSTOMER
    public function fetch_cust($col)
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('mo_customer');
        $variable = $queryBuilder->select($col)->from('mo_customer')->where($queryBuilder->expr()->eq('id', $queryBuilder->createNamedParameter(1, PDO::PARAM_INT)))->execute()->fetchColumn(0);
        return $variable;
    }

    //  LOGOUT CUSTOMER
    public function remove_cust(){
        $this->updateCustomer(Constants::CUSTOMER_KEY,'');
        $this->updateCustomer(Constants::CUSTOMER_EMAIL,'');
        $this->updateCustomer(Constants::CUSTOMER_TOKEN,'');
        $this->updateCustomer(Constants::CUSTOMER_API_KEY, '');
        $this->updateCustomer(Constants::CUSTOMER_REGSTATUS,'');
    }

    // --------------------SUPPORT QUERY---------------------
	public function support()
    {
        if(!$this->mo_oidc_is_curl_installed() ) {
        	    Utilities::showErrorFlashMessage('ERROR: <a href="http://php.net/manual/en/curl.installation.php" target="_blank">PHP cURL extension</a> is not installed or disabled. Query submit failed.');
            return;
        }
        // Contact Us query
        $email    = $_POST['mo_oauth_contact_us_email'];
        $phone    = $_POST['mo_oauth_contact_us_phone'];
        $query    = $_POST['mo_oauth_contact_us_query'];

        $customer = new CustomerMo();

        if($this->mo_oidc_check_empty_or_null( $email ) || $this->mo_oidc_check_empty_or_null( $query ) ) {
          Utilities::showErrorFlashMessage('Please enter a valid Email address. ');
        }elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        	  Utilities::showErrorFlashMessage('Please enter a valid Email address. ');
        }else {
            $submitted = json_decode((string)$customer->submit_contact( $email, $phone, $query ), true);
						if ( $submitted['status'] == 'SUCCESS' ) {
							Utilities::showSuccessFlashMessage('Support query sent ! We will get in touch with you shortly.');
						}else {
											Utilities::showErrorFlashMessage('could not send query. Please try again later or mail us at info@miniorange.com');
						}
        }

    }

    public function mo_oidc_is_curl_installed() {
        if ( in_array( 'curl', get_loaded_extensions() ) ) {
            return 1;
        } else {
            return 0;
        }
    }

    public function mo_oidc_check_empty_or_null( $value ) {
        if( ! isset( $value ) || empty( $value ) ) {
            return true;
        }
        return false;
    }

    public function validateURL($url)
    {
        if (filter_var($url, FILTER_VALIDATE_URL)) {
            return 1;
        } else {
            return 0;
        }
    }
}
