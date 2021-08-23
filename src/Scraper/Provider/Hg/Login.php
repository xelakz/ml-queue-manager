<?php
namespace MultilineQM\Scraper\Provider\Hg;

use MultilineQM\Scraper\LoginInterface;
use MultilineQM\Scraper\Client\Request;

class Login implements LoginInterface {
  
    private $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function process()
    {
        echo "HG Login Process" . "\n";
    }
}