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
 * Attendance Report — site-wide summary for the Moodle Attendance plugin.
 *
 * Linked from the Quick Links block (block_aiplugin_nav) Reports dropdown.
 * Requires mod_attendance to be installed.
 *
 * @package    block_aiplugin_nav
 * @copyright  2026 Essay Grader AI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

require_login();
// Broadened from moodle/site:config so academic coordinators and managers can access.
require_capability('moodle/site:viewreports', context_system::instance());

// Verify mod_attendance is installed.
if (!array_key_exists('attendance', core_component::get_plugin_list('mod'))) {
    throw new moodle_exception('pluginnotinstalled', 'error', '', 'mod_attendance');
}

// ── Filters ────────────────────────────────────────────────────────────────.
$filtercourseid = optional_param('courseid', 0, PARAM_INT);
$filterfromstr = optional_param('from_str', '', PARAM_ALPHANUMEXT);
$filtertostr   = optional_param('to_str',   '', PARAM_ALPHANUMEXT);
$export          = optional_param('export', '', PARAM_ALPHA);
$filterfrom     = 0;
$filterto       = 0;

// Validate date strings strictly (YYYY-MM-DD only) — prevents silent strtotime() failures.
if ($filterfromstr && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterfromstr)) {
    [$fy, $fm, $fd] = explode('-', $filterfromstr);
    $filterfrom = mktime(0, 0, 0, (int)$fm, (int)$fd, (int)$fy);
}
if ($filtertostr && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filtertostr)) {
    [$ty, $tm, $td] = explode('-', $filtertostr);
    $filterto = mktime(23, 59, 59, (int)$tm, (int)$td, (int)$ty);
}

// ── Session-level WHERE clause (date only — applied to LEFT JOINs on sessions) ─
// Kept separate from the course filter because sessions use LEFT JOIN; putting
// The course filter here would accidentally exclude activities with no sessions.
$sessionjoincond = '';
$sessionparams    = [];
if ($filterfrom > 0) {
    $sessionjoincond    .= ' AND s.sessdate >= :sfrom';
    $sessionparams['sfrom'] = $filterfrom;
}
if ($filterto > 0) {
    $sessionjoincond   .= ' AND s.sessdate <= :sto';
    $sessionparams['sto'] = $filterto;
}

// ── Combined WHERE clause (date + course) — used in summary stats and at-risk ─
// All four summary stat queries JOIN attendance → course so both filters apply.
$combinedwhere  = '1=1';
$combinedparams = [];
if ($filterfrom > 0) {
    $combinedwhere          .= ' AND s.sessdate >= :sfrom';
    $combinedparams['sfrom'] = $filterfrom;
}
if ($filterto > 0) {
    $combinedwhere         .= ' AND s.sessdate <= :sto';
    $combinedparams['sto'] = $filterto;
}
if ($filtercourseid > 0) {
    $combinedwhere              .= ' AND a.course = :courseid';
    $combinedparams['courseid'] = $filtercourseid;
}

// ── Activity-level WHERE clause (course filter for the activities breakdown) ──.
$activitywhere  = '';
$activityparams = $sessionparams; // Date filter applied to session LEFT JOIN
if ($filtercourseid > 0) {
    $activitywhere                   = ' AND a.course = :act_courseid';
    $activityparams['act_courseid']  = $filtercourseid;
}

// ── Summary stats — ALL FOUR cards respect both date and course filters ──────.

// Total attendance activities.
$totalactivities = $filtercourseid > 0
    ? $DB->count_records('attendance', ['course' => $filtercourseid])
    : $DB->count_records('attendance');

// Total sessions.
$totalsessions = (int)$DB->count_records_sql(
    "SELECT COUNT(s.id)
       FROM {attendance_sessions} s
       JOIN {attendance} a ON a.id = s.attendanceid
      WHERE $combinedwhere",
    $combinedparams
);

// Total unique students with at least one attendance log.
$totalstudents = (int)$DB->count_records_sql(
    "SELECT COUNT(DISTINCT al.studentid)
       FROM {attendance_log} al
       JOIN {attendance_sessions} s ON s.id = al.sessionid
       JOIN {attendance} a          ON a.id = s.attendanceid
      WHERE $combinedwhere",
    $combinedparams
);

// Overall attendance rate — uses AVG(grade) which matches the attendance plugin's
// Own percentage calculation (grade is a 0–1 decimal set per status).
// Count(grade > 0) / Count(*) was wrong — it misclassified "Late" and "Excused" statuses.
$raterow = $DB->get_record_sql(
    "SELECT COUNT(al.id) AS total_logs, AVG(ast.grade) AS avg_grade
       FROM {attendance_log} al
       JOIN {attendance_sessions} s   ON s.id  = al.sessionid
       JOIN {attendance} a            ON a.id  = s.attendanceid
       JOIN {attendance_statuses} ast ON ast.id = al.statusid
      WHERE $combinedwhere",
    $combinedparams
);
$overallrate = ($raterow && $raterow->total_logs > 0)
    ? min(100.0, round((float)$raterow->avg_grade * 100, 1))
    : null;

// ── Course list for filter dropdown ─────────────────────────────────────────.
$coursesall = $DB->get_records_sql(
    "SELECT DISTINCT c.id, c.fullname
       FROM {course} c
       JOIN {attendance} a ON a.course = c.id
      ORDER BY c.fullname ASC"
);

// ── Pre-load all attendance course modules in ONE query (prevents N+1) ───────.
$modid = $DB->get_field('modules', 'id', ['name' => 'attendance']);
$cmmap = [];
if ($modid) {
    foreach ($DB->get_records('course_modules', ['module' => $modid], '', 'instance, id, course') as $row) {
        $cmmap[(int)$row->instance] = $row;
    }
}

// ── Per-activity breakdown table ─────────────────────────────────────────────
// Rate: AVG(grade) per activity (NULL when no logs → shown as N/A in PHP).
// ROUND() not used in SQL to avoid PostgreSQL NUMERIC cast issues.
$activities = $DB->get_records_sql(
    "SELECT
         a.id,
         a.name                                  AS activity_name,
         c.fullname                               AS course_name,
         c.id                                     AS course_id,
         COUNT(DISTINCT s.id)                     AS session_count,
         MAX(s.sessdate)                          AS last_session,
         COUNT(DISTINCT al.studentid)             AS student_count,
         COUNT(al.id)                             AS total_logs,
         AVG(ast.grade) * 100                     AS attendance_pct_raw
     FROM {attendance} a
     JOIN {course} c              ON c.id = a.course
     LEFT JOIN {attendance_sessions} s   ON s.attendanceid = a.id $sessionjoincond
     LEFT JOIN {attendance_log} al       ON al.sessionid  = s.id
     LEFT JOIN {attendance_statuses} ast ON ast.id        = al.statusid
     WHERE 1=1 $activitywhere
     GROUP BY a.id, a.name, c.fullname, c.id
     ORDER BY c.fullname ASC, a.name ASC",
    $activityparams
);

// ── At-risk students (attendance below 80%) ──────────────────────────────────.
$atriskparams              = $combinedparams;
$atriskparams['threshold'] = 80;
$atriskstudents = $DB->get_records_sql(
    "SELECT
         al.studentid                             AS id,
         u.firstname,
         u.lastname,
         u.email,
         COUNT(al.id)                             AS total_logs,
         AVG(ast.grade) * 100                     AS attendance_pct_raw
     FROM {attendance_log} al
     JOIN {attendance_sessions} s   ON s.id  = al.sessionid
     JOIN {attendance} a            ON a.id  = s.attendanceid
     JOIN {attendance_statuses} ast ON ast.id = al.statusid
     JOIN {user} u                  ON u.id  = al.studentid
     WHERE $combinedwhere
     GROUP BY al.studentid, u.firstname, u.lastname, u.email
     HAVING AVG(ast.grade) * 100 < :threshold
     ORDER BY AVG(ast.grade) ASC",
    $atriskparams,
    0,   // Limitfrom — Moodle-compliant cross-DB row limiting
    100  // Limitnum
);

// ── Recent sessions (last 20) ────────────────────────────────────────────────.
$recentwhereextra = $filtercourseid > 0 ? ' AND a.course = :rc_courseid' : '';
$recentparams      = $sessionparams;
if ($filtercourseid > 0) {
    $recentparams['rc_courseid'] = $filtercourseid;
}
// LIMIT not used in SQL — use get_records_sql $limitnum for cross-DB compatibility.
$recentsessions = $DB->get_records_sql(
    "SELECT
         s.id,
         s.sessdate,
         a.name                           AS activity_name,
         c.fullname                        AS course_name,
         c.id                              AS course_id,
         COUNT(al.id)                      AS log_count,
         AVG(ast.grade) * 100              AS attendance_pct_raw
     FROM {attendance_sessions} s
     JOIN {attendance} a          ON a.id = s.attendanceid
     JOIN {course} c              ON c.id = a.course
     LEFT JOIN {attendance_log} al     ON al.sessionid = s.id
     LEFT JOIN {attendance_statuses} ast ON ast.id     = al.statusid
     WHERE 1=1 $sessionjoincond $recentwhereextra
     GROUP BY s.id, s.sessdate, a.name, c.fullname, c.id
     ORDER BY s.sessdate DESC",
    $recentparams,
    0,   // Limitfrom
    20   // Limitnum
);

// ── CSV export ───────────────────────────────────────────────────────────────.
if ($export === 'csv') {
    $filename = 'attendance_report_' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');

    // Summary.
    fputcsv($out, ['Attendance Report Summary']);
    fputcsv($out, ['Generated', date('Y-m-d H:i:s')]);
    $coursefilterlabel = $filtercourseid > 0 && isset($coursesall[$filtercourseid])
        ? $coursesall[$filtercourseid]->fullname
        : 'All courses';
    fputcsv($out, ['Course Filter', $coursefilterlabel]);
    fputcsv($out, ['Date From', $filterfromstr ?: 'All dates']);
    fputcsv($out, ['Date To', $filtertostr ?: 'All dates']);
    fputcsv($out, []);
    fputcsv($out, ['Activities', 'Sessions', 'Students Tracked', 'Overall Attendance %']);
    fputcsv($out, [$totalactivities, $totalsessions, $totalstudents, $overallrate !== null ? $overallrate . '%' : 'N/A']);
    fputcsv($out, []);

    // Per-activity breakdown.
    fputcsv($out, ['ACTIVITIES BY COURSE']);
    fputcsv($out, ['Course', 'Activity', 'Sessions', 'Students', 'Attendance %', 'Last Session']);
    foreach ($activities as $act) {
        $pct = $act->attendance_pct_raw !== null ? round((float)$act->attendance_pct_raw, 1) . '%' : 'N/A';
        $last = $act->last_session ? date('Y-m-d', $act->last_session) : '';
        fputcsv($out, [$act->course_name, $act->activity_name, $act->session_count, $act->student_count, $pct, $last]);
    }
    fputcsv($out, []);

    // At-risk students.
    fputcsv($out, ['AT-RISK STUDENTS (below 80%)']);
    fputcsv($out, ['First Name', 'Last Name', 'Email', 'Sessions Logged', 'Attendance %']);
    foreach ($atriskstudents as $stu) {
        $pct = $stu->attendance_pct_raw !== null ? round((float)$stu->attendance_pct_raw, 1) . '%' : 'N/A';
        fputcsv($out, [$stu->firstname, $stu->lastname, $stu->email, $stu->total_logs, $pct]);
    }
    fputcsv($out, []);

    // Recent sessions.
    fputcsv($out, ['RECENT SESSIONS (last 20)']);
    fputcsv($out, ['Date', 'Course', 'Activity', 'Logs', 'Attendance %']);
    foreach ($recentsessions as $sess) {
        $pct = $sess->attendance_pct_raw !== null ? round((float)$sess->attendance_pct_raw, 1) . '%' : 'N/A';
        fputcsv($out, [date('Y-m-d', $sess->sessdate), $sess->course_name, $sess->activity_name, $sess->log_count, $pct]);
    }

    fclose($out);
    exit;
}

// ── Helper functions ─────────────────────────────────────────────────────────.
function attendance_pct_badge($pctraw) {
    if ($pctraw === null || $pctraw === '' || $pctraw === false) {
        return '<span class="atnrpt-badge atnrpt-badge-neutral">N/A</span>';
    }
    // Clamp to 100 — older attendance plugin versions may store grade as 0-100 integer
    // Instead of 0-1 decimal; without clamping, AVG(grade)*100 would return 10000%.
    $pct = min(100.0, round((float)$pctraw, 1));
    $cls = $pct >= 80 ? 'atnrpt-badge-good' : ($pct >= 60 ? 'atnrpt-badge-warn' : 'atnrpt-badge-poor');
    return '<span class="atnrpt-badge ' . $cls . '">' . number_format($pct, 1) . '%</span>';
}

// Build the CSV export URL preserving current filters.
$csvurl = new moodle_url('/blocks/aiplugin_nav/attendance_report.php', [
    'courseid' => $filtercourseid,
    'from_str' => $filterfromstr,
    'to_str'   => $filtertostr,
    'export'   => 'csv',
]);
$clearurl = new moodle_url('/blocks/aiplugin_nav/attendance_report.php');

// ── Page setup ───────────────────────────────────────────────────────────────.
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/blocks/aiplugin_nav/attendance_report.php'));
$PAGE->set_title('Attendance Report');
$PAGE->set_heading('Attendance Report');
$PAGE->set_pagelayout('admin');

// ── Output ───────────────────────────────────────────────────────────────────.
echo $OUTPUT->header();
?>
<style>
.atnrpt-wrap {
    max-width: 1100px;
    margin: 0 auto;
    padding: 24px 16px 48px;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}
.atnrpt-page-title {
    font-size: 22px;
    font-weight: 700;
    color: #1e293b;
    margin: 0 0 4px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.atnrpt-page-title svg { color: #6366f1; }
.atnrpt-subtitle { color: #64748b; font-size: 13px; margin: 0 0 24px; }
/* Stat cards */
.atnrpt-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 16px;
    margin-bottom: 28px;
}
.atnrpt-stat-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 20px 20px 16px;
}
.atnrpt-stat-label {
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .05em;
    color: #94a3b8;
    margin-bottom: 6px;
}
.atnrpt-stat-value {
    font-size: 30px;
    font-weight: 800;
    color: #1e293b;
    line-height: 1;
}
.atnrpt-stat-value.rate-good { color: #16a34a; }
.atnrpt-stat-value.rate-warn { color: #d97706; }
.atnrpt-stat-value.rate-poor { color: #dc2626; }
.atnrpt-stat-note { font-size: 11px; color: #94a3b8; margin-top: 4px; }
/* Filter bar */
.atnrpt-filter-bar {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 16px 20px;
    margin-bottom: 28px;
    display: flex;
    flex-wrap: wrap;
    gap: 14px;
    align-items: flex-end;
}
.atnrpt-filter-group { display: flex; flex-direction: column; gap: 4px; }
.atnrpt-filter-label {
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: #64748b;
}
.atnrpt-filter-bar select,
.atnrpt-filter-bar input[type="date"] {
    height: 36px;
    padding: 0 10px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    font-size: 13px;
    background: #fff;
    color: #1e293b;
    min-width: 150px;
}
.atnrpt-filter-bar input[type="date"] { min-width: 130px; }
.atnrpt-filter-btn {
    height: 36px;
    padding: 0 18px;
    background: #6366f1;
    color: #fff;
    border: none;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    align-self: flex-end;
}
.atnrpt-filter-btn:hover { background: #4f46e5; }
.atnrpt-csv-btn {
    height: 36px;
    padding: 0 16px;
    background: #fff;
    color: #374151;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    align-self: flex-end;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.atnrpt-csv-btn:hover { background: #f8fafc; text-decoration: none; }
.atnrpt-clear-link {
    align-self: flex-end;
    font-size: 12px;
    color: #64748b;
    text-decoration: none;
    height: 36px;
    display: flex;
    align-items: center;
    padding: 0 4px;
}
.atnrpt-clear-link:hover { color: #1e293b; }
/* Section title */
.atnrpt-section-title { font-size: 15px; font-weight: 700; color: #1e293b; margin: 0 0 12px; }
.atnrpt-section-subtitle { font-size: 12px; color: #64748b; margin: -8px 0 12px; }
/* Tables */
.atnrpt-table-wrap {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    overflow: hidden;
    margin-bottom: 32px;
}
.atnrpt-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.atnrpt-table th {
    background: #f8fafc;
    padding: 10px 14px;
    text-align: left;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .05em;
    color: #64748b;
    border-bottom: 1px solid #e2e8f0;
    white-space: nowrap;
}
.atnrpt-table td {
    padding: 10px 14px;
    border-bottom: 1px solid #f1f5f9;
    color: #334155;
    vertical-align: middle;
}
.atnrpt-table tr:last-child td { border-bottom: none; }
.atnrpt-table tr:hover td { background: #f8fafc; }
.atnrpt-table .course-link { color: #6366f1; text-decoration: none; font-weight: 600; }
.atnrpt-table .course-link:hover { text-decoration: underline; }
.atnrpt-table .text-muted { color: #94a3b8; font-size: 12px; }
.atnrpt-empty { padding: 40px 20px; text-align: center; color: #94a3b8; font-size: 14px; }
/* Badges */
.atnrpt-badge { display: inline-block; padding: 2px 8px; border-radius: 20px; font-size: 12px; font-weight: 700; }
.atnrpt-badge-good    { background: #f0fdf4; color: #15803d; }
.atnrpt-badge-warn    { background: #fffbeb; color: #b45309; }
.atnrpt-badge-poor    { background: #fef2f2; color: #dc2626; }
.atnrpt-badge-neutral { background: #f1f5f9; color: #64748b; }
/* At-risk section */
.atnrpt-atrisk-banner {
    background: #fff7ed;
    border: 1px solid #fed7aa;
    border-radius: 8px;
    padding: 12px 16px;
    margin-bottom: 12px;
    font-size: 13px;
    color: #92400e;
    display: flex;
    align-items: center;
    gap: 8px;
}
</style>

<div class="atnrpt-wrap">

    <h1 class="atnrpt-page-title">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
            stroke-linecap="round" stroke-linejoin="round">
            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
            <path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
        </svg>
        Attendance Report
    </h1>
    <p class="atnrpt-subtitle">Site-wide attendance summary from all Moodle™ Attendance plugin
        activities. All filters apply to every section on this page.</p>

    <!-- Filter bar -->
    <form method="get" action="">
        <div class="atnrpt-filter-bar">
            <div class="atnrpt-filter-group">
                <span class="atnrpt-filter-label">Course</span>
                <select name="courseid">
                    <option value="0">All courses</option>
                    <?php foreach ($coursesall as $c): ?>
                        <option value="<?php echo (int)$c->id; ?>" <?php echo ($filtercourseid == $c->id ? 'selected' : ''); ?>>
                            <?php echo htmlspecialchars($c->fullname, ENT_QUOTES); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="atnrpt-filter-group">
                <span class="atnrpt-filter-label">From</span>
                <input type="date" name="from_str"
                    value="<?php echo htmlspecialchars($filterfromstr, ENT_QUOTES); ?>">
            </div>
            <div class="atnrpt-filter-group">
                <span class="atnrpt-filter-label">To</span>
                <input type="date" name="to_str"
                    value="<?php echo htmlspecialchars($filtertostr, ENT_QUOTES); ?>">
            </div>
            <button type="submit" class="atnrpt-filter-btn">Apply</button>
            <?php if ($filtercourseid || $filterfrom || $filterto): ?>
                <a href="<?php echo $clearurl->out(); ?>" class="atnrpt-clear-link">Clear filters</a>
            <?php endif; ?>
        </div>
    </form>

    <!-- Summary stats — respect both course and date filters -->
    <div class="atnrpt-stats">
        <div class="atnrpt-stat-card">
            <div class="atnrpt-stat-label">Activities</div>
            <div class="atnrpt-stat-value"><?php echo (int)$totalactivities; ?></div>
        </div>
        <div class="atnrpt-stat-card">
            <div class="atnrpt-stat-label">Sessions</div>
            <div class="atnrpt-stat-value"><?php echo (int)$totalsessions; ?></div>
        </div>
        <div class="atnrpt-stat-card">
            <div class="atnrpt-stat-label">Students Logged</div>
            <div class="atnrpt-stat-value"><?php echo (int)$totalstudents; ?></div>
            <div class="atnrpt-stat-note">Students with at least one attendance record</div>
        </div>
        <div class="atnrpt-stat-card">
            <div class="atnrpt-stat-label">Overall Attendance</div>
            <?php if ($overallrate !== null): ?>
                <?php $ratecls = $overallrate >= 80 ? 'rate-good' : ($overallrate >= 60 ? 'rate-warn' : 'rate-poor'); ?>
                <div class="atnrpt-stat-value <?php echo $ratecls; ?>"><?php echo number_format($overallrate, 1); ?>%</div>
            <?php else: ?>
                <div class="atnrpt-stat-value" style="font-size:18px;color:#94a3b8;">No data</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Per-activity breakdown -->
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;margin-bottom:12px;">
        <div class="atnrpt-section-title" style="margin:0;">Activities by Course</div>
        <a href="<?php echo $csvurl->out(); ?>" class="atnrpt-csv-btn">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                <polyline points="7 10 12 15 17 10"/>
                <line x1="12" y1="15" x2="12" y2="3"/>
            </svg>
            Export CSV
        </a>
    </div>
    <div class="atnrpt-table-wrap">
        <?php if (empty($activities)): ?>
            <div class="atnrpt-empty">No attendance activities found.</div>
        <?php else: ?>
            <table class="atnrpt-table">
                <thead>
                    <tr>
                        <th>Course</th>
                        <th>Activity</th>
                        <th>Sessions</th>
                        <th>Students</th>
                        <th>Attendance Rate</th>
                        <th>Last Session</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($activities as $act): ?>
                        <?php
                            // CM looked up from pre-loaded map — no per-row DB query.
                            $cm         = $cmmap[(int)$act->id] ?? null;
                            $cmurl     = $cm ? ($CFG->wwwroot . '/mod/attendance/view.php?id=' . $cm->id)   : null;
                            $reporturl = $cm ? ($CFG->wwwroot . '/mod/attendance/report.php?id=' . $cm->id) : null;
                        ?>
                        <tr>
                            <td>
                                <a class="course-link"
                                   href="<?php echo $CFG->wwwroot; ?>/course/view.php?id=<?php echo (int)$act->course_id; ?>">
                                    <?php echo htmlspecialchars($act->course_name, ENT_QUOTES); ?>
                                </a>
                            </td>
                            <td><?php echo htmlspecialchars($act->activity_name, ENT_QUOTES); ?></td>
                            <td><?php echo (int)$act->session_count; ?></td>
                            <td><?php echo (int)$act->student_count; ?></td>
                            <td><?php echo attendance_pct_badge($act->attendance_pct_raw); ?></td>
                            <td>
                                <?php if ($act->last_session): ?>
                                    <?php echo userdate((int)$act->last_session, get_string('strftimedate', 'langconfig')); ?>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($reporturl): ?>
                                    <a href="<?php echo htmlspecialchars($reporturl, ENT_QUOTES); ?>"
                                       class="course-link" style="font-size:12px;">View report</a>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- At-risk students -->
    <div class="atnrpt-section-title">At-Risk Students</div>
    <p class="atnrpt-section-subtitle">Students with overall attendance below 80% based on current filters.</p>
    <?php if (!empty($atriskstudents)): ?>
        <div class="atnrpt-atrisk-banner">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                stroke-linecap="round" stroke-linejoin="round">
                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                <line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
            </svg>
            <?php echo count($atriskstudents); ?> student<?php echo count($atriskstudents) !== 1 ? 's' : ''; ?>
            below 80% attendance threshold — contact for intervention.
        </div>
    <?php endif; ?>
    <div class="atnrpt-table-wrap">
        <?php if (empty($atriskstudents)): ?>
            <div class="atnrpt-empty">
                <?php echo $totalstudents > 0 ? 'All tracked students are above 80% a' .
                    'ttendance.' : 'No attendance data found for current filters.'; ?>
            </div>
        <?php else: ?>
            <table class="atnrpt-table">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Email</th>
                        <th>Sessions Logged</th>
                        <th>Attendance Rate</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($atriskstudents as $stu): ?>
                        <tr>
                            <td>
                                <a class="course-link"
                                   href="<?php echo $CFG->wwwroot; ?>/user/view.php?id=<?php echo (int)$stu->id; ?>">
                                    <?php echo htmlspecialchars(fullname($stu), ENT_QUOTES); ?>
                                </a>
                            </td>
                            <td><a href="mailto:<?php echo htmlspecialchars($stu->email, ENT_QUOTES); ?>"
                                   style="color:#64748b;font-size:12px;">
                                <?php echo htmlspecialchars($stu->email, ENT_QUOTES); ?></a>
                            </td>
                            <td><?php echo (int)$stu->total_logs; ?></td>
                            <td><?php echo attendance_pct_badge($stu->attendance_pct_raw); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Recent sessions -->
    <div class="atnrpt-section-title">Recent Sessions (last 20)</div>
    <div class="atnrpt-table-wrap">
        <?php if (empty($recentsessions)): ?>
            <div class="atnrpt-empty">No sessions found.</div>
        <?php else: ?>
            <table class="atnrpt-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Course</th>
                        <th>Activity</th>
                        <th>Total Logs</th>
                        <th>Rate</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentsessions as $sess): ?>
                        <tr>
                            <td style="white-space:nowrap;">
                                <?php echo userdate((int)$sess->sessdate, get_string('strftimedate', 'langconfig')); ?>
                            </td>
                            <td>
                                <a class="course-link"
                                   href="<?php echo $CFG->wwwroot; ?>/course/view.php?id=<?php echo (int)$sess->course_id; ?>">
                                    <?php echo htmlspecialchars($sess->course_name, ENT_QUOTES); ?>
                                </a>
                            </td>
                            <td><?php echo htmlspecialchars($sess->activity_name, ENT_QUOTES); ?></td>
                            <td><?php echo (int)$sess->log_count; ?></td>
                            <td><?php echo attendance_pct_badge($sess->attendance_pct_raw); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

</div>

<?php echo $OUTPUT->footer(); ?>
