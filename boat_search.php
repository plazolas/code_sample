<?php
/**
 *  Plugin Snippet: Boat Search
 *  Description: Dynamically assembles query depending on user criteria.
 *               Retrieves, Displays and paginates Boat listings.
 *               Procedural Design used for portability among MODX sites
 *             
 * Version: 1.0
 * Author: Oswald Plazola
 * @since 04.15.2016
 *
 * @global type MODX Revolution CMS Configuration Settings object instance.
 * 
 *        
 * */

/**
 * search
 * 
 * Performs dynamic queries, assembles pagination and returns display html according to display templates.
 * 
 * @param  type int $featured ; posible values: [1 | 0] wether to display a list featured of non featured items.
 * @return string  $output; the complete html document for given search result
 * @uses   templates  featuredTPL and searchTPL
 * @global type MODX Revolution CMS Configuration Settings singleton object instance.
 * 
 * */

function search($featured) {
    global $modx;
    $main_table = 'boats';
    $image_table = 'boat_images';
    $secondary_table = 'boat_engines';
    $price_field = 'Price';
    $join_field = 'DocumentID';
    $main_field = 'DocumentID';
    $featuredTPL = 'featuredTPL';
    $searchTPL = 'searchTPL';
    
    $page_post             = filter_input(INPUT_POST, 'lowhigh', FILTER_SANITIZE_SPECIAL_CHARS);
    $submit_search_post    = filter_input(INPUT_POST, 'submit_s', FILTER_SANITIZE_SPECIAL_CHARS);

    if (false !== $page_post && $page_post > 0 && false !== $submit_search_post) {
        $page_number = filter_var($page_post, FILTER_SANITIZE_NUMBER_INT, FILTER_FLAG_STRIP_HIGH);
        if (!is_numeric($page_number)) {
            $page_number = 1; //if there's no page number, set it to 1
            $_REQUEST["page"] = 1;
        }
    } else {
        $page_number = 1; //if there's no page number, set it to 1
        $_REQUEST["page"] = 1;
    }

    $config = $modx->getConfig();
    $mysqli = mysqli_connect('localhost', $config['connections'][0]['username'], $config['connections'][0]['password']);
    // Check connection
    if (mysqli_connect_errno()) {
        echo "Failed to connect to MySQL: " . mysqli_connect_error();
    }
    // Check selected database
    if (!mysqli_select_db($mysqli,$config['dbname'])) {
        die('Could not select database: ' . mysqli_error());
    }

    $select = " SELECT * ";
    $from = "FROM `{$main_table}` ";
    $join = '';
    $where = " WHERE 1 = 1 ";
    $filter = "";
    $order = "";
    
    $ipp = filter_input(INPUT_POST, 'ipp', FILTER_SANITIZE_SPECIAL_CHARS);
    $items_per_page = ($ipp) ? $ipp : 10;    // defaults items per page to 10
    
    $lowhigh_post      = trim(filter_input(INPUT_POST, 'lowhigh', FILTER_SANITIZE_SPECIAL_CHARS));
    $price_post        = trim(filter_input(INPUT_POST, 'price', FILTER_SANITIZE_SPECIAL_CHARS));
    $maxprice_post     = trim(filter_input(INPUT_POST, 'maxprice', FILTER_SANITIZE_SPECIAL_CHARS));
    $category_post     = trim(filter_input(INPUT_POST, 'category', FILTER_SANITIZE_SPECIAL_CHARS));
    $sale_class_post   = trim(filter_input(INPUT_POST, 'sale_class', FILTER_SANITIZE_SPECIAL_CHARS));
    $cabins_post       = trim(filter_input(INPUT_POST, 'cabins', FILTER_SANITIZE_SPECIAL_CHARS));
    $make_post         = trim(filter_input(INPUT_POST, 'make', FILTER_SANITIZE_SPECIAL_CHARS));
    $model_post        = trim(filter_input(INPUT_POST, 'model', FILTER_SANITIZE_SPECIAL_CHARS));
    $year_post         = trim(filter_input(INPUT_POST, 'year', FILTER_SANITIZE_SPECIAL_CHARS));
    $length_post       = trim(filter_input(INPUT_POST, 'length', FILTER_SANITIZE_SPECIAL_CHARS));
    $state_post        = trim(filter_input(INPUT_POST, 'state', FILTER_SANITIZE_SPECIAL_CHARS));
    $country_post      = trim(filter_input(INPUT_POST, 'country', FILTER_SANITIZE_SPECIAL_CHARS));
    $engines_post      = trim(filter_input(INPUT_POST, 'engines', FILTER_SANITIZE_SPECIAL_CHARS));
    $eng_type_post     = trim(filter_input(INPUT_POST, 'engine_type', FILTER_SANITIZE_SPECIAL_CHARS));
    $eng_make_post     = trim(filter_input(INPUT_POST, 'engine_make', FILTER_SANITIZE_SPECIAL_CHARS));
    
    if (false !== $lowhigh_post && $lowhigh_post == "ASC" ) {
        $order_field = " `{$price_field}` ASC"; //price low to high
    } else {
        $order_field = " `{$price_field}` DESC"; //price high to low
    }

    if (false !== $price_post || $price_post > 0 ) {
        $price    = intval($mysqli::real_escape_string($price_post));
        $maxprice = intval($mysqli::real_escape_string($maxprice_post));
        if ($maxprice > 0 && $price > $maxprice) {
            $tmp_price = $price;
            $price = $maxprice;
            $maxprice =  $tmp_price;
        }
        if (false === $price || $price == '') {
            $price = 0;
        }
        if ( false === $maxprice || $maxprice == '') {
            $maxprice = PHP_INT_MAX;
        }
        $filter .= " AND {$price_field} >= " . $price . " AND {$price_field} <= " . $maxprice;
    }

    if (false !== $category_post && strlen($category_post) > 0 && $category_post != "0" ) {
        $category = $mysqli::real_escape_string($category_post);
        $filter .= " AND  BoatCategoryCode = '" . $category . "' ";
    }

    if (false !== $sale_class_post && strlen($sale_class_post) > 0 && $sale_class_post != "0" ) {
        $sale_class = $mysqli::real_escape_string($sale_class_post);
        $filter .= " AND  SaleClassCode = '" . $sale_class . "' ";
    }

    if (false !== $cabins_post && strlen($cabins_post) > 0 && $cabins_post != "0" ) {
        $cabins = intval($mysqli::real_escape_string($cabins_post));
        $filter .= " AND  `SingleBerthsCountNumeric` >= " . $cabins . " ";
    }

    if (false !== $make_post && strlen($make_post) > 0 && $make_post != "0" ) {
        $make_str = $mysqli::real_escape_string($make_post);

        $comma = substr($make_str, -1);
        if ($comma != ",") {
            //User did not use the AJAX make select options
            if (preg_match('/,/', $make_str)) {
                // User separated his own boat makes
                $make_str = substr($make_str, 0, strlen - 1);
                $make_arr = explode(',', $make_str);
            } else {
                //user typed his own boat make ( no comma at end of string)
                $make_arr[] = $make_str;
            }
        } else {
            $make_str = substr($make_str, 0, strlen - 1);
            $make_arr = explode(',', $make_str);
        }
        if (count($make_arr) == 1) {
            $make = array_pop($make_arr);
            $filter .= " AND MakeString LIKE '%" . $make . "%'";
        } else { // (count($make_arr) > 1) 
            $make_count = count($make_arr);
            $filter .= " AND ( ";
            $i = 0;
            foreach ($make_arr as $mymake) {
                $mymake = trim($mymake);
                if ($mymake != '') {
                    $filter .= " MakeString LIKE '%" . $mymake . "%' ";
                    if ($i++ < ($make_count - 1)) {
                        $filter .= " OR ";
                    }
                }
            }
            $filter .= " ) ";
        }
    }

    if (false !== $model_post && strlen($model_post) > 0 && $model_post != "0") {
        $model = $mysqli::real_escape_string($model_post);
        $filter .= " AND  `Model` LIKE  '%" . $model . "%' ";
    }

    if (false !== $year_post && strlen($year_post) > 0 && $year_post != "0") {
        $year = $mysqli::real_escape_string($year_post);
        $filter .= " AND  `ModelYear` LIKE  '%" . $year . "%' ";
    }

    if (false !== $length_post && strlen($length_post) > 0 && $length_post != "0") {
        $length_str = $mysqli::real_escape_string($length_post);

        switch ($length_str) {
            case '< 23':
                $filter .= " AND  CAST(`NominalLength` AS UNSIGNED) <  23 ";
                break;
            case '23-46':
                $filter .= " AND  CAST(`NominalLength` AS UNSIGNED) >= 23 AND  CAST(`NominalLength` AS UNSIGNED) <= 46 ";
                break;
            case '> 46':
                $filter .= " AND  CAST(`NominalLength` AS UNSIGNED) > 46 ";
                break;
        }
    }

    if (false !== $state_post && strlen($state_post) > 0 && $state_post != "0" && $country_post == 'US') {
        $state = $mysqli::real_escape_string($state_post);
        $filter .= " AND  `BoatLoc_BoatStateCode` LIKE  '%" . $state . "%' ";
    }

    if (false !== $country_post && strlen($country_post) > 0 && $country_post != "0") {
        $country = $mysqli::real_escape_string($country_post);
        $filter .= " AND  `BoatLoc_BoatCountryID` LIKE  '%" . $country . "%' ";
    }

    if (false !== $engines_post && strlen($engines_post) > 0 && $engines_post != "0") {
        $engines = $mysqli::real_escape_string($engines_post);
        if ($engines == '4') {
            $filter .= " AND  `NumberOfEngines` >= 4 ";
        } else {
            $filter .= " AND  `NumberOfEngines` = " . $engines . " ";
        }
    }

    if (false !== $eng_type_post && strlen($eng_type_post) > 0 && $eng_type_post != "0") {
        $eng_type = $mysqli::real_escape_string($eng_type_post);
        $select = " SELECT DISTINCT a.* ";
        $from = " FROM {$main_table} a ";
        $join = " INNER JOIN `{$secondary_table}` b ON a.{$join_field} = b.{$join_field} ";
        $filter .= " AND  b.Type LIKE '%" . $eng_type . "%' ";
    }

    if (false !== $eng_make_post && strlen($eng_make_post) > 0 && $eng_make_post != "0") {
        $eng_make = $mysqli::real_escape_string($eng_type_post);
        $select = " SELECT DISTINCT a.* ";
        $from = " FROM {$main_table} a ";
        $join = " INNER JOIN `{$secondary_table}` b ON a.{$join_field} = b.{$join_field} ";
        $filter .= " AND  b.Make LIKE '%" . $eng_make . "%' ";
    }

    $page_position = (($page_number - 1) * $items_per_page);

    $order = " ORDER BY " . $order_field;
    $limit = " LIMIT $page_position, $items_per_page";

    $query_str = $select . $from . $join . $where . $filter . $order . $limit;
    $query_num_rows_str = $select . $from . $join . $where . $filter;
    $query = mysqli_query($mysqli, $query_str);
    $query_num_rows = mysqli_query($mysqli, $query_num_rows_str);

    $total = $total_rows = mysqli_num_rows($mysqli, $query_num_rows);

    // Uncomment below line to display actual query when in DEBUG
    //$output = $query_str . " | low-high:" . $_REQUEST['lowhigh'] . '|num_rows:' . $total_rows . '|page=' . $_REQUEST['page'];

    $count = 1;
    $cursor = 1;
    $page = 0;
    $active = 1;

    $total_pages = ceil($total / $items_per_page); //break records into pages

    if ($total_rows == 0) {
        $output.="<h2 style='text-align: center'>There are NO LISTINGS for given criteria</h2>";
    }

    $display_count = 0;
    $listing_count = 0;
    while ($listing = mysqli_fetch_array($mysqli, $query)) {
        foreach ($listing as $key => $value) {
            $modx->setPlaceholder($key, $value);
        }
        $modx->setPlaceholder('price', number_format($listing[$price_field], 0, ".", ","));

        $image_sql = "SELECT * FROM {$image_table} WHERE `{$main_field}` = " . $listing[$main_field] . " LIMIT 1";
        $photo_query = mysqli_query($mysqli,$image_sql);
        $photo = mysqli_fetch_array($mysqli,$photo_query);

        if (isset($photo['Uri'])) {
            $modx->setPlaceholder('image', $photo['Uri']);
            $modx->setPlaceholder('alt', $photo['Caption']);
            if ($count == 1) {
                $active = (!$page) ? "active" : "";
                $page++;
                $output.='<div class="item ' . $active . '"><div class="row">';
            }
            if ($featured == 1) {
                $output .= $modx->getChunk($featuredTPL);
            } else {
                $output .= $modx->getChunk($searchTPL);
            }
            $display_count++;
            if ($cursor == $total || $count == 2) {
                $count = 0;
                $output.="</div></div>";
            }
        }
        $count++;
        $cursor++;
        $listing_count++;
    }

    $page_url = "search.php";
    if ($total_rows > $items_per_page) {
        $pagination = paginate($items_per_page, $page_number, $total_rows, $total_pages, $page_url);
    }

    if (isset($featured) && $featured == '1') {
        $featuredNav = "";
        for ($i = 0; $i < $page; $i++) {
            $class = (!$i) ? 'class="active"' : '';
            $featuredNav .='<li data-target="#Carousel" data-slide-to="' . $i . '" ' . $class . '></li>';
        }
        $modx->setPlaceholder('featuredNav', $featuredNav);
    }

    if ($page_position == 0) {
        $count1 = 1;
        $count2 = $listing_count;
    } else {
        $count1 = $page_position;
        $count2 = $count1 + $listing_count;
    }
    $modx->setPlaceholder('search_results', $output);
    $modx->setPlaceholder('pagination', $pagination);
    $modx->setPlaceholder('total_items', $total_rows);
    $modx->setPlaceholder('count_one', $count1);
    $modx->setPlaceholder('count_two', $count2);
    
    mysqli_close($mysqli);

    return $output;
}

/**
 * paginate
 * 
 * Assembles pagination links for current page search
 * 
 * @param type int $items_per_page.
 * @param type int $current_page.
 * @param type int $total_records.
 * @param type int $total_pages.
 * @param type int $page_url.
 * @return string  $pagination; Assembled pagination links
 * */
function paginate($items_per_page, $current_page, $total_records, $total_pages, $page_url) {
    $pagination = '';

    foreach ($_REQUEST as $key => $value) {
        if ($key == 'id' || $key == 'PHPSESSID' || $key == 'page' || $key == 'submit-search' || $key == 'q' || preg_match('/__utm/', $key) || preg_match('/_ga/', $key)) {
            continue;
        }
        $request_url .= '&' . $key . '=' . urlencode($value);
    }

    if ($total_pages > 0 && $total_pages != 1 && $current_page <= $total_pages) { //verify total pages and current page number
        $pagination .= '<ul class="pagination">';
        $right_links = $current_page + 3;
        $previous = $current_page - 3; //previous link 
        $first_link = true; //boolean var to decide our first link

        if ($current_page > 1) {
            $previous_link = ($previous == 0) ? 1 : $previous;
            $pagination .= '<li class="first"><a href="' . $page_url . '&page=1' . $request_url . '" title="First">&laquo;</a></li>'; //first link
            $pagination .= '<li><a href="' . $page_url . '&page=' . $previous_link . $request_url . '" title="Previous">&lt;</a></li>'; //previous link
            for ($i = ($current_page - 2); $i < $current_page; $i++) { //Create left-hand side links
                if ($i > 0) {
                    $pagination .= '<li><a href="' . $page_url . '&page=' . $i . $request_url . '">' . $i . '</a></li>';
                }
            }
            $first_link = false; //set first link to false
        }

        if ($first_link) { //if current active page is first link
            $pagination .= '<li class="first active"><span>' . $current_page . '</span></li>';
            $count1 = 1;
            $count2 = $items_per_page;
        } elseif ($current_page == $total_pages) { //if it's the last active link
            $pagination .= '<li class="last active"><span>' . $current_page . '</span></li>';
            $count1 = 1;
            $count2 = $total_pages;
        } else { //regular current link
            $pagination .= '<li class="active"><span>' . $current_page . '</span></li>';
        }

        for ($i = $current_page + 1; $i < $right_links; $i++) { //create right-hand side links
            if ($i <= $total_pages) {
                $pagination .= '<li><a href="' . $page_url . $request_url . '&page=' . $i . '">' . $i . '</a></li>';
            }
        }
        if ($current_page < $total_pages) {
            $next_link = ($i > $total_pages) ? $total_pages : $i;
            $pagination .= '<li><a href="' . $page_url . '&page=' . $next_link . $request_url . '" >&gt;</a></li>'; //next link
            $pagination .= '<li class="last"><a href="' . $page_url . '&page=' . $total_pages . $request_url . '" title="Last">&raquo;</a></li>'; //last link
        }
        $pagination .= '</ul>';
    }
    return $pagination;
}
