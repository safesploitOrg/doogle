<?php
require_once __DIR__ . '/../vendor/autoload.php';

class SiteResultsProvider
{
	private $fieldFormatter;
	private $searchService;

	public function __construct($con, $paginator = null, $fieldFormatter = null, $sites = null, $searchService = null)
	{
		$paginator = $paginator ?: new \Doogle\Search\Paginator();
		$this->fieldFormatter = $fieldFormatter ?: new \Doogle\Search\FieldFormatter();
		$sites = $sites ?: new \Doogle\Repository\SiteRepository($con);
		$this->searchService = $searchService ?: new \Doogle\Search\SearchService($sites, $paginator);
	}

	public function getNumResults($term)
	{
		return $this->searchService->count((string) $term);
	}

	public function getResultsHtml($page, $pageSize, $term)
	{
		$searchPage = $this->searchService->search((string) $term, (int) $page, (int) $pageSize);

		$resultsHtml = "<div class='siteResults'>";

		foreach($searchPage->results as $result)
		{
			$id = $result->id;
			$url = $result->url;
			$title = $result->title;
			$description = $result->description;

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
