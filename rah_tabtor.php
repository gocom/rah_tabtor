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

new rah_tabtor();

class rah_tabtor {

	static public $version = '0.3.1';
	
	/**
	 * @var array Stores plugin areas
	 */
	
	protected $plugin_areas = array();

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
		
		if((string) get_pref(__CLASS__.'_version') === self::$version) {
			return;
		}
		
		safe_query(
			"CREATE TABLE IF NOT EXISTS ".safe_pfx('rah_tabtor')." (
				`id` INT(11) NOT NULL auto_increment,
				`tabgroup` VARCHAR(255) NOT NULL default '',
				`page` VARCHAR(255) NOT NULL default '',
				`label` VARCHAR(255) NOT NULL default '',
				`position` INT(2) NOT NULL default 1,
				PRIMARY KEY(`id`)
			) PACK_KEYS=1 AUTO_INCREMENT=1 CHARSET=utf8"
		);
		
		@safe_query('DROP TABLE IF EXISTS '.safe_pfx('rah_tabtor_prefs'));
		
		set_pref(__CLASS__.'_version', self::$version, 'rah_tabtor', 2, '', 0);
		$prefs[__CLASS__.'_version'] = self::$version;
	}
	
	/**
	 * Constructor
	 */
	
	public function __construct() {
		add_privs('rah_tabtor', '1,2');
		add_privs('plugin_prefs.rah_tabtor', '1,2');
		register_tab('extensions', 'rah_tabtor', gTxt('rah_tabtor'));
		register_callback(array($this, 'panes'),'rah_tabtor');
		register_callback(array($this, 'prefs'), 'plugin_prefs.rah_tabtor');
		register_callback(array(__CLASS__, 'install'), 'plugin_lifecycle.rah_tabtor');
		$this->register();
	}

	/**
	 * Registers the tabs
	 */

	public function register() {
		
		global $plugin_areas;
		
		@$rs = 
			safe_rows(
				'tabgroup, page, label',
				'rah_tabtor',
				'1=1 ORDER BY position asc'
			);
		
		if(!$rs) {
			return;
		}
		
		$this->plugin_areas = $plugin_areas;
		$unset = array();
		
		foreach($rs as $a) {
			
			foreach($plugin_areas as $area => $items) {
				foreach($items as $title => $event) {
					if($a['page'] === $event && !in_array($event, $unset)) {
						unset($plugin_areas[$area][$title]);
						$unset[] = $event;
					}
				}
			}
			
			register_tab($a['tabgroup'], $a['page'], gTxt($a['label']));
		}
	}

	/**
	 * Delivers panes
	 */

	public function panes() {
		require_privs('rah_tabtor');

		global $step;
		
		$steps = 
			array(
				'browser' => false,
				'edit' => false,
				'save' => true,
				'multi_edit' => true,
			);
		
		if(!$step || !bouncer($step, $steps)) {
			$step = 'browser';
		}
		
		$this->$step();
	}

	/**
	 * The main pane
	 * @param string $message The message shown in the page header.
	 */

	public function browser($message='') {
		
		global $event;
		
		$out[] = 
			
			'<form method="post" action="index.php" class="multi_edit_form">'.n.
			tInput().
			
			'<div class="txp-listtables">'.n.
			'	<table class="txp-list">'.n.
			'		<thead>'.n.
			'			<tr>'.n.
			'				<th title="'.gTxt('toggle_all_selected').'" class="multi-edit"><input name="select_all" type="checkbox" value="0" /></th>'.n.
			'				<th>'.gTxt('rah_tabtor_label').'</th>'.n.
			'				<th>'.gTxt('rah_tabtor_page').'</th>'.n.
			'				<th>'.gTxt('rah_tabtor_group').'</th>'.n.
			'			</tr>'.n.
			'		</thead>'.n.
			'		<tbody>'.n;
		
		$rs = 
			safe_rows(
				'*',
				'rah_tabtor',
				'1=1 ORDER BY label asc, page asc, tabgroup asc'
			);
			
		if($rs) {
			foreach($rs as $a) {
				$out[] = 
					'			<tr>'.n.
					'				<td><input type="checkbox" name="selected[]" value="'.$a['id'].'" /></td>'.n.
					'				<td><a href="?event='.$event.'&amp;step=edit&amp;id='.$a['id'].'">'.txpspecialchars($a['label']).'</a></td>'.n.
					'				<td>'.txpspecialchars($a['page']).'</td>'.n.
					'				<td>'.txpspecialchars($a['tabgroup']).'</td>'.n.
					'			</tr>'.n;
			}
		}

		else {
			$out[] =
				'			<tr>'.n.
				'				<td colspan="4">'.
				
				gTxt(
					'rah_tabtor_nothing_to_show',
					array(
						'{link}' => '<a href="?event='.$event.'&amp;step=edit">'.gTxt('rah_tabtor_start_by_link').'</a>'
					), false
				).
				
				'</td>'.n.
				'			</tr>'.n;
		}
		
		$out[] = 
			'		</tbody>'.n.
			'	</table>'.n.
			'</div>'.n.
			multi_edit(array('delete' => gTxt('rah_tabtor_delete')), $event, 'multi_edit').n.
			'</form>';
		
		$this->pane($out, 'rah_tabtor', $message);
	}

	/**
	 * The editor pane
	 * @param string $message The message shown in the page header.
	 */

	public function edit($message='') {
		
		global $event;
		
		extract(psa(array(
			'label',
			'page',
			'tabgroup',
			'position'
		)));
		
		if(($id = gps('id')) && $id && !ps('id')) {
			
			$rs = 
				safe_row(
					'*',
					'rah_tabtor',
					"id='".doSlash($id)."'"
				);
			
			if(!$rs) {
				$this->browser(array(gTxt('rah_tabtor_unknown_item'), E_ERROR));
				return;
			}
			
			extract($rs);
		}
		
		$advanced_editor = get_pref('rah_tabtor_advanced_editor');
		$tabs = $this->get_events();
		
		$out[] = 
			'<form method="post" action="index.php">'.n.
			tInput().
			eInput($event).
			sInput('save').
			hInput('id', $id).
			
			'	<p>'.n.
			'		<label>'.n.
			'			'.gTxt('rah_tabtor_label').'<br />'.n.
			'			<input type="text" name="label" value="'.txpspecialchars($label).'" />'.n.
			'		</label>'.n.
			'	</p>'.n.
			
			'	<p>'.n.
			'		<label>'.n.
			'			'.gTxt('rah_tabtor_page').'<br />'.n;
		
		if($tabs !== false && !$advanced_editor && (empty($page) || isset($tabs['events'][$page]))) {
			$out[] = selectInput('page', $tabs['events'], $page);
		}
		
		else {
			$out[] =
				'			<input type="text" name="page" value="'.txpspecialchars($page).'" />'.n;
		}
		
		$out[] =
			
			'		</label>'.n.
			'	</p>'.n.
			
			'	<p>'.n.
			'		<label>'.n.
			'			'.gTxt('rah_tabtor_group').'<br />'.n;
		
		if($tabs !== false && !$advanced_editor && (empty($tabgroup) || isset($tabs['groups'][$tabgroup]))) {
			$out[] = selectInput('tabgroup', $tabs['groups'], $tabgroup);
		}
		
		else {
			$out[] =
				'			<input type="text" name="tabgroup" value="'.txpspecialchars($tabgroup).'" />'.n;
		}
		
		$out[] =
			
			'		</label>'.n.
			'	</p>'.n.
			
			'	<p>'.n.
			'		<label>'.n.
			'			'.gTxt('rah_tabtor_position').'<br />'.n.
			selectInput('position', array_combine(range(1, 9), range(1, 9)), (int) $position);
		
		$out[] =
			'		</label>'.n.
			'	</p>'.n.
			
			'	<p>'.n.
			'		<input type="submit" value="'.gTxt('rah_tabtor_save').'" class="publish" />'.n.
			'	</p>'.n.
			'</form>'
		;
		
		$this->pane($out, 'rah_tabtor', $message);
	}

	/**
	 * Does the saving work
	 */

	public function save() {
		
		extract(doSlash(doArray(psa(array(
			'label',
			'page',
			'tabgroup',
			'id',
			'position'
		)), 'trim')));
		
		if(!$label || !$page || !$tabgroup || !in_array($position, range(1, 9))) {
			$this->edit(array(gTxt('rah_tabtor_required_fields'), E_ERROR));
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
				$this->browser(array(gTxt('rah_tabtor_unknown_item'), E_ERROR));
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
				$this->edit(array(gTxt('rah_tabtor_save_failed'), E_ERROR));
				return;
			}
			
			$this->browser(gTxt('rah_tabtor_updated'));
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
			$this->edit(array(gTxt('rah_tabtor_already_exists'), E_WARNING));
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
			$this->edit(array(gTxt('rah_tabtor_save_failed'), E_ERROR));
			return;
		}
		
		register_tab($tabgroup, $page, gTxt($label));
		$this->browser(gTxt('rah_tabtor_saved'));
	}
	
	/**
	 * Multi-edit handler
	 */
	
	public function multi_edit() {
		
		extract(psa(array(
			'selected',
			'edit_method',
		)));
		
		if(!is_string($edit_method) || empty($selected) || !is_array($selected)) {
			$this->browser(array(gTxt('rah_tabtor_select_something'), E_WARNING));
			return;
		}
		
		$method = 'multi_option_' . $edit_method;
		
		if(!method_exists($this, $method)) {
			$method = 'browser';
		}
		
		$this->$method();
	}

	/**
	 * Delete selected items
	 */

	private function multi_option_delete() {
		
		if(
			safe_delete(
				'rah_tabtor',
				'id in('.implode(',', quote_list(ps('selected'))).')'
			) === false
		) {
			$this->browser(array(gTxt('rah_tabtor_delete_failed'), E_ERROR));
			return;
		}
		
		$this->browser(gTxt('rah_tabtor_removed'));
	}

	/**
	 * Outputs the pane HTML markup and sets page title.
	 * @param mixed $out Pane markup. Accepts arrays and strings.
	 * @param string $pagetop Page title.
	 * @param string $message Message shown in the header.
	 */

	private function pane($out, $pagetop, $message) {
		
		global $event;
		
		pagetop(gTxt($pagetop), $message);
		
		if(is_array($out)) {
			$out = implode('', $out);
		}
		
		echo 
			n.
			'<h1 class="txp-heading">'.gTxt('rah_tabtor').'</h1>'.n.
			'<div class="txp-container">'.n.
			'	<p class="txp-buttons">'.
				'<a href="?event='.$event.'&amp;step=edit">'.gTxt('rah_tabtor_create_new').'</a>'.
			'</p>'.n.
			$out.n.
			'</div>'.n;
	}

	/**
	 * Lists events and tab groups
	 */

	private function get_events() {
		
		global $plugin_areas;
		
		if(!function_exists('areas') || !is_array(areas())) {
			return false;
		}
		
		$r = $plugin_areas;
		
		if($this->plugin_areas) {
			$plugin_areas = $this->plugin_areas;
		}
		
		$out = array();
		
		foreach(areas() as $key => $group) {
			$out['groups'][$key] = gTxt('tab_'.$key);
			
			foreach($group as $title => $name) {
				$out['events'][$name] = $title;
			}
		}
		
		$out['events'] = array_unique($out['events']);
		asort($out['events']);
		$plugin_areas = $r;
		
		return $out;
	}

	/**
	 * Redirect to the admin-side interface
	 */

	public function prefs() {
		header('Location: ?event=rah_tabtor');
		echo 
			'<p>'.n.
			'	<a href="?event=rah_tabtor">'.gTxt('continue').'</a>'.n.
			'</p>';
	}
}

?>