<?php

require __DIR__.'/../vendor/autoload.php';

use PHPUnit\Framework\TestCase;
use MultilineQM\Queue\Driver\Redis;

class CacheTest extends TestCase
{
    public function testRedisConnection()
    {
        $host = (getenv('REDIS_HOST') ? getenv('REDIS_HOST') : '127.0.0.1');

        $cache = new Redis($host);

        $connect = $cache->getConnect();

        $this->assertSame(get_class($connect), 'Redis');
    }
}
