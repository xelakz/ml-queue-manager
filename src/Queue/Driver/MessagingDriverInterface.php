<?php
namespace MultilineQM\Queue\Driver;


/**
 * Interface MessagingDriverInterface
 */
interface MessagingDriverInterface
{
    /**
     * Get connection
     */
    public function getConnect();

    /**
     * Close the current connection instance
     * @return mixed|void
     */
    public function close();
}