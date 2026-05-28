<?php
require_once __DIR__ . '/../vendor/autoload.php';

class SiteResultsProvider
{
	private $fieldFormatter;
	private $paginator;
	private $sites;

	public function __construct($con, $paginator = null, $fieldFormatter = null, $sites = null)
	{
		$this->paginator = $paginator ?: new \Doogle\Search\Paginator();
		$this->fieldFormatter = $fieldFormatter ?: new \Doogle\Search\FieldFormatter();
		$this->sites = $sites ?: new \Doogle\Repository\SiteRepository($con);
	}

	public function getNumResults($term)
	{
		return $this->sites->countBySearchTerm((string) $term);
	}

	public function getResultsHtml($page, $pageSize, $term) 
	{
		/*
			Pagination system logic ($fromLimit)
			page1: (1 - 1) * 20 = 0
			page2: (2 - 1) * 20 = 20
			page3: (3 - 1) * 20 = 40
			...
		*/
		$fromLimit = $this->paginator->offset((int) $page, (int) $pageSize);

		$resultsHtml = "<div class='siteResults'>";

		foreach($this->sites->search((string) $term, $fromLimit, (int) $pageSize) as $row)
		{
			$id = $row["id"];
			$url = $row["url"];
			$title = $row["title"];
			$description = $row["description"];

			$title = $this->trimField($title, 55);
			$description = $this->trimField($description, 230);
			
			$resultsHtml .= "<div class='resultContainer'>
								<h3 class='title'>
									<a class='result' href='$url' data-linkId='$id'>
										$title
									</a>
								</h3>
								<span class='url'>$url</span>
								<span class='description'>$description</span>
							</div>";
		}

		$resultsHtml .= "</div>";

		return $resultsHtml;
	}

	private function trimField($string, $characterLimit)
	{
		return $this->fieldFormatter->trim((string) $string, (int) $characterLimit);
	}
}
?>
