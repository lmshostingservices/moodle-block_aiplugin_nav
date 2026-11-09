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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Course Report helper for LMS Labs plugins.
 *
 * @copyright 2026 LMS Labs
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_aiplugin_nav\local\course_report;

defined('MOODLE_INTERNAL') || die();

/**
 * Consent-bound external guardian reports with hash-only signed link tokens.
 *
 * @package block_aiplugin_nav
 */
final class guardian_reports {
    /** @var int Confirmation links are short-lived and single-use. */
    private const CONFIRM_TTL = 1209600;
    /** @var int Report links expire after seven days. */
    private const REPORT_TTL = 604800;
    /** @var int Unsubscribe links remain valid for five years. */
    private const UNSUBSCRIBE_TTL = 157680000;

    /**
     * Return the next report date at 07:00 in the Moodle site timezone.
     *
     * @param string $frequency weekly, fortnightly, or monthly.
     * @param int $fromtime Timestamp to schedule from.
     * @param bool $aftersend Whether this is scheduling after a successful send.
     * @return int
     */
    public static function next_send_time(string $frequency, int $fromtime, bool $aftersend = false): int {
        if (!in_array($frequency, ['weekly', 'fortnightly', 'monthly'], true)) {
            return 0;
        }
        $timezone = \core_date::get_server_timezone_object();
        $now = (new \DateTimeImmutable('@' . $fromtime))->setTimezone($timezone);
        if ($frequency === 'monthly') {
            $next = $now->modify('first day of next month')->setTime(7, 0);
            while ((int)$next->format('N') !== 1) {
                $next = $next->modify('+1 day');
            }
            return $next->getTimestamp();
        }
        $next = $now->modify('monday this week')->setTime(7, 0);
        if ($next <= $now) {
            $next = $next->modify('+1 week');
        }
        if ($frequency === 'fortnightly' && $aftersend) {
            $next = $next->modify('+1 week');
        }
        return $next->getTimestamp();
    }

    /**
     * Create a high-entropy one-time token; only its SHA-256 digest is stored.
     *
     * @param int $linkid
     * @param string $purpose
     * @param int $ttl
     * @return string Raw token; callers must only put it in an emailed URL.
     */
    public static function create_token(int $linkid, string $purpose, int $ttl): string {
        global $DB;
        if (!in_array($purpose, ['confirm', 'report', 'unsubscribe'], true)) {
            throw new \invalid_parameter_exception('Invalid guardian-link token purpose.');
        }
        $now = time();
        $DB->execute(
            "UPDATE {block_aiplugin_nav_gtoken}
                SET revoked = 1
              WHERE linkid = :linkid AND purpose = :purpose AND revoked = 0",
            ['linkid' => $linkid, 'purpose' => $purpose]
        );
        $token = bin2hex(random_bytes(32));
        $DB->insert_record('block_aiplugin_nav_gtoken', (object)[
            'linkid' => $linkid,
            'purpose' => $purpose,
            'tokenhash' => hash('sha256', $token),
            'expires' => $now + $ttl,
            'revoked' => 0,
            'timecreated' => $now,
        ]);
        return $token;
    }

    /**
     * Find a valid token and its associated link without returning the raw token.
     *
     * @param string $token
     * @param string $purpose
     * @return array|null
     */
    public static function lookup_token(string $token, string $purpose): ?array {
        global $DB;
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }
        $record = $DB->get_record('block_aiplugin_nav_gtoken', [
            'tokenhash' => hash('sha256', $token),
            'purpose' => $purpose,
            'revoked' => 0,
        ]);
        if (!$record || (int)$record->expires <= time()) {
            return null;
        }
        $link = $DB->get_record('block_aiplugin_nav_guardlink', ['id' => $record->linkid]);
        if (!$link) {
            return null;
        }
        $guardian = $DB->get_record('block_aiplugin_nav_guardian', ['id' => $link->guardianid]);
        $student = $DB->get_record('user', ['id' => $link->studentid, 'deleted' => 0, 'suspended' => 0]);
        $course = $DB->get_record('course', ['id' => $link->courseid]);
        if (!$guardian || !$student || !$course) {
            return null;
        }
        return ['token' => $record, 'link' => $link, 'guardian' => $guardian, 'student' => $student, 'course' => $course];
    }

    /**
     * Consume a token once.
     *
     * @param int $tokenid
     * @return void
     */
    public static function consume_token(int $tokenid): void {
        global $DB;
        $DB->set_field('block_aiplugin_nav_gtoken', 'revoked', 1, ['id' => $tokenid]);
    }

    /**
     * Revoke all existing tokens for a link.
     *
     * @param int $linkid
     * @return void
     */
    public static function revoke_link_tokens(int $linkid): void {
        global $DB;
        $DB->set_field('block_aiplugin_nav_gtoken', 'revoked', 1, ['linkid' => $linkid]);
    }

    /**
     * Send a one-time confirmation request to an external guardian.
     *
     * @param \stdClass $guardian
     * @param \stdClass $link
     * @param \stdClass $student
     * @param \stdClass $course
     * @return bool
     */
    public static function send_confirmation(\stdClass $guardian, \stdClass $link, \stdClass $student, \stdClass $course): bool {
        $token = self::create_token((int)$link->id, 'confirm', self::CONFIRM_TTL);
        $url = (new \moodle_url('/blocks/aiplugin_nav/guardian_confirm.php', ['token' => $token]))->out(false);
        $subject = get_string('guardian_confirmation_subject', 'block_aiplugin_nav');
        $text = get_string('guardian_confirmation_text', 'block_aiplugin_nav', [
            'guardian' => trim($guardian->firstname . ' ' . $guardian->lastname),
            'student' => fullname($student),
            'course' => format_string($course->fullname),
            'url' => $url,
        ]);
        $html = \html_writer::tag('p', s(get_string('guardian_confirmation_intro', 'block_aiplugin_nav')))
            . \html_writer::tag('p', s(fullname($student) . ' · ' . format_string($course->fullname)))
            . \html_writer::link($url, get_string('guardian_confirm_button', 'block_aiplugin_nav'))
            . \html_writer::tag('p', s(get_string('guardian_confirmation_expiry', 'block_aiplugin_nav')));
        return self::send_mail($guardian, (int)$link->id, $subject, $text, $html);
    }

    /**
     * Send an email without storing email contents or signed tokens in the log.
     *
     * @param \stdClass $guardian
     * @param int|null $linkid
     * @param string $subject
     * @param string $text
     * @param string $html
     * @return bool
     */
    public static function send_mail(\stdClass $guardian, ?int $linkid, string $subject, string $text, string $html): bool {
        global $DB;
        $safeerror = null;
        $sent = false;
        try {
            $recipient = (object)[
                'id' => -1,
                'username' => 'external-guardian-' . (int)$guardian->id,
                'firstname' => (string)$guardian->firstname,
                'lastname' => (string)$guardian->lastname,
                'email' => (string)$guardian->email,
                'auth' => 'manual',
                'confirmed' => 1,
                'deleted' => 0,
                'suspended' => 0,
                'emailstop' => 0,
                'maildisplay' => 1,
                'mailformat' => 1,
                'lang' => (string)($guardian->language ?? 'en'),
                'timezone' => '99',
            ];
            $sent = email_to_user($recipient, \core_user::get_support_user(), $subject, $text, $html);
            if (!$sent) {
                $safeerror = 'mail_transport_failed';
            }
        } catch (\Throwable $e) {
            $safeerror = 'mail_transport_error';
        }
        $DB->insert_record('block_aiplugin_nav_gmail', (object)[
            'guardianid' => (int)$guardian->id,
            'linkid' => $linkid,
            'status' => $sent ? 'sent' : 'failed',
            'error' => $safeerror,
            'sentat' => $sent ? time() : 0,
            'timecreated' => time(),
        ]);
        return $sent;
    }

    /**
     * Build the privacy-minimised data sent to the parent-summary endpoint.
     *
     * The learner's name, guardian details, identifiers, marks and answers are
     * deliberately excluded. Only the course title and completion percentage
     * are sent for a teacher-requested draft.
     *
     * @param \stdClass $course
     * @param array $progress
     * @return array
     */
    public static function summary_metrics(\stdClass $course, array $progress): array {
        $percent = $progress['report']['percent'] ?? null;
        return [
            'courseName' => trim(format_string((string)$course->fullname)),
            'progressPercent' => is_numeric($percent) ? (int)round((float)$percent) : null,
        ];
    }

    /**
     * Fingerprint the exact course/progress context for a teacher-approved summary.
     *
     * @param array $metrics
     * @return string
     */
    public static function summary_hash(array $metrics): string {
        return hash('sha256', json_encode([
            'courseName' => (string)($metrics['courseName'] ?? ''),
            'progressPercent' => $metrics['progressPercent'] ?? null,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Request a zero-credit AI summary draft from LMS Labs.
     *
     * @param \stdClass $course
     * @param array $progress
     * @return string
     */
    public static function generate_summary(\stdClass $course, array $progress): string {
        global $CFG;
        $metrics = self::summary_metrics($course, $progress);
        if ($metrics['progressPercent'] === null) {
            throw new \moodle_exception('guardian_summary_progress_unavailable', 'block_aiplugin_nav');
        }
        require_once($CFG->dirroot . '/local/aiconfig/lib.php');
        $siteid = function_exists('local_aiconfig_get_siteid') ? local_aiconfig_get_siteid() : '';
        $apikey = function_exists('local_aiconfig_get_apikey') ? local_aiconfig_get_apikey() : '';
        if ($siteid === '' || $apikey === '') {
            throw new \moodle_exception('guardian_summary_not_configured', 'block_aiplugin_nav');
        }

        $apiurl = rtrim((string)(get_config('local_aiconfig', 'apiurl') ?: 'https://lms-labs.com'), '/');
        $curl = new \curl();
        $curl->setopt([
            'CURLOPT_TIMEOUT' => 30,
            'CURLOPT_CONNECTTIMEOUT' => 10,
            'CURLOPT_SSL_VERIFYPEER' => true,
        ]);
        $curl->setHeader(['Content-Type: application/json', 'Accept: application/json']);
        $response = $curl->post($apiurl . '/api/moodle/course-report/guardian-summary', json_encode([
            'siteId' => $siteid,
            'apiKey' => $apikey,
            'courses' => [$metrics],
        ]));
        $info = $curl->get_info();
        $decoded = json_decode((string)$response, true);
        if (
            (int)($info['http_code'] ?? 0) !== 200 || empty($decoded['success'])
            || empty($decoded['summary']) || !is_string($decoded['summary'])
        ) {
            throw new \moodle_exception('guardian_summary_unavailable', 'block_aiplugin_nav');
        }
        return trim($decoded['summary']);
    }

    /**
     * Keep the original single-course API while using the aggregate renderer.
     *
     * @param \stdClass $link
     * @param \stdClass $guardian
     * @param array $progress
     * @return array
     */
    public static function weekly_message(\stdClass $link, \stdClass $guardian, array $progress): array {
        return self::aggregate_weekly_message($guardian, [[
            'link' => $link,
            'progress' => $progress,
        ]]);
    }

    /**
     * Build one parent-safe email covering all currently subscribed courses.
     *
     * Each course keeps its own short-lived report and unsubscribe links. A
     * teacher-approved summary is included only while its progress fingerprint
     * still matches the current report data.
     *
     * @param \stdClass $guardian
     * @param array $entries Each entry contains link and progress records.
     * @return array
     */
    public static function aggregate_weekly_message(\stdClass $guardian, array $entries): array {
        if (!$entries) {
            throw new \invalid_parameter_exception('A guardian progress email needs at least one subscribed course.');
        }

        $text = [get_string('guardian_weekly_intro', 'block_aiplugin_nav')];
        $html = \html_writer::tag('p', s(get_string('guardian_weekly_intro', 'block_aiplugin_nav')));
        foreach ($entries as $entry) {
            $link = $entry['link'];
            $progress = $entry['progress'];
            $course = $progress['course'];
            $student = $progress['user'];
            $metrics = self::summary_metrics($course, $progress);
            $reporttoken = self::create_token((int)$link->id, 'report', self::REPORT_TTL);
            $unsubtoken = self::create_token((int)$link->id, 'unsubscribe', self::UNSUBSCRIBE_TTL);
            $reporturl = (new \moodle_url('/blocks/aiplugin_nav/guardian_report.php', ['token' => $reporttoken]))->out(false);
            $unsubscribeurl = (new \moodle_url('/blocks/aiplugin_nav/guardian_unsubscribe.php', ['token' => $unsubtoken]))->out(false);
            $studentname = fullname($student);
            $coursename = format_string((string)$course->fullname);
            $percent = $progress['report']['percent'] === null
                ? get_string('guardian_not_tracked', 'block_aiplugin_nav')
                : $progress['report']['percent'] . '%';
            $summary = trim((string)($link->weeklysummary ?? ''));
            $summaryhash = (string)($link->summaryhash ?? '');
            $summarycurrent = $metrics['progressPercent'] !== null
                && $summary !== '' && $summaryhash !== ''
                && hash_equals(self::summary_hash($metrics), $summaryhash);

            $text[] = $studentname . ' · ' . $coursename;
            $text[] = get_string('guardian_progress_label', 'block_aiplugin_nav') . ': ' . $percent;
            if ($summarycurrent) {
                $text[] = $summary;
            }
            $text[] = get_string('guardian_open_report', 'block_aiplugin_nav') . ': ' . $reporturl;
            $text[] = get_string('guardian_unsubscribe', 'block_aiplugin_nav') . ': ' . $unsubscribeurl;
            $text[] = '';

            $html .= \html_writer::tag(
                'section',
                \html_writer::tag('h3', s($studentname . ' · ' . $coursename))
                . \html_writer::tag('p', s(get_string('guardian_progress_label', 'block_aiplugin_nav') . ': ' . $percent))
                . ($summarycurrent ? \html_writer::tag('p', s($summary)) : '')
                . \html_writer::link($reporturl, get_string('guardian_open_report', 'block_aiplugin_nav'))
                . \html_writer::tag(
                    'p',
                    \html_writer::link(
                        $unsubscribeurl,
                        get_string('guardian_unsubscribe_course', 'block_aiplugin_nav', $coursename)
                    )
                ),
                ['class' => 'guardian-course-report']
            );
        }

        return [
            'subject' => get_string('guardian_weekly_subject_aggregate', 'block_aiplugin_nav'),
            'text' => implode("\n", $text),
            'html' => $html,
        ];
    }
}
