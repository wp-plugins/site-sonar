<?php
/*
Plugin Name: Site-Sonar
Plugin URI: http://site-sonar.com
Description: Real-Time Web Site Monitoring.
Version: 0.2
Author: Technalab
Author URI: http://technalab.com
License: GPL2
*/



if(!is_admin())
{
	add_action('wp_head', 'site_sonar_include');
	
	function site_sonar_include()
	{
		include(dirname(__FILE__).'/site-sonar.php');
	}
}
else
{
	add_action('admin_menu', 'site_sonar_admin_menu');
	register_activation_hook(__FILE__, 'site_sonar_activation');

	function site_sonar_admin_menu() 
	{
		add_plugins_page('Site Sonar', 'Site Sonar', 'activate_plugins',	'site-sonar', 'site_sonar_html_page');
	}
	
	function site_sonar_activation($password = '', $sessionfields = '')
	{
		$extensions = get_loaded_extensions();
		
		if(!in_array('sqlite', $extensions) && !in_array('pdo_sqlite', $extensions))
		{
			deactivate_plugins(__FILE__);
			wp_die("You have not met a requirement of this plugin. <br /><br /><b>SQLITE</b> or <b>PDO_SQLITE</b> PHP module is required", 'ERROR', array('back_link' => true));
			return false;
		}
		
		if($password == '' && file_exists(dirname(__FILE__).'/site-sonar-config.php'))
			return;
			
		if($password == '')
		{
			$password = substr(sha1(''.rand()), 5, 10);
			$sessionfields = '[user_login] ([user_id])';
		}
			
		$cbody = '';
		$cbody .= '<?'."\n";
		$cbody .= '$SS_PASSWORD = \''.str_replace("'", "\\'", $password).'\';'."\n";
		$cbody .= '$SS_DATABASE_FILENAME = \''.dirname(__FILE__).'/logs.sqlite\';'."\n";
		$cbody .= '$SS_PAGEVIEWS_LIMIT = -1;'."\n";
		$cbody .= '$SS_SESSION_FIELDS = \''.str_replace("'", "\\'", $sessionfields).'\';'."\n";
		$cbody .= '$SS_SCRIPT_PATH = \''.plugins_url().'/site-sonar/\';'."\n";
		$cbody .= '$SS_SERVER_DATA = array();'."\n";
		$cbody .= 'if(function_exists(\'get_currentuserinfo\')) {'."\n";
		$cbody .= ' global $current_user;'."\n";
		$cbody .= ' get_currentuserinfo();'."\n";
		$cbody .= ' $SS_SERVER_DATA = array(\'user_id\' => ltrim(\'\'.$current_user->ID, \'0\'), \'user_login\' => $current_user->user_login, \'user_email\' => $current_user->user_email, \'user_firstname\' => $current_user->user_firstname, \'user_lastname\' => $current_user->user_lastname);'."\n";
		$cbody .= "}\n";
		$cbody .= '?>';
		
		file_put_contents(dirname(__FILE__).'/site-sonar-config.php', $cbody);
		
		if(!file_exists(dirname(__FILE__).'/site-sonar-config.php'))
		{
			deactivate_plugins(__FILE__);
			wp_die("Directory ".dirname(__FILE__)." is not writable.", 'ERROR', array('back_link' => true));
			return false;
		}
	}

	function site_sonar_html_page()
	{
		if(isset($_POST['submit']))
		{
			if($_POST['submit'] == 'Save Changes')
			{
				site_sonar_activation($_POST['ss_pswd'], $_POST['ss_sd']);
			}
			else if($_POST['submit'] == 'Reset logs')
			{
				if(file_exists(dirname(__FILE__).'/logs.sqlite'))
					unlink(dirname(__FILE__).'/logs.sqlite');
			}
		}
		
		include(dirname(__FILE__).'/site-sonar-config.php');
?>
<div class="wrap">
	<? screen_icon(); ?>
  <h2>Site Sonar Settings</h2>
  <a href="http://site-sonar.com">Site Sonar</a> by <a href="http://technalab.com">Technalab</a>
  <form action="" method="post" onSubmit="if(document.getElementById('ss_pswd').value == '') { alert('Password is required'); return false;}"><br>
    <table class="widefat fixed" >
    	<thead>
				<tr>
					<th width="110">&nbsp;</th>
					<th>&nbsp;</th>
          <th width="270">&nbsp;</th>
				</tr>
			</thead>
      <tfoot>
				<tr>
					<th>&nbsp;</th>
					<th>&nbsp;</th>
          <th>&nbsp;</th>
				</tr>
			</tfoot>
      <tr>
        <td valign="top" nowrap>Password</td>
        <td valign="top"><input name="ss_pswd" id="ss_pswd" type="text" class="regular-text code" value="<?= $SS_PASSWORD ?>" /><br />
        <span class="description">Password which you need to login from Windows Client.</span></td>
        <td rowspan="2" align="center" valign="top" nowrap><img src="http://site-sonar.com/wp-content/uploads/splash.png" /><br>
          <a class="row-title" style="color:#063" href="http://site-sonar.com/?page_id=1784" target="_blank">Download latest version</a><br /><br />
          <iframe width="250" height="199" src="http://www.youtube.com/embed/yCgEfic32x4?rel=0" frameborder="0" allowfullscreen></iframe></td>
      </tr>
      <tr>
        <td valign="top" nowrap>Session Data</td>
        <td valign="top"><input name="ss_sd" id="ss_sd" type="text" class="regular-text code" value="<?= $SS_SESSION_FIELDS ?>" /><br />
          <span class="description">You can monitor user's session data. Use below available tags for that.</span><br />
        <kbd>[user_id]</kbd>, <kbd>[user_login]</kbd>, <kbd>[user_email]</kbd>, <kbd>[user_firstname]</kbd>, <kbd>[user_lastname]</kbd></td>
      </tr>
      <tr>
        <td valign="top" nowrap>Log file size</td>
        <td style="color:#063" colspan="2" valign="top"><? if(file_exists(dirname(__FILE__).'/logs.sqlite'))
					echo number_format(filesize(dirname(__FILE__).'/logs.sqlite') / 1024 / 1024, 2, ',', ' ').' MB'; ?>&nbsp;</td>
      </tr>
      <tr>
        <td valign="top" nowrap>Script path</td>
        <td style="color:#063" colspan="2" valign="top"><?= plugins_url() ?>/site-sonar/site-sonar.php&nbsp;</td>
      </tr>
    </table>
    <p>
    <input type="submit" name="submit" id="submit" class="button-primary" value="Save Changes">
    <input type="submit" name="submit" id="submit" class="button-primary" value="Reset logs" onClick="if(!confirm('Are you sure you want to reset all logs?')) return false;">
    </p>
  </form>
</div>
<?
	}
}
?>