# Doogle
Doogle is a web crawler and search engine which can crawl and index images and websites, and then using keywords be searched later. 

Written primarily in OOP style PHP with the intent of better understanding OOP and how web crawlers work.
# Features

- Search sites
   *    Displays title, URL and description
- Search images
    *   Hover over images to preview description (alt tag)
    *   Masonary layout for searched images
    *   Image search page responds dynamically
- Organises search results by clicks/visits
- Filters broken image results
- Shows 'results found' for search term
- Pagination system at the bottom of the search page
- Clean homepage

# Table of Contents 



# Programming Logic

## Pagination

### Logic of pagination system
Inside search.php, pagination is implemented  

<img width="261" alt="image demonstrating pagnigation" src="https://user-images.githubusercontent.com/10171446/165146284-cf5362c0-bfe1-4489-b68e-5f7363d243dd.png">

In the example above, currentPage=11. 
The number of pages to show is always 10.

### Handling an edge case

An edge case can occur when no more pages are available.

So, for 331 results, **17 pages** will be available. However, without an edge case scenario consider, the UI for the pagination system will allow scrolling through pages which don't exist; which would return an empty result.

To handle an edge case the following logic is implemented in the while-loop:

    if($currentPage + $pagesLeft > $numPages + 1)
        $currentPage = $numPages + 1 - $pagesLeft;

    while($pagesLeft != 0 && $currentPage <= $numPages) 
    { ... }
    
    
# Preview Images

Coming soon

# Preview Video

