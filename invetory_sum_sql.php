<?php
/**
 * Name: Inventory.php
 * Description: Sample code to ilustrate dynamic Oracle SQL statement
 * 
 * Author: Oswald Plazola
 * @since 05.12.2007
 * 
 * This is old PHP4 code
 *
 * */

$vmodule = 'INVENTORY';
$lname = $login;
$lpass = lockpass($password);   //locked password
$module = getUserModule($login, $password, "INVENTORY");
$company = $accessarr[0];   //warehouse id
$accesslevel = $accessarr[1];   //the current users access level
$jobtype = $accessarr[2];   //job types current user is allowed to see
if ($func == "login") {
    $func = "getinventory";
    $warehouse = "all";
}
$orderby = ($orderby) ? $orderby : 'SKU';
$ordermethod = ($ordermethod) ? $ordermethod : 'asc';
if (empty($company)) {
    generateLogin('inventory.php', ucwords(strtolower($vmodule)));
} else {
    if (!isset($warehouse)) {
        $warehouse = '1';
    }
    $user->labelid = $labelid;
    $accountstr = $user->getaccounts_csv();
    $make_visible[0] = "form1.warehouse";
    $content_table_width = "100%";
    $client_id = $session->user->getprimaryaccount();
}

if (isset($func) && $func == "getinventory") {
    $invsql = "
	SELECT max(NVL(products.PRODUCTID,0)) as productid,
			max(NVL(products.PRODUCTSKU,rpad(' ',20))) as productsku,
			max(products.description) as descript,
			sum(whinbounddetails.qtyordered)  as InOrdered,
			sum(whinbounddetails.qtyordered)  - sum(whinbounddetails.QTYRECEIVED) as Inbound,
			sum(whinbounddetails.QTYRECEIVED) as qtyreceived,
			max(Products.unitcode)  as UnitCode,
			sum((whinbounddetails.QtyOrdered
			-decode(NVL(whinbound.Status,'  '), '  ', 0, whinbounddetails.QTYRECEIVED))
			+decode(NVL(whinbound.Status,'  '), '  ', 0, whinbounddetails.QTYONHAND)) as qtyforecast,
			sum((select nvl(sum(wod.qtyordered),0)
        from whoutbounddetails wod, whoutbound wo
        where wod.whinbdetail_id=whinbounddetails.whinbounddetail_id
        and wo.whoutbound_id=wod.whoutb_id
        and wod.qtyordered is not null
        and (wo.status<>'01' or wo.status is null))) as qtyplanned,
			sum(whinbounddetails.qtyonhand)  as available
			FROM whinbounddetails, whinbound, products
			WHERE whinbounddetails.Whinbound_id = whinbound.Whinbound_id
			and whinbounddetails.ProductId = products.PRODUCTID
			and whinbound.Received is not null
			and NVL(Products.INACTIVE,0) = 0
			and NVL(whinbound.Status,'  ')='01'
			and whinbound.clientid = '$client_id'
			group by Products.productid
    ";
    if (trim($orderby) != "") {
        $invsql .= " order by productsku " . $ordermethod;
    }

    $invtotsql = "select count(*) as totals from (" . $invsql . ") temp";
    $gettotals = $session->dbs->read->query($invtotsql);
    $session->dbs->read->fetchinto($gettotals, $totals);
    if ($totals['TOTALS'] != 0) {
        $query = $session->dbs->read->query($invsql);
        $col = 1;
        buildGridHead_new("SKU", "", true, "nowrap", $link, "center", "", "SKU", $orderby, $ordermethod);
        $col++;
        buildGridHead_new($session->user->locale->str('HITSWEB-2', 458), "", true, "nowrap", $link, "center", "", "descript", $orderby, $ordermethod);
        $col++;
        buildGridHead_new($session->user->locale->str('HITSWEB-1', 130), "", true, "nowrap", $link, "center", "", "descript", $orderby, $ordermethod);
        $col++;
        buildGridHead_new($session->user->locale->str('HITSWEB-2', 1189), "", true, "nowrap", $link, "center", "", "unitcode", $orderby, $ordermethod);
        $col++;
        buildGridHead_new($session->user->locale->str('HITSWEB-2', 667), "", true, "nowrap", $link, "center", "", "inbound", $orderby, $ordermethod);
        $col++;
        buildGridHead_new("On Hand", "", true, "nowrap", $link, "center", "", "available", $orderby, $ordermethod);
        $col++;
        buildGridHead_new("Planned", "", true, "nowrap", $link, "center", "", "qtyplanned", $orderby, $ordermethod);
        $col++;
        buildGridHead_new("Received", "", true, "nowrap", $link, "center", "", "qtyplanned", $orderby, $ordermethod);
        $col++;
        buildGridHead_new("Forecast", "", true, "nowrap", $link, "center", "", "qtyforecast", $orderby, $ordermethod);
        $col++;
        echo "</tr>";
        $oddvar = 1;

        if ($totals['TOTALS'] != 1) {
            $siteminsert = "s";
        }
        HTML::pn_links('', $session->rowcount, 1, $session->rowcount, $session->rowcount);

        echo(HTML::pn_links('', $totals['TOTALS'], 1, $totals['TOTALS'], $totals['TOTALS']));
    } else {
        noResultsFound();
    }
}			
