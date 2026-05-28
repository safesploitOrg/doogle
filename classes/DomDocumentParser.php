<?php

use Doogle\Security\CrawlerSecurityPolicy;

class DomDocumentParser
{
	private $doc;

	public function __construct($url, ?CrawlerSecurityPolicy $securityPolicy = null)
	{
		$securityPolicy ??= CrawlerSecurityPolicy::fromEnvironment();
		$html = '<?xml encoding="UTF-8">';

		$options = array(
			'http'=>array(
				'method'=>"GET",
				'header'=>"User-Agent: " . $securityPolicy->userAgent() . "\r\n",
				'timeout'=>$securityPolicy->timeoutSeconds(),
				'ignore_errors'=>true,
				'follow_location'=>0,
				'max_redirects'=>0,
			)
			);
		$context = stream_context_create($options);
		$getConstants = @file_get_contents(
			$url,
			false,
			$context,
			0,
			$securityPolicy->maxResponseBytes()
		);

		if ($getConstants === false) {
			$getConstants = '';
		}

		$this->doc = new DomDocument('1.0', 'utf-8');
		@$this->doc->loadHTML($html . $getConstants);
		//@ Error supression is unnecessary, PHP>7.0 supports HTML5
	}

	public function getlinks() 
	{
		return $this->doc->getElementsByTagName("a");
	}

	public function getTitleTags() 
	{
		return $this->doc->getElementsByTagName("title");
	}

	public function getMetaTags() 
	{
		return $this->doc->getElementsByTagName("meta");
	}

	public function getImages() 
	{
		return $this->doc->getElementsByTagName("img");
	}

}
?>
