<?php
/**
 * File containing the eZProxy class.
 *
 * @package proxy
 * @version //autogentag//
 * @copyright Copyright (C) 2007 xrow. All rights reserved.
 * @license http://www.gnu.org/licenses/gpl.txt GPL License
 */
include_once( 'extension/proxy/classes/PHProxy.class.php' );

class eZProxy extends PHProxy
{
    //
    // Configurable vars
    //

    var $banned_hosts = array
   (
       '.localhost',
       '127.0.0.1'
    );
    var $flags = array
    (
        'include_form'    => 1, 
        'remove_scripts'  => 0,
        'accept_cookies'  => 1,
        'show_images'     => 1,
        'show_referer'    => 1,
        'rotate13'        => 0,
        'base64_encode'   => 1,
        'strip_meta'      => 0,
        'strip_title'     => 0,
        'session_cookies' => 1
    );

    //
    // End Configurable vars
    //

    //
    // Edit the $config variables in index.php and javascript.js instead
    //

    var $config = array
    (
        'url_var_name'             => 'q',
        'flags_var_name'           => 'hl',
        'get_form_name'            => '__script_get_form',
        'proxy_url_form_name'      => 'poxy_url_form',
        'proxy_settings_form_name' => 'poxy_settings_form',
        'max_file_size'            => -1
    );

    var $version;
    var $script_url;
    var $http_host;
    var $url;
    var $url_segments;
    var $base;

    var $socket;


    var $request_method;
    var $request_headers;
    var $basic_auth_header;
    var $basic_auth_realm;
    var $data_boundary;
    var $post_body;

    var $response_headers;
    var $response_code;
    var $content_type;
    var $content_length;
    var $response_body;
	function eZProxy( $config, $flags = 'previous' )
	{
		parent::PHProxy( $config, $flags );
	}
    function start_transfer($url)
    {
        $this->set_url($url);
        $this->open_socket();
        if ( $this->socket === false )
            return false;
        $this->http_basic_auth();
        $this->set_request_headers();
        eZDebug::writeDebug( $this->request_headers , "Request Headers" );
        $this->set_response();
        $this->http_basic_auth();
    }
    function open_socket()
    {
        $ini = eZINI::instance( "proxy.ini" );
        $this->socket = fsockopen($this->url_segments['host'], $this->url_segments['port'], $err_no, $err_str, $ini->variable( 'ProxySettings', 'SocketTimeout') );

        if ($this->socket === false)
        {
            eZDebug::writeError( "Proxy Timeout", "eZProxy::open_socket()");
        }
    }
    function set_post_body( $type, $array = array() )
    {
        /*  A POST var like "answer[3].answerId" is parsed in PHP like $_POST = array( 'answer' => array ( '22' ) );
                <input type=hidden name="answer[0].answerId" value="22"/>
        */
        $this->post_body = @file_get_contents('php://input');
    }
    function send_response_headers( $passthrough = false )
    {
        $headers = explode("\r\n", $this->response_headers);
        if ( $passthrough === false ) 
        {    
            $headers[] = 'Content-Disposition: ' . ($this->content_type == 'application/octet_stream' ? 'attachment' : 'inline') . '; filename=' . $this->url_segments['file'];
        }
        $headers = array_filter($headers);

        foreach ($headers as $header)
        {
            if ( strpos( $header, 'Connection:' ) === false and
                 strpos( $header, 'Content-Location:' ) === false and
                 strpos( $header, 'Content-Type:' ) === false  )
                header($header);
        }
    }
}
?>
