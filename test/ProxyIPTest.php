<?php

require __DIR__.'/../vendor/autoload.php';

use PHPUnit\Framework\TestCase;
use MultilineQM\Queue\Driver\Redis;

class ProxyIPTest extends TestCase
{
    private $cache=null;

    private $ip = '192.168.1.1';

    public function testSetIP()
    {
        $host = (getenv('REDIS_HOST') ? getenv('REDIS_HOST') : '127.0.0.1');

        $this->cache = new Redis($host);

        $ret = $this->cache->getConnect()->set('proxy-ips-test', $this->ip);

        $this->assertSame(true, $ret);
    }

    public function testNotEmptyIP()
    {
        $host = (getenv('REDIS_HOST') ? getenv('REDIS_HOST') : '127.0.0.1');

        $this->cache = new Redis($host);

        $ip = $this->cache->getConnect()->get('proxy-ips-test');
        $this->assertNotEmpty($ip);
    }

    public function testGetSameIP()
    {
        $host = (getenv('REDIS_HOST') ? getenv('REDIS_HOST') : '127.0.0.1');

        $this->cache = new Redis($host);

        $ip = $this->cache->getConnect()->get('proxy-ips-test');

        $this->assertSame($ip, $this->ip);
    }
}
