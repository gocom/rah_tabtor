<?php

/*
 * rah_tabtor - Move around Textpattern CMS' admin-side navigation links
 * https://github.com/gocom/rah_tabtor
 *
 * Copyright (C) 2019 Jukka Svahn
 *
 * This file is part of rah_tabtor.
 *
 * rah_tabtor is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation, version 2.
 *
 * rah_tabtor is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with rah_tabtor. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * The plugin class.
 */
final class Rah_Tabtor
{
    /**
     * Stores plugin areas.
     *
     * @var array
     */
    private $pluginAreas = [];

    /**
     * Constructor.
     */
    public function __construct()
    {
        add_privs('rah_tabtor', '1,2');
        add_privs('plugin_prefs.rah_tabtor', '1,2');
        register_tab('extensions', 'rah_tabtor', gTxt('rah_tabtor'));
        register_callback([$this, 'panes'], 'rah_tabtor');
        register_callback([$this, 'prefs'], 'plugin_prefs.rah_tabtor');
        register_callback([$this, 'install'], 'plugin_lifecycle.rah_tabtor', 'installed');
        register_callback([$this, 'uninstall'], 'plugin_lifecycle.rah_tabtor', 'deleted');
        $this->register();
    }

    /**
     * Installer.
     */
    public function install()
    {
        safe_create(
            'rah_tabtor',
            "`id` INT(11) NOT NULL auto_increment,
            `tabgroup` VARCHAR(255) NOT NULL default '',
            `page` VARCHAR(255) NOT NULL default '',
            `label` VARCHAR(255) NOT NULL default '',
            `position` INT(2) NOT NULL default 1,
            PRIMARY KEY(`id`)"
        );
    }

    /**
     * Uninstaller.
     */
    public function uninstall()
    {
        safe_drop('rah_tabtor');
    }

    /**
     * Registers the tabs.
     */
    public function register()
    {
        global $plugin_areas;

        $tabs = safe_rows(
            'tabgroup, page, label',
            'rah_tabtor',
            '1=1 order by position asc'
        );

        if (!$tabs) {
            return;
        }

        $this->pluginAreas = $plugin_areas;
        $unset = [];

        foreach ($tabs as $tab) {
            foreach ($plugin_areas as $area => $items) {
                foreach ($items as $title => $event) {
                    if ($tab['page'] === $event && !in_array($event, $unset)) {
                        unset($plugin_areas[$area][$title]);
                        $unset[] = $event;
                    }
                }
            }

            register_tab($tab['tabgroup'], $tab['page'], gTxt($tab['label']));
        }
    }

    /**
     * Delivers panes.
     */
    public function panes()
    {
        require_privs('rah_tabtor');

        global $step;

        $steps = [
            'browser' => false,
            'edit' => false,
            'save' => true,
            'multiEdit' => true,
        ];

        if (!$step || !bouncer($step, $steps)) {
            $step = 'browser';
        }

        $this->$step();
    }

    /**
     * The main pane.
     *
     * @param string $message The activity message
     */
    public function browser($message = '')
    {
        global $event;

        $create = tag(
            sLink('article', '', gTxt('rah_tabtor_create_new'), 'txp-button'),
            'div',
            ['class' => 'txp-control-panel']
        );

        $rs = safe_rows(
            '*',
            'rah_tabtor',
            '1 = 1 order by label asc, page asc, tabgroup asc'
        );

        if ($rs) {
            $out[] = tag_start('form', [
                    'class' => 'multi_edit_form',
                    'method' => 'post',
                    'action' => 'index.php',
                ]).
                tInput().
                n.tag_start('div', ['class' => 'txp-listtables']).
                n.tag_start('table', ['class' => 'txp-list']).
                n.tag_start('thead').
                tr(
                    hCell(
                        fInput(
                            'checkbox',
                            'select_all',
                            0,
                            '',
                            '',
                            '',
                            '',
                            '',
                            'select_all'
                        ),
                        '',
                        'class="txp-list-col-multi-edit" scope="col" title="'.gTxt('toggle_all_selected').'"'
                    ).
                    hCell(gTxt('rah_tabtor_label')).
                    hCell(gTxt('rah_tabtor_page')).
                    hCell(gTxt('rah_tabtor_group'))
                ).
                n.tag_end('thead').
                n.tag_start('tbody');

            foreach ($rs as $a) {
                $out[] = tr(
                    td(fInput('checkbox', 'selected[]', $a['id'], 'checkbox')).
                    td(eLink($event, 'edit', 'id', $a['id'], txpspecialchars($a['label']))).
                    td(txpspecialchars($a['page'])).
                    td(txpspecialchars($a['tabgroup']))
                );
            }

            $out[] = n.tag_end('tbody').
                n.tag_end('table')
                n.tag_end('div').
                multi_edit(['delete' => gTxt('rah_tabtor_delete')], $event, 'multiEdit').
                n.tag_end('form');
        }

        $out = \Txp::get('\Textpattern\Admin\Table', $event)->render([], null, $create, implode(n, $out));
        $this->pane($out, 'rah_tabtor', $message);
    }

    /**
     * The editor pane.
     *
     * @param string $message The activity message
     */
    public function edit($message = '')
    {
        global $event;

        extract(psa([
            'label',
            'page',
            'tabgroup',
            'position'
        ]));

        if (($id = gps('id')) && $id && !ps('id')) {
            $rs = safe_row('*', 'rah_tabtor', "id='".doSlash($id)."'");

            if (!$rs) {
                $this->browser([gTxt('rah_tabtor_unknown_item'), E_ERROR]);
                return;
            }

            extract($rs);
        }

        $tabs = $this->getEvents();

        if ($tabs !== false && (empty($page) || isset($tabs['events'][$page]))) {
            $pageInput = selectInput('page', $tabs['events'], $page);
        } else {
            $pageInput = fInput('text', 'page', $page);
        }

        if ($tabs !== false && (empty($tabgroup) || isset($tabs['groups'][$tabgroup]))) {
            $groupInput = selectInput('tabgroup', $tabs['groups'], $tabgroup);
        } else {
            $groupInput = fInput('text', 'tabgroup', $tabgroup);
        }

        $out[] = tag_start('form', [
                'class' => 'txp-edit',
                'method' => 'post',
                'action' => 'index.php',
            ]).
            tInput().
            eInput($event).
            sInput('save').
            hInput('id', $id).

            inputLabel(
                'rah_tabtor_label',
                fInput('text', 'label', $label),
                'label',
                '',
                ['class' => 'txp-form-field']
            ).

            inputLabel(
                'rah_tabtor_page',
                $pageInput,
                'page',
                '',
                ['class' => 'txp-form-field']
            ).

            inputLabel(
                'rah_tabtor_group',
                $groupInput,
                'group',
                '',
                ['class' => 'txp-form-field']
            ).

            inputLabel(
                'rah_tabtor_position',
                selectInput('position', array_combine(range(1, 9), range(1, 9)), (int)$position),
                'position',
                '',
                ['class' => 'txp-form-field']
            ).

            graf(
                sLink($event, '', gTxt('cancel'), 'txp-button').
                fInput('submit', 'save', gTxt('rah_tabtor_save'), 'publish'),
                ['class' => 'txp-edit-actions']
            ).

            tag_end('form');

        $this->pane($out, 'rah_tabtor', $message);
    }

    /**
     * Saves sent forms.
     */
    public function save()
    {
        extract(doSlash(doArray(psa([
            'label',
            'page',
            'tabgroup',
            'id',
            'position',
        ]), 'trim')));

        if (!$label || !$page || !$tabgroup || !in_array($position, range(1, 9))) {
            $this->edit([gTxt('rah_tabtor_required_fields'), E_ERROR]);
            return;
        }

        if ($id) {
            if (!safe_row('id', 'rah_tabtor', "id='$id' limit 0, 1")) {
                $this->browser([gTxt('rah_tabtor_unknown_item'), E_ERROR]);
                return;
            }

            $update = safe_update(
                'rah_tabtor',
                "label='$label',
                page='$page',
                tabgroup='$tabgroup',
                position='$position'",
                "id='$id'"
            );

            if ($update === false) {
                $this->edit([gTxt('rah_tabtor_save_failed'), E_ERROR]);
                return;
            }

            $this->browser(gTxt('rah_tabtor_updated'));
            return;
        }

        $matches = safe_count(
            'rah_tabtor',
            "label='$label' and
            page='$page' and
            tabgroup='$tabgroup' and
            position='$position'"
        );

        if ($matches > 0) {
            $this->edit([gTxt('rah_tabtor_already_exists'), E_WARNING]);
            return;
        }

        $insert = safe_insert(
            'rah_tabtor',
            "label='$label',
            page='$page',
            tabgroup='$tabgroup',
            position='$position'"
        );

        if ($insert === false) {
            $this->edit([gTxt('rah_tabtor_save_failed'), E_ERROR]);
            return;
        }

        register_tab($tabgroup, $page, gTxt($label));
        $this->browser(gTxt('rah_tabtor_saved'));
    }

    /**
     * Multi-edit handler.
     */
    public function multiEdit()
    {
        extract(psa([
            'selected',
            'edit_method',
        ]));

        if (!is_string($edit_method) || empty($selected) || !is_array($selected)) {
            $this->browser([gTxt('rah_tabtor_select_something'), E_WARNING]);
            return;
        }

        $methods = [
            'delete' => [$this, 'multiOptionDelete'],
        ];

        $method = 'multi_option_' . $edit_method;

        if (!isset($methods[$edit_method])) {
            $this->browser();
            return;
        }

        call_user_func($methods[$edit_method]);
    }

    /**
     * Deletes selected items.
     */
    private function multiOptionDelete()
    {
        $delete = safe_delete(
            'rah_tabtor',
            'id in('.implode(',', quote_list(ps('selected'))).')'
        );

        if ($delete === false) {
            $this->browser([gTxt('rah_tabtor_delete_failed'), E_ERROR]);
            return;
        }

        $this->browser(gTxt('rah_tabtor_removed'));
    }

    /**
     * Outputs the panel's HTML markup and sets the page title.
     *
     * @param string|array $out     Pane markup
     * @param string       $pagetop Page title
     * @param string       $message Activity message
     */
    private function pane($out, $pagetop, $message)
    {
        pagetop(gTxt($pagetop), $message);
        echo $out;
    }

    /**
     * Lists admin-side events and tab groups.
     *
     * @return array List of events
     */
    private function getEvents()
    {
        global $plugin_areas;

        if (!function_exists('areas') || !is_array(areas())) {
            return false;
        }

        $r = $plugin_areas;

        if ($this->pluginAreas) {
            $plugin_areas = $this->pluginAreas;
        }

        $out = [];

        foreach (areas() as $key => $group) {
            $out['groups'][$key] = gTxt('tab_'.$key);

            foreach ($group as $title => $name) {
                $out['events'][$name] = $title;
            }
        }

        $out['events'] = array_unique($out['events']);
        asort($out['events']);
        $plugin_areas = $r;

        return $out;
    }

    /**
     * Plugin options page.
     *
     * Redirects to the admin-side interface.
     */
    public function prefs()
    {
        header('Location: ?event=rah_tabtor');
        echo graf(href(gTxt('continue'), ['event' => 'rah_tabtor']));
    }
}
