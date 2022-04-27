# Doogle
Doogle is a web crawler and search engine which can crawl and index images and websites, and then using keywords be searched later. 

Written primarily in OOP style PHP with the intent of better understanding OOP and how web crawlers work.

<p align="center">
  <img width="527" alt="DoogleHomepage-Preview" src="https://user-images.githubusercontent.com/10171446/165316199-b0fe279c-cb11-4a36-84b8-53a514ac488a.png">
</p>

# Features

- Search sites
   *    Displays title, URL and description
- Search images
    *   Hover over images to preview description (alt tag)
    *   Masonary layout for searched images
    *   Image preview using Fancybox
    *   Image search page responds dynamically
- Organises search results by clicks/visits
- Filters broken image results
- Shows 'results found' for search term
- Pagination system at the bottom of the search page
- Clean homepage

# Table of Contents 

- [Setup and Usage](#setup-and-usage)
  - [Server Setup](#server-setup)
  - [Connecting PHP to MySQL Server](#connecting-php-to-mysql-server)
  - [Crawling Websites to Populate Images and Sites tables](#crawling-websites-to-populate-images-and-sites-tables)
- [Programming Logic](#programming-logic)
  - [Pagination](#pagination)
  - [Image Search](#image-search)
  - [Site Search - Trimming Results](#site-search---trimming-results)
  - [Telemetry](#telemetry)
  - [User-Agent](#user-agent)
- [Preview Images](#preview-images)
  - [Doogle Homepage](#doogle-homepage)
  - [Doogle Search - Sites](#doogle-search---sites)
  - [Doogle Search - Images](#doogle-search---images)
  - [Pagination System](#pagination-system)
  - [doogleBot Crawl Form](#dooglebot-crawl-form)
- [Preview Video](#preview-video)

# Setup and Usage

## Server Setup

Please refer to [XAMPP](https://www.apachefriends.org/index.html) for the web server, PHP server and MySQL server configuration.
XAMPP is the simplest method as several servers are required to use Doogle.

[MySQL Setup on XAMPP](https://www.rose-hulman.edu/class/se/csse290-WebProgramming/201520/SupportCode/SQL-setup.html) will use PHPMyAdmin as a GUI method of setting up the database.

Once logged into the database via PHPMyAdmin under the **PHPMyAdmin > SQL** tab, the content of 'doogle-tables-no-data.sql' can be pasted into the field

<img width="960" alt="Image1-PHPMyAdmin" src="https://user-images.githubusercontent.com/10171446/165310962-7ec771d2-50a0-4117-87f8-60373f694e55.png">


## Connecting PHP to MySQL Server

In the file config.php the following must be entered correctly for your database configuration:

    $dbname = "doogle";
    $dbhost = "127.0.0.1";
    $dbuser = "root";
    $dbpass = "";

In the file 'doogle-tables-no-data.sql' the database will be created as 'doogle', but the remaining parameters must still be filled.

## Crawling Websites to Populate Images and Sites tables

### Form-based crawl

In your browser go to where the file is hosted http://127.0.0.1/crawl-formSubmit.php

Paste the URL into the input field and press the Crawl button.

### Manual crawl

At the bottom of crawl-manual.php the variable $startUrl is where to paste the URL of the website to be crawled:

    $startUrl = "https://thehackernews.com/";
  
Then in your browser go to where the file is hosted http://127.0.0.1/crawl-manual.php

### Explination

The crawling process will take some time, it will completely depend on the size of the website being crawled. 
The page will continue to load (without output) until the crawl.php script finishes.

Check the tables 'images' and 'sites' in the database to ensure they are being populated.

<img width="960" alt="Image2-PHPMyAdmin" src="https://user-images.githubusercontent.com/10171446/165312292-c2830b80-365d-4a39-b176-8226bd0d7f65.png">


Once the tables are populated visit the Doogle homepage and search!
See preview images.

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
    
    
## Image Search

### Image Captions

To make image searches more informative, the 'alt' tag is part of the search term. As shown in ./classes/ImageResultsProvider.php line 34

<img width="419" alt="ImageResultsProvider-query" src="https://user-images.githubusercontent.com/10171446/165472615-fd149596-3a39-4e48-8308-bd4f1ed16968.png">


### Loading Images with JavaScript
In the 'images' table there is a row 'broken' which tracks images which return an error.

Because images are already loaded with a pure server-side solution, AJAX must be leveraged, loading images dynamically. Which is shown in ./assets/js/script.js


<img width="319" alt="script js-loadImage-broken" src="https://user-images.githubusercontent.com/10171446/165471191-6119b5cf-dc77-49a4-b84d-12276232813a.png">




### Masonry
Image searches are using [Masonry - Cascading grid layout library](https://masonry.desandro.com/).

Masonry allows images a grid layout which is responsive due to jQuery.
The image below shows an example layout:

<img width="428" alt="Masonry-item-layout" src="https://user-images.githubusercontent.com/10171446/165469864-97c2bec4-2af7-4987-917f-02885d407ba9.png">



## Site Search - Trimming Results

As shown in the preview images, Doogle when performing a site search will return (title, URL and description) for each result.

However, to make some results easier to read, a trimming process is performed. Inside ./classes/SiteResultsProvider.php the function trimField() is called:

<img width="380" alt="SiteResultsProvider-trim1" src="https://user-images.githubusercontent.com/10171446/165468731-9176be82-c3ed-4bf4-bcbb-bf5dd838398b.png">

<img width="374" alt="SiteResultsProvider-trim2" src="https://user-images.githubusercontent.com/10171446/165468845-5e382320-71ce-4b6a-988b-8d4ddf3f341a.png">

Title's are trimmed at 55 characters and description's are trimmed at 230 characters.


## Telemetry

Both the 'images' and 'sites' tables in the database have a row containing 'clicks' for each column.

The 'clicks' field is increased each time a site is visited or image is previewed.

When performing a search, results returned are organised in decending order of clicks.
This behaviour is shown by the $query inside ./classes/SiteResultsProvider.php function getResultsHtml(). See line 43.

<img width="443" alt="SiteResultsProvider-getResultsHtml" src="https://user-images.githubusercontent.com/10171446/165467418-37de4f8c-1901-4911-a7c9-33b42806f0bb.png">


## User-Agent

Inside ./classes/DomDocumentParser.php the user-agent data used during crawling is located.
As indicated on line 9:

<img width="481" alt="DomDocumentParser-bot" src="https://user-images.githubusercontent.com/10171446/165465964-2bba0582-2846-44f1-abd1-b51ac316b186.png">


# Preview Images
## Doogle Homepage

<img width="701" alt="Image3-DoogleHomepage-Edge" src="https://user-images.githubusercontent.com/10171446/165313393-fcfdb9fc-1b19-4c8f-ac08-b96ff393ab63.png">

## Doogle Search - Sites

<img width="701" alt="Image4-DoogleSearch-PoC" src="https://user-images.githubusercontent.com/10171446/165313470-02c30d0a-e7e6-4fcf-8c09-6be9e633fc0f.png">

## Doogle Search - Images

<img width="882" alt="Image5-DoogleSearch-PoC-images" src="https://user-images.githubusercontent.com/10171446/165313548-686a79e3-5b1d-4e9e-a3d7-ab7775a9b171.png">

### Image Preview

Image preview is done using Fancybox.

The title, image URL and site URL are available on the bottom left corner.

<img width="883" alt="Image9-DoogleSearch-imagePreview" src="https://user-images.githubusercontent.com/10171446/165315386-8bc4a25e-0a9f-4622-82b8-d733bc343a3b.png">



## Pagination System

Naturally certain search terms may return many results like 'bbc'.

To which Doogle only displays **20 sites** per page.
At the bottom of the page, we can view the next 10 pages.

### Results Shown

<img width="883" alt="Image6-DoogleSearch-pagination-ResultsShown" src="https://user-images.githubusercontent.com/10171446/165314211-5daf2903-5ecc-44ad-942a-2270a361dec5.png">

### Bottom of Page

<img width="883" alt="Image7-DoogleSearch-pagination-Bottom" src="https://user-images.githubusercontent.com/10171446/165314516-d00bf38a-6fef-467c-9182-88d0d6ce07d2.png">

### Bottom of Page 13

<img width="883" alt="Image8-DoogleSearch-pagination-scrollingThrough" src="https://user-images.githubusercontent.com/10171446/165314716-08834b0c-4ba0-4e90-b466-58a57e91bf69.png">

## doogleBot Crawl Form

An HTML form to submit a URL for crawling

<img width="581" alt="Image10-doogleBot-Crawler-formpng" src="https://user-images.githubusercontent.com/10171446/165463270-d36f7b78-379c-46da-b859-f5dde9304668.png">
