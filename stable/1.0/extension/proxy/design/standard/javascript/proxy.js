/* 
Author: Björn Dieding
Date: 01.02.2007

Within your proxied application dynamicly build URLs with javascript can't be parsed by the proxy.
We need to use a little helper function to created the proper url for us.
The function itself will only work on pages from the proxy module.

The example below show the usage of this function.
If the function "proxyURL" exists we grab the new URL from the function.
If the function "proxyURL" doesn't exists we will use the original url.
 
<script type="text/javascript">
var myURL='http://ez.examle.org.uk/sense/propertyDetailsPublic.do?action=view';
if ( window.proxyURL )
{
   myURL = proxyURL(myURL);
}
alert( myURL );
</script>

*/
// This function takes an absolute URL and transforms it into eZ proxy urls
// @param URL absolute URL
if ( !window.proxyURL )
{
function proxyURL( URL )
{
    if ( window.location.pathname.indexOf("proxy/view/") != -1 )
    {
        if ( window.location.port == '' )
        {
            var hostport = window.location.host;
        }
        else
        {
            var hostport = window.location.host + ':' + window.location.port;
        }
        var newURL = window.location.protocol + '//' + hostport +  window.location.pathname;
        if ( URL.indexOf( "?" ) != -1 )
        {
            var parts = URL.split("?");
            newURL += '?URL=' + base64_encode( parts[0] ) + '&' + parts[1];
        }
        else
        {
            newURL += '?URL=' + base64_encode( URL )
        }
        return newURL;
    }
    else
    {
        return URL;
    }
}
function base64_encode(input)
{
   var keyStr = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=";
   var output = "";
   var chr1, chr2, chr3;
   var enc1, enc2, enc3, enc4;
   var i = 0;

   do {
      chr1 = input.charCodeAt(i++);
      chr2 = input.charCodeAt(i++);
      chr3 = input.charCodeAt(i++);

      enc1 = chr1 >> 2;
      enc2 = ((chr1 & 3) << 4) | (chr2 >> 4);
      enc3 = ((chr2 & 15) << 2) | (chr3 >> 6);
      enc4 = chr3 & 63;

      if (isNaN(chr2)) {
         enc3 = enc4 = 64;
      } else if (isNaN(chr3)) {
         enc4 = 64;
      }

      output = output + keyStr.charAt(enc1) + keyStr.charAt(enc2) + 
         keyStr.charAt(enc3) + keyStr.charAt(enc4);
   } while (i < input.length);
   
   return output;
}

function base64_decode(input)
{
   var keyStr = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=";
   var output = "";
   var chr1, chr2, chr3;
   var enc1, enc2, enc3, enc4;
   var i = 0;

   // remove all characters that are not A-Z, a-z, 0-9, +, /, or =
   input = input.replace(/[^A-Za-z0-9\+\/\=]/g, "");

   do {
      enc1 = keyStr.indexOf(input.charAt(i++));
      enc2 = keyStr.indexOf(input.charAt(i++));
      enc3 = keyStr.indexOf(input.charAt(i++));
      enc4 = keyStr.indexOf(input.charAt(i++));

      chr1 = (enc1 << 2) | (enc2 >> 4);
      chr2 = ((enc2 & 15) << 4) | (enc3 >> 2);
      chr3 = ((enc3 & 3) << 6) | enc4;

      output = output + String.fromCharCode(chr1);

      if (enc3 != 64) {
         output = output + String.fromCharCode(chr2);
      }
      if (enc4 != 64) {
         output = output + String.fromCharCode(chr3);
      }
   } while (i < input.length);

   return output;
}
}