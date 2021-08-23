<?php
namespace MultilineQM\Scraper;

interface ProviderServiceInterface {


	/**
     * Process
     * @param array $message payload message
     */
    public function process($message);

}
?>
