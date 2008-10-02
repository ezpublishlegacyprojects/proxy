<?php
/**
 * File module.php
 *
 * @package proxy
 * @version //autogentag//
 * @copyright Copyright (C) 2007 xrow. All rights reserved.
 * @license http://www.gnu.org/licenses/gpl.txt GPL License
 */
$Module = array( "name" => "Proxy",
                 "variable_params" => true,
                 "function" => array(
                     "script" => "proxy.php",
                     "params" => array( ) ) );

$ViewList = array();
$ViewList["view"] = array(
    'functions' => array( 'proxy' ),
    'default_navigation_part' => 'proxy',
    "script" => "view.php",
    'params' => array( 'ViewMode', 'NodeID', 'proxyname' ),
'unordered_params' => array( 'language' => 'Language',
                                 'offset' => 'Offset',
                                 'year' => 'Year',
                                 'month' => 'Month',
                                 'day' => 'Day' ) );


$FunctionList['proxy'] = array( );
?>
