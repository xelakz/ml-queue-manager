<?php
declare(strict_types=1);
namespace MultilineQM\Scraper;

use MultilineQM\Queue\Driver\DriverInterface;
use MultilineQM\OutPut\OutPut;

class ProxyIP
{
    private $driver = null;
    private $provider = '';

    /**
     * Queue constructor.
     * @param \MultilineQM\Queue\Driver\DriverInterface $driver Queue driver
     * @param string $provider provider code
     */
    public function __construct(DriverInterface $driver, $provider='hg')
    {
        $this->driver = $driver;
        $this->provider = $provider;
    }

    /**
     * Get list of proxy ips
     * @return mixed|null
     */
    public function getAll()
    {
        return $this->driver->getConnect()->get('proxy-ips');
    }

    /**
     * Get one available proxy
     * @throws \Exception
     * @return mixed|null
     */
    public function getOne()
    {
        $cache_members = $this->driver->getConnect()->smembers("url-proxy-ips");
        if (!empty($cache_members))
        {
            $url = '';
            foreach ($cache_members as $row) {
                if ($this->isAvailable($row))
                {
                    $url = $row;
                    break;
                }
            }
            return $url;
        }
        else {
            OutPut::warning("Proxy IP should not be empty" . PHP_EOL);
        }

        return '';
    }

    /**
     * Check if proxy is available
     * @param string $url proxy
     * @return boolean
     */
    private function isAvailable($url)
    {
        if (empty($url)) {
            return false;
        }

        $proxy_members = $this->driver->getConnect()->smembers("session:{$this->provider}:assignedproxyurl");
        if (empty($proxy_members))
        {
            return true;
        }
        
        $used_proxies = $this->getUsedProxy();

        $is_available = false;
        if (!in_array($url, $used_proxies))
        {
            $is_available = true;
        }
        return $is_available;
    }

    private function getUsedProxy()
    {
        $used_proxy = [];
        $cache_user_members = $this->driver->getConnect()->smembers("session:{$this->provider}:users");
        foreach ($cache_user_members as $user)
        {
            $cache_active_user = $this->driver->getConnect()->get("session:{$this->provider}:active:{$user}");
            if (empty($cache_active_user))
            {
                continue;
            }
            $user_dict = json_decode($cache_active_user, true);

            if (!empty($user_dict))
            {
                $proxy = $user_dict["proxy"];
                array_push($used_proxy, $proxy);
            }
        }
        return $used_proxy;
    }
}