<?php

namespace Epayco;


use Epayco\Utils\PaycoAes;
use Epayco\Util;
use Epayco\Exceptions\ErrorException;

/**
 * Client conection api epayco
 */
class Client extends GraphqlClient
{

    const BASE_URL = "https://api.secure.payco.co";
    const BASE_URL_SECURE = "https://secure.payco.co";
    const IV = "0000000000000000";
    const LENGUAGE = "php";

    /**
     * Request api epayco
     * @param  String $method      method petition
     * @param  String $url         url request api epayco
     * @param  String $api_key     public key commerce
     * @param  Object $data        data petition
     * @param  String $private_key private key commerce
     * @param  String $test        type petition production or testing
     * @param  Boolean $switch     type api petition
     * @return Object
     */
    public function request(
        $method,
        $url,
        $api_key,
        $data = null,
        $private_key,
        $test,
        $switch,
        $lang,
        $cash = null,
        $safetyp = null,
        $card = null
    ) {

        /**
         * Resources ip, traslate keys
         */
        $util = new Util();

        /**
         * Switch traslate keys array petition in secure
         */
        if ($switch && is_array($data)) {
            if ($safetyp) {
             $data = $util->setKeys($data, $safetyp);
            }else{
            $data = $util->setKeys($data);
          }
        }
//42677847
        
        /**
         * Set heaToken bearer
         */
      $dataAuth =$this->authentication($api_key,$private_key);
    $auth=gettype($dataAuth);
   $json = json_decode($dataAuth);
   $bearer_token=$json->bearer_token;

        /**
         * Set headers
         */
        $headers= array("Content-Type" => "application/json","Accept" => "application/json","Type"=>'sdk-jwt',"Authorization"=>'Bearer '.$bearer_token );

        try {
            $options = array(
              // 'auth' => new \Requests_Auth_Basic(array($api_key, '')),
                'timeout' => 120,
                'connect_timeout' => 120,
            );

            if ($method == "GET") {
                if ($switch) {
                    if($test){
                        $test="TRUE";
                    }else{
                        $test="FALSE";
                    }

                    $response = \Requests::get(Client::BASE_URL_SECURE . $url, $headers, $options);
                } else {
                    $response = \Requests::get(Client::BASE_URL . $url, $headers, $options);
                }
            } elseif ($method == "POST") {

                if ($switch) {
                    $data = $util->mergeSet($data, $test, $lang, $private_key, $api_key , $cash);

                    $response = \Requests::post(Client::BASE_URL_SECURE . $url, $headers, json_encode($data), $options);
                } else {
 
                    if ($card) {
     
                          $response = \Requests::post(Client::BASE_URL . $url, $headers, json_encode($data), $options);
                    }else{
       
                  $data["ip"] = isset($data["ip"]) ? $data["ip"] : getHostByName(getHostName());
                    $data["test"] = $test;
        
                     $response = \Requests::post(Client::BASE_URL . $url, $headers, json_encode($data), $options);
                    }
  
                }
                if ($safetyp) {
                    $headers2= array( "Accept" => "multipart/form-data");
                    $data = $util->mergeSet($data, $test, $lang, $private_key, $api_key , $cash);
                         $response = \Requests::post(Client::BASE_URL_SECURE . $url, $headers2,$data, $options);
                      
                }
            } elseif ($method == "DELETE") {
                $response = \Requests::delete(Client::BASE_URL . $url, $headers, $options);
            }
        } catch (\Exception $e) {
            throw new ErrorException($e->getMessage(), 101);
        }
        if ($response->status_code >= 200 && $response->status_code <= 206) {
            if ($method == "DELETE") {
                return $response->status_code == 204 || $response->status_code == 200;
            }
            return json_decode($response->body);
        }
        if ($response->status_code == 400) {
            $code = 0;
            $message = "";
            try {
                $error = (array) json_decode($response->body)->errors[0];
                $code = key($error);
                $message = current($error);
            } catch (\Exception $e) {
                throw new ErrorException($lang, 102);
            }
            throw new ErrorException($lang, 103);
        }
        if ($response->status_code == 401) {
            throw new ErrorException($lang, 104);
        }
        if ($response->status_code == 404) {
            throw new ErrorException($lang, 105);
        }
        if ($response->status_code == 403) {
            throw new ErrorException($lang, 106);
        }
        if ($response->status_code == 405) {
            throw new ErrorException($lang, 107);
        }
        throw new ErrorException($lang, 102);
    }

    public function graphql(
        $query,
        $schema,
        $api_key,
        $type,
        $custom_key)
    {
        try{
            $queryString = "";
            $initial_key = "";
            switch ($type){
                case "wrapper":
                    $this->validate($query); //query validator
                    $schema = $query->action === "find" ? $schema."s": $schema;
                    $this->canPaginateSchema($query->action,$query->pagination,$schema);
                    $selectorParams = $this->paramsBuilder($query);

                    $queryString = $this->queryString(
                        $selectorParams,
                        $schema,
                        $query); //rows returned
                    $initial_key = $schema;
                    break;
                case "fixed":
                    $queryString = $query;
                    $initial_key = $custom_key;
                    break;
            }
            $result = $this->sendRequest($queryString,$api_key);
            return $this->successResponse($result,$initial_key);
        }catch (\Exception $e){
            throw new ErrorException($e->getMessage(), 301);
        }

    }

    public function authentication($api_key,$private_key)
    {  
        $data = array(
                'public_key' => $api_key,
                'private_key' => $private_key
            );
         $json=  json_encode($data);
$curl = curl_init();

curl_setopt_array($curl, array(
  CURLOPT_URL => "https://api.secure.payco.co/v1/auth/login",
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => "",
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 0,
  CURLOPT_FOLLOWLOCATION => true,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => "POST",
  CURLOPT_POSTFIELDS =>$json,
  CURLOPT_HTTPHEADER => array(
    "Content-Type: application/json",
    "type: sdk-jwt",
    "Accept: application/json"
  ),
));

$response = curl_exec($curl);

curl_close($curl);

         return $response;
    }
}

