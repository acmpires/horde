<?php
/**
 * Nag list script.
 *
 * Copyright 2001-2009 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 */

require_once dirname(__FILE__) . '/lib/base.php';

$vars = Horde_Variables::getDefaultVariables();

/* Get the current action ID. */
$actionID = $vars->actionID;

/* Sort out the sorting values and task filtering. */
if ($vars->exists('sortby')) {
    $prefs->setValue('sortby', $vars->sortby);
}
if ($vars->exists('sortdir')) {
    $prefs->setValue('sortdir', $vars->sortdir);
}
if ($vars->exists('show_completed')) {
    $prefs->setValue('show_completed', $vars->get('show_completed'));
} else {
    $vars->set('show_completed', $prefs->getValue('show_completed'));
}

/* Page variables. */
$title = _("My Tasks");

switch ($actionID) {
case 'search_tasks':
    /* Get the search parameters. */
    $search_pattern = $vars->search_pattern;
    $search_name = ($vars->search_name == 'on');
    $search_desc = ($vars->search_desc == 'on');
    $search_category = ($vars->search_category == 'on');
    $search_completed = $vars->search_completed;

    $vars->set('show_completed', $search_completed);

    /* Get the full, sorted task list. */
    $tasks = Nag::listTasks($prefs->getValue('sortby'),
                            $prefs->getValue('sortdir'),
                            $prefs->getValue('altsortby'),
                            null,
                            $search_completed);
    if (is_a($tasks, 'PEAR_Error')) {
        $notification->push($tasks, 'horde.error');
        $tasks = new Nag_Task();
    }

    if (!empty($search_pattern) &&
        ($search_name || $search_desc || $search_category)) {
        $pattern = '/' . preg_quote($search_pattern, '/') . '/i';
        $search_results = new Nag_Task();
        $tasks->reset();
        while ($task = &$tasks->each()) {
            if (($search_name && preg_match($pattern, $task->name)) ||
                ($search_desc && preg_match($pattern, $task->desc)) ||
                ($search_category && preg_match($pattern, $task->category))) {
                $search_results->add($task);
            }
        }

        /* Reassign $tasks to the search result. */
        $tasks = $search_results;
        $title = sprintf(_("Search: Results for \"%s\""), $search_pattern);
    }
    break;

default:
    /* Get the full, sorted task list. */
    $tasks = Nag::listTasks($prefs->getValue('sortby'),
                            $prefs->getValue('sortdir'),
                            $prefs->getValue('altsortby'));
    if (is_a($tasks, 'PEAR_Error')) {
        $notification->push($tasks, 'horde.error');
        $tasks = new Nag_Task();
    }
    break;
}

$print_view = (bool)$vars->print;
if (!$print_view) {
    Horde::addScriptFile('popup.js', 'horde', true);
    Horde::addScriptFile('tooltips.js', 'horde', true);
    Horde::addScriptFile('effects.js', 'horde', true);
    Horde::addScriptFile('QuickFinder.js', 'horde', true);
    $print_link = Horde::applicationUrl(Horde_Util::addParameter('list.php', array('print' => 1)));
}

require NAG_TEMPLATES . '/common-header.inc';

if ($print_view) {
    require_once $registry->get('templates', 'horde') . '/javascript/print.js';
} else {
    require NAG_TEMPLATES . '/menu.inc';
    echo '<div id="page">';

    if (!$prefs->isLocked('show_completed')) {
        $listurl = Horde::applicationUrl('list.php');
        $tabs = new Horde_UI_Tabs('show_completed', $vars);
        $tabs->addTab(_("_All tasks"), $listurl, 1);
        $tabs->addTab(_("Incom_plete tasks"), $listurl, 0);
        $tabs->addTab(_("_Future tasks"), $listurl, 3);
        $tabs->addTab(_("_Completed tasks"), $listurl, 2);
        echo $tabs->render($vars->get('show_completed'));
    }
}

require NAG_TEMPLATES . '/list.html.php';

if (!$print_view) {
    require NAG_TEMPLATES . '/panel.inc';
}
require $registry->get('templates', 'horde') . '/common-footer.inc';
