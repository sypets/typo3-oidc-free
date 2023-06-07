<?php

namespace Miniorange\Helper;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class CustomerMo {

    public $email;
    public $phone;

    private $defaultCustomerKey = Constants::DEFAULT_CUSTOMER_KEY;
    private $defaultApiKey = Constants::HOSTNAME;

    function create_customer($email,$password) {

        $url = Constants::HOSTNAME.'/moas/rest/customer/add';
        // $current_user = wp_get_current_user();
        $this->email = $email;
        $password = $password;
        $fields = array (
            'companyName' => $_SERVER['SERVER_NAME'],
            'areaOfInterest' => Constants::AREA_OF_INTEREST,
            'email' => $this->email,
            'password' => $password
        );
        $field_string = json_encode ( $fields );

        $ch = $this->prepareCurlOptions($url,$field_string);
        curl_setopt ( $ch, CURLOPT_HTTPHEADER, array (
            'Content-Type: application/json',
            'charset: UTF - 8',
            'Authorization: Basic'
        ) );

        $response = curl_exec( $ch );
        error_log("create_customer response : ".print_r($response,true));

        if (curl_errno ( $ch )) {
            echo 'Request Error:' . curl_error ( $ch );
            exit ();
        }

        curl_close ( $ch );
        return $response;
    }

    public function submit_contact($email, $phone, $query)
    {
        $this->objectManager = GeneralUtility::makeInstance('TYPO3\\CMS\\Extbase\\Object\\ObjectManager');
        error_log(" TYPO3 SUPPORT QUERY : ");

        sendMail:
        $url = Constants::HOSTNAME.'/moas/api/notify/send';
        $subject = "miniOrange Typo3 OAuth/OIDC Free version Support";

        $customerKey = MoUtilities::fetch_cust(Constants::CUSTOMER_KEY);
        $apiKey      = MoUtilities::fetch_cust(Constants::CUSTOMER_API_KEY);;

        if($customerKey==""){
            $customerKey= $this->defaultCustomerKey ;
            $apiKey = "$this->defaultApiKey";
        }

        $currentTimeInMillis = round(microtime(true) * 1000);
        $stringToHash = $customerKey . number_format($currentTimeInMillis, 0, '', '') . $apiKey;
        $hashValue = hash("sha512", $stringToHash);
        $customerKeyHeader = "Customer-Key: " . $customerKey;
        $timestampHeader = "Timestamp: " . number_format($currentTimeInMillis, 0, '', '');
        $authorizationHeader = "Authorization: " . $hashValue;

        $content = '<div >Hello, <br><br><b>Company :</b><a href="' . $_SERVER['SERVER_NAME'] . '" target="_blank" >' . $_SERVER['SERVER_NAME'] . '</a><br><br><b>Phone Number :</b>' . $phone . '<br><br><b>Email :<a href="mailto:' . $email . '" target="_blank">' . $email . '</a></b><br><br><b>Query: ' . $query . '</b></div>';

        $support_email_id = 'magentosupport@xecurify.com';

        $fields = array(
            'customerKey' => $customerKey,
            'sendEmail' => true,
            'email' => array(
                'customerKey' => $customerKey,
                'fromEmail'   => $email,
                'fromName'    => 'miniOrange',
                'toEmail'     => $support_email_id,
                'toName'      => $support_email_id,
                'subject'     => $subject,
                'content'     => $content
            ),
        );
        $field_string = json_encode($fields);

        error_log("TYPO3 support content : ".print_r($content,true));

        $ch = $this->prepareCurlOptions($url,$field_string);
        curl_setopt($ch, CURLOPT_HTTPHEADER,
                            array("Content-Type: application/json",
                                $customerKeyHeader,
                                $timestampHeader,
                                $authorizationHeader)
        );

        $response = curl_exec($ch);
        error_log("submit_contact response : ".print_r($response,true));

        if (curl_errno($ch)) {
            $message = GeneralUtility::makeInstance(FlashMessage::class,'CURL ERROR','Error',FlashMessage::ERROR,true);
            $out = GeneralUtility::makeInstance(ListRenderer ::class)->render([$message]);
            echo $out;
            return;
        }

        curl_close($ch);
        return $response;

    }

    function check_customer($email,$password) {
        $url = Constants::HOSTNAME."/moas/rest/customer/check-if-exists";
        $fields = array (
            'email' => $email
        );
        $field_string = json_encode ( $fields );

        $ch = $this->prepareCurlOptions($url,$field_string);
        curl_setopt ( $ch, CURLOPT_HTTPHEADER, array (
            'Content-Type: application/json',
            'charset: UTF - 8',
            'Authorization: Basic'
        ) );

        $response = curl_exec ( $ch );
        error_log("check_customer response : ".print_r($response,true));

        if (curl_errno ( $ch )) {
            echo 'Error in sending curl Request';
            exit ();
        }
        curl_close ( $ch );

        return $response;
    }

    function get_customer_key($email,$password) {
        $url = Constants::HOSTNAME."/moas/rest/customer/key";
        $fields = array (
            'email' => $email,
            'password' => $password
        );
        $field_string = json_encode ( $fields );

        $ch = $this->prepareCurlOptions($url,$field_string);
        curl_setopt ( $ch, CURLOPT_HTTPHEADER, array (
            'Content-Type: application/json',
            'charset: UTF - 8',
            'Authorization: Basic'
        ) );

        $response = curl_exec ( $ch );
        error_log("get_customer_key response : ".print_r($response,true));

        if (curl_errno ( $ch )) {
            echo 'Error in sending curl Request';
            exit ();
        }
        curl_close ( $ch );

        return $response;
    }

    function prepareCurlOptions($url, $field_string){

        $ch = curl_init($url);
        curl_setopt ( $ch, CURLOPT_FOLLOWLOCATION, true );
        curl_setopt ( $ch, CURLOPT_ENCODING, "" );
        curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt ( $ch, CURLOPT_AUTOREFERER, true );
        curl_setopt ( $ch, CURLOPT_SSL_VERIFYPEER, false ); // required for https urls
        curl_setopt ( $ch, CURLOPT_SSL_VERIFYHOST, false );
        curl_setopt ( $ch, CURLOPT_MAXREDIRS, 10 );
        curl_setopt ( $ch, CURLOPT_POST, true );
        curl_setopt ( $ch, CURLOPT_POSTFIELDS, $field_string );

        return $ch;
    }

}