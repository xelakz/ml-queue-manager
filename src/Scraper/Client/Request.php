<?php
declare(strict_types=1);
namespace MultilineQM\Scraper\Client;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

use MultilineQM\Scraper\RequestInterface;
use MultilineQM\OutPut\OutPut;

/**
 * Download api/site content
 * Class Request
 * @package MultilineQM\Socket
 */
class Request implements RequestInterface
{
    private $client;
    private $params;

    public function __construct(Client $client, $params)
    {
        $this->client = $client;
        $this->params = $params;
    }

    public function get($query)
    {
        return $this->method("GET", $query);
    }

    public function post($query, $body)
    {
        return $this->method("POST", $query, $body);
    }

    public function close() : void
    {
        $this->client->close();
    }

    private function method($method, $query, $body=null)
    {
        if (($method == "POST") && (!empty($body)))
        {
            $this->params["body"] = $body;
        }

        try {
            return $this->client->request($method, $query, $this->params);
        } catch (RequestException $e) {
            if ($e->hasResponse()){
                return $e->getResponse();
            }
        } catch (\Exception $e) {
            OutPut::normal("Error: " . $e->getFile() . ":" . $e->getLine() . ":" . $e->getMessage() . PHP_EOL);
        }
        return null;
    }
}
?>
