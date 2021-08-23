<?php

namespace MultilineQM\Client;

interface ClientInterface
{

    /**
     * Return the connection status of the Client
     * @return mixed
     */
    public function isConnected(): bool;

    /**
     * Send a message
     * @param string $type message type
     * @param null $data message data
     * @param string $msg message description information
     * @return mixed
     */
    public function send(string $type, $data = null, string $msg ='');

    /**
     * Receive messages
     * @param float $timeout timeout time (seconds)
     * @return mixed
     */
    public function recv(float $timeout = -1);

    /**
     * Receive and execute messages
     * @param float $timeout Timeout time for receiving messages (seconds)
     * @return mixed
     */
    public function recvAndExec(float $timeout = -1);

    /**
     * Close connection
     * @return mixed
     */
    public function close(): bool;


    /**
     * The peek method is only used to peek at the data in the kernel socket buffer area without offset. After using peek, you can still read this part of the data by calling recv
     * The peek method is non-blocking, it will return immediately. When there is data in the socket buffer, the data content will be returned. Return false when the buffer area is empty, and set $client->errCode
     * The connection has been closed peek will return an empty string
     * @param int $length
     * @return mixed
     */
    public function peek(int $length = 65535);

    /**
     * Export the current client socket object
     * @return mixed
     */
    public function exportSocket();

    /**
     * Set the current client protocol (to prevent sticky packets)
     * @return bool
     */
    public function setProtocol(): bool;


}