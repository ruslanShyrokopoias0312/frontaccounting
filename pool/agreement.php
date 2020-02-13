<?php 
/**********************************************************************
    Copyright (C) FrontAccounting, LLC.
	Released under the terms of the GNU General Public License, GPL, 
	as published by the Free Software Foundation, either version 3 
	of the License, or (at your option) any later version.
    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  
    See the License here <http://www.gnu.org/licenses/gpl-3.0.html>.
***********************************************************************/
$page_security = 'SA_CREATECOMPANY';
$path_to_root="..";
include_once($path_to_root . "/includes/session.inc");

include_once($path_to_root . "/includes/date_functions.inc");
include_once($path_to_root . "/admin/db/company_db.inc");
include_once($path_to_root . "/admin/db/maintenance_db.inc");
include_once($path_to_root . "/includes/ui.inc");
include_once($path_to_root . "/includes/page/header.inc");

$comp_subdirs = array('images', 'pdf_files', 'backup','js_cache', 'reporting', 'attachments');

simple_page_mode(true);
/*
	FIXME: tb_pref_counter should track prefix per database.
*/
//---------------------------------------------------------------------------------------------

function check_data($selected_id)
{
	global $db_connections, $tb_pref_counter;

	if ($selected_id != -1) {
		if ($_POST['name'] == "")
		{
			display_error(_("Database settings are not specified."));
	 		return false;
		}
	} else {
		if (!get_post('name') || !get_post('host') || !get_post('dbuser') || !get_post('dbname'))
		{
			display_error(_("Database settings are not specified."));
	 		return false;
		}
		if ($_POST['port'] != '' && !is_numeric($_POST['port'])) 
		{
			display_error(_('Database port has to be numeric or empty.'));
			return false;
		}
	
		foreach($db_connections as $id=>$con)
		{
		 if($id != $selected_id && $_POST['host'] == $con['host'] 
		 	&& $_POST['dbname'] == $con['dbname'])
	  		{
				if ($_POST['tbpref'] == $con['tbpref'])
				{
					display_error(_("This database settings are already used by another company."));
					return false;
				}
				if (($_POST['tbpref'] == 0) ^ ($con['tbpref'] == ''))
				{
					display_error(_("You cannot have table set without prefix together with prefixed sets in the same database."));
					return false;
				}
		  	}
		}
	}
	return true;
}

//---------------------------------------------------------------------------------------------

function remove_connection($id) {
	global $db_connections;

	$err = db_drop_db($db_connections[$id]);

	unset($db_connections[$id]);
	$conn = array_values($db_connections);
	$db_connections = $conn;
    return $err;
}
//---------------------------------------------------------------------------------------------

function handle_submit($selected_id)
{
	global $db_connections, $def_coy, $tb_pref_counter, $db,
	    $comp_subdirs, $path_to_root, $Mode;

	$error = false;

	if ($selected_id==-1)
		$selected_id = count($db_connections);

	$new = !isset($db_connections[$selected_id]);

	if (check_value('def'))
		$def_coy = $selected_id;

	$db_connections[$selected_id]['name'] = $_POST['name'];
	if ($new) {
		$db_connections[$selected_id]['host'] = $_POST['host'];
		$db_connections[$selected_id]['port'] = $_POST['port'];
		$db_connections[$selected_id]['dbuser'] = $_POST['dbuser'];
		$db_connections[$selected_id]['dbpassword'] = html_entity_decode($_POST['dbpassword'], ENT_QUOTES, 
			$_SESSION['language']->encoding=='iso-8859-2' ? 'ISO-8859-1' : $_SESSION['language']->encoding);
		$db_connections[$selected_id]['dbname'] = $_POST['dbname'];
		$db_connections[$selected_id]['collation'] = $_POST['collation'];
		if (is_numeric($_POST['tbpref']))
		{
			$db_connections[$selected_id]['tbpref'] = $_POST['tbpref'] == 1 ?
			  $tb_pref_counter."_" : '';
		}
		else if ($_POST['tbpref'] != "")
			$db_connections[$selected_id]['tbpref'] = $_POST['tbpref'];
		else
			$db_connections[$selected_id]['tbpref'] = "";

		$conn = $db_connections[$selected_id];
		if (($db = db_create_db($conn)) === false)
		{
			display_error(_("Error creating Database: ") . $conn['dbname'] . _(", Please create it manually"));
			$error = true;
		} else {
			if (strncmp(db_get_version(), "5.6", 3) >= 0) 
				db_query("SET sql_mode = ''");
			if (!db_import($path_to_root.'/sql/'.get_post('coa'), $conn, $selected_id)) {
				display_error(_('Cannot create new company due to bugs in sql file.'));
				$error = true;
			} 
			else
			{
				if (!isset($_POST['admpassword']) || $_POST['admpassword'] == "")
					$_POST['admpassword'] = "password";
				update_admin_password($conn, md5($_POST['admpassword']));
			}
		}
		if ($error) {
			remove_connection($selected_id);
			return false;
		}
	}
	$error = write_config_db($new);

	if ($error == -1)
		display_error(_("Cannot open the configuration file - ") . $path_to_root . "/config_db.php");
	else if ($error == -2)
		display_error(_("Cannot write to the configuration file - ") . $path_to_root . "/config_db.php");
	else if ($error == -3)
		display_error(_("The configuration file ") . $path_to_root . "/config_db.php" . _(" is not writable. Change its permissions so it is, then re-run the operation."));
	if ($error != 0)
	{
		return false;
	}

	if ($new)
	{
		create_comp_dirs(company_path($selected_id), $comp_subdirs);
		$exts = get_company_extensions();
		write_extensions($exts, $selected_id);
	}
	display_notification($new ? _('New company has been created.') : _('Company has been updated.'));

	$Mode = 'RESET';
	return true;
}

//---------------------------------------------------------------------------------------------

function handle_delete($id)
{
	global $Ajax, $def_coy, $db_connections, $comp_subdirs, $path_to_root, $Mode;

	// First make sure all company directories from the one under removal are writable. 
	// Without this after operation we end up with changed per-company owners!
	for($i = $id; $i < count($db_connections); $i++) {
			$comp_path = company_path($i);
		if (!is_dir($comp_path) || !is_writable($comp_path)) {
			display_error(_('Broken company subdirectories system. You have to remove this company manually.'));
			return;
		}
	}
	// make sure config file is writable
	if (!is_writeable($path_to_root . "/config_db.php"))
	{
		display_error(_("The configuration file ") . $path_to_root . "/config_db.php" . _(" is not writable. Change its permissions so it is, then re-run the operation."));
		return;
	}
	// rename directory to temporary name to ensure all
	// other subdirectories will have right owners even after
	// unsuccessfull removal.
	$cdir = company_path($id);
	$tmpname  = company_path('/old_'.$id);
	if (!@rename($cdir, $tmpname)) {
		display_error(_('Cannot rename subdirectory to temporary name.'));
		return;
	}
	// 'shift' company directories names
	for ($i = $id+1; $i < count($db_connections); $i++) {
		if (!rename(company_path($i), company_path($i-1))) {
			display_error(_("Cannot rename company subdirectory"));
			return;
		}
	}
	$err = remove_connection($id);
	if ($err == 0)
		display_error(_("Error removing Database: ") . $id . _(", please remove it manually"));

	if ($def_coy == $id)
		$def_coy = 0;

	$error = write_config_db();
	if ($error == -1)
		display_error(_("Cannot open the configuration file - ") . $path_to_root . "/config_db.php");
	else if ($error == -2)
		display_error(_("Cannot write to the configuration file - ") . $path_to_root . "/config_db.php");
	else if ($error == -3)
		display_error(_("The configuration file ") . $path_to_root . "/config_db.php" . _(" is not writable. Change its permissions so it is, then re-run the operation."));
	if ($error != 0) {
		@rename($tmpname, $cdir);
		return;
	}
	// finally remove renamed company directory
	@flush_dir($tmpname, true);
	if (!@rmdir($tmpname))
	{
		display_error(_("Cannot remove temporary renamed company data directory ") . $tmpname);
		return;
	}
	display_notification(_("Selected company has been deleted"));
	$Ajax->activate('_page_body');
	$Mode = 'RESET';
}

//---------------------------------------------------------------------------------------------

function display_companies()
{
	global $def_coy, $db_connections, $supported_collations;

	$coyno = user_company();

	start_table(TABLESTYLE);

	$th = array(_("Pool"), _("Database Host"), _("Database Port"), _("Database User"),
		_("Database Name"), _("Table Pref"), _("Charset"), _("Default"), "", "", "");
	table_header($th);

	$k=0;
	$conn = $db_connections;
	$n = count($conn);
	for ($i = 0; $i < $n; $i++)
	{
		if ($i == $coyno)
    		start_row("class='stockmankobg'");
    	else
    		alt_table_row_color($k);

		label_cell($conn[$i]['name']);
		label_cell($conn[$i]['host']);
		label_cell(isset($conn[$i]['port']) ? $conn[$i]['port'] : '');
		label_cell($conn[$i]['dbuser']);
		label_cell($conn[$i]['dbname']);
		label_cell($conn[$i]['tbpref']);
		label_cell(isset($conn[$i]['collation']) ? $supported_collations[$conn[$i]['collation']] : '');
		label_cell($i == $def_coy ? _("Yes") : _("No"));
	 	edit_button_cell("Edit".$i, _("Edit"));
		if ($i != $coyno)
		{
	 		delete_button_cell("Delete".$i, _("Delete"));
			submit_js_confirm("Delete".$i, 
				sprintf(_("You are about to remove company \'%s\'.\nDo you want to continue ?"), 
					$conn[$i]['name']));
	 	} else
			 label_cell('');
		echo '<td align="center" style="min-width:50px"><button type="submit" class="editbutton" name="company_index" value="'.$i.'" title="Show"><img src="../themes/default/images/invoice.gif" style="vertical-align:middle;width:12px;height:12px;border:0;"></button></td>';
		end_row();
	}

	end_table();
    display_note(_("The marked pool is the current pool which cannot be deleted."), 0, 0, "class='currentfg'");
    display_note(_("If no Admin Password is entered, the new Admin Password will be '<b>password</b>' by default "));
    display_note(_("Set Only Port value if you cannot use the default port 3306."));
}

//---------------------------------------------------------------------------------------------

function display_company_edit($selected_id)
{
	global $def_coy, $db_connections, $tb_pref_counter;

	start_table(TABLESTYLE2);

	if ($selected_id != -1)
	{
		$conn = $db_connections[$selected_id];
		$_POST['name'] = $conn['name'];
		$_POST['host']  = $conn['host'];
		$_POST['port'] = isset($conn['port']) ? $conn['port'] : '';
		$_POST['dbuser']  = $conn['dbuser'];
		$_POST['dbpassword']  = $conn['dbpassword'];
		$_POST['dbname']  = $conn['dbname'];
		$_POST['tbpref']  = $conn['tbpref'];
		$_POST['def'] = $selected_id == $def_coy;
		$_POST['dbcreate']  = false;
		$_POST['collation']  = isset($conn['collation']) ? $conn['collation'] : '';
		hidden('tbpref', $_POST['tbpref']);
		hidden('dbpassword', $_POST['dbpassword']);
	}
	else
	{
		$_POST['tbpref'] = $tb_pref_counter."_";

		// Use current settings as default
		$conn = $db_connections[user_company()];
		$_POST['name'] = '';
		$_POST['host']  = $conn['host'];
		$_POST['port']  = isset($conn['port']) ? $conn['port'] : '';
		$_POST['dbuser']  = $conn['dbuser'];
		$_POST['dbpassword']  = $conn['dbpassword'];
		$_POST['dbname']  = $conn['dbname'];
		$_POST['collation']  = isset($conn['collation']) ? $conn['collation'] : '';
		unset($_POST['def']);
	}

	text_row_ex(_("Pool"), 'name', 50);

	if ($selected_id == -1)
	{
		text_row_ex(_("Host"), 'host', 30, 60);
		text_row_ex(_("Port"), 'port', 30, 60);
		text_row_ex(_("Database User"), 'dbuser', 30);
		text_row_ex(_("Database Password"), 'dbpassword', 30);
		text_row_ex(_("Database Name"), 'dbname', 30);
		collations_list_row(_("Database Collation:"), 'collation');
		yesno_list_row(_("Table Pref"), 'tbpref', 1, $_POST['tbpref'], _("None"), false);
		check_row(_("Default Pool"), 'def');
		coa_list_row(_("Database Script"), 'coa');
		text_row_ex(_("New script Admin Password"), 'admpassword', 20);
	} else {
		label_row(_("Host"), $_POST['host']);
		label_row(_("Port"), $_POST['port']);
		label_row(_("Database User"), $_POST['dbuser']);
		label_row(_("Database Name"), $_POST['dbname']);
		collations_list_row(_("Database Collation:"), 'collation');
		label_row(_("Table Pref"), $_POST['tbpref']);
		if (!get_post('def'))
			check_row(_("Default Pool"), 'def');
		else
			label_row(_("Default Pool"), _("Yes"));
	}

	end_table(1);
}

//---------------------------------------------------------------------------------------------

if (($Mode=='ADD_ITEM' || $Mode=='UPDATE_ITEM') && check_data($selected_id))
	handle_submit($selected_id);

if ($Mode == 'Delete')
	handle_delete($selected_id);

if ($Mode == 'RESET')
{
	$selected_id = -1;
	unset($_POST);
}

/*
	pool type section.
*/
//---------------------------------------------------------------------------------------------

function display_pool_type()
{
	$selectedPoolType_id = 10;

	start_table(TABLESTYLE);

	$th = array(_("No"), _("Pool Type"), "Edit", "Delete", "Show");
	table_header($th);

	$k=0;
	
	// Begin getting Pool Type list from db
	$sql = "SELECT * FROM 00_pool_type";	
	$result = db_query($sql, "could not get Pool Type");
	$poolType_list = db_fetch_assoc($result);

	// $poolType_list = [
	// 	[
	// 		'id'=>1,
	// 		'pool_type'=>'Equipment'
	// 	],
	// 	[
	// 		'id'=>2,
	// 		'pool_type'=>'Manufacturing'
	// 	],
	// 	[
	// 		'id'=>3,
	// 		'pool_type'=>'tables'
	// 	],
	// 	[
	// 		'id'=>4,
	// 		'pool_type'=>'chairs'
	// 	]
	// ];
	// End getting Pool Type list from db

	while($poolType_row = db_fetch_assoc($result)) {
        if ($poolType_row['id'] == $selectedPoolType_id)
    		start_row("class='stockmankobg'");
    	else
    		alt_table_row_color($k);

		label_cell($i + 1);
		label_cell($poolType_row['pool_type']);
		echo '<td align="center" ><button type="submit" class="editbutton" name="edit_pool_type_id" value="'.$poolType_row['id'].'" title="Edit"><img src="../themes/default/images/edit.gif" style="vertical-align:middle;width:12px;height:12px;border:0;"></button></td>';
		echo '<td align="center" ><button type="submit" class="editbutton" name="delete_pool_type_id" value="'.$poolType_row['id'].'" title="Show"><img src="../themes/default/images/delete.gif" style="vertical-align:middle;width:12px;height:12px;border:0;"></button></td>';
		echo '<td align="center" style="min-width:50px"><button type="submit" class="editbutton" name="pool_type_show" value="'.$poolType_row['id'].'" title="Show"><img src="../themes/default/images/invoice.gif" style="vertical-align:middle;width:12px;height:12px;border:0;"></button></td>';
		end_row();
    }

	end_table();
    
}

//---------------------------------------------------------------------------------------------

function display_pool_type_edit($edit_pool_type_id)
{
	start_table(TABLESTYLE2);

	if (!isset($edit_pool_type_id))
	{	
		$_POST['add_pool_type'] = "";
		text_row_ex(_("Pool Type :"), 'add_pool_type', 20, 20);	
	}
	else
	{
		$sql = "SELECT pool_type FROM 00_pool_type WHERE id=".db_escape($edit_pool_type_id);
		$result = db_query($sql, "could not get pool type $id");
		$selected_pool_type_row = db_fetch($result);

		$_POST['add_pool_type'] = "";
		$_POST['update_pool_type'] = "";
		if ($selected_pool_type_row) {
			text_row_ex(_("Pool Type :"), 'update_pool_type', 20, 20, null, $selected_pool_type_row['pool_type']);
			hidden('update_pool_type_id', $edit_pool_type_id);
		} else {
			text_row_ex(_("Pool Type :"), 'add_pool_type', 20, 20);
		}

	}
	end_table(1);
	
	echo'<br>';
	if (!isset($edit_pool_type_id))
	{
		echo '<center><button class="inputsubmit" type="submit"  name="" value=""><img src="../themes/default/images/ok.gif" height="12" alt=""><span>Add new</span></button>
		</center>';
	}
	else
	{
		echo '<center><button class="inputsubmit" type="submit"  name="" id="" value="" title="Submit changes"><img src="../themes/default/images/ok.gif" height="12" alt=""><span>Update</span></button>
		<button class="ajaxsubmit" type="submit" name="" value="" title="Cancel edition"><img src="../themes/default/images/escape.png" height="12" alt=""><span>Cancel</span></button>
		</center>';

	}
	
}

//---------------------------------------------------------------------------------------------

if ( isset($_POST['add_pool_type']) && trim($_POST['add_pool_type'])!=''  ) {
	$sql = "INSERT 00_pool_type (pool_type) VALUES (".db_escape($_POST['add_pool_type']).")";
	db_query($sql, "could not add pool type");
}

if ( isset($_POST['update_pool_type']) && trim($_POST['update_pool_type'])!=''  ) {

	if (isset($_POST['update_pool_type_id'])) {
		$sql = "UPDATE 00_pool_type SET pool_type=".db_escape($_POST['update_pool_type'])." WHERE id=".$_POST['update_pool_type_id'];
		db_query($sql, "could not update pool type for ".$_POST['update_pool_type_id']);
	}
}

if ( isset($_POST['delete_pool_type_id']) ) {
	$sql = "DELETE FROM 00_pool_type WHERE id=".$_POST['delete_pool_type_id'];
	db_query($sql, "could not delete pool type for ".$_POST['delete_pool_type_id']);
}



//---------------------------------------------------------------------------------------------
	
echo "<link href='$path_to_root/themes/default/default.css' rel='stylesheet' type='text/css'> \n";
//send_scripts();
echo "<body>\n";
	echo '<table class="callout_main" border="0" cellpadding="0" cellspacing="0"><tbody><tr><td colspan="2" rowspan="2">';

		echo '<table class="main_page" border="0" cellpadding="0" cellspacing="0"><tbody><tr><td>';
			echo '<table width="100%" border="0" cellpadding="0" cellspacing="0">
					<tbody>
						<tr>
							<td class="quick_menu">
								<table cellpadding="0" cellspacing="0" width="100%">
									<tbody><tr><td>
										<div class="tabs" style="height:18.4px">
											<a class="menu_tab" href="../index.php">ERP</a>
											<a class="selected" href="./member.php">Cooperative</a>
										</div>
									</td></tr></tbody>
                                </table>
                                <table cellpadding="0" cellspacing="0" width="100%">
									<tbody><tr><td>
										<div class="tabs" style="height:18.4px">
											<a class="menu_tab" href="./member.php">Member</a>
                                            <a class="menu_tab" href="./funding.php">Funding</a>
                                            <a class="menu_tab" href="./income.php">Income Split</a>
                                            <a class="menu_tab" href="./expense.php">Expenses Split</a>
                                            <a class="selected" href="./agreement.php">Agreements</a>
										</div>
									</td></tr></tbody>
								</table>
								<table class="logoutBar">
									<tbody>
										<tr>
											<td class="logoutBarRight"><img id="ajaxmark" src="../themes/default/images/progressbar.gif" align="center" style="visibility: hidden;" alt="ajaxmark"></td>
											<td class="logoutBarRight">
                                                <a class="shortcut" href="../admin/change_current_user_password.php?selected_id=admin"><img src="../themes/default/images/lock.gif" style="width:14px;height:14px;border:0;vertical-align:middle;" alt="Change Password">&nbsp;&nbsp;Change password</a>&nbsp;&nbsp;&nbsp;
                                                <a class="shortcut" href="../pool/pool-setup.php?"><img src="../themes/default/images/menu_system.png" style="width:14px;height:14px;border:0;vertical-align:middle;" alt="Pool Setup">&nbsp;&nbsp;Pool Setup</a>
												<a class="shortcut" href="../access/logout.php?"><img src="../themes/default/images/login.gif" style="width:14px;height:14px;border:0;vertical-align:middle;" alt="Logout">&nbsp;&nbsp;Logout</a>&nbsp;&nbsp;&nbsp;
											</td>
										</tr>
										<tr>
											<td colspan="3">
											</td>
										</tr>
									</tbody>
								</table>
							</td>
						</tr>
					</tbody>
				</table>';
			echo "<Center><table class='titletext'><tr><td>Agreements</td></tr></table></Center>\n";
			echo '<div id="msgbox"></div>';
			div_start('_page_body');
				br();br();
				
				

				br();br();
			div_end();
		echo '</td></tr></tbody></table>';

		echo "<table class='bottomBar'>\n";
			echo "<tr>";
				if (isset($_SESSION['wa_current_user'])) 
					$date = Today() . " | " . Now();
				else	
					$date = date("m/d/Y") . " | " . date("h.i am");
				echo "<td class='bottomBarCell'>$date</td>\n";
		echo "</tr></table>\n";

	echo '</td></tr></tbody></table>';
	
	echo "<table align='center' id='footer'>\n";
		echo "<tr>\n";
			echo "<td align='center' class='footer'><a target='_blank' href='".$SysPrefs->power_url."' tabindex='-1'><font color='#ffffff'>".$SysPrefs->app_title
				." $version - " . _("Theme:") . " " . user_theme() . " - ".show_users_online()."</font></a></td>\n";
			echo "</tr>\n";
		echo "<tr>\n";
			echo "<td align='center' class='footer'><a target='_blank' href='".$SysPrefs->power_url
				."' tabindex='-1'><font color='#ffff00'>".$SysPrefs->power_by."</font></a></td>\n";
		echo "</tr>\n";
	echo "</table><br><br>\n";
echo "</body></html>\n";

