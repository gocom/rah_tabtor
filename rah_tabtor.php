<?php

/**
 * Rah_tabtor plugin for Textpattern CMS.
 *
 * @author Jukka Svahn
 * @date 2010-
 * @license GNU GPLv2
 * @link http://rahforum.biz/plugins/rah_tabtor
 * 
 * Copyright (C) 2011 Jukka Svahn <http://rahforum.biz>
 * Licensed under GNU Genral Public License version 2
 * http://www.gnu.org/licenses/gpl-2.0.html
 */

	if(@txpinterface == 'admin') {
		add_privs('rah_tabtor', '1,2');
		add_privs('plugin_prefs.rah_tabtor', '1,2');
		register_tab('extensions', 'rah_tabtor', gTxt('rah_tabtor'));
		register_callback(array('rah_tabtor', 'panes'),'rah_tabtor');
		register_callback(array('rah_tabtor', 'head'), 'admin_side', 'head_end');
		register_callback(array('rah_tabtor', 'prefs'), 'plugin_prefs.rah_tabtor');
		register_callback(array('rah_tabtor', 'install'), 'plugin_lifecycle.rah_tabtor');
		rah_tabtor::register();
	}

class rah_tabtor {

	static public $version = '0.2';

	/**
	 * Does installing and uninstalling.
	 * @param string $event The admin-side event.
	 * @param string $step The admin-side / plugin-lifecycle step.
	 */

	static public function install($event='', $step='') {
		
		global $prefs;
		
		if($step == 'deleted') {
			
			@safe_query(
				'DROP TABLE IF EXISTS '.safe_pfx('rah_tabtor')
			);
			
			safe_delete(
				'txp_prefs',
				"name like 'rah\_tabtor\_%'"
			);
			
			return;
		}
		
		$current = 
			isset($prefs['rah_tabtor_version']) ?
				$prefs['rah_tabtor_version'] : 'base';
		
		if(self::$version == $current)
			return;
		
		/*
			Stores tab definitions
			
			* id: Primary key. Used for updating and deleting.
			* tabgroup: The name of the main tab group.
			* page: The page linking to.
			* label: Link label.
			* position: Sorting value.
		*/
		
		safe_query(
			"CREATE TABLE IF NOT EXISTS ".safe_pfx('rah_tabtor')." (
				`id` INT(11) NOT NULL auto_increment,
				`tabgroup` VARCHAR(255) NOT NULL,
				`page` VARCHAR(255) NOT NULL,
				`label` VARCHAR(255) NOT NULL,
				`position` INT(2) NOT NULL default 1,
				PRIMARY KEY(`id`)
			) PACK_KEYS=1 AUTO_INCREMENT=1 CHARSET=utf8"
		);
		
		if($current == 'base') {
			@safe_query(
				'DROP TABLE IF EXISTS '.safe_pfx('rah_tabtor_prefs')
			);
		}
		
		set_pref('rah_tabtor_advanced_editor', 0, 'rah_tabtor', 2, '', 0);
		set_pref('rah_tabtor_version', self::$version, 'rah_tabtor', 2, '', 0);
		$prefs['rah_tabtor_version'] = self::$version;
	}

	/**
	 * Registers the tabs
	 */

	static public function register() {
		
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
	 * Delivers panes
	 */

	static public function panes() {
		require_privs('rah_tabtor');
		self::install();
		global $step;
		
		$steps = 
			array(
				'browse' => false,
				'edit' => false,
				'save' => true,
				'delete' => true
			);
		
		if(!$step || !bouncer($step, $steps))
			$step = 'browse';
		
		$panes = new rah_tabtor();
		$panes->$step();
	}

	/**
	 * The main pane
	 * @param string $message The message shown in the page header.
	 */

	public function browse($message='') {
		
		global $event;
		
		$out[] = 
			
			'	<table cellspacing="0" cellpadding="0" id="list">'.n.
			'		<thead>'.n.
			'			<tr>'.n.
			'				<th>'.gTxt('rah_tabtor_id').'</th>'.n.
			'				<th>'.gTxt('rah_tabtor_label').'</th>'.n.
			'				<th>'.gTxt('rah_tabtor_page').'</th>'.n.
			'				<th>'.gTxt('rah_tabtor_group').'</th>'.n.
			'				<th>&#160;</th>'.n.
			'			</tr>'.n.
			'		</thead>'.n.
			'		<tbody>'.n;
		
		$rs = 
			safe_rows(
				'*',
				'rah_tabtor',
				'1=1'
			);
			
		if($rs) {
		
			foreach($rs as $a)
				$out[] = 
					'			<tr>'.n.
					'				<td><a href="?event='.$event.'&amp;step=edit&amp;id='.$a['id'].'">'.$a['id'].'</a></td>'.n.
					'				<td><a href="?event='.$event.'&amp;step=edit&amp;id='.$a['id'].'">'.htmlspecialchars($a['label']).'</a></td>'.n.
					'				<td>'.htmlspecialchars($a['page']).'</td>'.n.
					'				<td>'.htmlspecialchars($a['tabgroup']).'</td>'.n.
					'				<td><input type="checkbox" name="selected[]" value="'.$a['id'].'" /></td>'.n.
					'			</tr>'.n;
		
		} else 
			$out[] =
				'			<tr>'.n.
				'				<td colspan="5">'.
				
				str_replace(
					'{link}',
					'<a href="?event='.$event.'&amp;step=edit">'.gTxt('rah_tabtor_start_by_link').'</a>',
					gTxt('rah_tabtor_nothing_to_show')
				).
				
				'</td>'.n.
				'			</tr>'.n;
		
		
		$out[] = 
			'		</tbody>'.n.
			'	</table>'.n.
			'	<p id="rah_tabtor_step" class="rah_ui_step">'.n.
			'		<select name="step">'.n.
			'			<option value="">'.gTxt('rah_tabtor_with_selected').'</option>'.n.
			'			<option value="delete">'.gTxt('rah_tabtor_delete').'</option>'.n.
			'		</select>'.n.
			'		<input type="submit" class="smallerbox" value="'.gTxt('go').'" />'.n.
			'	</p>'.n;
		
		$this->pane($out, 'rah_tabtor_title', $message);
	}

	/**
	 * The editor pane
	 * @param string $message The message shown in the page header.
	 */

	public function edit($message='') {
		
		global $prefs;
		
		extract(
			psa(
				array(
					'label',
					'page',
					'tabgroup',
					'position'
				)
			)
		);
		
		if(($id = gps('id')) && $id && !ps('id')) {
			
			$rs = 
				safe_row(
					'*',
					'rah_tabtor',
					"id='".doSlash($id)."'"
				);
			
			if(!$rs) {
				$this->browse('rah_tabtor_unknown_item');
				return;
			}
			
			extract($rs);
			
		}
		
		$advanced_editor = $prefs['rah_tabtor_advanced_editor'];
		$tabs = $this->get_events();
		
		$out[] = 
		
			'	<input type="hidden" name="step" value="save" />'.n.
			($id ? '	<input type="hidden" name="id" value="'.$id.'" />'.n : '').
			
			'	<p>'.n.
			'		<label>'.n.
			'			'.gTxt('rah_tabtor_label').'<br />'.n.
			'			<input class="edit" type="text" name="label" value="'.htmlspecialchars($label).'" />'.n.
			'		</label>'.n.
			'	</p>'.n.
			
			'	<p>'.n.
			'		<label>'.n.
			'			'.gTxt('rah_tabtor_page').'<br />'.n;
		
		if($tabs !== false && $advanced_editor == 0 && (empty($page) || isset($tabs['events'][$page]))) {
			
			$out[] =
		
				'			<select name="page" class="rah_tabtor_select">'.n.
				'				<option value="">'.gTxt('rah_tabtor_select').'</option>'.n;
			
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
			'			'.gTxt('rah_tabtor_group').'<br />'.n;
		
		if($tabs !== false && $advanced_editor == 0 && (empty($tabgroup) || isset($tabs['groups'][$tabgroup]))) {
			
			$out[] =
				'			<select name="tabgroup" class="rah_tabtor_select">'.n.
				'				<option value="">'.gTxt('rah_tabtor_select').'</option>'.n;
		
			foreach($tabs['groups'] as $key => $val) 
				
				$out[] = 
					'				<option value="'.$key.'"'.($tabgroup == $key ? ' selected="selected"' : '').'>'.($val ? $val : $key).'</option>';
			
			
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
			'			'.gTxt('rah_tabtor_position').'<br />'.n.
			'			<select name="position" class="rah_tabtor_select">'.n;
		
		for($i=1;$i<10;$i++)
			$out[] = 
				'				<option value="'.$i.'"'.($position == $i ? ' selected="selected"' : '').'>'.$i.'</option>';
		
		$out[] =
			'			</select>'.n.
			'		</label>'.n.
			'	</p>'.n.
			
			'	<p>'.n.
			'		<input type="submit" value="'.gTxt('rah_tabtor_save').'" class="publish" />'.n.
			'	</p>'.n
		;
		
		$this->pane($out,'rah_tabtor_title',$message);
	}

	/**
	 * Does the saving work
	 */

	public function save() {
		
		extract(
			doSlash(
				psa(
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
		
		if(empty($label) || empty($page) || empty($tabgroup) || !in_array($position,range(1,9))) {
			$this->edit('rah_tabtor_required_fields');
			return;
		}
		
		if($id) {
			
			if(
				!safe_row(
					'id',
					'rah_tabtor',
					"id='$id' LIMIT 0, 1"
				)
			) {
				$this->browse('rah_tabtor_unknown_item');
				return;
			}
			
			if(
				safe_update(
					'rah_tabtor',
					"label='$label',
					page='$page',
					tabgroup='$tabgroup',
					position='$position'",
					"id='$id'"
				) == false
			) {
				$this->edit('rah_tabtor_error_saving');
				return;
			}
			
			$this->edit('rah_tabtor_updated');
			return;
		}
		
		if(
			safe_count(
				'rah_tabtor',
				"label='$label' and 
				page='$page' and 
				tabgroup='$tabgroup' and 
				position='$position'"
			) > 0
		) {
			$this->browse('rah_tabtor_already_exists');
			return;	
		}
		
		if(
			safe_insert(
				'rah_tabtor',
				"label='$label',
				page='$page',
				tabgroup='$tabgroup',
				position='$position'"
			) == false
		) {
			$this->edit('rah_tabtor_error_saving');
			return;
		}
			
		register_tab($tabgroup,$page,gTxt($label));
		$this->browse('rah_tabtor_saved');
	}

	/**
	 * Delete selected items
	 */

	public function delete() {
		
		$selected = ps('selected');
		
		if(!is_array($selected) || empty($selected)) {
			$this->browse('rah_tabtor_select_something');
			return;
		}
		
		foreach($selected as $id)
			$ids[] = "'".doSlash($id)."'";
		
		if(
			safe_delete(
				'rah_tabtor',
				'id in('.implode(',',$ids).')'
			) == false
		) {
			$this->browse('rah_tabtor_error_deleting');
			return;	
		}
		
		$this->browse('rah_tabtor_removed');
	}

	/**
	 * Outputs the pane HTML markup and sets page title.
	 * @param mixed $out Pane markup. Accepts arrays and strings.
	 * @param string $pagetop Page title.
	 * @param string $message Message shown in the header.
	 */

	private function pane($out, $pagetop, $message) {
		
		global $event;
		
		$message = $message ? gTxt($message) : '';
		
		pagetop(gTxt($pagetop), $message);
		
		if(is_array($out))
			$out = implode('', $out);
		
		echo 
			n.
			'<form method="post" action="index.php" id="rah_tabtor_container" class="rah_ui_container">'.n.
			'	<input type="hidden" name="event" value="'.$event.'" />'.n.
			'	<input type="hidden" name="_txp_token" value="'.form_token().'" />'.n.
			'	<p class="rah_ui_nav">'.
				'<span class="rah_ui_sep">&#187;</span> <a href="?event='.$event.'">'.gTxt('rah_tabtor_main').'</a> '.
				'<span class="rah_ui_sep">&#187;</span> <strong><a href="?event='.$event.'&amp;step=edit">'.gTxt('rah_tabtor_create_new').'</a></strong> '.
				'<span class="rah_ui_sep">&#187;</span> <a href="?event=plugin&amp;step=plugin_help&amp;name=rah_tabtor">'.gTxt('rah_tabtor_documentation').'</a>'.
			'</p>'.n.
			
			$out.n.
			
			'</form>'.n;
	}

	/**
	 * Adds styles and JavaScript to the <head>
	 */
	
	static public function head() {
		global $event;
		
		if($event != 'rah_tabtor')
			return;
			
		$msg = gTxt('are_you_sure');
		
		echo 
			<<<EOF
			<script type="text/javascript">
				<!--
				$(document).ready(function(){
					if($('#rah_tabtor_step').length < 1)
						return;
					
					$('#rah_tabtor_step .smallerbox').hide();

					if($('#rah_tabtor_container input[type=checkbox]:checked').val() == null)
						$('#rah_tabtor_step').hide();

					/*
						Reset the value
					*/

					$('#rah_tabtor_container select[name="step"]').val('');

					/*
						Every time something is checked, check if
						the dropdown should be shown
					*/

					$('#rah_tabtor_container input[type=checkbox], #rah_tabtor_container td').click(
						function(){
							$('#rah_tabtor_container select[name="step"]').val('');
							if($('table#list input[type=checkbox]:checked').val() != null)	
								$('#rah_tabtor_step').slideDown();
							else
								$('#rah_tabtor_step').slideUp();
						}
					);

					/*
						If value is changed, send the form
					*/

					$('#rah_tabtor_container select[name="step"]').change(
						function(){
							$('#rah_tabtor_container').submit();
						}
					);

					/*
						Verify if the sent is allowed
					*/

					$('form#rah_tabtor_container').submit(
						function() {
							if(!verify('{$msg}')) {
								$('#rah_tabtor_container select[name="step"]').val('');
								return false;
							}
						}
					);
				});
				-->
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
	 * Lists events and tab groups
	 */

	private function get_events() {
		
		if(!function_exists('areas') || !is_array(areas()))
			return false;
		
		$out = array();
		
		foreach(areas() as $key => $group) {
			$out['groups'][$key] = gTxt('tab_'.$key);
			foreach ($group as $title => $name) 
				$out['events'][$name] = $title;
		}
		
		$out['events'] = array_unique($out['events']);
		asort($out['events']);
		
		return $out;
	}

	/**
	 * Redirect to the admin-side interface
	 */

	static public function prefs() {
		header('Location: ?event=rah_tabtor');
		echo 
			'<p>'.n.
			'	<a href="?event=rah_tabtor">'.gTxt('continue').'</a>'.n.
			'</p>';
	}
}

?>