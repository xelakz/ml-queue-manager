<?php
namespace MultilineQM\Scraper;

interface RequestInterface {

    /**
     * Get / extract content
     * @param string $query query url
     * @return mixed
     */
    public function get($query);

	/**
     * Close client
     */
    public function close();

}
?>
