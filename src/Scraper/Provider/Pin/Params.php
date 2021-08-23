<?php
namespace MultilineQM\Scraper\Provider\Pin;

use MultilineQM\Scraper\Provider\ParamsAbstract;

class Params extends ParamsAbstract {
    private $username;
    private $password;
    private $proxy;

    public function __construct($username, $password, $proxy)
    {
        $this->username =  $username;
        $this->password = $password;
        $this->proxy = $proxy;
    }

    public function getRequestParams()
    {
        $headers = [
            'headers' => [
                'Content-Type'=>"application/json",
            ]
        ];

        if (!empty($this->username) && !empty($this->password))
        {
            $credentials = base64_encode("{$this->username}:{$this->password}");
            $headers["headers"]["Authorization"] = "Basic {$credentials}";
        }
        
        if (!empty($this->proxy))
        {
            $headers["proxy"] = $this->proxy;
        }
        return $headers;
    }

    public function getBaseParams()
    {
        return [
            'base_uri' => 'https://api.ps3838.com',
            'timeout'  => 5.0,
        ];
    }
}