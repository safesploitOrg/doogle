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

- [Doogle](#doogle)
- [Features](#features)
- [Setup and Usage](#setup-and-usage)
  - [Server Setup](#server-setup)
  - [Connecting PHP to MySQL Server](#connecting-php-to-mysql-server)
  - [Crawling Websites to Populate Images and Sites tables](#crawling-websites-to-populate-images-and-sites-tables)
- [Programming Logic](#programming-logic)
  - [Pagination](#pagination)
- [Preview Images](#preview-images)
  - [Doogle Homepage](#doogle-homepage)
  - [Doogle Search - Sites](#doogle-search---sites)
  - [Doogle Search - Images](#doogle-search---images)
  - [Pagination System](#pagination-system)
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

At the bottom of crawl.php the variable $startUrl is where to paste the URL of the website to be crawled:

    $startUrl = "https://thehackernews.com/";
  
Then in your browser go to where the file is hosted http://127.0.0.1/crawl.php

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

# Preview Video

