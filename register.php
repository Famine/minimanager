﻿<?php

require_once __DIR__ . '/vendor/autoload.php';
use Laizerox\Wowemu\SRP\UserClient;

require_once("header.php");


//#####################################################################################################
// REGISTER PRINT FORM
//#####################################################################################################
function register()
{
	global	$lang_register, $lang_global, $output, $lang_command,
			$expansion_select, $lang_captcha, $enable_captcha, $invited_by;

	if (empty($_GET['ref']))
		$referred_by = '';
	else
		$referred_by = $_GET['ref'];

$output .= "
<center>
<script type=\"text/javascript\" src=\"libs/js/sha1.js\"></script>
<script type=\"text/javascript\">
	function do_submit_data () {
		if (document.form.pass1.value != document.form.pass2.value)
		{
			alert('{$lang_register['diff_pass_entered']}');
			return;
		}
		else if (document.form.pass1.value.length > 225)
		{
			alert('{$lang_register['pass_too_long']}');
			return;
		}
		else
		{
			document.form.pass.value = hex_sha1(document.form.username.value.toUpperCase()+':'+document.form.pass1.value.toUpperCase());
			document.form.pass2.value = '0';
			do_submit();
		}
	}
	answerbox.btn_ok='{$lang_register['i_agree']}';
	answerbox.btn_cancel='{$lang_register['i_dont_agree']}';
	answerbox.btn_icon='';
</script>
<fieldset class=\"half_frame\">
<legend>{$lang_register['create_acc']}</legend>
	<form method=\"post\" action=\"register.php?action=doregister\" name=\"form\">
	<input type=\"hidden\" name=\"pass\" value=\"\" maxlength=\"256\" />
		<table class=\"flat\">
			<tr>
				<td valign=\"top\">{$lang_register['username']}:</td>
				<td><input type=\"text\" name=\"username\" size=\"45\" maxlength=\"14\" /><br />
					{$lang_register['use_eng_chars_limited_len']}<br />
				</td>
			</tr>
			<tr>
				<td valign=\"top\">{$lang_register['password']}:</td>
				<td><input type=\"password\" name=\"pass1\" size=\"45\" maxlength=\"25\" /></td>
			</tr>
			<tr>
				<td valign=\"top\">{$lang_register['confirm_password']}:</td>
				<td><input type=\"password\" name=\"pass2\" size=\"45\"  maxlength=\"25\" /><br />
					{$lang_register['min_pass_len']}<br />
				</td>
			</tr>
			<tr>
				<td valign=\"top\">{$lang_register['email']}:</td>
				<td><input type=\"text\" name=\"email\" size=\"45\" maxlength=\"225\" /><br />
					{$lang_register['use_valid_mail']}</td>
			</tr>";
	if ( $invited_by )
	{
$output .= "
			<tr>
				<td valign=\"top\">{$lang_register['invited_by']}:</td>
				<td><input type=\"text\" name=\"referredby\" value=\"$referred_by\" size=\"45\" maxlength=\"12\" /><br />
					{$lang_register['invited_info']}</td>
			</tr>";
	}
	if ( $enable_captcha )
	{
$output .= "
			<tr>
				<td>
				</td>
				<td><img src=\"libs/captcha/CaptchaSecurityImages.php?width=300&height=80&characters=6\" /><br /><br /></td>
			</tr>
			<tr>
				<td valign=\"top\">{$lang_captcha['security_code']}:</td>
				<td><input type=\"text\" name=\"security_code\" size=\"45\" /><br />
				</td>
			</tr>";
	}
	if ( $expansion_select )
	{
$output .= "
			<tr>
				<td valign=\"top\">{$lang_register['acc_type']}:</td>
				<td>
					<select name=\"expansion\">
						<option value=\"2\">{$lang_register['wotlk']}</option>
						<option value=\"1\">{$lang_register['tbc']}</option>
						<option value=\"0\">{$lang_register['classic']}</option>
					</select>
						- {$lang_register['acc_type_desc']}
				</td>
			</tr>";
	}
$output .= "
			<tr>
				<td colspan=\"2\"><hr /></td>
			</tr>
			<tr>
				<td colspan=\"2\">{$lang_register['read_terms']}.</td>
			</tr>
			<tr>
				<td colspan=\"2\"><hr /></td>
			</tr>
			<tr>
				<td>";

	$terms = "<textarea rows=\'18\' cols=\'80\' readonly=\'readonly\'>";
	$fp = fopen("core/mail_templates/terms.tpl", 'r') or die (error("Couldn't Open terms.tpl File!"));
	while (!feof($fp)) $terms .= fgets($fp, 1024);
	fclose($fp);
	$terms .= "</textarea>";

				makebutton($lang_register['create_acc_button'], "javascript:answerBox('{$lang_register['terms']}<br />$terms', 'javascript:do_submit_data()')",150);
$output .= "
				</td>
				<td>";
				makebutton($lang_global['back'], "login.php", 328);
$output .= "
				</td>
			</tr>
		</table>
	</form>
</fieldset>
<br /><br />
</center>";
}


//#####################################################################################################
// DO REGISTER
//
// Variables passed by Form (_POST):
//	pass - SHA1 encrypted password
//	pass1 - plaintext password
//	pass2 - "0"
//	email
//	username
//
//#####################################################################################################
function doregister()
{
	global 	$lang_global, 
			$characters_db, $realm_db, $mmfpm_db, $realm_id,
			$disable_acc_creation, $limit_acc_per_ip, $valid_ip_mask,
			$send_mail_on_creation, $create_acc_locked, $from_mail, $defaultoption, $require_account_verify,
			$mailer_type, $smtp_cfg, $title;

	if ( empty($_POST['pass']) || empty($_POST['email']) || empty($_POST['username']) )
	{
		redirect("register.php?err=1");
	}

	$sqlr = new SQL;
	$sqlr->connect($realm_db['addr'], $realm_db['user'], $realm_db['pass'], $realm_db['name']);

	//Set username and password variables WE SHOULD USE PASS1 FOR S/V
	$user_name = $sqlr->quote_smart(trim($_POST['username']));
	$pass = $sqlr->quote_smart($_POST['pass']);
	$pass1 = $sqlr->quote_smart($_POST['pass1']);

	//make sure username is at least 4 chars long and less than max
	if ((strlen($user_name) < 4) || (strlen($user_name) > 15))
	{
		$sqlr->close();
		redirect("register.php?err=5");
	}
 
	//Username check
	$result = $sqlr->query("
		SELECT username 
		FROM account
		WHERE username='$user_name'");
		
	//there is already someone with same account name
	if ($sqlr->num_rows($result))
	{
	$sqlr->close();
		redirect("register.php?err=3&usr=$user_name");
	}
	else
	{

    /* Create your v and s values. */
	$client = new UserClient($user_name);
    $salt = $client->generateSalt();
    $verifier = $client->generateVerifier($pass1);

/*		This is the correct values list. for $result below

			VALUES 
				(UPPER('$user_name'),
				'$verifier',
				'$salt',
				0,
				'$mail',
				now(),
				0,
				$create_acc_locked,
				NULL,
				0,
				$expansion)");
*/				

		$expansion = $defaultoption;

		$result = $sqlr->query("
			INSERT INTO account 
				(username,
				v,
				s,
				gmlevel,
				email,
				joindate,
				failed_logins,
				locked,
				last_login,
				active_realm_id,
				expansion)
			VALUES 
				(UPPER('$user_name'),
				'$verifier',
				'$salt',
				0,
				'$mail',
				now(),
				0,
				$create_acc_locked,
				NULL,
				0,
				$expansion)");
				

	}

		$sqlr->close();
	
	if ($result) redirect("login.php?error=6");


	
}


//#####################################################################################################
// PRINT PASSWORD RECOVERY FORM
//#####################################################################################################
function pass_recovery()
{
	global $lang_register, $lang_global, $output;
$output .= "
<center>
<fieldset class=\"half_frame\">
<legend>{$lang_register['recover_acc_password']}</legend>
<form method=\"post\" action=\"register.php?action=do_pass_recovery\" name=\"form\">
	<table class=\"flat\">
		<tr>
			<td valign=\"top\">{$lang_register['username']} :</td>
			<td><input type=\"text\" name=\"username\" size=\"45\" maxlength=\"14\" /><br />
				{$lang_register['user_pass_rec_desc']}<br />
		</td>
	</tr>
	<tr>
		<td valign=\"top\">{$lang_register['email']} :</td>
		<td><input type=\"text\" name=\"email\" size=\"45\" maxlength=\"225\" /><br />
			{$lang_register['mail_pass_rec_desc']}
		</td>
	</tr>
	<tr>
		<td>";
			makebutton($lang_register['recover_pass'], "javascript:do_submit()",150);
$output .= "
		</td>
		<td>";
			makebutton($lang_global['back'], "javascript:window.history.back()", 328);
$output .= "
		</td>
	</tr>
</table>
</form>
</fieldset>
<br /><br />
</center>";
}

//#####################################################################################################
// DO RECOVER PASSWORD
//#####################################################################################################
function do_pass_recovery()
{
	global	$lang_global, 
			$realm_db, 
			$from_mail, $mailer_type, $smtp_cfg, $title;

	if ( empty($_POST['username']) || empty($_POST['email']) ) redirect("register.php?action=pass_recovery&err=1");

	$sql = new SQL;
	$sql->connect($realm_db['addr'], $realm_db['user'], $realm_db['pass'], $realm_db['name']);

	$user_name = $sql->quote_smart(trim($_POST['username']));
	$mail = $sql->quote_smart($_POST['email']);

	$result = $sql->query("
		SELECT sha_pass_hash 
		FROM account 
		WHERE username = '$user_name' AND email = '$mail'");

	if ($sql->num_rows($result) == 1)
	{

require_once './libs/mailer/class.phpmailer.php';

		$mailer = new PHPMailer();
		$mailer->Mailer = $mailer_type;
		if ($mailer_type == "smtp")
		{
			$mailer->Host = $smtp_cfg['host'];
			$mailer->Port = $smtp_cfg['port'];
			if($smtp_cfg['user'] != '')
			{
				$mailer->SMTPAuth  = true;
				$mailer->Username  = $smtp_cfg['user'];
				$mailer->Password  =  $smtp_cfg['pass'];
			}
		}

		$file_name = "core/mail_templates/recover_password.tpl";
		$fh = fopen($file_name, 'r');
		$subject = fgets($fh, 4096);
		$body = fread($fh, filesize($file_name));
		fclose($fh);

		$body = str_replace("\n", "<br />", $body);
		$body = str_replace("\r", " ", $body);
		$body = str_replace("<base_url>", $_SERVER['SERVER_NAME'], $body);
		$body = str_replace("<username>", $user_name, $body);
		$body = str_replace("<password>", substr(sha1(strtoupper($user_name)),0,7), $body);
		$body = str_replace("<activate_link>",
			$_SERVER['HTTP_HOST'].$_SERVER['SCRIPT_NAME']."?action=do_pass_activate&amp;h=".$sql->result($result, 0, 'sha_pass_hash')."&amp;p=".substr(sha1(strtoupper($user_name)),0,7), $body);

		$mailer->WordWrap = 50;
		$mailer->From = $from_mail;
		$mailer->FromName = "$title Admin";
		$mailer->Subject = $subject;
		$mailer->IsHTML(true);
		$mailer->Body = $body;
		$mailer->AddAddress($mail);

		if(!$mailer->Send())
		{
			$mailer->ClearAddresses();
			redirect("register.php?action=pass_recovery&err=11&usr=".$mailer->ErrorInfo);
		}
		else
		{
			$mailer->ClearAddresses();
			redirect("register.php?action=pass_recovery&err=12");
		}

unset($mailer);
	}

	else 
		redirect("register.php?action=pass_recovery&err=10");
}


//#####################################################################################################
// DO ACTIVATE RECOVERED PASSWORD
//#####################################################################################################
function do_pass_activate()
{
	global	$lang_global,
			$realm_db;

	if ( empty($_GET['h']) || empty($_GET['p']) ) redirect("register.php?action=pass_recovery&err=1");

	$sql = new SQL;
	$sql->connect($realm_db['addr'], $realm_db['user'], $realm_db['pass'], $realm_db['name']);

	$pass = $sql->quote_smart(trim($_GET['p']));
	$hash = $sql->quote_smart($_GET['h']);

	$result = $sql->query("
		SELECT id,username 
		FROM account 
		WHERE sha_pass_hash = '$hash'");

	if ($sql->num_rows($result) == 1)
	{
		$username = $sql->result($result, 0, 'username');
		$id = $sql->result($result, 0, 'id');
		if (substr(sha1(strtoupper($sql->result($result, 0, 'username'))),0,7) == $pass)
		{
		$sql->query("
			UPDATE account 
			SET sha_pass_hash=SHA1(CONCAT(UPPER('$username'),':',UPPER('$pass'))), v=0, s=0 
			WHERE id = '$id'");
				redirect("login.php");
		}

	}
	else
		redirect("register.php?action=pass_recovery&err=1");

}


//#####################################################################################################
// MAIN
//#####################################################################################################

// load language
$lang_captcha = lang_captcha();

// $_GET and SECURE
$err = (isset($_GET['err'])) ? $_GET['err'] : NULL;

if (isset($_GET['usr'])) $usr = $_GET['usr'];
	else $usr = NULL;

$output .=  '
<div class="top">';

// defines the title header in error cases
if(1 ==  $err)
$output .= '
	<h1><font class="error\">'.$lang_global['empty_fields'].'</font></h1>';
else if(2 == $err)
$output .= '
	<h1><font class="error\">'.$lang_register['diff_pass_entered'].'</font></h1>';
else if(3 == $err)
$output .= '
	<h1><font class="error\">'.$lang_register['username'].' '.$usr.' '.$lang_register['already_exist'].'</font></h1>';
else if(4 == $err)
$output .= '
	<h1><font class="error\">'.$lang_register['acc_reg_closed'].'</font></h1>';
else if(5 == $err)
$output .= '
	<h1><font class="error\">'.$lang_register['wrong_pass_username_size'].'</font></h1>';
else if(6 == $err)
$output .= '
	<h1><font class="error\">'.$lang_register['bad_chars_used'].'</font></h1>';
else if(7 == $err)
$output .= '
	<h1><font class="error\">'.$lang_register['invalid_email'].'</font></h1>';
else if(8 == $err)
$output .= '
	<h1><font class="error\">'.$lang_register['banned_ip'].' '.$usr.' <br />'.$lang_register['contact_serv_admin'].'</font></h1>';
else if(9 == $err)
$output .= '
	<h1><font class="error\">'.$lang_register['users_ip_range'].': '.$usr.' '.$lang_register['cannot_create_acc'].'</font></h1>';
else if(10 == $err)
$output .= '
	<h1><font class="error\">'.$lang_register['user_mail_not_found'].'</font></h1>';
else if(11 == $err)
$output .= '
	<h1><font class="error\">Mailer Error: '.$usr.'</font></h1>';
else if(12 == $err)
$output .= '
	<h1><font class="error\">'.$lang_register['recovery_mail_sent'].'</font></h1>';
else if(13 == $err)
$output .= '
	<h1><font class="error\">'.$lang_captcha['invalid_code'].'</font></h1>';
else if(14 == $err)
$output .= '
	<h1><font class="error\">'.$lang_register['email_address_used'].'</font></h1>';
else if(15 == $err)
$output .= '
	<h1><font class="error\">'.$lang_register['account_needs_verified'].'</font></h1>';
else if(16 == $err)
$output .= '
	<h1><font class="error\">'.$lang_register['validation_mail_sent'].'</font></h1>';
else if(17 == $err)
$output .= '
	<h1><font class="error\">'.$lang_register['referrer_not_found'].'</font></h1>';
else
$output .= '
	<h1>'.$lang_register['fill_all_fields'].'</h1>';

$output .= '
</div>';

// $_GET and SECURE
$action = (isset($_GET['action'])) ? $_GET['action'] : NULL;

// define functions to be called by actions
if ('doregister' == $action)
	doregister();
else if('pass_recovery' == $action)
	pass_recovery();
else if('do_pass_recovery' == $action)
	do_pass_recovery();
else if('do_pass_activate' == $action)
	do_pass_activate();
else
	register();

// close whats not needed anymore
unset($err);
unset($action);
unset($action_permission);
unset($lang_captcha);

// page footer
require_once("footer.php");

?>
