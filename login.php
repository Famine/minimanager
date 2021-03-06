<?php

require_once __DIR__ . '/vendor/autoload.php';
use Laizerox\Wowemu\SRP\UserClient;

require_once 'header.php';

//#############################################################################
// Login 
//#############################################################################
function dologin(&$sqlr)
{
global $mmfpm_db, $require_account_verify;

if (empty($_POST['user']) || empty($_POST['pass']))
redirect('login.php?error=2');

$user_name  = strtoupper($sqlr->quote_smart($_POST['user']));
$user_pass  = $sqlr->quote_smart($_POST['pass']);

//echo $user_name;
//echo $user_pass;

if (255 < strlen($user_name) || 255 < strlen($user_pass))
{
redirect('login.php?error=1');
}


$saltFromDatabase = $sqlr->result($sqlr->query("SELECT s FROM account WHERE username='$user_name'"));
$verifierFromDatabase = strtoupper($sqlr->result($sqlr->query("SELECT v FROM account WHERE username='$user_name'")));

$client = new UserClient($user_name, $saltFromDatabase);
$verifier = strtoupper($client->generateVerifier($user_pass));

$result = $sqlr->query('SELECT id, gmlevel, username FROM account WHERE username = \''.$user_name.'\' AND v = \''.$verifier.'\'');


if ($verifierFromDatabase === $verifier) 
{
	$id_DB = $sqlr->result($sqlr->query('SELECT id, FROM account WHERE username = \''.$user_name.'\' AND v = \''.$verifier.'\''));
	$gmlevel_DB = $sqlr->result($sqlr->query('SELECT gmlevel FROM account WHERE username = \''.$user_name.'\' AND v = \''.$verifier.'\''));
	$user_name_DB = $sqlr->result($sqlr->query('SELECT username FROM account WHERE username = \''.$user_name.'\' AND v = \''.$verifier.'\''));

	$id = $sqlr->result($result, 0, 'id');
	if ($sqlr->result($sqlr->query('SELECT count(*) FROM account_banned WHERE id = '.$id.' AND active = \'1\''), 0))
	{
		redirect('login.php?error=3');
	}
	else
	{
		$_SESSION['user_id']   = $id;
		$_SESSION['uname']     = $user_name;
		//if (($sqlr->result($result, 0, 'gmlevel')) == null)
		//$_SESSION['user_lvl']  = 0;
		//else
		$_SESSION['user_lvl']  = $gmlevel_DB;
		$_SESSION['realm_id']  = $sqlr->quote_smart($_POST['realm']);
		$_SESSION['client_ip'] = (isset($_SERVER['REMOTE_ADDR']) ) ? $_SERVER['REMOTE_ADDR'] : getenv('REMOTE_ADDR');
		$_SESSION['logged_in'] = true;
		if (isset($_POST['remember']) && $_POST['remember'] != '')
		{
			setcookie(   'uname', $_SESSION['uname'], time()+60*60*24*7);
			setcookie('realm_id', $_SESSION['realm_id'], time()+60*60*24*7);
			setcookie(  'p_hash', $verifier, time()+60*60*24*7);
		}
		unset($user_name);
		redirect('index.php');
	}
}
else
{
	redirect('login.php?error=1');
}

}
//#################################################################################################
// Print login form
//#################################################################################################
function login(&$sqlr)
{
global $output, $lang_login, $characters_db, $server, $remember_me_checked;

$output .= '
<center>
<fieldset class="half_frame">
<legend>'.$lang_login['login'].'</legend>
<form method="post" action="login.php?action=dologin" name="form">
	<input type="hidden" name="pass" value="" maxlength="256" />
	<table class="hidden">
		<tr>
			<td>
				<hr />
			</td>
		</tr>
		<tr align="right">
			<td>'.$lang_login['username'].' : <input type="text" name="user" size="24" maxlength="16" /></td>
		</tr>
		<tr align="right">
			<td>'.$lang_login['password'].' : <input type="password" name="pass" size="24" maxlength="40" /></td>
		</tr>';

$result = $sqlr->query('SELECT id, name FROM realmlist LIMIT 10');

if ($sqlr->num_rows($result) > 1 && (count($server) > 1) && (count($characters_db) > 1))
{
	$output .= '
	<tr align="right">
		<td>'.$lang_login['select_realm'].' :
			<select name="realm">';
				while ($realm = $sqlr->fetch_assoc($result))
				if(isset($server[$realm['id']]))
				$output .= '
				<option value="'.$realm['id'].'">'.htmlentities($realm['name']).'</option>';
				$output .= '
			</select>
		</td>
	</tr>';
}
else
$output .= '
<input type="hidden" name="realm" value="'.$sqlr->result($result, 0, 'id').'" />';
$output .= '
<tr>
	<td>
	</td>
</tr>
<tr align="right">
	<td>'.$lang_login['remember_me'].' : <input type="checkbox" name="remember" value="1"';
if ($remember_me_checked)
$output .= ' checked="checked"';
$output .= ' /></td>
</tr>
<tr>
<td>
</td>
</tr>
<tr align="right">
<td width="290">
<input type="submit" value="" style="display:none" />';
makebutton($lang_login['not_registrated'], 'register.php" type="wrn', 130);
makebutton($lang_login['login'], 'javascript:do_submit()" type="def', 130);
$output .= '
</td>
</tr>
<tr align="center">
	<td><a href="register.php?action=pass_recovery">'.$lang_login['pass_recovery'].'</a></td>
</tr>
<tr>
<td>
<hr />
</td>
</tr>
</table>
<script type="text/javascript">
// <![CDATA[
document.form.user.focus();
// ]]>
</script>
</form>
<br />
</fieldset>
<br /><br />
</center>';
}
//#################################################################################################
// Login via set cookie
//#################################################################################################
function do_cookie_login(&$sqlr)
{
if (empty($_COOKIE['uname']) || empty($_COOKIE['p_hash']) || empty($_COOKIE['realm_id']))
redirect('login.php?error=2');

$user_name = $sqlr->quote_smart($_COOKIE['uname']);
$user_pass = $sqlr->quote_smart($_COOKIE['p_hash']);

$result = $sqlr->query('SELECT id, gmlevel, username FROM account WHERE username = \''.$user_name.'\' AND v = \''.$user_pass.'\'');
	$id_DB = $sqlr->result($sqlr->query('SELECT id, FROM account WHERE username = \''.$user_name.'\' AND v = \''.$user_pass.'\''));
	$gmlevel_DB = $sqlr->result($sqlr->query('SELECT gmlevel FROM account WHERE username = \''.$user_name.'\' AND v = \''.$user_pass.'\''));
	$user_name_DB = $sqlr->result($sqlr->query('SELECT username FROM account WHERE username = \''.$user_name.'\' AND v = \''.$user_pass.'\''));



if ($sqlr->num_rows($result))
{
	$id = $sqlr->result($result, 0, 'id');
	if ($sqlr->result($sqlr->query('SELECT count(*) FROM account_banned WHERE id ='.$id.' AND active = \'1\''), 0))
	{
		redirect('login.php?error=3');
	}
	else
	{
	$_SESSION['user_id']   = $id;
	$_SESSION['uname']     = $user_name;
	//if (($sqlr->result($result, 0, 'gmlevel')) == null)
	//$_SESSION['user_lvl']  = 0;
	//else
	$_SESSION['user_lvl']  = $gmlevel_DB;
	$_SESSION['realm_id']  = $sqlr->quote_smart($_COOKIE['realm_id']);
	$_SESSION['client_ip'] = (isset($_SERVER['REMOTE_ADDR']) ) ? $_SERVER['REMOTE_ADDR'] : getenv('REMOTE_ADDR');
	$_SESSION['logged_in'] = true;
	unset($user_name);
	unset($user_pass);
	redirect('index.php');
	}
}
else
{
	setcookie (   'uname', '', time() - 3600);
	setcookie ('realm_id', '', time() - 3600);
	setcookie (  'p_hash', '', time() - 3600);
	redirect('login.php?error=1');
}
}
//#################################################################################################
// MAIN
//#################################################################################################
if (isset($_COOKIE["uname"]) && isset($_COOKIE["p_hash"]) && isset($_COOKIE["realm_id"]) && empty($_GET['error']))
do_cookie_login($sqlr);

$err = (isset($_GET['error'])) ? $_GET['error'] : NULL;

$lang_login = lang_login();

$output .= '<div class="top">';

if (1 == $err)
$output .=  '<h1><font class="error">'.$lang_login['bad_pass_user'].'</font></h1>';
elseif (2 == $err)
$output .=  '<h1><font class="error">'.$lang_login['missing_pass_user'].'</font></h1>';
elseif (3 == $err)
$output .=  '<h1><font class="error">'.$lang_login['banned_acc'].'</font></h1>';
elseif (5 == $err)
$output .=  '<h1><font class="error">'.$lang_login['no_permision'].'</font></h1>';
elseif (6 == $err)
$output .=  '<h1><font class="error">'.$lang_login['after_registration'].'</font></h1>';
elseif (7 == $err)
$output .=  '<h1><font class="error">'.$lang_login['verify_required'].'</font></h1>';
else
$output .=  '<h1>'.$lang_login['enter_valid_logon'].'</h1>';

unset($err);

$output .= '</div>';

$action = (isset($_GET['action'])) ? $_GET['action'] : NULL;

if ('dologin' === $action)
dologin($sqlr);
else
login($sqlr);

unset($action);
unset($action_permission);
unset($lang_login);

require_once 'footer.php';

?>
