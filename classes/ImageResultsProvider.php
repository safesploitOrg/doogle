<?php
require_once __DIR__ . '/../vendor/autoload.php';

class ImageResultsProvider
{
	private $imageSearchService;

	public function __construct($con, $paginator = null, $images = null, $imageSearchService = null)
	{
		$paginator = $paginator ?: new \Doogle\Search\Paginator();
		$images = $images ?: new \Doogle\Repository\ImageRepository($con);
		$this->imageSearchService = $imageSearchService ?: new \Doogle\Search\ImageSearchService($images, $paginator);
	}

	public function getNumResults($term)
	{
		return $this->imageSearchService->count((string) $term);
	}

	public function getResultsHtml($page, $pageSize, $term)
	{
		$searchPage = $this->imageSearchService->search((string) $term, (int) $page, (int) $pageSize);

		$resultsHtml = "<div class='imageResults'>";

		$count = 0;
		foreach($searchPage->results as $result)
		{
			$count++;
			$imageUrl = $result->imageUrl;
			$siteUrl = $result->siteUrl;
			$displayText = $result->displayText();

			$resultsHtml .= "<div class='gridItem image$count'>
								<a href='$imageUrl' data-fancybox data-caption='$displayText'
									data-siteurl='$siteUrl'>
									
									<script>
									$(document).ready(function() {
										loadImage(\"$imageUrl\", \"image$count\");
									});
									</script>

									<span class='details'>$displayText</span>
								</a>

							</div>";
		}

		$resultsHtml .= "</div>";

		return $resultsHtml;
	}
}
?>
