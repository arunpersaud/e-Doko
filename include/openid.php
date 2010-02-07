<?php

require_once("db.php");

  /* provide OpenID support
   *
   * taken from http://www.plaxo.com/api/openid_recipe
   */

function OpenIDVerify()
{
  global $OPENIDPATH;

  /* need the openip library */
  require_once $OPENIDPATH."examples/consumer/common.php";

  $consumer = getConsumer();

  $return_to = getReturnTo();
  $response = $consumer->complete($return_to);

  // Check the response status.
  if ($response->status == Auth_OpenID_CANCEL) {
    // This means the authentication was cancelled.
    echo 'Verification cancelled.';
    return False;
  } else if ($response->status == Auth_OpenID_FAILURE) {
    // Authentication failed; display the error message.
    echo "OpenID authentication failed: " . $response->message;
    return False;
  } else if ($response->status == Auth_OpenID_SUCCESS) {
    // This means the authentication succeeded; extract the
    // identity URL and Simple Registration data (if it was
    // returned).
    $openid = $response->getDisplayIdentifier();

    $sreg_resp = Auth_OpenID_SRegResponse::fromSuccessResponse($response);
    $sreg = $sreg_resp->contents();
  }

  if(isset($sreg))
    return $sreg;
  else
    return "ok";
}

function OpenIDAskForVerification($openid_url)
{
  global $OPENIDPATH;

  /* ask for openid verification */
  require_once $OPENIDPATH."examples/consumer/common.php";

  $openid =$_REQUEST['openid_url'];
  $consumer = getConsumer();

  /* check for authentication */
  // Begin the OpenID authentication process.
  $auth_request = $consumer->begin($openid);

  // No auth request means we can't begin OpenID.
  if (!$auth_request) {
    echo "Authentication error; not a valid OpenID.";
    }

  $sreg_request = Auth_OpenID_SRegRequest::build(array(),array('fullname','email', 'nickname'));

  if ($sreg_request) {
    $auth_request->addExtension($sreg_request);
  }

  // Redirect the user to the OpenID server for authentication.
  // Store the token for this authentication so we can verify the
  // response.

  // For OpenID 1, send a redirect.  For OpenID 2, use a Javascript
  // form to send a POST request to the server.
  if ($auth_request->shouldSendRedirect()) {
    $redirect_url = $auth_request->redirectURL(getTrustRoot(),
					       getReturnTo());

    // If the redirect URL can't be built, display an error
    // message.
    if (Auth_OpenID::isFailure($redirect_url)) {
      displayError("Could not redirect to server: " . $redirect_url->message);
    } else {
	// Send redirect.
      header("Location: ".$redirect_url);
      }
  } else {
    // Generate form markup and render it.
    $form_id = 'openid_message';
    $form_html = $auth_request->htmlMarkup(getTrustRoot(), getReturnTo(),
					   false, array('id' => $form_id));

    // Display an error if the form markup couldn't be generated;
    // otherwise, render the HTML.
    if (Auth_OpenID::isFailure($form_html)) {
	displayError("Could not redirect to server: " . $form_html->message);
    } else {
      print $form_html;
    }
  }
}

function OpenIDUrlEncode($openid_url)
{
  /* this converts each url to a standard form
   * (domain lowercase and http at the beginning)
   */

  $return = "";

  $parts = explode("/",$openid_url);
  $return .= "http://";

  /* check for http:// */
  if( strtolower($parts[0]) == "http:" )
    array_shift($parts);
  if( $parts[0] == "")
    array_shift($parts);

  /* next part is the server*/
  $return .= strtolower( $parts[0] );
  array_shift($parts);

  foreach ($parts as $t)
    $return .= "/$t";

  return $return;
}

function DB_GetUserId($openid_url)
{
  $result = DB_query_array("SELECT user_id FROM user_openids WHERE openid_url = ".DB_quote_smart(OpenIDUrlEncode($openid_url)));

  if($result)
    return $result[0];
  else
    return False;
}

function DB_GetOpenIDsByUser($user_id)
{
  return DB_query_array_all("SELECT openid_url FROM user_openids WHERE user_id = '$user_id'");
}

function DB_AttachOpenID($openid_url, $user_id)
{
  DB_query("INSERT INTO user_openids VALUES (".DB_quote_smart(OpenIDUrlEncode($openid_url)).", '$user_id')");
}

function DB_DetachOpenID($openid_url, $user_id)
{
  DB_query("DELETE FROM user_openids WHERE openid_url = ".DB_quote_smart(OpenIDUrlEncode($openid_url))." AND user_id = '$user_id'");
}

function DB_DetachOpenIDsByUser($user_id)
{
  DB_query("DELETE FROM user_openids WHERE user_id = '$user_id'");
}

?>