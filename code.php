<?php	##################
	#
	#	rah_tabtor-plugin for Textpattern
	#	version 0.1
	#	by Jukka Svahn
	#	http://rahforum.biz
	#
	###################

	if(@txpinterface == 'admin') {
		add_privs('rah_tabtor','1');
		register_tab('extensions','rah_tabtor','Tabtor');
		register_callback('rah_tabtor','rah_tabtor');
		register_callback('rah_tabtor_head','admin_side','head_end');
		rah_tabtor_register();
	}

/**
	Installer
*/

	function rah_tabtor_install() {
		safe_query(
			"CREATE TABLE IF NOT EXISTS ".safe_pfx('rah_tabtor')." (
				`id` INT(11) NOT NULL auto_increment,
				`tabgroup` VARCHAR(255) NOT NULL,
				`page` VARCHAR(255) NOT NULL,
				`label` VARCHAR(255) NOT NULL,
				`position` INT(2) NOT NULL default 1,
				PRIMARY KEY(`id`)
			)"
		);
		safe_query(
			"CREATE TABLE IF NOT EXISTS ".safe_pfx('rah_tabtor_prefs')." (
				`name` VARCHAR(64) NOT NULL,
				`value` TEXT NOT NULL,
				PRIMARY KEY(`name`)
			)"
		);
		
		/*
			Add some stuff to the prefs
			table
		*/
		
		rah_tabtor_add_prefs(
			array(
				'advanced_editor' => 0,
			)
		);
	}

/**
	Registers the tabs
*/

	function rah_tabtor_register() {
		
		@$rs = 
			safe_rows(
				'tabgroup,page,label',
				'rah_tabtor',
				'1=1 order by position asc'
			);
		
		if(!$rs) 
			return;
		
		foreach($rs as $a)
			register_tab($a['tabgroup'],$a['page'],gTxt($a['label']));
	}

/**
	Delivers panes
*/

	function rah_tabtor() {
		require_privs('rah_tabtor');
		rah_tabtor_install();
		global $step;
		if(in_array($step,array(
			'rah_tabtor_edit',
			'rah_tabtor_prefs',
			'rah_tabtor_prefs_save',
			'rah_tabtor_save',
			'rah_tabtor_delete'
		))) $step();
		else rah_tabtor_list();
	}

/**
	The main pane
*/

	function rah_tabtor_list($message='') {
		
		global $event;
		
		$out[] = 
			
			'	<table cellspacing="0" cellpadding="0" id="list">'.n.
			'		<tr>'.n.
			'			<th>#ID</th>'.n.
			'			<th>Label</th>'.n.
			'			<th>Event</th>'.n.
			'			<th>Group</th>'.n.
			'			<th>&#160;</th>'.n.
			'		</tr>'.n;
		
		$rs = 
			safe_rows(
				'*',
				'rah_tabtor',
				'1=1'
			);
			
		if($rs) {
		
			foreach($rs as $a)
				$out[] = 
					'		<tr>'.n.
					'			<td><a href="?event='.$event.'&amp;step=rah_tabtor_edit&amp;id='.$a['id'].'">'.$a['id'].'</a></td>'.n.
					'			<td><a href="?event='.$event.'&amp;step=rah_tabtor_edit&amp;id='.$a['id'].'">'.htmlspecialchars($a['label']).'</a></td>'.n.
					'			<td>'.htmlspecialchars($a['page']).'</td>'.n.
					'			<td>'.htmlspecialchars($a['tabgroup']).'</td>'.n.
					'			<td><input type="checkbox" name="selected[]" value="'.$a['id'].'" /></td>'.n.
					'		</tr>'.n;
		
		} else 
			$out[] =
				'		<tr>'.n.
				'			<td colspan="5">Nothing to show yet. Start by <a href="?event='.$event.'&amp;step=rah_tabtor_edit">creating your first rule</a>.</td>'.n.
				'		</tr>'.n;
		
		
		$out[] = 
			'	</table>'.n.
			'	<p id="rah_tabtor_step">'.n.
			'		<select name="step">'.n.
			'			<option value="">With selected...</option>'.n.
			'			<option value="rah_tabtor_delete">Delete</option>'.n.
			'		</select>'.n.
			'		<input type="submit" class="smallerbox" value="Go" />'.n.
			'	</p>'.n;
		
		rah_tabtor_header($out,'rah_tabtor',$message);
		
	}

/**
	The editor pane
*/

	function rah_tabtor_edit($message='') {
		
		
		extract(
			gpsa(
				array(
					'label',
					'page',
					'tabgroup',
					'id',
					'position'
				)
			)
		);
		
		extract(
			rah_tabtor_do_prefs()
		);
		
		/*
			If editing, not creating new,
			we need the existing information
		*/
		
		if($id && !ps('id')) {
			
			$rs = 
				safe_row(
					'*',
					'rah_tabtor',
					"id='".doSlash($id)."'"
				);
			
			if(!$rs) {
				rah_tabtor_list('Item doesn\'t exists.');
				return;
			}
			
			extract($rs);
			
		}
		
		$tabs = 
			rah_tabtor_events();
		
		$out[] = 
		
			'	<input type="hidden" name="step" value="rah_tabtor_save" />'.n.
			($id ? '	<input type="hidden" name="id" value="'.$id.'" />'.n : '').
			
			'	<p>'.n.
			'		<label>'.n.
			'			Label:<br />'.n.
			'			<input class="edit" type="text" name="label" value="'.htmlspecialchars($label).'" />'.n.
			'		</label>'.n.
			'	</p>'.n.
			
			'	<p>'.n.
			'		<label>'.n.
			'			Event:<br />'.n;
		
		/*
			Show easy dropdown if possible,
			otherwise plain text field
		*/
		
		if($tabs !== false && $advanced_editor == 0 && (empty($page) || isset($tabs['events'][$page]))) {
			
			
			
			$out[] =
		
				'			<select name="page" class="rah_tabtor_select">'.n.
				'				<option value="">Select...</option>'.n;
			
			foreach($tabs['events'] as $key => $val)
				$out[] = 
					'				<option value="'.htmlspecialchars($key).'"'.(($page == $key) ? ' selected="selected"' : '').'>'.($val ? $val : $key).'</option>';
				
				
			$out[] =
				'			</select>'.n;
		
		} else 
			$out[] =
				'			<input type="text" name="page" class="edit" value="'.htmlspecialchars($page).'" />'.n;
		
		$out[] =
			
			'		</label>'.n.
			'	</p>'.n.
			
			'	<p>'.n.
			'		<label>'.n.
			'			Group:<br />'.n;
		
		/*
			You don't like advanced? Understood.
			Let's see what we can do...
		*/
		
		if($tabs !== false && $advanced_editor == 0 && (empty($tabgroup) || isset($tabs['groups'][$tabgroup]))) {
			
			$out[] =
				'			<select name="tabgroup" class="rah_tabtor_select">'.n.
				'				<option value="">Select...</option>'.n;
		
			foreach($tabs['groups'] as $key => $val) 
				
				$out[] = 
					'				<option value="'.$key.'"'.(($tabgroup == $key) ? ' selected="selected"' : '').'>'.($val ? $val : $key).'</option>';
			
			
			$out[] = 
				
				'			</select>'.n;
			
		} else 
			$out[] =
				'			<input type="text" name="tabgroup" class="edit" value="'.htmlspecialchars($tabgroup).'" />'.n;
		
		$out[] =
			
			'		</label>'.n.
			'	</p>'.n.
			
			'	<p>'.n.
			'		<label>'.n.
			'			Position:<br />'.n.
			'			<select name="position" class="rah_tabtor_select">'.n;
		
		for($i=1;$i<10;$i++)
			$out[] = 
				'				<option value="'.htmlspecialchars($i).'"'.(($position == $i) ? ' selected="selected"' : '').'>'.$i.'</option>';
		
		$out[] =
			'			</select>'.n.
			'		</label>'.n.
			'	</p>'.n.
			
			'	<p>'.n.
			'		<input type="submit" value="Save" class="publish" />'.n.
			'	</p>'.n
		;
		
		rah_tabtor_header($out,'rah_tabtor',$message);
	}

/**
	Does the dirty saving work;
	"They call me Data".
*/

	function rah_tabtor_save() {
		
		extract(
			doSlash(
				gpsa(
					array(
						'label',
						'page',
						'tabgroup',
						'id',
						'position'
					)
				)
			)
		);
		
		/*
			Fields are utterly required.
		*/
		
		if(empty($label) || empty($page) || empty($tabgroup)) {
			rah_tabtor_edit('Fields label, event and group are required.');
			return;
		}
		
		if(empty($position) || trim($position,'123456789'))
			$position = 1;
		
		/*
			If ID, we are updating
		*/
		
		if($id) {
			
			if(
				safe_count(
					'rah_tabtor',
					"id='$id'"
				) == 0
			) {
				rah_tabtor_list('Item doesn\'t exists.');
				return;
			}
			
			safe_update(
				'rah_tabtor',
				"label='$label',
				page='$page',
				tabgroup='$tabgroup',
				position='$position'",
				"id='$id'"
			);
			
			rah_tabtor_list('Updated.');
			return;
		}
		
		/*
			We are adding
		*/
		
		if(
			safe_count(
				'rah_tabtor',
				"label='$label' and 
				page='$page' and 
				tabgroup='$tabgroup' and 
				position='$position'"
			) == 0
		) {
			safe_insert(
				'rah_tabtor',
				"label='$label',
				page='$page',
				tabgroup='$tabgroup',
				position='$position'"
			);
			
			/*
				Hey, TXP we have new temp tab.
			*/
			
			register_tab($tabgroup,$page,gTxt($label));
		}
		rah_tabtor_list('Item saved.');
	}

/**
	Preferences pane
*/

	function rah_tabtor_prefs($message=''){
		
		extract(
			rah_tabtor_do_prefs()
		);
		
		$out = 
			
			'<input type="hidden" name="step" value="rah_tabtor_prefs_save" />'.n.
			
			'<p>'.n.
			'	<label>'.n.
			'		<strong>Use advanced editor?</strong> Gives you ability to freely define events and groups, but removes the helpful dropdown fields from the editor.<br />'.n.
			'		<select name="advanced_editor" class="rah_tabtor_select">'.n.
			'			<option value="0"'.(($advanced_editor == 0) ? ' selected="selected"' : '').'>No</option>'.n.
			'			<option value="1"'.(($advanced_editor == 1) ? ' selected="selected"' : '').'>Yes</option>'.n.
			'		</select>'.n.
			'	</label>'.n.
			'</p>'.n.
			
			'<p>'.n.
			'	<input type="submit" class="publish" value="Save" />'.n.
			'</p>'.n;
		
		
		rah_tabtor_header($out,'rah_tabtor',$message);
		
	}

/**
	Save preferences
*/
	
	function rah_tabtor_prefs_save(){
		
		
		$prefs = rah_tabtor_do_prefs();
		
		foreach($prefs as $pref => $value) {
			safe_update(
				'rah_tabtor_prefs',
				"value='".doSlash(ps($pref))."'",
				"name='".doSlash($pref)."'"
			);
		}
		
		rah_tabtor_prefs('Preferences saved.');
		
	}

/**
	Delete selected items
*/

	function rah_tabtor_delete() {
		
		$selected = ps('selected');
		
		if(!is_array($selected)) {
			rah_tabtor_list('Nothing selected.');
			return;
		}
		
		foreach($selected as $id)
			$ids[] = "'".doSlash($id)."'";
		
		if(!isset($ids)) {
			rah_tabtor_list('Something gone wrong.');
			return;
		}
		
		safe_delete(
			'rah_tabtor',
			'id in('.implode(',',$ids).')'
		);
		
		rah_tabtor_list('Selected items removed.');
	}

/**
	Outputter
*/

	function rah_tabtor_header($out,$pagetop,$message,$title='Exchange navigation links between tabs') {
		
		global $event;
		
		pagetop($pagetop,$message);
		
		if(is_array($out))
			$out = implode('',$out);
		
		echo 
			n.
			'<form method="post" action="index.php" id="rah_tabtor_container">'.n.
			'	<input type="hidden" name="event" value="'.$event.'" />'.n.
			'	<h1><strong>rah_tabtor</strong> | '.$title.'</h1>'.n.
			'	<p>'.
				'&#187; <a href="?event='.$event.'">Main</a> '.
				'&#187; <strong><a href="?event='.$event.'&amp;step=rah_tabtor_edit">Create a new rule</a></strong> '.
				'&#187; <a href="?event='.$event.'&amp;step=rah_tabtor_prefs">Preferences</a> '.
				'&#187; <a href="?event=plugin&amp;step=plugin_help&amp;name=rah_tabtor">Documentation</a>'.
			'</p>'.n.
			
			$out.n.
			
			'</form>'.n;
		
	}

/**
	Adds styles to <head>
*/
	
	function rah_tabtor_head() {
		global $event;
		
		if($event != 'rah_tabtor')
			return;
		
		echo 
			<<<EOF
			<script type="text/javascript">
				$(document).ready(function(){
					$('#rah_tabtor_step').hide();
					$('#rah_tabtor_container input[type=checkbox]').click(function(){
						if($('#rah_tabtor_container input[type=checkbox]:checked').val() != null) {
							$('#rah_tabtor_step').slideDown();
						} else {
							$('#rah_tabtor_step').slideUp();
						}
					});
				});
			</script>
			<style type="text/css">
				#rah_tabtor_container {
					width: 950px;
					margin: 0 auto;
				}
				#rah_tabtor_container table {
					width: 100%;
				}
				#rah_tabtor_container #rah_tabtor_step {
					text-align: right;
				}
				#rah_tabtor_container input.edit {
					width: 940px;
				}
				#rah_tabtor_container .rah_tabtor_select {
					width: 640px;
				}
			</style>

EOF;
	}

/**
	Lists events and tab groups
*/

	function rah_tabtor_events() {
	
		/*
			Someone called us before areas() was defined.
			Fallback to the advanced editor.
		*/
		
		if(!function_exists('areas') || !is_array(areas()))
			return false;
		
		$out = array();
		
		foreach(areas() as $key => $group) {
			$out['groups'][$key] = gTxt('tab_'.$key);
			foreach ($group as $title => $name) 
				$out['events'][$name] = $title;
		}
		
		/*
			These events are all over the place.
			Let's do some cleaning.
		*/
		
		$out['events'] = array_unique($out['events']);
		asort($out['events']);
		
		return $out;
	}

/**
	Adds preferences array to database
*/

	function rah_tabtor_add_prefs($array) {
		foreach($array as $name => $value) {
			if(
				safe_count(
					'rah_tabtor_prefs',
					"name='".doSlash($name)."'"
				) == 0
			)
				safe_insert(
					'rah_tabtor_prefs',
					"name='".doSlash($name)."',
					value='".doSlash($value)."'"
				);
		}
	}

/**
	Fetch preferences to usable format from the database
*/

	function rah_tabtor_do_prefs() {
		
		$rs = 
			safe_rows(
				'name,value',
				'rah_tabtor_prefs',
				'1=1'
			);
			
		if(!$rs)
			return;
		
		foreach($rs as $a) 
			$out[$a['name']] = $a['value'];
		
		return $out;
	}