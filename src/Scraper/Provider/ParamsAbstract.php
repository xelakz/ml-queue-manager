<?php
namespace MultilineQM\Scraper\Provider;

abstract class ParamsAbstract implements ParamsInterface
{
    abstract public function getRequestParams();
    abstract public function getBaseParams();
}