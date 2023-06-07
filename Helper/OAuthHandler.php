<?php

namespace Miniorange\Helper;

class OAuthHandler {

    function getAccessToken($tokenendpoint, $grant_type, $clientid, $clientsecret, $code, $redirect_url, $send_headers, $send_body){
        $response = $this->getToken ($tokenendpoint, $grant_type, $clientid, $clientsecret, $code, $redirect_url, $send_headers, $send_body);
        
        if(isset($response["access_token"])) {
            return $response["access_token"];
        } else {
            echo 'Invalid response received from OAuth Provider. Contact your administrator for more details.<br><br><b>Response : </b><br>'.print_r($response,true);
            exit;
        }
    }

    function getToken($tokenendpoint, $grant_type, $clientid, $clientsecret, $code, $redirect_url, $send_headers, $send_body){
        
        $ch = $this->prepareCurlOptions($tokenendpoint);

        if($send_headers=='on') {
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Authorization: Basic ' . base64_encode( $clientid . ":" . $clientsecret ),
                'Accept: application/json'
            ));
            curl_setopt( $ch, CURLOPT_POSTFIELDS, 'redirect_uri='.urlencode($redirect_url).'&grant_type='.$grant_type.'&code='.$code);

        }else{
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Accept: application/json'
            ));
            curl_setopt( $ch, CURLOPT_POSTFIELDS, 'redirect_uri='.urlencode($redirect_url).'&grant_type='.$grant_type.'&client_id='.$clientid.'&client_secret='.$clientsecret.'&code='.$code);
        }

        $response = curl_exec($ch);

        if(curl_error($ch)){
            echo "<b>Response : </b><br>";print_r($response);echo "<br><br>";
            exit( curl_error($ch) );
        }

        if(!is_array(json_decode((string)$response, true))){
            echo "<b>Response : </b><br>";print_r($response);echo "<br><br>";
            exit("Invalid response received. 1");
        }

        $response = json_decode((string)$response,true);
        if (isset($response["error"])) {
            if (is_array($response["error"])) {
                $response["error"] = $response["error"]["message"];
            }
            exit($response["error"]);
        }
        else if(isset($response["error_description"])){
            exit($response["error_description"]);
        }
        return $response;
    }

    function getIdToken($tokenendpoint, $grant_type, $clientid, $clientsecret, $code, $redirect_url, $send_headers, $send_body){
        $content = $this->getToken ($tokenendpoint, $grant_type, $clientid, $clientsecret, $code, $redirect_url, $send_headers, $send_body);

        if(isset($content["id_token"]) || isset($content["access_token"])) {
            return $content;
            exit;
        } else {
            echo 'Invalid response received from OpenId Provider. Contact your administrator for more details.<br><br><b>Response : </b><br>'.$response;
            exit;
        }
    }

    function getResourceOwnerFromIdToken($id_token){
        $id_array = explode(".", $id_token);
        if(isset($id_array[1])) {
            $id_body = base64_decode($id_array[1]);
            if(isset($id_body) && !empty($id_body) && is_array(json_decode((string)$id_body, true))){
                return json_decode((string)$id_body,true);
            }
        }
        echo 'Invalid response received.<br><b>Id_token : </b>'.$id_token;
        exit;
    }

    function getResourceOwner($resourceownerdetailsurl, $access_token){
        $ch = $this->prepareCurlOptions($resourceownerdetailsurl);

        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer ' .$access_token));

        $response = curl_exec($ch);
        $content = json_decode((string)$response,true);
        error_log("userinfo response array: ".print_r($content,true));
        if(isset($content["error_description"])){
            exit($content["error_description"]);
        } else if(isset($content["error"])){
            exit(print_r($content["error"],true));
        }
        return $content;
    }

    function getResponse($url){
        $response = wp_remote_get($url, array(
            'method' => 'GET',
            'timeout' => 45,
            'redirection' => 5,
            'httpversion' => 1.0,
            'blocking' => true,
            'headers' => array(),
            'cookies' => array(),
            'sslverify' => false,
        ));

        $content = isset($response) && !empty($response) ? json_decode((string)$response,true) : array();
        if(isset($content["error_description"])){
            exit($content["error_description"]);
        } else if(isset($content["error"])){
            exit($content["error"]);
        }

        return $content;
    }

    function prepareCurlOptions($url){
        $ch = curl_init($url);
        curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true );
        curl_setopt( $ch, CURLOPT_ENCODING, "" );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch, CURLOPT_AUTOREFERER, true );
        curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
        curl_setopt( $ch, CURLOPT_MAXREDIRS, 10 );
        curl_setopt( $ch, CURLOPT_POST, true);

        return $ch;
    }

}

