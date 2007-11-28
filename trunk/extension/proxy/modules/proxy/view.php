<?php
/**
 * File view.php
 *
 * @package proxy
 * @version //autogentag//
 * @copyright Copyright (C) 2007 xrow. All rights reserved.
 * @license http://www.gnu.org/licenses/gpl.txt GPL License
 */

include_once( 'extension/proxy/classes/ezproxy.php' );
include_once( 'lib/ezfile/classes/ezlog.php' );
include_once( 'lib/ezxml/classes/ezxml.php' );
include_once( 'kernel/common/template.php' );
include_once( 'kernel/classes/ezcontentobjecttreenode.php' );
include_once( 'kernel/classes/eznodeviewfunctions.php' );
$proxyini = eZINI::instance( 'proxy.ini' );


if ( !$proxyini->hasGroup( $Params['proxyname'] ) )
{
    eZDebug::writeError('No such group "' . $Params['proxyname']. '"', "Proxy" );
    return $Module->handleError( EZ_ERROR_KERNEL_NOT_AVAILABLE, 'kernel' );
}
if ( !$proxyini->hasVariable( $Params['proxyname'], 'URL' ) )
{
    eZDebug::writeError('No URL in group "' . $Params['proxyname']. '"', "Proxy" );
    return $Module->handleError( EZ_ERROR_KERNEL_ACCESS_DENIED, 'kernel' );
}
$url = $proxyini->variable($Params['proxyname'], 'URL' );
$id = $proxyini->variable($Params['proxyname'], 'ID' );
$NodeID = $Params['node_id'];
$ViewMode = $Params['view'];


$flags = array
    (
        'include_form'    => 1, 
        'remove_scripts'  => 0,
        'accept_cookies'  => 1,
        'show_images'     => 1,
        'show_referer'    => 0,
        'rotate13'        => 0,
        'base64_encode'   => 1,
        'strip_meta'      => 0,
        'strip_title'     => 0,
        'session_cookies' => 1
    );
$config = array
    (
        'url_var_name'             => 'URL',
        'flags_var_name'           => 'hl',
        'get_form_name'            => '__script_get_form',
        'proxy_url_form_name'      => 'poxy_url_form',
        'proxy_settings_form_name' => 'poxy_settings_form',
        'max_file_size'            => -1
    );
$PHProxy = new eZProxy( $config, $flags );

$PHProxy->script_url = 'http' 
                          . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on' ? 's' : '')
                          . '://'
                          . $PHProxy->http_host
                          . eZSys::requestURI();

if ( isset( $_GET['URL'] ) )
{
    $url = decode_url($_GET['URL']);
    if( strpos( $url, '://') > 0 )
        $url = $url;
    else
        $url = base64_decode( $_GET['URL'] );
}
eZDebug::writeDebug( $PHProxy->version , "Proxy: Version");
eZDebug::writeDebug( $url , "Proxy: Requested URL");
$testurlA = parse_url( $proxyini->variable( $Params['proxyname'], 'URL' ) );
$testurlB = parse_url( $url );
if ( $testurlB['host'] != $testurlA['host'] )
{
    eZDebug::writeError( 'Host mismatch in group "' . $Params['proxyname'] . '". ' . $testurlB['host'] .' vs ' . $testurlA['host'], "Proxy" );
    return $Module->handleError( EZ_ERROR_KERNEL_ACCESS_DENIED, 'kernel' );
}







$arr = explode('&', $_SERVER['QUERY_STRING']);
if (preg_match('#^'.$PHProxy->config['get_form_name'].'#', $arr[0]))
{
    array_shift($arr);
}
for( $i = 0; count($arr) > $i; $i++ )
{
    if ( preg_match('#^'. $config['url_var_name'] .'=.*#', $arr[$i] ) )
    {
        array_shift( $arr );
        break;
    }
}
if ( count( $arr ) and preg_match('#\?#', $url) )
{
    if ( strpos( $url, '?' ) === strlen( $url ) )
        $url .= implode('&', $arr );
    else
        $url .= '&' . implode('&', $arr );
}
elseif( count( $arr ) and !preg_match('#\?#', $url) )
{
    $url .= '?' . implode('&', $arr );
}
eZDebug::accumulatorStart( 'proxy_request', 'proxy_total', 'proxy_requests' );
if ( $PHProxy->start_transfer( encode_url( $url ) ) === false )
        return $Module->handleError( EZ_ERROR_KERNEL_NOT_AVAILABLE, 'kernel' );
eZDebug::accumulatorStop( 'proxy_request' );
eZDebug::accumulatorStart( 'proxy_parsing', 'proxy_total', 'proxy_parsing' );
if ( $PHProxy->content_type == "text/html" )
    $response = trim( $PHProxy->return_response() );
else
{
    echo $PHProxy->return_response();
    exit();
}

include_once( "lib/ezi18n/classes/eztextcodec.php" );
$OutputTextCodec =& eZTextCodec::instance( 'iso-8859-1', false, false );
$response = $OutputTextCodec->convertString( $response );

eZDebug::writeDebug( $response , "Proxy: RAW Response");
$xml = new eZXML();

#$response = preg_replace("/proxyURL\(myURL\);/", "proxyURL(myURL);alert(myURL);document.write(myURL);", $response );
$response = preg_replace("/type=hidden/", "type=\"hidden\"", $response );
$response = preg_replace("/\s{6}/", "", $response );
$response = preg_replace("/&#(\d{2,5});/e", "unichr($1);", $response);
$response = htmlEnitiesToUTF8( $response );
$response = preg_replace("/Â/e", "", $response);
$response = preg_replace("/(<([\w]+)[^>]*>)([\s]*)(<\/\\2>)/", "\$1<![CDATA[]]>\$4", $response);

$dom = $xml->domTree( $response );
$out = null;
if ( $dom )
{
    $root =& $dom->get_root();
    $return = findNode( $root, $id);
    if ( $return === false )
    {
        $doc = new eZDOMDocument();
        $doc->setRoot( $root );
        $out .= $doc->toString();
    }
    elseif ( is_object( $return ) )
    {
        foreach ( $return->Children as $Child )
        {
            $doc = new eZDOMDocument();
            $doc->setRoot( $Child );
            $out .= $doc->toString();
        }
    }
    else 
    {
        foreach ( $root->Children as $Child )
        {
            $doc = new eZDOMDocument();
            $doc->setRoot( $Child );
            $out .= $doc->toString();
        }
    }
    $out = str_replace( '<?xml version="1.0" encoding="UTF-8"?>' . "\n", '', $out );
    $out = preg_replace("/<textarea([^>]+)\\/>/", "<textarea$1></textarea>", $out );
    $out = preg_replace("/<script([^>]+)\\/>/", "<script$1></script>", $out );
    $out = preg_replace("/<\!\[CDATA\[\]\]>/", "", $out );
    $out = preg_replace("/&quot;/", "\"", $out );
    $out = preg_replace("/&apos;/", "'", $out );
    
}
else
{
    $out = preg_replace("/<\!\[CDATA\[\]\]>/", "", $out );
    $out = $response;
}
eZDebug::accumulatorStop( 'proxy_parsing' );

if(empty($out))
{
    $out="Sorry, this module is temporarily not available.";
    /* return $Module->handleError( EZ_ERROR_KERNEL_NOT_AVAILABLE, 'kernel');*/
}

unlink( 'var/log/proxy.log' );
eZLog::write( "\n" . $response . "\n---------------------","proxy.log");
unlink( 'var/log/proxy_out.log' );
eZLog::write( "\n" . $out . "\n---------------------","proxy_out.log");
eZDebug::writeDebug( $out , "Proxy output");
$tpl =& templateInit();
$tpl->setVariable( 'proxy_result', $out );

$Result = array();

 	


                              
                              
$cacheFileArray = array( 'cache_dir' => false, 'cache_path' => false );

    $node = eZContentObjectTreeNode::fetch( $NodeID );

    if ( !is_object( $node ) )
        return $Module->handleError( EZ_ERROR_KERNEL_NOT_AVAILABLE, 'kernel' );

    $object =& $node->attribute( 'object' );

    if ( !is_object( $object ) )
        return $Module->handleError( EZ_ERROR_KERNEL_NOT_AVAILABLE, 'kernel' );

    if ( !get_class( $object ) == 'ezcontentobject' )
        return $Module->handleError( EZ_ERROR_KERNEL_NOT_AVAILABLE, 'kernel' );

    if ( $node === null )
        return $Module->handleError( EZ_ERROR_KERNEL_NOT_AVAILABLE, 'kernel' );

    if ( $object === null )
        return $Module->handleError( EZ_ERROR_KERNEL_ACCESS_DENIED, 'kernel' );

    if ( $node->attribute( 'is_invisible' ) && !eZContentObjectTreeNode::showInvisibleNodes() )
        return $Module->handleError( EZ_ERROR_KERNEL_ACCESS_DENIED, 'kernel' );

    if ( !$object->canRead() )
    {
        return $Module->handleError( EZ_ERROR_KERNEL_ACCESS_DENIED, 'kernel', array( 'AccessList' => $object->accessList( 'read' ) ) );
    }
                                 
                              
                              
// caching of the node                              
 $Result =& eZNodeviewfunctions::generateNodeView( $tpl, $node, $object, $LanguageCode, $ViewMode, $Offset,
                                                     $cacheFileArray['cache_dir'], $cacheFileArray['cache_path'], $viewCacheEnabled, $viewParameters,
                                                     $collectionAttributes, $validation ); 	                              
                              

// setting custom module_result e.g. $layout for SiteDesign     


$data_map =$node->DataMap();
if ( isset( $data_map['layout'] ) )
{
	$layoutSelectionArray = $data_map['layout']->content();
	$layout = $layoutSelectionArray[0];
}
                                                     
		$section = eZSection::fetch( $object->attribute( 'section_id' ) );
        if ( $section )
            $navigationPartIdentifier = $section->attribute( 'navigation_part_identifier' );
        else
            $navigationPartIdentifier = null;
        $parents =& $node->attribute( 'path' );

        $path = array();
        $titlePath = array();
        foreach ( $parents as $parent )
        {
            $path[] = array( 'text' => $parent->attribute( 'name' ),
                             'url' => '/content/view/full/' . $parent->attribute( 'node_id' ),
                             'url_alias' => $parent->attribute( 'url_alias' ),
                             'node_id' => $parent->attribute( 'node_id' )
                             );
        }

        $titlePath = $path;
        $path[] = array( 'text' => $object->attribute( 'name' ),
                         'url' => false,
                         'url_alias' => false,
                         'node_id' => $node->attribute( 'node_id' ) );

        $titlePath[] = array( 'text' => $object->attribute( 'name' ),
                              'url' => false,
                              'url_alias' => false );                                                     
                                                     
                                                     
$Result['view_parameters'] =& $viewParameters;
$Result['path'] =& $path;
$Result['title_path'] =& $titlePath;
$Result['section_id'] =& $object->attribute( 'section_id' );
$Result['node_id'] =& $node->attribute( 'node_id' );
$Result['navigation_part'] = $navigationPartIdentifier;

 		$contentInfoArray = array();
        $contentInfoArray['object_id'] = $object->attribute( 'id' );
        $contentInfoArray['node_id'] = $node->attribute( 'node_id' );
        $contentInfoArray['parent_node_id'] =  $node->attribute( 'parent_node_id' );
        $contentInfoArray['class_id'] = $object->attribute( 'contentclass_id' );
        $contentInfoArray['class_identifier'] = $node->attribute( 'class_identifier' );
        $contentInfoArray['offset'] = $offset;
        $contentInfoArray['viewmode'] = $viewMode;
        $contentInfoArray['navigation_part_identifier'] = $navigationPartIdentifier;
        $contentInfoArray['node_depth'] = $node->attribute( 'depth' );
        $contentInfoArray['url_alias'] = $node->attribute( 'url_alias' );
        $contentInfoArray['persistent_variable'] = false;
        $contentInfoArray['layout'] = $layout;
        
        if ( $tpl->variable( 'persistent_variable' ) !== false )
        {
            $contentInfoArray['persistent_variable'] = $tpl->variable( 'persistent_variable' );
        }
        $contentInfoArray['class_group'] = $object->attribute( 'match_ingroup_id_list' );

$Result['content_info'] = $contentInfoArray;

    return $Result;


function &findNode( &$root, $id = 'maincontent-design', $checkroot = true )
{
    if ( $checkroot )
    {
        $value = $root->getAttribute( 'id' );
        if ( $value === $id && hasNodeAttribute( $root, $id ) )
        {
            return $root;
        }
    }
    if ( $root->hasChildNodes() )
    {
        for ( $i = 0; count( $root->Children ) > $i; $i++ )
        {
            $value = $root->Children[$i]->getAttribute( 'id' );
            if ( $value === $id && hasNodeAttribute( $root->Children[$i], $id ) )
            {
                return $root->Children[$i];
            }
            $return = findNode( $root->Children[$i], $id, false );
            if ( $return )
                return $return;
        }
    }
    return false;
}
function &hasNodeAttribute( &$root, $id )
{
    if ( is_array( $root->Attributes ) )
    {
        for ( $i = 0; count( $root->Attributes ) > $i; $i++ )
        {
            if ( $root->Attributes[$i]->Content === $id )
            {
                return true;
            }
        }
    }
    return false;
}
function unichr($dec) {
  if ($dec < 128) {
   $utf = chr($dec);
  } else if ($dec < 2048) {
   $utf = chr(192 + (($dec - ($dec % 64)) / 64));
   $utf .= chr(128 + ($dec % 64));
  } else {
   $utf = chr(224 + (($dec - ($dec % 4096)) / 4096));
   $utf .= chr(128 + ((($dec % 4096) - ($dec % 64)) / 64));
   $utf .= chr(128 + ($dec % 64));
  }
  return $utf;
}
function htmlEnitiesToUTF8( $str )
{

$htmlentities_utf8 = array('&nbsp;' => "\xc2\xa0",
'&iexcl;' => "\xc2\xa1",
'&cent;' => "\xc2\xa2",
'&pound;' => "\xc2\xa3",
'&curren;' => "\xc2\xa4",
'&yen;' => "\xc2\xa5",
'&brvbar;' => "\xc2\xa6",
'&sect;' => "\xc2\xa7",
'&uml;' => "\xc2\xa8",
'&copy;' => "\xc2\xa9",
'&ordf;' => "\xc2\xaa",
'&laquo;' => "\xc2\xab",
'&not;' => "\xc2\xac",
'&shy;' => "\xc2\xad",
'&reg;' => "\xc2\xae",
'&macr;' => "\xc2\xaf",
'&deg;' => "\xc2\xb0",
'&plusmn;' => "\xc2\xb1",
'&sup2;' => "\xc2\xb2",
'&sup3;' => "\xc2\xb3",
'&acute;' => "\xc2\xb4",
'&micro;' => "\xc2\xb5",
'&para;' => "\xc2\xb6",
'&middot;' => "\xc2\xb7",
'&cedil;' => "\xc2\xb8",
'&sup1;' => "\xc2\xb9",
'&ordm;' => "\xc2\xba",
'&raquo;' => "\xc2\xbb",
'&frac14;' => "\xc2\xbc",
'&frac12;' => "\xc2\xbd",
'&frac34;' => "\xc2\xbe",
'&iquest;' => "\xc2\xbf",
'&Agrave;' => "\xc3\x80",
'&Aacute;' => "\xc3\x81",
'&Acirc;' => "\xc3\x82",
'&Atilde;' => "\xc3\x83",
'&Auml;' => "\xc3\x84",
'&Aring;' => "\xc3\x85",
'&AElig;' => "\xc3\x86",
'&Ccedil;' => "\xc3\x87",
'&Egrave;' => "\xc3\x88",
'&Eacute;' => "\xc3\x89",
'&Ecirc;' => "\xc3\x8a",
'&Euml;' => "\xc3\x8b",
'&Igrave;' => "\xc3\x8c",
'&Iacute;' => "\xc3\x8d",
'&Icirc;' => "\xc3\x8e",
'&Iuml;' => "\xc3\x8f",
'&ETH;' => "\xc3\x90",
'&Ntilde;' => "\xc3\x91",
'&Ograve;' => "\xc3\x92",
'&Oacute;' => "\xc3\x93",
'&Ocirc;' => "\xc3\x94",
'&Otilde;' => "\xc3\x95",
'&Ouml;' => "\xc3\x96",
'&times;' => "\xc3\x97",
'&Oslash;' => "\xc3\x98",
'&Ugrave;' => "\xc3\x99",
'&Uacute;' => "\xc3\x9a",
'&Ucirc;' => "\xc3\x9b",
'&Uuml;' => "\xc3\x9c",
'&Yacute;' => "\xc3\x9d",
'&THORN;' => "\xc3\x9e",
'&szlig;' => "\xc3\x9f",
'&agrave;' => "\xc3\xa0",
'&aacute;' => "\xc3\xa1",
'&acirc;' => "\xc3\xa2",
'&atilde;' => "\xc3\xa3",
'&auml;' => "\xc3\xa4",
'&aring;' => "\xc3\xa5",
'&aelig;' => "\xc3\xa6",
'&ccedil;' => "\xc3\xa7",
'&egrave;' => "\xc3\xa8",
'&eacute;' => "\xc3\xa9",
'&ecirc;' => "\xc3\xaa",
'&euml;' => "\xc3\xab",
'&igrave;' => "\xc3\xac",
'&iacute;' => "\xc3\xad",
'&icirc;' => "\xc3\xae",
'&iuml;' => "\xc3\xaf",
'&eth;' => "\xc3\xb0",
'&ntilde;' => "\xc3\xb1",
'&ograve;' => "\xc3\xb2",
'&oacute;' => "\xc3\xb3",
'&ocirc;' => "\xc3\xb4",
'&otilde;' => "\xc3\xb5",
'&ouml;' => "\xc3\xb6",
'&divide;' => "\xc3\xb7",
'&oslash;' => "\xc3\xb8",
'&ugrave;' => "\xc3\xb9",
'&uacute;' => "\xc3\xba",
'&ucirc;' => "\xc3\xbb",
'&uuml;' => "\xc3\xbc",
'&yacute;' => "\xc3\xbd",
'&thorn;' => "\xc3\xbe",
'&yuml;' => "\xc3\xbf",
'&fnof;' => "\xc6\x92",
'&Alpha;' => "\xce\x91",
'&Beta;' => "\xce\x92",
'&Gamma;' => "\xce\x93",
'&Delta;' => "\xce\x94",
'&Epsilon;' => "\xce\x95",
'&Zeta;' => "\xce\x96",
'&Eta;' => "\xce\x97",
'&Theta;' => "\xce\x98",
'&Iota;' => "\xce\x99",
'&Kappa;' => "\xce\x9a",
'&Lambda;' => "\xce\x9b",
'&Mu;' => "\xce\x9c",
'&Nu;' => "\xce\x9d",
'&Xi;' => "\xce\x9e",
'&Omicron;' => "\xce\x9f",
'&Pi;' => "\xce\xa0",
'&Rho;' => "\xce\xa1",
'&Sigma;' => "\xce\xa3",
'&Tau;' => "\xce\xa4",
'&Upsilon;' => "\xce\xa5",
'&Phi;' => "\xce\xa6",
'&Chi;' => "\xce\xa7",
'&Psi;' => "\xce\xa8",
'&Omega;' => "\xce\xa9",
'&alpha;' => "\xce\xb1",
'&beta;' => "\xce\xb2",
'&gamma;' => "\xce\xb3",
'&delta;' => "\xce\xb4",
'&epsilon;' => "\xce\xb5",
'&zeta;' => "\xce\xb6",
'&eta;' => "\xce\xb7",
'&theta;' => "\xce\xb8",
'&iota;' => "\xce\xb9",
'&kappa;' => "\xce\xba",
'&lambda;' => "\xce\xbb",
'&mu;' => "\xce\xbc",
'&nu;' => "\xce\xbd",
'&xi;' => "\xce\xbe",
'&omicron;' => "\xce\xbf",
'&pi;' => "\xcf\x80",
'&rho;' => "\xcf\x81",
'&sigmaf;' => "\xcf\x82",
'&sigma;' => "\xcf\x83",
'&tau;' => "\xcf\x84",
'&upsilon;' => "\xcf\x85",
'&phi;' => "\xcf\x86",
'&chi;' => "\xcf\x87",
'&psi;' => "\xcf\x88",
'&omega;' => "\xcf\x89",
'&thetasym;' => "\xcf\x91",
'&upsih;' => "\xcf\x92",
'&piv;' => "\xcf\x96",
'&bull;' => "\xe2\x80\xa2",
'&hellip;' => "\xe2\x80\xa6",
'&prime;' => "\xe2\x80\xb2",
'&Prime;' => "\xe2\x80\xb3",
'&oline;' => "\xe2\x80\xbe",
'&frasl;' => "\xe2\x81\x84",
'&weierp;' => "\xe2\x84\x98",
'&image;' => "\xe2\x84\x91",
'&real;' => "\xe2\x84\x9c",
'&trade;' => "\xe2\x84\xa2",
'&alefsym;' => "\xe2\x84\xb5",
'&larr;' => "\xe2\x86\x90",
'&uarr;' => "\xe2\x86\x91",
'&rarr;' => "\xe2\x86\x92",
'&darr;' => "\xe2\x86\x93",
'&harr;' => "\xe2\x86\x94",
'&crarr;' => "\xe2\x86\xb5",
'&lArr;' => "\xe2\x87\x90",
'&uArr;' => "\xe2\x87\x91",
'&rArr;' => "\xe2\x87\x92",
'&dArr;' => "\xe2\x87\x93",
'&hArr;' => "\xe2\x87\x94",
'&forall;' => "\xe2\x88\x80",
'&part;' => "\xe2\x88\x82",
'&exist;' => "\xe2\x88\x83",
'&empty;' => "\xe2\x88\x85",
'&nabla;' => "\xe2\x88\x87",
'&isin;' => "\xe2\x88\x88",
'&notin;' => "\xe2\x88\x89",
'&ni;' => "\xe2\x88\x8b",
'&prod;' => "\xe2\x88\x8f",
'&sum;' => "\xe2\x88\x91",
'&minus;' => "\xe2\x88\x92",
'&lowast;' => "\xe2\x88\x97",
'&radic;' => "\xe2\x88\x9a",
'&prop;' => "\xe2\x88\x9d",
'&infin;' => "\xe2\x88\x9e",
'&ang;' => "\xe2\x88\xa0",
'&and;' => "\xe2\x88\xa7",
'&or;' => "\xe2\x88\xa8",
'&cap;' => "\xe2\x88\xa9",
'&cup;' => "\xe2\x88\xaa",
'&int;' => "\xe2\x88\xab",
'&there4;' => "\xe2\x88\xb4",
'&sim;' => "\xe2\x88\xbc",
'&cong;' => "\xe2\x89\x85",
'&asymp;' => "\xe2\x89\x88",
'&ne;' => "\xe2\x89\xa0",
'&equiv;' => "\xe2\x89\xa1",
'&le;' => "\xe2\x89\xa4",
'&ge;' => "\xe2\x89\xa5",
'&sub;' => "\xe2\x8a\x82",
'&sup;' => "\xe2\x8a\x83",
'&nsub;' => "\xe2\x8a\x84",
'&sube;' => "\xe2\x8a\x86",
'&supe;' => "\xe2\x8a\x87",
'&oplus;' => "\xe2\x8a\x95",
'&otimes;' => "\xe2\x8a\x97",
'&perp;' => "\xe2\x8a\xa5",
'&sdot;' => "\xe2\x8b\x85",
'&lceil;' => "\xe2\x8c\x88",
'&rceil;' => "\xe2\x8c\x89",
'&lfloor;' => "\xe2\x8c\x8a",
'&rfloor;' => "\xe2\x8c\x8b",
'&lang;' => "\xe2\x8c\xa9",
'&rang;' => "\xe2\x8c\xaa",
'&loz;' => "\xe2\x97\x8a",
'&spades;' => "\xe2\x99\xa0",
'&clubs;' => "\xe2\x99\xa3",
'&hearts;' => "\xe2\x99\xa5",
'&diams;' => "\xe2\x99\xa6",
'&quot;' => "\x22",
'&amp;' => "\x26",
'&lt;' => "\x3c",
'&gt;' => "\x3e",
'&OElig;' => "\xc5\x92",
'&oelig;' => "\xc5\x93",
'&Scaron;' => "\xc5\xa0",
'&scaron;' => "\xc5\xa1",
'&Yuml;' => "\xc5\xb8",
'&circ;' => "\xcb\x86",
'&tilde;' => "\xcb\x9c",
'&ensp;' => "\xe2\x80\x82",
'&emsp;' => "\xe2\x80\x83",
'&thinsp;' => "\xe2\x80\x89",
'&zwnj;' => "\xe2\x80\x8c",
'&zwj;' => "\xe2\x80\x8d",
'&lrm;' => "\xe2\x80\x8e",
'&rlm;' => "\xe2\x80\x8f",
'&ndash;' => "\xe2\x80\x93",
'&mdash;' => "\xe2\x80\x94",
'&lsquo;' => "\xe2\x80\x98",
'&rsquo;' => "\xe2\x80\x99",
'&sbquo;' => "\xe2\x80\x9a",
'&ldquo;' => "\xe2\x80\x9c",
'&rdquo;' => "\xe2\x80\x9d",
'&bdquo;' => "\xe2\x80\x9e",
'&dagger;' => "\xe2\x80\xa0",
'&Dagger;' => "\xe2\x80\xa1",
'&permil;' => "\xe2\x80\xb0",
'&lsaquo;' => "\xe2\x80\xb9",
'&rsaquo;' => "\xe2\x80\xba",
'&euro;' => "\xe2\x82\xac");
foreach($htmlentities_utf8 as $key => $value ) $str = str_replace( $key, $value, $str );
return $str;
}
?>
