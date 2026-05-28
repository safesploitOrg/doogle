<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/DomDocumentParser.php';

class Crawler
{
	private $con;
	private \Doogle\Security\CrawlerSecurityPolicy $securityPolicy;
	private \Doogle\Crawl\UrlValidator $urlValidator;

	/** @var array<string, true> */
	private array $alreadyCrawled = [];

	/** @var array<string, true> */
	private array $alreadyParsed = [];

	/** @var list<string> */
	private array $alreadyFoundImages = [];

	private int $pagesCrawled = 0;

	public function __construct(
		$con,
		?\Doogle\Security\CrawlerSecurityPolicy $securityPolicy = null,
		?\Doogle\Crawl\UrlValidator $urlValidator = null,
	)
	{
		$this->con = $con;
		$this->securityPolicy = $securityPolicy ?? \Doogle\Security\CrawlerSecurityPolicy::fromEnvironment();
		$this->urlValidator = $urlValidator ?? new \Doogle\Crawl\UrlValidator($this->securityPolicy);
	}

	function linkExists($url)
	{
		$query = $this->con->prepare("SELECT * FROM sites WHERE url = :url");

		$query->bindValue(":url", $url);
		$query->execute();

		return $query->rowCount() != 0;
	}

	function imageExists($src)
	{
		$query = $this->con->prepare("SELECT * FROM images WHERE imageUrl = :src");

		$query->bindValue(":src", $src);
		$query->execute();

		return $query->rowCount() != 0;
	}

	function insertLink($url, $title, $description, $keywords)
	{
		$query = $this->con->prepare("INSERT INTO sites(url, title, description, keywords)
								VALUES(:url, :title, :description, :keywords)");

		$query->bindValue(":url", $url);
		$query->bindValue(":title", $title);
		$query->bindValue(":description", $description);
		$query->bindValue(":keywords", $keywords);

		return $query->execute();
	}

	function insertImage($url, $src, $alt, $title)
	{
		$query = $this->con->prepare("INSERT INTO images(siteUrl, imageUrl, alt, title)
								VALUES(:siteUrl, :imageUrl, :alt, :title)");

		$query->bindValue(":siteUrl", $url);
		$query->bindValue(":imageUrl", $src);
		$query->bindValue(":alt", $alt);
		$query->bindValue(":title", $title);

		return $query->execute();
	}

	/* Converts relative link to absolute link */
	function createLink($src, $url)
	{
		return (new \Doogle\Crawl\UrlNormalizer())->normalize((string) $src, (string) $url);
	}

	function getDetails($url, int $depth = 0)
	{
		$url = (string) $url;
		$reason = $this->urlValidator->rejectionReason($url, $depth);

		if ($reason !== null) {
			echo "SKIPPED: $url ($reason)<br>";
			return false;
		}

		if (!$this->securityPolicy->allowsMorePages($this->pagesCrawled)) {
			echo "SKIPPED: maximum pages per job reached<br>";
			return false;
		}

		$this->pagesCrawled++;
		$parser = new DomDocumentParser($url, $this->securityPolicy);

		$titleArray = $parser->getTitleTags();

		if(sizeof($titleArray) == 0 || $titleArray->item(0) == NULL)
			return false;

		//Replace linebreak
		$title = $titleArray->item(0)->nodeValue;
		$title = str_replace("\n", "", $title);

		//Return if no <title>
		if($title == "")
			return false;

		$description = "";
		$keywords = "";

		$metasArray = $parser->getMetatags();

		foreach($metasArray as $meta)
		{
			if($meta->getAttribute("name") == "description")
				$description = $meta->getAttribute("content");

			if($meta->getAttribute("name") == "keywords")
				$keywords = $meta->getAttribute("content");
		}

		$description = str_replace("\n", "", $description);
		$keywords = str_replace("\n", "", $keywords);

		if($this->linkExists($url))
			echo "$url already exists<br>";
		else if($this->insertLink($url, $title, $description, $keywords))
			echo "SUCCESS: $url<br>";
		else
			echo "ERROR: Failed to insert $url<br>";

		$src = "";
		$alt = "";
		$imageTitle = "";
		$imageArray = $parser->getImages();
		foreach($imageArray as $image)
		{
			$src = $image->getAttribute("src");
			$alt = $image->getAttribute("alt");
			$imageTitle = $image->getAttribute("title");

			if(!$imageTitle && !$alt)
				continue;

			$src = $this->createLink($src, $url);
			$imageReason = $this->urlValidator->rejectionReason($src, $depth);

			if ($imageReason !== null) {
				echo "SKIPPED: $src ($imageReason)<br>";
				continue;
			}

			if(!in_array($src, $this->alreadyFoundImages, true))
			{
				$this->alreadyFoundImages[] = $src;

				if($this->imageExists($src))
					echo "$src already exists<br>";
				else if($this->insertImage($url, $src, $alt, $imageTitle))
					echo "SUCCESS: $src<br>";
				else
					echo "ERROR: Failed to insert $src<br>";
			}

		}

		echo "<b>URL:</b> $url, <b>Title:</b> $title, <b>Description:</b> $description, <b>keywords:</b> $keywords<br>"; //DEBUGGING sites

		if ($src !== "") {
			echo "<b>src:</b> <a href=$src>$src</a>, <b>alt:</b> $alt, <b>title:</b> $imageTitle, <b>url:</b> $url<br>"; //DEBUGGING images
		}

		return true;
	}

	function followLinks($url)
	{
		$queue = [[(string) $url, 0]];

		while($queue !== [])
		{
			if (!$this->securityPolicy->allowsMorePages($this->pagesCrawled)) {
				echo "SKIPPED: maximum pages per job reached<br>";
				break;
			}

			$current = array_shift($queue);
			$currentUrl = (string) $current[0];
			$depth = (int) $current[1];

			if (isset($this->alreadyParsed[$currentUrl])) {
				continue;
			}

			$this->alreadyParsed[$currentUrl] = true;
			$reason = $this->urlValidator->rejectionReason($currentUrl, $depth);

			if ($reason !== null) {
				echo "SKIPPED: $currentUrl ($reason)<br>";
				continue;
			}

			$parser = new DomDocumentParser($currentUrl, $this->securityPolicy);
			$linkList = $parser->getLinks();

			foreach($linkList as $link)
			{
				$href = $link->getAttribute("href");

				// Filter hrefs
				if($this->shouldSkipHref($href))
					continue;

				$href = $this->createLink($href, $currentUrl);
				$nextDepth = $depth + 1;
				$reason = $this->urlValidator->rejectionReason($href, $nextDepth);

				if ($reason !== null) {
					echo "SKIPPED: $href ($reason)<br>";
					continue;
				}

				if(!isset($this->alreadyCrawled[$href]))
				{
					if (!$this->securityPolicy->allowsMorePages($this->pagesCrawled)) {
						echo "SKIPPED: maximum pages per job reached<br>";
						break;
					}

					$this->alreadyCrawled[$href] = true;
					$this->getDetails($href, $nextDepth);

					if($nextDepth < $this->securityPolicy->maxDepth() && !isset($this->alreadyParsed[$href]))
						$queue[] = [$href, $nextDepth];
				}

				echo ($href . "<br>"); //DEBUGGING
			}
		}
	}

	private function shouldSkipHref(string $href): bool
	{
		$href = trim($href);

		return $href === ''
			|| strpos($href, "#") !== false
			|| stripos($href, "javascript:") === 0;
	}
}
?>
