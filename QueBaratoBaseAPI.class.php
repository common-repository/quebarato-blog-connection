<?php

include_once "RestClient.class.php";

class QueBaratoBaseAPI{
	
	protected $api_key;

	protected $base_url;
	const RESULT_SUCCESS 				= 200;
	const RESULT_UNAUTHORIZED			= 401;
	const RESULT_NOT_MODIFIED			= 304;
	const RESULT_BAD_REQUEST			= 400;
	const RESULT_NOT_ACCEPTABLE			= 406;
	const RESULT_SERVICE_UNAVALIABLE	= 503;
	const RESULT_INTERNAL_SERVER_ERROR	= 500;
	

	const RESULT_UNSUPPORTED_MEDIA_TYPE	= 415;
	
	public function __construct($p_url, $p_api_key){
		$this->setBaseUrl($p_url);
		$this->setApiKey($p_api_key);
	}
	public function setBaseUrl($p_url){
		$this->url = $p_url;
	}
	public function setApiKey($p_api_key){
		$this->api_key = $p_api_key;
	}
	public function getBaseUrl(){
		return $this->base_url;
	}
	public function getApiKey(){
		return $this->api_key;
	}

	protected static function defaultResultHandler($codeReturn){
		$ReturnResultVO = new ReturnResultVO();
		switch($codeReturn){
			case self::RESULT_INTERNAL_SERVER_ERROR:
        		$ReturnResultVO->success 	= FALSE;
        		$ReturnResultVO->result 	= self::RESULT_INTERNAL_SERVER_ERROR;
        		$ReturnResultVO->addMessage("Internal Server Error.");
        		break;
        	case self::RESULT_NOT_ACCEPTABLE:
        		$ReturnResultVO->success = FALSE;
        		$ReturnResultVO->result 	= self::RESULT_NOT_ACCEPTABLE;
        		$ReturnResultVO->addMessage("Não aceito.");
        		break;
        	case self::RESULT_NOT_MODIFIED:
        		$ReturnResultVO->success = FALSE;
        		$ReturnResultVO->result 	= self::RESULT_NOT_MODIFIED;
        		$ReturnResultVO->addMessage("Não modificado.");
        		break;
        	case self::RESULT_UNAUTHORIZED:
        		$ReturnResultVO->success = FALSE;
        		$ReturnResultVO->result 	= self::RESULT_UNAUTHORIZED;
        		$ReturnResultVO->addMessage("Não autorizado.");
        		break;
        	case self::RESULT_SERVICE_UNAVALIABLE:
        		$ReturnResultVO->success 	= FALSE;
        		$ReturnResultVO->result 	= self::RESULT_SERVICE_UNAVALIABLE;
        		$ReturnResultVO->addMessage("Serviço não disponível no momento.");
        		break;
        	default:
        		$ReturnResultVO->success = FALSE;
        		$ReturnResultVO->result 	= -1;
        		$ReturnResultVO->addMessage("Sem resultado[ $codeReturn ] ");
        		break;
		}
		return $ReturnResultVO;
	}
	
	
	public function post($base, $data, $user = NULL, $pass = NULL, $type = 'application/json'){
		
		set_time_limit(0);
		
		$authorizationHeader = null;
		
		if($user != null && $pass != null){
			$authorizationHeader = array("Authorization",base64_encode($user.":".$pass));
		}
		
		$rt = 	RestClient::post(
            $this->url.$base,
            $data,
            null,
            null,
            $type,
            array(
            	$authorizationHeader,
				array("X-QB-Key", $this->api_key ),
				array("Accept", "application/json")
			)
        );
        
        $resultCode 	= $rt->getResponseCode()*1;
        
        $result 		= json_decode($rt->getResponse());
		
		
		return (object) array("resultCode"=>$resultCode,"result"=>$result, "header"=>$rt->getHeaders());
		
	}
	

	public function get($base, $id = NULL){
		return $this->select($base  . (isset($id)? "/" . $id : "" ) );
	}
	

	public function getAuthorized($base, $id = NULL, $user = NULL, $pass = NULL){
		return $this->selectAuthorized($base  . (isset($id)? "/" . $id : "" ), $user, $pass);
	}
	
	public function head($base, $id = NULL){
		return $this->select($base  . (isset($id)? "/" . $id : "" ) );
	}
	
	
	public function select($base = ""){
		set_time_limit(0);
		
		$rt = 	RestClient::get(
            $this->url.$base,
            null,
            null,
            null,
            "application/json",
            array(
				array("X-QB-Key", $this->api_key ),
				array("Accept", "application/json")
			)
        );
        
        $resultCode 	= $rt->getResponseCode()*1;
		
        $result 		= json_decode($rt->getResponse());

		return (object) array("resultCode"=>$resultCode,"result"=>$result);
	}
	

	public function selectAuthorized($base = "", $user = NULL, $pass = NULL){
		
		set_time_limit(0);
		
		$authorizationHeader = null;
		
		if($user != null && $pass != null){
			$authorizationHeader = array("Authorization",base64_encode($user.":".$pass));
		}
		
		$rt = 	RestClient::get(
            $this->url.$base,
            null,
            null,
            null,
            "application/json",
            array(
            	$authorizationHeader,
				array("X-QB-Key", $this->api_key ),
				array("Accept", "application/json")
			)
        );
        
        $resultCode 	= $rt->getResponseCode()*1;
		
        $result 		= json_decode($rt->getResponse());

		return (object) array("resultCode"=>$resultCode,"result"=>$result);
	}

}?>