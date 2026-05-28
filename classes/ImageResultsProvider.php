<?php
require_once __DIR__ . '/../vendor/autoload.php';

class ImageResultsProvider
{
	private $images;
	private $paginator;

	public function __construct($con, $paginator = null, $images = null)
	{
		$this->paginator = $paginator ?: new \Doogle\Search\Paginator();
		$this->images = $images ?: new \Doogle\Repository\ImageRepository($con);
	}

	public function getNumResults($term)
	{
		return $this->images->countBySearchTerm((string) $term);
	}

	public function getResultsHtml($page, $pageSize, $term) 
	{
		$fromLimit = $this->paginator->offset((int) $page, (int) $pageSize);

		$resultsHtml = "<div class='imageResults'>";

		$count = 0;
		foreach($this->images->search((string) $term, $fromLimit, (int) $pageSize) as $row)
		{
			$count++;
			$id = $row["id"];
			$imageUrl = $row["imageUrl"];
			$siteUrl = $row["siteUrl"];
			$title = $row["title"];
			$alt = $row["alt"];

			if($title)
				$displayText = $title;
			else if($alt)
				$displayText = $alt;
			else
				$displayText = $imageUrl;
			
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
