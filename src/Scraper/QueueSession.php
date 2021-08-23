<?php
declare(strict_types=1);
namespace MultilineQM\Scraper;

use MultilineQM\Queue\Driver\DriverInterface;
use MultilineQM\OutPut\OutPut;

class QueueSession
{
    private $driver = null;
    private $provider;
    private $username;

    /**
     * QueueSession constructor.
     * @param \MultilineQM\Queue\Driver\DriverInterface $driver Queue driver
     * @param string $provider provider code
     * @param string $username user to find / use
     */
    public function __construct(DriverInterface $driver, string $provider, string $username)
    {
        $this->driver = $driver;
        $this->provider = (empty($provider)) ? 'pin' : $provider;
        $this->username = $username;
    }

    public function getAvailableSession($category, $task)
    {
        $active_users = $this->getActiveUsersByCategory($category);
        if (empty($active_users))
        {
            OutPut::normal("No active users." . PHP_EOL);
            return [];
        }

        $selected_session = [];
        if ($category == "bet")
        {
            $cache_active_user = $this->driver->getConnect()->get("session:{$this->provider}:active:{$this->username}");
            $user_dict = json_decode($cache_active_user, true);
            if (!empty($user_dict))
            {
                if (!empty($user_dict["token"]))
                {
                    OutPut::normal("Selected user: {$this->username}" . PHP_EOL);
                    $selected_session = array(
                        "username"=>$this->username,
                        "password"=>$user_dict["password"],
                        "token"=>$user_dict["token"],
                        "cookies"=>$user_dict["cookies"],
                        "proxy"=>$user_dict["proxy"],
                        "category"=>$user_dict["category"],
                        "ver"=>$user_dict["ver"],
                    );

                    $this->driver->getConnect()->set("session:{$this->provider}:last_call:{$this->username}", strtotime("now"));
                    $this->driver->getConnect()->set("session:{$this->provider}:assigned:{$this->username}", json_encode($task));
                }
            }
            return $selected_session;
        }

        $previous_user = '';
        $i = 1;
        while ($i <= 10) {
            $continue = true;
            $current_user = $this->driver->getConnect()->rpop("session:{$this->provider}:queue:{$category}");
            if (empty($current_user))
            {
                OutPut::normal("Queue [" . "session:{$this->provider}:queue:{$category}" . "] is empty." . PHP_EOL);
                break;
            }
            $continue = ($previous_user == $current_user) ? false:true;

            if ($continue)
            {
                $previous_user = $current_user;
                $cache_active_user = $this->driver->getConnect()->get("session:{$this->provider}:active:{$current_user}");
                $user_dict = json_decode($cache_active_user, true);
                if (!empty($user_dict))
                {
                    $this->driver->getConnect()->lpush("session:{$this->provider}:queue:{$category}", $current_user);

                    if (!empty($user_dict["token"]))
                    {
                        OutPut::normal("Selected user: {$current_user}" . PHP_EOL);
                        $selected_session = array(
                            "username"=>$current_user,
                            "password"=>$user_dict["password"],
                            "token"=>$user_dict["token"],
                            "cookies"=>$user_dict["cookies"],
                            "proxy"=>$user_dict["proxy"],
                            "category"=>$user_dict["category"],
                            "ver"=>$user_dict["ver"],
                        );

                        $this->driver->getConnect()->set("session:{$this->provider}:last_call:{$current_user}", strtotime("now"));
                        $this->driver->getConnect()->set("session:{$this->provider}:assigned:{$current_user}", json_encode($task));
                        break;
                    }
                }
            }
            $i++;
        }

        return $selected_session;
    }

    /**
     * Get Active Users by Category
     * @param string $category account category
     * @return mixed|null
     */
    private function getActiveUsersByCategory(string $category='odds')
    {
        $category = (empty($category)) ? 'odds' : $category;

        $cache_user_members = $this->driver->getConnect()->smembers("session:{$this->provider}:users");
        if (empty($cache_user_members))
        {
            OutPut::normal("Undefined users." . PHP_EOL);
            return [];
        }

        $active_users = [];
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
                if ($user_dict["category"] == $category)
                {
                    array_push($active_users, $user);
                }
            }
        }
        return $active_users;
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
            // throw new \Exception('Proxy IP should not be empty');
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
        
        $is_available = false;
        foreach ($proxy_members as $proxy_member) {
            if ($proxy_member != $url)
            { 
                $is_available = true;
                break;
            }
        }
        return $is_available;
    }

    /**
     * Get All User Info
     * @return mixed|null
     */
    public function getAllUserDetails()
    {
        $sessions = [];
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
                if (!empty($user_dict["token"]))
                {
                    $selected_session = array(
                        "username"=>$user,
                        "password"=>$user_dict["password"],
                        "token"=>$user_dict["token"],
                        "cookies"=>$user_dict["cookies"],
                        "proxy"=>$user_dict["proxy"],
                        "category"=>$user_dict["category"],
                        "ver"=>$user_dict["ver"],
                    );
                    array_push($sessions, $selected_session);
                }
            }
        }
        return $sessions;
    }

    /**
     * Remove assigned
     * @return mixed|null
     */
    public function removeAssigned($user)
    {
        return $this->driver->getConnect()->del("session:{$this->provider}:assigned:{$user}");
    }
}