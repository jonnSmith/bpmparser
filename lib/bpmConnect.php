<?php

class bpmConnect {

    private static $service = 'https://realestate.bpmonline.com/';
    private static $agent = 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; .NET CLR 1.0.3705; .NET CLR 1.1.4322)';
    private static $UserName = 'UserName';
    private static $UserPassword = 'password';
    public $message;

    public function __construct() {
        $login_url = self::$service."ServiceModel/AuthService.svc/Login";
        $login_data = $this->Login($login_url);
        $login_message = json_decode(substr($login_data,strpos($login_data,'{')));
        $this->message = $login_message->Message;
    }

    public function getMessage() {
        return $this->message;
    }

    private function requestCurl($url, $request, $params=[]) {
        global $path;
        $ch = curl_init();
        if(strtolower((substr(self::$service.$url,0,5))=='https')) { // если соединяемся с https
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        }
        if($request == 'filtered_data') {
            curl_setopt($ch, CURLOPT_URL, self::$service.$url.'?$filter='.$params['filter'].'+eq+'.$params['type']."'".$params['value']."'");
        } else if ($request == 'filtered_entry') {
            curl_setopt($ch, CURLOPT_URL, self::$service.$url.'?$filter='.$params['filter'].'+eq+'.$params['type']."'".$params['value']."'".'&$orderby=ModifiedOn+desc&$top=1');
        } else if ($request == 'data') {
            curl_setopt($ch, CURLOPT_URL, self::$service.$url.'?$skip='.$params['offset'].'&$top='.$params['limit'].$params['orderby']);
        } else if ($request == 'image') {
            curl_setopt($ch, CURLOPT_URL, self::$service.$url);
            curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
        } else if ($request == 'login') {
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POSTFIELDS,$params['fields']);
        }
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, self::$agent);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        if($request == 'login') {
            curl_setopt($ch, CURLOPT_REFERER, $url);
            curl_setopt($ch, CURLOPT_VERBOSE, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HEADER, 1);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
            curl_setopt($ch, CURLOPT_COOKIEJAR, $path.'/tmp/cookie.txt');
        } else {
            curl_setopt($ch, CURLOPT_POST, false);
            curl_setopt($ch, CURLOPT_COOKIEFILE, $path.'/tmp/cookie.txt');
        }
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }

    private function Login($url) {
        $requestData["UserName"] = self::$UserName;
        $requestData["UserPassword"] = self::$UserPassword;
        return $this->requestCurl($url,'login',['fields'=>json_encode($requestData)]);
    }

    public function getData($url, $offset, $limit, $orderby='') {
        return $this->requestCurl($url, 'data',['offset'=>$offset,'limit'=>$limit,'orderby'=>$orderby]);
    }

    public function getFilterData($url, $filter, $value, $type) {
        return $this->requestCurl($url, 'filtered_data',['filter'=>$filter,'value'=>$value,'type'=>$type]);
    }

    public function getFilterEntry($url, $filter, $value, $type) {
        return $this->requestCurl($url, 'filtered_entry',['filter'=>$filter,'value'=>$value,'type'=>$type]);
    }

    public function getImageFile($url) {
        return $this->requestCurl($url, 'image',[]);
    }

}