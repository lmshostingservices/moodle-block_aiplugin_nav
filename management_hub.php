<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Durable management hub for LMS Labs plugins and links.
 *
 * @package    block_aiplugin_nav
 * @copyright  2026 Essay Grader AI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/blocks/moodleblock.class.php');
require_once(__DIR__ . '/block_aiplugin_nav.php');

require_login();

$context = context_system::instance();
$canmanage = has_capability('moodle/site:config', $context);
$views = array('plugins', 'reports', 'updates', 'customlinks', 'help');
$view = optional_param('view', 'plugins', PARAM_ALPHA);
$view = in_array($view, $views, true) ? $view : 'plugins';
if ($view === 'updates' && !$canmanage) {
    throw new required_capability_exception($context, 'moodle/site:config', 'nopermissions', '');
}

$query = trim(optional_param('q', '', PARAM_TEXT));
$filter = optional_param('filter', 'all', PARAM_ALPHANUMEXT);
$selectedcomponent = optional_param('plugin', '', PARAM_COMPONENT);
$baseurl = new moodle_url('/blocks/aiplugin_nav/management_hub.php');

$stateparams = array('q' => $query, 'filter' => $filter);
if ($selectedcomponent !== '') {
    $stateparams['plugin'] = $selectedcomponent;
}
$stateurl = static function (string $targetview, array $changes = array()) use ($baseurl, $stateparams): moodle_url {
    $params = array_merge($stateparams, array('view' => $targetview), $changes);
    foreach ($params as $key => $value) {
        if ($value === null || $value === '') {
            unset($params[$key]);
        }
    }
    return new moodle_url($baseurl, $params);
};

// Custom links stay in the same per-user preference used by the existing external API.
if ($view === 'customlinks' && data_submitted() && optional_param('action', '', PARAM_ALPHA) !== '') {
    require_sesskey();
    $action = required_param('action', PARAM_ALPHA);
    $linksjson = get_user_preferences('block_aiplugin_nav_custom_links', '[]', $USER->id);
    $links = json_decode($linksjson, true);
    $links = is_array($links) ? array_values($links) : array();

    if ($action === 'add') {
        $name = trim(required_param('name', PARAM_TEXT));
        $url = required_param('url', PARAM_URL);
        $scheme = core_text::strtolower((string)parse_url($url, PHP_URL_SCHEME));
        if ($name === '' || !filter_var($url, FILTER_VALIDATE_URL) ||
                !in_array($scheme, array('http', 'https'), true)) {
            throw new moodle_exception('invalidcustomlink', 'block_aiplugin_nav');
        }
        if (count($links) >= 20) {
            throw new moodle_exception('customlinklimit', 'block_aiplugin_nav');
        }
        $links[] = array('name' => core_text::substr($name, 0, 50), 'url' => $url, 'icon' => 'link');
        set_user_preference('block_aiplugin_nav_custom_links', json_encode($links), $USER->id);
    } else if ($action === 'edit') {
        $index = required_param('index', PARAM_INT);
        $name = trim(required_param('name', PARAM_TEXT));
        $url = required_param('url', PARAM_URL);
        $scheme = core_text::strtolower((string)parse_url($url, PHP_URL_SCHEME));
        if (!array_key_exists($index, $links) || $name === '' ||
                !filter_var($url, FILTER_VALIDATE_URL) ||
                !in_array($scheme, array('http', 'https'), true)) {
            throw new moodle_exception('invalidcustomlink', 'block_aiplugin_nav');
        }
        $links[$index]['name'] = core_text::substr($name, 0, 50);
        $links[$index]['url'] = $url;
        set_user_preference('block_aiplugin_nav_custom_links', json_encode($links), $USER->id);
    } else if ($action === 'delete') {
        $index = required_param('index', PARAM_INT);
        if (array_key_exists($index, $links)) {
            array_splice($links, $index, 1);
            set_user_preference('block_aiplugin_nav_custom_links', json_encode($links), $USER->id);
        }
    } else if ($action === 'moveup' || $action === 'movedown') {
        $index = required_param('index', PARAM_INT);
        $target = $action === 'moveup' ? $index - 1 : $index + 1;
        if (array_key_exists($index, $links) && array_key_exists($target, $links)) {
            $moving = $links[$index];
            $links[$index] = $links[$target];
            $links[$target] = $moving;
            set_user_preference('block_aiplugin_nav_custom_links', json_encode($links), $USER->id);
        }
    }
    redirect($stateurl('customlinks'));
}

// Custom reports retain the add/delete workflow that was previously embedded in the block.
if ($view === 'reports' && data_submitted() && optional_param('action', '', PARAM_ALPHA) !== '') {
    require_sesskey();
    $action = required_param('action', PARAM_ALPHA);
    $reportsjson = get_user_preferences('block_aiplugin_nav_custom_reports', '[]', $USER->id);
    $customreports = json_decode($reportsjson, true);
    $customreports = is_array($customreports) ? array_values($customreports) : array();

    if ($action === 'addreport') {
        $name = trim(required_param('name', PARAM_TEXT));
        $url = required_param('url', PARAM_URL);
        $scheme = core_text::strtolower((string)parse_url($url, PHP_URL_SCHEME));
        if ($name === '' || !filter_var($url, FILTER_VALIDATE_URL) ||
                !in_array($scheme, array('http', 'https'), true)) {
            throw new moodle_exception('invalidcustomlink', 'block_aiplugin_nav');
        }
        if (count($customreports) >= 20) {
            throw new moodle_exception('customreportlimit', 'block_aiplugin_nav');
        }
        $customreports[] = array(
            'name' => core_text::substr($name, 0, 50),
            'url' => $url,
            'icon' => 'bar-chart',
        );
        set_user_preference(
            'block_aiplugin_nav_custom_reports',
            json_encode($customreports),
            $USER->id
        );
    } else if ($action === 'deletereport') {
        $index = required_param('index', PARAM_INT);
        if (array_key_exists($index, $customreports)) {
            array_splice($customreports, $index, 1);
            set_user_preference(
                'block_aiplugin_nav_custom_reports',
                json_encode($customreports),
                $USER->id
            );
        }
    }
    redirect($stateurl('reports'));
}

$PAGE->set_context($context);
$PAGE->set_url($stateurl($view));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('managementhub', 'block_aiplugin_nav'));
$PAGE->set_heading(get_string('managementhub', 'block_aiplugin_nav'));

$manager = new block_aiplugin_nav();
$master = $manager->get_master_plugin_registry();
$catalogue = array();
foreach ($manager->get_complete_plugin_registry() as $plugin) {
    $catalogue[$plugin['component']] = $plugin;
}
foreach ($master as $component => $plugin) {
    if (!isset($catalogue[$component])) {
        $catalogue[$component] = array_merge($plugin, array('component' => $component));
    } else {
        $catalogue[$component] = array_merge($plugin, $catalogue[$component]);
    }
}
foreach ($catalogue as $component => &$plugin) {
    $plugin['component'] = $component;
    $plugin['is_installed'] = $manager->is_plugin_installed($plugin['plugin_type'], $plugin['plugin_name']);
}
unset($plugin);
uasort($catalogue, static function (array $left, array $right): int {
    return strcasecmp($left['name'], $right['name']);
});

$installedcount = count(array_filter($catalogue, static function (array $plugin): bool {
    return $plugin['is_installed'];
}));

echo $OUTPUT->header();
echo html_writer::start_div('block_aiplugin_nav');
echo html_writer::start_tag('main', array('id' => 'ainav-management-hub', 'class' => 'ainav-hub'));
echo html_writer::tag('p', get_string('managementhubintro', 'block_aiplugin_nav'), array('class' => 'ainav-hub-intro'));

echo html_writer::start_tag('nav', array(
    'class' => 'ainav-hub-tabs',
    'aria-label' => get_string('hubviews', 'block_aiplugin_nav'),
));
foreach ($views as $tabview) {
    if ($tabview === 'updates' && !$canmanage) {
        continue;
    }
    $attributes = array('class' => $tabview === $view ? 'active' : '');
    if ($tabview === $view) {
        $attributes['aria-current'] = 'page';
    }
    echo html_writer::link(
        $stateurl($tabview),
        get_string('hubview' . $tabview, 'block_aiplugin_nav'),
        $attributes
    );
}
echo html_writer::end_tag('nav');

if (in_array($view, $views, true)) {
    echo html_writer::start_tag('form', array('method' => 'get', 'class' => 'ainav-hub-search'));
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'view', 'value' => $view));
    if ($selectedcomponent !== '') {
        echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'plugin', 'value' => $selectedcomponent));
    }
    echo html_writer::label(get_string('hubsearch', 'block_aiplugin_nav'), 'ainav-hub-q', false, array('class' => 'sr-only'));
    echo html_writer::empty_tag('input', array(
        'type' => 'search',
        'id' => 'ainav-hub-q',
        'name' => 'q',
        'value' => $query,
        'placeholder' => get_string('hubsearchplaceholder', 'block_aiplugin_nav'),
    ));
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'filter', 'value' => $filter));
    echo html_writer::tag('button', get_string('search'), array('type' => 'submit', 'class' => 'btn btn-primary'));
    if ($query !== '') {
        echo html_writer::link($stateurl($view, array('q' => null)), get_string('clear'), array('class' => 'btn btn-secondary'));
    }
    echo html_writer::end_tag('form');
}

if ($view === 'plugins') {
    $categories = array();
    foreach ($catalogue as $plugin) {
        $categories[$plugin['category'] ?? 'other'] = true;
    }
    ksort($categories);
    echo html_writer::start_div('ainav-hub-filters', array('aria-label' => get_string('hubfilters', 'block_aiplugin_nav')));
    foreach (array('all', 'installed', 'available') as $statusfilter) {
        echo html_writer::link(
            $stateurl('plugins', array('filter' => $statusfilter, 'plugin' => null)),
            get_string('hubfilter' . $statusfilter, 'block_aiplugin_nav'),
            array('class' => $filter === $statusfilter ? 'active' : '')
        );
    }
    foreach (array_keys($categories) as $category) {
        echo html_writer::link(
            $stateurl('plugins', array('filter' => $category, 'plugin' => null)),
            ucfirst(str_replace('_', ' ', $category)),
            array('class' => $filter === $category ? 'active' : '')
        );
    }
    echo html_writer::end_div();

    if ($selectedcomponent !== '' && isset($catalogue[$selectedcomponent])) {
        $plugin = $catalogue[$selectedcomponent];
        echo html_writer::start_tag('section', array('class' => 'ainav-plugin-detail', 'aria-labelledby' => 'ainav-detail-title'));
        echo html_writer::tag('h2', format_string($plugin['name']), array('id' => 'ainav-detail-title'));
        echo html_writer::tag('p', s($plugin['description'] ?? $plugin['component']));
        echo html_writer::start_div('ainav-plugin-meta');
        echo html_writer::tag('span', get_string(
            $plugin['is_installed'] ? 'installed' : 'not_installed',
            'block_aiplugin_nav'
        ));
        echo html_writer::tag('span', s($plugin['component']));
        $version = $plugin['is_installed']
            ? $manager->get_plugin_version($plugin['plugin_type'], $plugin['plugin_name'])
            : null;
        echo html_writer::tag('span', get_string('hubversion', 'block_aiplugin_nav', $version ?: get_string('unknown')));
        echo html_writer::end_div();
        echo html_writer::start_div('ainav-plugin-actions');
        if ($plugin['is_installed'] && $canmanage) {
            if (!empty($plugin['settings_url'])) {
                echo html_writer::link(
                    new moodle_url($plugin['settings_url']),
                    get_string('settings', 'block_aiplugin_nav')
                );
            }
            if (!empty($plugin['page_url'])) {
                echo html_writer::link(
                    new moodle_url($plugin['page_url']),
                    get_string('hubmanage', 'block_aiplugin_nav')
                );
            } else if (!empty($plugin['goto_url'])) {
                echo html_writer::link(
                    new moodle_url($plugin['goto_url']),
                    get_string('hubmanage', 'block_aiplugin_nav')
                );
            }
            if (!empty($plugin['report_url'])) {
                echo html_writer::link(
                    new moodle_url($plugin['report_url']),
                    get_string('reports', 'block_aiplugin_nav')
                );
            }
            if ($plugin['component'] === 'local_rtocompliance') {
                echo html_writer::link(
                    new moodle_url('/admin/settings.php', array('section' => 'local_rtocompliance_certs')),
                    get_string('hubcertificatesettings', 'block_aiplugin_nav')
                );
            }
            if (in_array($plugin['component'], array('plagiarism_essayguard', 'plagiarism_docguard'), true)) {
                echo html_writer::link(
                    new moodle_url('/admin/settings.php', array('section' => 'manageplagiarismplugins')),
                    get_string('hubmanageplagiarism', 'block_aiplugin_nav')
                );
            }
        }
        $docsurl = $manager->get_plugin_docs_url($plugin['component']);
        if ($docsurl) {
            echo html_writer::link($docsurl, get_string('docs', 'block_aiplugin_nav'), array(
                'target' => '_blank',
                'rel' => 'noopener noreferrer',
            ));
        }
        if ($canmanage && $plugin['is_installed']) {
            echo html_writer::link(
                $stateurl('updates', array('plugin' => $plugin['component'])),
                get_string('hubreviewstatus', 'block_aiplugin_nav')
            );
        }
        echo html_writer::end_div();
        echo html_writer::end_tag('section');
    }

    $results = array_filter($catalogue, static function (array $plugin) use ($query, $filter): bool {
        $haystack = implode(' ', array(
            $plugin['name'],
            $plugin['component'],
            $plugin['category'] ?? '',
            $plugin['description'] ?? '',
            $plugin['access'] ?? '',
        ));
        if ($query !== '' && core_text::strpos(core_text::strtolower($haystack), core_text::strtolower($query)) === false) {
            return false;
        }
        if ($filter === 'installed' && !$plugin['is_installed']) {
            return false;
        }
        if ($filter === 'available' && $plugin['is_installed']) {
            return false;
        }
        return in_array($filter, array('all', 'installed', 'available'), true)
            || ($plugin['category'] ?? 'other') === $filter;
    });
    echo html_writer::tag('p', get_string('hubresultcount', 'block_aiplugin_nav', count($results)), array(
        'class' => 'ainav-result-count',
        'aria-live' => 'polite',
    ));
    echo html_writer::start_div('ainav-plugin-list');
    foreach ($results as $plugin) {
        $status = get_string($plugin['is_installed'] ? 'installed' : 'not_installed', 'block_aiplugin_nav');
        echo html_writer::start_tag('article', array('class' => 'ainav-plugin-summary'));
        echo html_writer::tag('h3', html_writer::link(
            $stateurl('plugins', array('plugin' => $plugin['component'])),
            format_string($plugin['name'])
        ));
        echo html_writer::tag('p', s($plugin['component']), array('class' => 'ainav-component'));
        echo html_writer::tag('span', $status, array(
            'class' => 'ainav-status ' . ($plugin['is_installed'] ? 'is-installed' : 'is-available'),
        ));
        echo html_writer::end_tag('article');
    }
    echo html_writer::end_div();
    if (empty($results)) {
        echo html_writer::tag('p', get_string('hubemptyplugins', 'block_aiplugin_nav'), array('class' => 'alert alert-info'));
    }
} else if ($view === 'reports') {
    $reports = $manager->get_links_registry()['tools']['items'];
    $reports = array_filter($reports, static function (array $report) use ($query, $canmanage): bool {
        if (!empty($report['capability']) && !$canmanage) {
            return false;
        }
        return $query === '' ||
            core_text::strpos(core_text::strtolower($report['name']), core_text::strtolower($query)) !== false;
    });
    $allcustomreports = $manager->get_custom_reports();
    $visiblecustomreports = array_filter(
        $allcustomreports,
        static function (array $report) use ($query): bool {
            return $query === '' ||
                core_text::strpos(
                    core_text::strtolower($report['name']),
                    core_text::strtolower($query)
                ) !== false;
        }
    );
    $reportcount = count($reports) + count($visiblecustomreports);
    echo html_writer::tag('p', get_string('hubresultcount', 'block_aiplugin_nav', $reportcount), array(
        'class' => 'ainav-result-count',
        'aria-live' => 'polite',
    ));
    echo html_writer::start_tag('ul', array('class' => 'ainav-hub-directory'));
    foreach ($reports as $report) {
        echo html_writer::tag('li', html_writer::link($report['url'], format_string($report['name'])));
    }
    echo html_writer::end_tag('ul');
    if (!empty($visiblecustomreports)) {
        echo html_writer::tag('h3', get_string('customreports', 'block_aiplugin_nav'));
        echo html_writer::start_tag('ul', array('class' => 'ainav-hub-directory'));
        foreach ($visiblecustomreports as $index => $report) {
            $deleteurl = $stateurl('reports', array('confirmdeletereport' => $index));
            echo html_writer::tag(
                'li',
                html_writer::link($report['url'], format_string($report['name']), array(
                    'target' => '_blank',
                    'rel' => 'noopener noreferrer',
                )) .
                html_writer::link(
                    $deleteurl,
                    get_string('delete_report', 'block_aiplugin_nav'),
                    array('class' => 'btn btn-secondary')
                )
            );
        }
        echo html_writer::end_tag('ul');
    }
    if ($reportcount === 0) {
        echo html_writer::tag('p', get_string('hubemptyreports', 'block_aiplugin_nav'), array('class' => 'alert alert-info'));
    }
    $confirmdeletereport = optional_param('confirmdeletereport', -1, PARAM_INT);
    if (isset($allcustomreports[$confirmdeletereport])) {
        $report = $allcustomreports[$confirmdeletereport];
        echo html_writer::start_tag('section', array(
            'class' => 'ainav-delete-confirmation',
            'aria-labelledby' => 'ainav-delete-report-title',
        ));
        echo html_writer::tag(
            'h3',
            get_string('delete_report', 'block_aiplugin_nav'),
            array('id' => 'ainav-delete-report-title')
        );
        echo html_writer::tag(
            'p',
            get_string('deletecustomreportconfirm', 'block_aiplugin_nav', format_string($report['name']))
        );
        echo html_writer::start_tag('form', array('method' => 'post'));
        echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'view', 'value' => 'reports'));
        echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'action', 'value' => 'deletereport'));
        echo html_writer::empty_tag('input', array(
            'type' => 'hidden',
            'name' => 'index',
            'value' => $confirmdeletereport,
        ));
        echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()));
        echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'q', 'value' => $query));
        echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'filter', 'value' => $filter));
        echo html_writer::tag(
            'button',
            get_string('delete_report', 'block_aiplugin_nav'),
            array('type' => 'submit', 'class' => 'btn btn-danger')
        );
        echo ' ' . html_writer::link(
            $stateurl('reports', array('confirmdeletereport' => null)),
            get_string('cancel', 'block_aiplugin_nav'),
            array('class' => 'btn btn-secondary')
        );
        echo html_writer::end_tag('form');
        echo html_writer::end_tag('section');
    }
    echo html_writer::tag('h3', get_string('create_report', 'block_aiplugin_nav'));
    echo html_writer::start_tag('form', array('method' => 'post', 'class' => 'ainav-custom-link-form'));
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'view', 'value' => 'reports'));
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'action', 'value' => 'addreport'));
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()));
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'q', 'value' => $query));
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'filter', 'value' => $filter));
    echo html_writer::label(get_string('report_name', 'block_aiplugin_nav'), 'ainav-report-name');
    echo html_writer::empty_tag('input', array(
        'id' => 'ainav-report-name',
        'name' => 'name',
        'required' => 'required',
    ));
    echo html_writer::label(get_string('report_url', 'block_aiplugin_nav'), 'ainav-report-url');
    echo html_writer::empty_tag('input', array(
        'id' => 'ainav-report-url',
        'name' => 'url',
        'type' => 'url',
        'required' => 'required',
    ));
    echo html_writer::tag('button', get_string('save_report', 'block_aiplugin_nav'), array(
        'type' => 'submit',
        'class' => 'btn btn-primary',
    ));
    echo html_writer::end_tag('form');
} else if ($view === 'updates') {
    echo html_writer::tag('h2', get_string('hubviewupdates', 'block_aiplugin_nav'));
    echo html_writer::tag('p', get_string('updatesintro', 'block_aiplugin_nav'));
    $updatefilter = in_array($filter, array('all', 'installed', 'available'), true) ? $filter : 'all';
    echo html_writer::start_div('ainav-hub-filters', array(
        'aria-label' => get_string('hubfilters', 'block_aiplugin_nav'),
    ));
    foreach (array('all', 'installed', 'available') as $statusfilter) {
        echo html_writer::link(
            $stateurl('updates', array('filter' => $statusfilter)),
            get_string('hubfilter' . $statusfilter, 'block_aiplugin_nav'),
            array('class' => $updatefilter === $statusfilter ? 'active' : '')
        );
    }
    echo html_writer::end_div();
    // Reuse the established update renderer and APIs so single and bulk behaviour remain unchanged.
    echo $manager->render_plugin_management_section();
    // The update renderer supplies markup; its established controls are registered separately.
    $manager->get_required_javascript();
    $updatequery = $query;
    if ($updatequery === '' && isset($catalogue[$selectedcomponent])) {
        $updatequery = $catalogue[$selectedcomponent]['name'];
    }
    $updatescript = 'var ainavUpdateSearch = document.getElementById("q");' .
        'if (ainavUpdateSearch && ' . json_encode($updatequery !== '') . ') {' .
            'ainavUpdateSearch.value = ' . json_encode($updatequery) . ';' .
            'ainavUpdateSearch.dispatchEvent(new Event("input", {bubbles: true}));' .
        '}';
    if ($updatefilter === 'installed' || $updatefilter === 'available') {
        $updatescript .= 'var ainavUpdateFilter = document.querySelector(' .
            json_encode('.ainav-fdr .chip[data-status="' . $updatefilter . '"]') . ');' .
            'if (ainavUpdateFilter) { ainavUpdateFilter.click(); }';
    }
    $PAGE->requires->js_init_code($updatescript);
} else if ($view === 'customlinks') {
    $sitequicklinks = array_filter(
        $manager->get_available_site_links(),
        static function (array $link) use ($query): bool {
            $haystack = ($link['name'] ?? '') . ' ' . ($link['groupname'] ?? '');
            return $query === '' ||
                core_text::strpos(
                    core_text::strtolower($haystack),
                    core_text::strtolower($query)
                ) !== false;
        }
    );
    $alllinks = $manager->get_custom_links();
    $links = array_filter($alllinks, static function (array $link) use ($query): bool {
        return $query === '' ||
            core_text::strpos(core_text::strtolower($link['name']), core_text::strtolower($query)) !== false;
    });
    echo html_writer::tag('h2', get_string('hubviewcustomlinks', 'block_aiplugin_nav'));
    echo html_writer::tag(
        'p',
        get_string('hubresultcount', 'block_aiplugin_nav', count($sitequicklinks) + count($links)),
        array('class' => 'ainav-result-count', 'aria-live' => 'polite')
    );
    if (!empty($sitequicklinks)) {
        echo html_writer::tag('h3', get_string('site_quick_links', 'block_aiplugin_nav'));
        echo html_writer::start_tag('ul', array('class' => 'ainav-hub-directory'));
        foreach ($sitequicklinks as $link) {
            echo html_writer::tag(
                'li',
                html_writer::link($link['url'], format_string($link['name'])) .
                html_writer::tag('span', format_string($link['groupname']), array('class' => 'ainav-component'))
            );
        }
        echo html_writer::end_tag('ul');
    }
    echo html_writer::tag('h3', get_string('customlinks', 'block_aiplugin_nav'));
    echo html_writer::start_tag('ul', array('class' => 'ainav-hub-directory'));
    foreach ($links as $index => $link) {
        $deleteurl = $stateurl('customlinks', array('confirmdelete' => $index));
        $editurl = $stateurl('customlinks', array('editlink' => $index));
        $controls = html_writer::start_div('ainav-directory-actions');
        $controls .= html_writer::link(
            $editurl,
            get_string('edit_link', 'block_aiplugin_nav'),
            array('class' => 'btn btn-secondary')
        );
        foreach (array('moveup', 'movedown') as $moveaction) {
            $disabled = ($moveaction === 'moveup' && $index === array_key_first($alllinks)) ||
                ($moveaction === 'movedown' && $index === array_key_last($alllinks));
            $controls .= html_writer::start_tag('form', array('method' => 'post'));
            $controls .= html_writer::empty_tag('input', array(
                'type' => 'hidden',
                'name' => 'view',
                'value' => 'customlinks',
            ));
            $controls .= html_writer::empty_tag('input', array(
                'type' => 'hidden',
                'name' => 'action',
                'value' => $moveaction,
            ));
            $controls .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'index', 'value' => $index));
            $controls .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()));
            $controls .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'q', 'value' => $query));
            $controls .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'filter', 'value' => $filter));
            $buttonattributes = array('type' => 'submit', 'class' => 'btn btn-secondary');
            if ($disabled) {
                $buttonattributes['disabled'] = 'disabled';
            }
            $controls .= html_writer::tag(
                'button',
                get_string($moveaction, 'block_aiplugin_nav'),
                $buttonattributes
            );
            $controls .= html_writer::end_tag('form');
        }
        $controls .= html_writer::link(
            $deleteurl,
            get_string('delete_link', 'block_aiplugin_nav'),
            array('class' => 'btn btn-secondary')
        );
        $controls .= html_writer::end_div();
        echo html_writer::tag('li',
            html_writer::link($link['url'], format_string($link['name']), array(
                'target' => '_blank',
                'rel' => 'noopener noreferrer',
            )) . $controls
        );
    }
    echo html_writer::end_tag('ul');
    if (empty($links) && empty($sitequicklinks)) {
        echo html_writer::tag('p', get_string('hubemptycustomlinks', 'block_aiplugin_nav'), array('class' => 'alert alert-info'));
    }
    $confirmdelete = optional_param('confirmdelete', -1, PARAM_INT);
    if (isset($alllinks[$confirmdelete])) {
        $link = $alllinks[$confirmdelete];
        echo html_writer::start_tag('section', array('class' => 'ainav-delete-confirmation', 'aria-labelledby' => 'ainav-delete-title'));
        echo html_writer::tag('h3', get_string('delete_link', 'block_aiplugin_nav'), array('id' => 'ainav-delete-title'));
        echo html_writer::tag('p', get_string('deletecustomlinkconfirm', 'block_aiplugin_nav', format_string($link['name'])));
        echo html_writer::start_tag('form', array('method' => 'post'));
        echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'view', 'value' => 'customlinks'));
        echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'action', 'value' => 'delete'));
        echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'index', 'value' => $confirmdelete));
        echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()));
        echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'q', 'value' => $query));
        echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'filter', 'value' => $filter));
        echo html_writer::tag('button', get_string('delete_link', 'block_aiplugin_nav'), array('type' => 'submit', 'class' => 'btn btn-danger'));
        echo ' ' . html_writer::link($stateurl('customlinks'), get_string('cancel', 'block_aiplugin_nav'), array('class' => 'btn btn-secondary'));
        echo html_writer::end_tag('form');
        echo html_writer::end_tag('section');
    }
    $editlink = optional_param('editlink', -1, PARAM_INT);
    $editing = isset($alllinks[$editlink]);
    $editname = $editing ? $alllinks[$editlink]['name'] : '';
    $editurl = $editing ? $alllinks[$editlink]['url'] : '';
    echo html_writer::tag(
        'h3',
        get_string($editing ? 'edit_link' : 'create_link', 'block_aiplugin_nav')
    );
    echo html_writer::start_tag('form', array('method' => 'post', 'class' => 'ainav-custom-link-form'));
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'view', 'value' => 'customlinks'));
    echo html_writer::empty_tag('input', array(
        'type' => 'hidden',
        'name' => 'action',
        'value' => $editing ? 'edit' : 'add',
    ));
    if ($editing) {
        echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'index', 'value' => $editlink));
    }
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()));
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'q', 'value' => $query));
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'filter', 'value' => $filter));
    echo html_writer::label(get_string('link_name', 'block_aiplugin_nav'), 'ainav-link-name');
    echo html_writer::empty_tag('input', array(
        'id' => 'ainav-link-name',
        'name' => 'name',
        'required' => 'required',
        'value' => $editname,
    ));
    echo html_writer::label(get_string('link_url', 'block_aiplugin_nav'), 'ainav-link-url');
    echo html_writer::empty_tag('input', array(
        'id' => 'ainav-link-url',
        'name' => 'url',
        'type' => 'url',
        'required' => 'required',
        'value' => $editurl,
    ));
    echo html_writer::tag('button', get_string($editing ? 'update_link' : 'save_link', 'block_aiplugin_nav'), array(
        'type' => 'submit',
        'class' => 'btn btn-primary',
    ));
    if ($editing) {
        echo html_writer::link(
            $stateurl('customlinks', array('editlink' => null)),
            get_string('cancel', 'block_aiplugin_nav'),
            array('class' => 'btn btn-secondary')
        );
    }
    echo html_writer::end_tag('form');
} else {
    echo html_writer::tag('h2', get_string('hubviewhelp', 'block_aiplugin_nav'));
    echo html_writer::tag('p', get_string('helpintro', 'block_aiplugin_nav'));
    $helpitems = array(
        array('url' => 'https://lms-labs.com', 'label' => get_string('visit_website', 'block_aiplugin_nav')),
        array('url' => 'https://lms-labs.com/pricing', 'label' => get_string('buy_credits', 'block_aiplugin_nav')),
        array(
            'url' => 'https://lms-labs.com/affiliate/signup',
            'label' => get_string('become_affiliate', 'block_aiplugin_nav'),
        ),
    );
    if (isset($master['local_moodlesupport']) &&
            $manager->is_plugin_installed('local', 'moodlesupport')) {
        $helpitems[] = array(
            'url' => $CFG->wwwroot . $master['local_moodlesupport']['page_url'],
            'label' => get_string('ai_moodle_support', 'block_aiplugin_nav'),
            'internal' => true,
        );
    }
    $helpitems = array_filter($helpitems, static function (array $item) use ($query): bool {
        return $query === '' ||
            core_text::strpos(core_text::strtolower($item['label']), core_text::strtolower($query)) !== false;
    });
    echo html_writer::tag('p', get_string('hubresultcount', 'block_aiplugin_nav', count($helpitems)), array(
        'class' => 'ainav-result-count',
        'aria-live' => 'polite',
    ));
    echo html_writer::start_div('ainav-help-grid');
    foreach ($helpitems as $item) {
        $label = $item['label'];
        if ($item['url'] === 'https://lms-labs.com/pricing') {
            $label .= ' ' . html_writer::span(
                get_string('balanceunavailable', 'block_aiplugin_nav'),
                'ainav-credits-badge'
            );
            $PAGE->requires->js_call_amd('block_aiplugin_nav/credits', 'init');
        }
        $attributes = !empty($item['internal']) ? array() : array(
            'target' => '_blank',
            'rel' => 'noopener noreferrer',
        );
        echo html_writer::link($item['url'], $label, $attributes);
    }
    echo html_writer::end_div();
    if (empty($helpitems)) {
        echo html_writer::tag('p', get_string('hubemptyhelp', 'block_aiplugin_nav'), array('class' => 'alert alert-info'));
    }
    echo html_writer::tag('h3', get_string('hubdiagnostics', 'block_aiplugin_nav'));
    echo html_writer::tag('dl',
        html_writer::tag('dt', get_string('installed', 'block_aiplugin_nav')) .
        html_writer::tag('dd', (string)$installedcount) .
        html_writer::tag('dt', get_string('hubmoodleversion', 'block_aiplugin_nav')) .
        html_writer::tag('dd', s($CFG->release))
    );
    if ($canmanage) {
        echo $manager->render_cache_management_section();
        $manager->get_required_javascript();
    }
}

echo html_writer::end_tag('main');
echo html_writer::end_div();
echo $OUTPUT->footer();