<?php
/* $Id$ */
/*
	unbound_acls.php
	part of pfSense (https://www.pfsense.org/)

	Copyright (C) 2011 Warren Baker <warren@decoy.co.za>
	All rights reserved.

	Redistribution and use in source and binary forms, with or without
	modification, are permitted provided that the following conditions are met:

	1. Redistributions of source code must retain the above copyright notice,
	   this list of conditions and the following disclaimer.

	2. Redistributions in binary form must reproduce the above copyright
	   notice, this list of conditions and the following disclaimer in the
	   documentation and/or other materials provided with the distribution.

	THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
	INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
	AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
	AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
	OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
	SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
	INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
	CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
	POSSIBILITY OF SUCH DAMAGE.
*/

require("guiconfig.inc");
require("unbound.inc");

if (!is_array($config['installedpackages']['unboundacls']['config']))
	$config['installedpackages']['unboundacls']['config'] = array();

$a_acls = &$config['installedpackages']['unboundacls']['config'];

$id = $_GET['id'];
if (isset($_POST['aclid']))
	$id = $_POST['aclid'];

$act = $_GET['act'];
if (isset($_POST['act']))
	$act = $_POST['act'];

if ($act == "del") {
	if (!$a_acls[$id]) {
		pfSenseHeader("unbound_acls.php");
		exit;
	}

	unset($a_acls[$id]);
	write_config();
	unbound_reconfigure();
	$savemsg = gettext("Access List successfully deleted")."<br/>";
}

if ($act == "new") {
	$id = unbound_get_next_id();
}

if ($act == "edit") {
	if (isset($id) && $a_acls[$id]) {
		$pconfig = $a_acls[$id];
		$networkacl = $a_acls[$id]['row'];
	}
}

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation - only allow 50 entries in a single ACL*/
	for($x=0; $x<50; $x++) {
		if(isset($pconfig["acl_network{$x}"])) {
			$networkacl[$x] = array();
			$networkacl[$x]['acl_network'] = $pconfig["acl_network{$x}"];
			$networkacl[$x]['mask'] = $pconfig["mask{$x}"];
			$networkacl[$x]['description'] = $pconfig["description{$x}"];
			if (!is_ipaddr($networkacl[$x]['acl_network']))
				$input_errors[] = gettext("You must enter a valid network IP address for {$networkacl[$x]['acl_network']}.");

			if (is_ipaddr($networkacl[$x]['acl_network'])) {
				if (!is_subnet($networkacl[$x]['acl_network']."/".$networkacl[$x]['mask']))
					$input_errors[] = gettext("You must enter a valid IPv4 netmask for {$networkacl[$x]['acl_network']}/{$networkacl[$x]['mask']}.");
			} else if (function_exists("is_ipaddrv6")) {
				if (!is_ipaddrv6($networkacl[$x]['acl_network']))
					$input_errors[] = gettext("You must enter a valid IPv6 address for {$networkacl[$x]['acl_network']}.");
				else if (!is_subnetv6($networkacl[$x]['acl_network']."/".$networkacl[$x]['mask']))
					$input_errors[] = gettext("You must enter a valid IPv6 netmask for {$networkacl[$x]['acl_network']}/{$networkacl[$x]['mask']}.");
			} else
				$input_errors[] = gettext("You must enter a valid IPv4 address for {$networkacl[$x]['acl_network']}.");
		}
	}

	if (!$input_errors) {

		if ($pconfig['Submit'] == gettext("Save")) {
			if(!$a_acls[$id])
				$a_acls[$id]['aclid'] = $id;

			if (isset($id) && $a_acls[$id]) {
				$a_acls[$id]['aclid'] = $pconfig['aclid'];
				$a_acls[$id]['aclname'] = $pconfig['aclname'];
				$a_acls[$id]['aclaction'] = $pconfig['aclaction'];
				$a_acls[$id]['description'] = $pconfig['description'];
				$a_acls[$id]['row'] = array();
				foreach ($networkacl as $acl)
					$a_acls[$id]['row'][] = $acl;
				write_config();
				mark_subsystem_dirty("unbound");
				//unbound_reconfigure();
			}
			pfSenseHeader("/unbound_acls.php");
			exit;
		}

		if ($pconfig['apply']) {
				clear_subsystem_dirty("unbound");
				$retval = 0;
				$retval = unbound_reconfigure();
				$savemsg = get_std_save_message($retval);
		}
	}
}


$pgtitle = "Services: Unbound DNS Forwarder: Access Lists";
include("head.inc");

?>

<script type="text/javascript" src="/javascript/row_helper.js">
</script>

<script type="text/javascript">
	function mask_field(fieldname, fieldsize, n) {
		return '<select name="' + fieldname + n + '" class="formselect" id="' + fieldname + n + '"><?php
			$start = 24; if (function_exists("is_ipaddrv6")) $start = "128";
			for ($i = $start; $i >= 0; $i--) {
					echo "<option value=\"$i\">$i</option>";
			}
		?></select>';
	}

	rowtype[0] = "textbox";
	rowname[0] = "acl_network";
	rowsize[0] = "30";
	rowname[1] = "mask";
	rowtype[1] = mask_field;
	rowtype[2] = "textbox";
	rowname[2] = "description";
	rowsize[2] = "40";
</script>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">

<?php include("fbegin.inc"); ?>
<form action="unbound_acls.php" method="post" name="iform" id="iform">
<?php
if (!$savemsg)
	$savemsg = "";

if ($input_errors)
	print_input_errors($input_errors);

if ($savemsg)
	print_info_box($savemsg);

if (is_subsystem_dirty("unbound"))
		print_info_box_np(gettext("The settings for Unbound DNS has changed. You must apply the configuration to take affect."));
?>
<table width="100%" border="0" cellpadding="0" cellspacing="0">
	<tr>
		<td class="tabnavtbl">
			<ul id="tabnav">
			<?php
				$tab_array = array();
				$tab_array[] = array(gettext("Unbound DNS Settings"), false, "/pkg_edit.php?xml=unbound.xml&amp;id=0");
				$tab_array[] = array(gettext("Unbound DNS Advanced Settings"), false, "/pkg_edit.php?xml=unbound_advanced.xml&amp;id=0");
				$tab_array[] = array(gettext("Unbound DNS ACLs"), true, "/unbound_acls.php");
				$tab_array[] = array(gettext("Unbound DNS Status"), false, "/unbound_status.php");
				display_top_tabs($tab_array, true);
			?>
			</ul>
		</td>
	</tr>
	<tr>
		<td class="tabcont">

			<?php if($act=="new" || $act=="edit"): ?>


			<input name="aclid" type="hidden" value="<?=$id;?>">
			<input name="act" type="hidden" value="<?=$act;?>">

				<table width="100%" border="0" cellpadding="6" cellspacing="0">
					<tr>
						<td colspan="2" valign="top" class="listtopic"><?=sprintf(gettext("%s ACL"),$act);?></td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncellreq"><?=gettext("ACL name");?></td>
						<td width="78%" class="vtable">
							<input name="aclname" type="text" class="formfld" id="aclname" size="30" maxlength="30" value="<?=htmlspecialchars($pconfig['aclname']);?>">
							<br />
							<span class="vexpl"><?=gettext("Provide an ACL name.");?></span>
						</td>
					</tr>
					<tr>
						<td width="22%" valign="top" class="vncellreq"><?=gettext("Action");?></td>
						<td width="78%" class="vtable">
							<select name="aclaction" class="formselect">
								<?php $types = explode(",", "Allow,Deny,Refuse,Allow Snoop"); foreach ($types as $type): ?>
								<option value="<?=strtolower($type);?>" <?php if (strtolower($type) == strtolower($pconfig['aclaction'])) echo "selected"; ?>>
								<?=htmlspecialchars($type);?>
								</option>
								<?php endforeach; ?>
							</select>
							<br/>
							<span class="vexpl">
								<?=gettext("Choose what to do with DNS requests that match the criteria specified below.");?> <br/>
								<?=gettext("<b>Deny:</b> This action stops queries from hosts within the netblock defined below.");?> <br/>
								<?=gettext("<b>Refuse:</b> This action also stops queries from hosts within the netblock defined below, but sends a DNS rcode REFUSED error message back to the client.");?> <br/>
								<?=gettext("<b>Allow:</b> This action allows queries from hosts within the netblock defined below.");?> <br/>
								<?=gettext("<b>Allow Snoop:</b> This action allows recursive and nonrecursive access from hosts within the netblock defined below. Used for cache snooping and ideally should only be configured for your administrative host.");?> <br/>
							</span>
						</td>
					</tr>
					<tr>
					<td width="22%" valign="top" class="vncellreq"><?=gettext("Networks");?></td>
					<td width="78%" class="vtable">
						<table id="maintable">
							<tbody>
								<tr>
									<td><div id="onecolumn"><?=gettext("Network");?></div></td>
									<td><div id="twocolumn"><?=gettext("CIDR");?></div></td>
									<td><div id="threecolumn"><?=gettext("Description");?></div></td>
								</tr>
								<?php $counter = 0; ?>
								<?php
									if($networkacl)
										foreach($networkacl as $item):
								?>
										<?php
											$network = $item['acl_network'];
											$cidr = $item['mask'];
											$description = $item['description'];
										?>
								<tr>
									<td>
										<input autocomplete="off" name="acl_network<?=$counter;?>" type="text" class="formfld unknown" id="acl_network<?=$counter;?>" size="40" value="<?=htmlspecialchars($network);?>" />
									</td>
									<td>
										<select name="mask<?=$counter;?>" class="formselect" id="mask<?=$counter;?>">
										<?php
											for ($i = 128; $i > 0; $i--) {
												echo "<option value=\"$i\" ";
												if ($i == $cidr) echo "selected";
												echo ">" . $i . "</option>";
											}
										?>
										</select>
									</td>
									<td>
										<input autocomplete="off" name="description<?=$counter;?>" type="text" class="listbg" id="description<?=$counter;?>" size="40" value="<?=htmlspecialchars($description);?>" />
									</td>
									<td>
										<a onclick="removeRow(this); return false;" href="#"><img border="0" src="/themes/<?=$g['theme'];?>/images/icons/icon_x.gif" /></a>
									</td>
								</tr>
								<?php $counter++; ?>
								<?php endforeach; ?>
							</tbody>
							<tfoot>
							</tfoot>
						</table>
						<a onclick="javascript:addRowTo('maintable', 'formfldalias'); return false;" href="#">
							<img border="0" src="/themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" alt="" title="<?=gettext("add another entry");?>" />
						</a>
						<script type="text/javascript">
							field_counter_js = 3;
							rows = 1;
							totalrows = <?php echo $counter; ?>;
							loaded = <?php echo $counter; ?>;
						</script>

						</td>
					</tr>

					<tr>
						<td width="22%" valign="top" class="vncell"><?=gettext("Description");?></td>
						<td width="78%" class="vtable">
							<input name="description" type="text" class="formfld unknown" id="description" size="52" maxlength="52" value="<?=htmlspecialchars($pconfig['description']);?>">
							<br />
							<span class="vexpl"><?=gettext("You may enter a description here for your reference.");?></span>
						</td>
					</tr>
					<tr>
						<td>&nbsp;</td>
					</tr>
					<tr>
						<td width="22%" valign="top">&nbsp;</td>
						<td width="78%">
							&nbsp;<br>&nbsp;
							<input name="Submit" type="submit" class="formbtn" value="<?=gettext("Save"); ?>">  <input type="button" class="formbtn" value="<?=gettext("Cancel"); ?>" onclick="history.back()">
						</td>
					</tr>
				</table>
			</form>

			<?php else: ?>

			<table class="sortable" width="100%" border="0" cellpadding="0" cellspacing="0">
				<thead>
				<tr>
					<td width="25%" class="listhdrr"><?=gettext("Access List Name"); ?></td>
					<td width="25%" class="listhdrr"><?=gettext("Action"); ?></td>
					<td width="40%" class="listhdrr"><?=gettext("Description"); ?></td>
					<td width="10%" class="list"></td>
				</tr>
				</thead>
				<tbody>
				<?php
					$i = 0;
					foreach($a_acls as $acl):
				?>
				<tr ondblclick="document.location='unbound_acls.php?act=edit&id=<?=$i;?>'">
					<td class="listlr">
						<?=$acl['aclname'];?>
					</td>
					<td class="listr">
						<?=htmlspecialchars($acl['aclaction']);?>
					</td>
					<td class="listbg">
						<?=htmlspecialchars($acl['description']);?>
					</td>
					<td valign="middle" nowrap class="list">
						<a href="unbound_acls.php?act=edit&id=<?=$i;?>">
							<img src="./themes/<?=$g['theme'];?>/images/icons/icon_e.gif" title="<?=gettext("edit client"); ?>" width="17" height="17" border="0">
						</a>
						&nbsp;
						<a href="unbound_acls.php?act=del&id=<?=$i;?>" onclick="return confirm('<?=gettext("Do you really want to delete this client?"); ?>')">
							<img src="/themes/<?=$g['theme'];?>/images/icons/icon_x.gif" title="<?=gettext("delete client"); ?>" width="17" height="17" border="0">
						</a>
					</td>
				</tr>
				<?php
					$i++;
					endforeach;
				?>
				</tbody>
				<tfoot>
				<tr>
					<td class="list" colspan="4"></td>
					<td class="list">
						<a href="unbound_acls.php?act=new"><img src="./themes/<?=$g['theme'];?>/images/icons/icon_plus.gif" title="<?=gettext("Add new ACL"); ?>" width="17" height="17" border="0">
						</a>
					</td>
				</tr>
				<tr>
					<td colspan="4">
						<p>
							<?=gettext("Access Lists to control access to Unbound can be defined here.");?>
						</p>
					</td>
				</tr>
				</tfoot>
			</table>

			<?php endif; ?>

		</td>
	</tr>
</table>
</body>
<?php include("fend.inc"); ?>
