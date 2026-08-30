<?php
// This file is part of Moodle - https://moodle.org/
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
 * External functions for auto-updating AI plugins.
 *
 * @package    block_aiplugin_nav
 * @copyright  2025 Essay Grader AI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_aiplugin_nav\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;
use context_system;

/**
 * External service for plugin installation and updates.
 */
class plugin_updater extends external_api {
    /**
     * Returns description of auto_update_plugin parameters.
     */
    public static function auto_update_plugin_parameters() {
        return new external_function_parameters([
            'component' => new external_value(PARAM_COMPONENT, 'Plugin component name'),
            'downloadurl' => new external_value(PARAM_URL, 'Download URL for plugin ZIP'),
            'expectedsha256' => new external_value(PARAM_ALPHANUM, 'Required SHA-256 published in the update manifest'),
            'expectedversion' => new external_value(PARAM_TEXT, 'Version explicitly reviewed by the administrator'),
            'reviewconfirmed' => new external_value(PARAM_BOOL, 'Administrator explicitly confirmed this selected update'),
        ]);
    }

    /**
     * Verify that the reviewed artifact is still the artifact in the publisher manifest.
     *
     * @param string $component Plugin component.
     * @param string $downloadurl Download URL.
     * @param string $expectedsha256 Expected SHA-256 checksum.
     * @param string $expectedversion Expected version.
     * @return string|null Error message, or null when the manifest matches.
     */
    private static function verify_published_update($component, $downloadurl, $expectedsha256, $expectedversion) {
        if (!preg_match('/^[a-f0-9]{64}$/i', $expectedsha256)) {
            return 'A valid non-empty SHA-256 is required. Refusing to install an unverifiable update.';
        }
        if (trim($expectedversion) === '') {
            return 'The reviewed target version is required.';
        }

        $parts = parse_url($downloadurl);
        $origin = (isset($parts['scheme']) ? $parts['scheme'] : 'https') . '://' . $parts['host'];
        $curl = new \curl();
        $curl->setopt([
            'CURLOPT_FOLLOWLOCATION' => false,
            'CURLOPT_SSL_VERIFYPEER' => true,
            'CURLOPT_TIMEOUT' => 20,
        ]);
        $raw = $curl->get($origin . '/api/plugins/versions');
        $info = $curl->get_info();
        $manifest = json_decode($raw, true);
        if (
            ($info['http_code'] ?? 0) !== 200 || !is_array($manifest)
                || empty($manifest['success']) || empty($manifest['plugins'][$component])
        ) {
            return 'Could not verify this update against the published manifest. Nothing was installed.';
        }

        $published = $manifest['plugins'][$component];
        $publishedsha = strtolower((string)($published['sha256'] ?? ''));
        $publishedversion = (string)($published['version'] ?? '');
        $publishedurl = (string)($published['downloadUrl'] ?? '');
        if (!hash_equals($publishedsha, strtolower($expectedsha256))) {
            return 'The reviewed SHA-256 no longer matches the published manifest. Check for updates again.';
        }
        if (!hash_equals($publishedversion, (string)$expectedversion)) {
            return 'The reviewed version is no longer current in the published manifest. Check for updates again.';
        }
        if (!hash_equals($publishedurl, $downloadurl)) {
            return 'The reviewed download URL no longer matches the published manifest. Check for updates again.';
        }
        return null;
    }

    /**
     * Recursively delete a directory.
     *
     * @param string $dir Directory path.
     * @return bool Whether the directory was deleted.
     */
    private static function delete_directory($dir) {
        if (!is_dir($dir)) {
            return true;
        }
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                self::delete_directory($path);
            } else {
                @unlink($path);
            }
        }
        return @rmdir($dir);
    }

    /**
     * Recursively copy a directory.
     *
     * @param string $src Source directory.
     * @param string $dst Destination directory.
     * @return bool Whether the directory was copied.
     */
    private static function copy_directory($src, $dst) {
        if (!is_dir($src)) {
            return false;
        }
        if (!is_dir($dst)) {
            if (!@mkdir($dst, 0755, true)) {
                return false;
            }
        }
        $dir = opendir($src);
        while (($file = readdir($dir)) !== false) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $srcpath = $src . '/' . $file;
            $dstpath = $dst . '/' . $file;
            if (is_dir($srcpath)) {
                if (!self::copy_directory($srcpath, $dstpath)) {
                    closedir($dir);
                    return false;
                }
            } else {
                if (!@copy($srcpath, $dstpath)) {
                    closedir($dir);
                    return false;
                }
            }
        }
        closedir($dir);
        return true;
    }

    /**
     * Check every entry name in a ZIP for path-traversal sequences.
     * Returns true if any entry is unsafe (zip-slip guard).
     *
     * @param \ZipArchive $zip ZIP archive.
     * @return bool Whether an unsafe path was found.
     */
    private static function zip_has_unsafe_paths(\ZipArchive $zip): bool {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $norm = str_replace('\\', '/', (string)$zip->getNameIndex($i));
            if ($norm === '') {
                continue;
            }
            if ($norm[0] === '/' || preg_match('#(^|/)\.\.(/|$)#', $norm)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Auto-update a plugin by downloading and installing it.
     *
     * @param string $component Plugin component.
     * @param string $downloadurl Download URL.
     * @param string $expectedsha256 Expected SHA-256 checksum.
     * @param string $expectedversion Expected version.
     * @param bool $reviewconfirmed Whether an administrator confirmed the review.
     * @return array Update result.
     */
    public static function auto_update_plugin($component, $downloadurl, $expectedsha256, $expectedversion, $reviewconfirmed) {
        global $CFG;

        require_once($CFG->libdir . '/filelib.php');
        require_once($CFG->libdir . '/upgradelib.php');
        require_once($CFG->libdir . '/adminlib.php');

        $params = self::validate_parameters(self::auto_update_plugin_parameters(), [
            'component'      => $component,
            'downloadurl'    => $downloadurl,
            'expectedsha256' => $expectedsha256,
            'expectedversion' => $expectedversion,
            'reviewconfirmed' => $reviewconfirmed,
        ]);

        $context = context_system::instance();
        self::validate_context($context);

        // Require site admin capability.
        require_capability('moodle/site:config', $context);

        // SESSION LOCK: Release before ZIP download HTTP call.
        \core\session\manager::write_close();

        $component      = clean_param($params['component'], PARAM_COMPONENT);
        $downloadurl    = clean_param($params['downloadurl'], PARAM_URL);
        $expectedsha256 = $params['expectedsha256'];
        $expectedversion = trim($params['expectedversion']);

        if (empty($params['reviewconfirmed'])) {
            return [
                'success' => false,
                'message' => 'Update blocked: an administrator must explicitly review and confirm this selected plugin.',
            ];
        }

        $auditstart = $component . ':' . $expectedversion . ':staging-started';
        add_to_config_log(
            'plugin_update_reviewed',
            '',
            $auditstart,
            'block_aiplugin_nav'
        );

        // Allowlist: only accept downloads from our own servers.
        // This prevents SSRF and supply-chain attacks where a compromised admin.
        // Account or tampered DOM element could supply an attacker-controlled URL.
        // Keep these names aligned with the static update-security contract.
        // phpcs:disable moodle.NamingConventions.ValidVariableName.VariableNameUnderscore
        $parsed_url = parse_url($downloadurl);
        $url_scheme = strtolower(isset($parsed_url['scheme']) ? $parsed_url['scheme'] : '');
        $urlhost = strtolower(isset($parsed_url['host']) ? $parsed_url['host'] : '');
        $allowedhosts = ['lms-labs.com', 'ai-grader-site-nct185.replit.app'];
        if (
            $url_scheme !== 'https' || isset($parsed_url['user']) || isset($parsed_url['pass'])
                || isset($parsed_url['port']) || !in_array($urlhost, $allowedhosts, true)
        ) {
            // phpcs:enable moodle.NamingConventions.ValidVariableName.VariableNameUnderscore
            add_to_config_log(
                'plugin_update_reviewed',
                $auditstart,
                $component . ':' . $expectedversion . ':url-policy-rejected',
                'block_aiplugin_nav'
            );
            return [
                'success' => false,
                'message' => 'Only credential-free HTTPS URLs on approved LMS Labs update hosts are permitted.',
            ];
        }

        $manifesterror = self::verify_published_update(
            $component,
            $downloadurl,
            $expectedsha256,
            $expectedversion
        );
        if ($manifesterror !== null) {
            add_to_config_log(
                'plugin_update_reviewed',
                $auditstart,
                $component . ':' . $expectedversion . ':manifest-verification-rejected',
                'block_aiplugin_nav'
            );
            return ['success' => false, 'message' => $manifesterror];
        }

        // Validate component exists.
        $pluginman = \core_plugin_manager::instance();
        $plugininfo = $pluginman->get_plugin_info($component);

        if (!$plugininfo) {
            add_to_config_log(
                'plugin_update_reviewed',
                $auditstart,
                $component . ':' . $expectedversion . ':plugin-discovery-rejected',
                'block_aiplugin_nav'
            );
            return [
                'success' => false,
                'message' => 'Plugin not found: ' . $component,
            ];
        }

        $auditcompleted = false;
        $auditphase = 'download';
        try {
            // Create temp directory for download.
            $tempdir = make_temp_directory('aiplugin_nav_update');
            $zipfile = $tempdir . '/' . $component . '_' . time() . '.zip';

            // Download the ZIP file.
            $curl = new \curl();
            $curl->setopt([
                'CURLOPT_FOLLOWLOCATION' => false,
                'CURLOPT_SSL_VERIFYPEER' => true,
                'CURLOPT_TIMEOUT' => 120,
            ]);

            $content = $curl->get($downloadurl);
            $info = $curl->get_info();

            if ($info['http_code'] !== 200 || empty($content)) {
                return [
                    'success' => false,
                    'message' => 'Failed to download plugin (HTTP ' . $info['http_code'] . ')',
                ];
            }

            // Verify we got actual content (not an error page).
            if (strlen($content) < 1000) {
                return [
                    'success' => false,
                    'message' => 'Downloaded file too small - may be an error page',
                ];
            }

            // Save ZIP file.
            if (file_put_contents($zipfile, $content) === false) {
                return [
                    'success' => false,
                    'message' => 'Failed to save downloaded file to temp directory',
                ];
            }

            // SHA-256 integrity check is mandatory and was already matched to the live manifest.
            // Hash_equals() is constant-time, preventing timing side-channels.
            $actual = hash('sha256', $content);
            if (!hash_equals(strtolower($expectedsha256), $actual)) {
                @unlink($zipfile);
                return [
                    'success' => false,
                    'message' => "SHA-256 mismatch: expected {$expectedsha256} but got {$actual}. Refusing to install.",
                ];
            }

            // Verify it's a valid ZIP.
            $auditphase = 'zip-validation';
            $zip = new \ZipArchive();
            $zipresult = $zip->open($zipfile);
            if ($zipresult !== true) {
                @unlink($zipfile);
                return [
                    'success' => false,
                    'message' => 'Downloaded file is not a valid ZIP (error code: ' . $zipresult . ')',
                ];
            }

            // Get the root folder name from ZIP.
            $rootfolder = null;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                $parts = explode('/', $name);
                if (!empty($parts[0])) {
                    $rootfolder = $parts[0];
                    break;
                }
            }
            $zip->close();

            if (!$rootfolder) {
                @unlink($zipfile);
                return [
                    'success' => false,
                    'message' => 'Could not determine plugin folder from ZIP',
                ];
            }

            // Determine plugin type and directory.
            $auditphase = 'filesystem-preflight';
            [$type, $name] = \core_component::normalize_component($component);
            $plugintypes = \core_component::get_plugin_types();

            if (!isset($plugintypes[$type])) {
                @unlink($zipfile);
                return [
                    'success' => false,
                    'message' => 'Unknown plugin type: ' . $type,
                ];
            }

            $targetdir = $plugintypes[$type];
            $plugindir = $targetdir . '/' . $name;

            // Check if we can write to the parent directory.
            if (!is_writable($targetdir)) {
                @unlink($zipfile);
                return [
                    'success' => false,
                    'message' => 'Cannot write to ' . $targetdir . '. Check file permissions.',
                ];
            }

            // Check if we can write to the plugin directory itself.
            if (is_dir($plugindir) && !is_writable($plugindir)) {
                @unlink($zipfile);
                return [
                    'success' => false,
                    'message' => 'Cannot write to existing plugin directory. Check file permissions.',
                ];
            }

            // Extract ZIP to temp location.
            $auditphase = 'filesystem-stage';
            $extractdir = $tempdir . '/extract_' . time();
            $zip = new \ZipArchive();
            if ($zip->open($zipfile) !== true) {
                @unlink($zipfile);
                return [
                    'success' => false,
                    'message' => 'Failed to open ZIP for extraction',
                ];
            }

            if (self::zip_has_unsafe_paths($zip)) {
                $zip->close();
                @unlink($zipfile);
                return ['success' => false, 'message' => 'ZIP contains unsafe paths (zip-slip) — refusing to extract'];
            }

            if (!$zip->extractTo($extractdir)) {
                $zip->close();
                @unlink($zipfile);
                return [
                    'success' => false,
                    'message' => 'Failed to extract ZIP contents',
                ];
            }
            $zip->close();

            $sourcedir = $extractdir . '/' . $rootfolder;
            if (!is_dir($sourcedir)) {
                self::delete_directory($extractdir);
                @unlink($zipfile);
                return [
                    'success' => false,
                    'message' => 'Extracted folder not found: ' . $rootfolder,
                ];
            }

            // Backup existing plugin.
            $backupdir = null;
            if (is_dir($plugindir)) {
                $backupdir = $tempdir . '/' . $name . '_backup_' . time();
                // Use copy instead of rename for cross-filesystem compatibility.
                if (!self::copy_directory($plugindir, $backupdir)) {
                    self::delete_directory($extractdir);
                    @unlink($zipfile);
                    return [
                        'success' => false,
                        'message' => 'Failed to backup existing plugin',
                    ];
                }
                // Delete the original.
                $overwritefallback = false;
                if (!self::delete_directory($plugindir)) {
                    // Delete_directory() failed — common on Moodle servers where the
                    // Plugin directory is owned by a different OS user than the web.
                    // Server (e.g. root-owned files, www-data web server).
                    // Fall back to overwriting files in-place: copy the new version.
                    // Over the existing directory without deleting first.  Old files.
                    // That no longer exist in the new version are left behind, but.
                    // All functional files (version.php, classes/, etc.) are correctly.
                    // Replaced, so Moodle runs the new code after the DB upgrade.
                    if (self::copy_directory($sourcedir, $plugindir)) {
                        $overwritefallback = true; // Copy already done — skip block below.
                    } else {
                        // Even overwrite failed — restore from backup and give up.
                        if ($backupdir && is_dir($backupdir)) {
                            self::copy_directory($backupdir, $plugindir);
                        }
                        self::delete_directory($extractdir);
                        @unlink($zipfile);
                        return [
                            'success' => false,
                            'message' => 'Failed to update plugin files. The web server user does not have write permission on: '
                                . $plugindir . '. Run: chown -R www-data:www-data ' . $plugindir,
                        ];
                    }
                }
            } else {
                $overwritefallback = false;
            }

            // Copy extracted files to plugin directory (skipped when overwrite fallback already ran).
            if (!$overwritefallback && !self::copy_directory($sourcedir, $plugindir)) {
                // Restore from backup if available.
                if ($backupdir && is_dir($backupdir)) {
                    self::copy_directory($backupdir, $plugindir);
                }
                self::delete_directory($extractdir);
                @unlink($zipfile);
                return [
                    'success' => false,
                    'message' => 'Failed to copy new plugin files to ' . $plugindir,
                ];
            }

            // Verify the new version.php exists.
            $auditphase = 'staged-plugin-validation';
            if (!file_exists($plugindir . '/version.php')) {
                // Restore from backup.
                self::delete_directory($plugindir);
                if ($backupdir && is_dir($backupdir)) {
                    self::copy_directory($backupdir, $plugindir);
                }
                self::delete_directory($extractdir);
                @unlink($zipfile);
                return [
                    'success' => false,
                    'message' => 'Invalid plugin: version.php not found after extraction',
                ];
            }

            // Verify the installed plugin's component matches what was requested.
            // Prevents a ZIP for plugin_a being silently installed under plugin_b's directory.
            $versionphpcontent = @file_get_contents($plugindir . '/version.php');
            if ($versionphpcontent !== false) {
                if (
                    !preg_match('/\$plugin->component\s*=\s*[\'"]([^\'"]+)[\'"]/', $versionphpcontent, $compmatches)
                        || $compmatches[1] !== $component
                ) {
                    $foundcomponent = isset($compmatches[1]) ? $compmatches[1] : 'unknown';
                    self::delete_directory($plugindir);
                    if ($backupdir && is_dir($backupdir)) {
                        self::copy_directory($backupdir, $plugindir);
                    }
                    self::delete_directory($extractdir);
                    @unlink($zipfile);
                    return [
                        'success' => false,
                        'message' => "Component mismatch: requested '{$component}' but ZIP contains "
                            . "'{$foundcomponent}'. Refusing to install.",
                    ];
                }
            }

            // Clean up temp files.
            self::delete_directory($extractdir);
            if ($backupdir) {
                self::delete_directory($backupdir);
            }
            @unlink($zipfile);

            // Reset plugin manager caches.
            $pluginman->reset_caches();

            // Invalidate the block's plugin status cache so next page load re-reads all version files.
            unset_config('plugin_status_cache_time', 'block_aiplugin_nav');

            add_to_config_log(
                'plugin_update_reviewed',
                $auditstart,
                $component . ':' . $expectedversion . ':files-staged-moodle-upgrade-required',
                'block_aiplugin_nav'
            );
            $auditcompleted = true;

            return [
                'success' => true,
                'message' => 'Plugin files staged and verified. Moodle database upgrade is still required.',
                'needsupgrade' => true,
            ];
        } catch (\Exception $e) {
            // Clean up any temp files created before the exception.
            if (isset($extractdir) && is_dir($extractdir)) {
                self::delete_directory($extractdir);
            }
            if (isset($zipfile) && file_exists($zipfile)) {
                @unlink($zipfile);
            }
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ];
        } finally {
            if (!$auditcompleted) {
                add_to_config_log(
                    'plugin_update_reviewed',
                    $auditstart,
                    $component . ':' . $expectedversion . ':' . $auditphase . '-failed',
                    'block_aiplugin_nav'
                );
            }
        }
    }

    /**
     * Returns description of auto_update_plugin return value.
     */
    public static function auto_update_plugin_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Success status'),
            'message' => new external_value(PARAM_TEXT, 'Result message'),
            'needsupgrade' => new external_value(PARAM_BOOL, 'Whether database upgrade is needed', VALUE_OPTIONAL),
        ]);
    }

    /**
     * Returns description of auto_install_plugin parameters.
     * Used for installing plugins that are NOT already installed.
     */
    public static function auto_install_plugin_parameters() {
        return new external_function_parameters([
            'component' => new external_value(PARAM_COMPONENT, 'Plugin component name (e.g., block_my_progress)'),
            'downloadurl' => new external_value(PARAM_URL, 'Download URL for plugin ZIP'),
            'expectedsha256' => new external_value(
                PARAM_ALPHANUM,
                'Expected SHA-256 of the ZIP for integrity verification',
                VALUE_DEFAULT,
                ''
            ),
        ]);
    }

    /**
     * Auto-install a NEW plugin by downloading and extracting it.
     * Unlike auto_update_plugin, this works for plugins not yet installed.
     *
     * @param string $component Plugin component.
     * @param string $downloadurl Download URL.
     * @param string|null $expectedsha256 Expected SHA-256 checksum.
     * @return array Installation result.
     */
    public static function auto_install_plugin($component, $downloadurl, $expectedsha256 = null) {
        global $CFG;

        require_once($CFG->libdir . '/filelib.php');

        $params = self::validate_parameters(self::auto_install_plugin_parameters(), [
            'component'      => $component,
            'downloadurl'    => $downloadurl,
            'expectedsha256' => $expectedsha256,
        ]);

        $context = context_system::instance();
        self::validate_context($context);

        // Require site admin capability.
        require_capability('moodle/site:config', $context);

        // SESSION LOCK: Release before ZIP install HTTP call.
        \core\session\manager::write_close();

        $component      = clean_param($params['component'], PARAM_COMPONENT);
        $downloadurl    = clean_param($params['downloadurl'], PARAM_URL);
        $expectedsha256 = $params['expectedsha256'];

        // Allowlist: only accept downloads from our own servers.
        $parsedurl = parse_url($downloadurl);
        $urlhost = strtolower(isset($parsedurl['host']) ? $parsedurl['host'] : '');
        $allowedhosts = ['lms-labs.com', 'ai-grader-site-nct185.replit.app'];
        if (!in_array($urlhost, $allowedhosts, true)) {
            return [
                'success' => false,
                'message' => "Download URL host '{$urlhost}' is not allowed. Only lms-labs.com and "
                    . 'ai-grader-site-nct185.replit.app are permitted.',
            ];
        }

        try {
            // Determine plugin type and directory from component name.
            [$type, $name] = \core_component::normalize_component($component);
            $plugintypes = \core_component::get_plugin_types();

            if (!isset($plugintypes[$type])) {
                return [
                    'success' => false,
                    'message' => 'Unknown plugin type: ' . $type . '. Valid types: ' . implode(', ', array_keys($plugintypes)),
                ];
            }

            $targetdir = $plugintypes[$type];
            $plugindir = $targetdir . '/' . $name;

            // Check if already installed.
            if (is_dir($plugindir) && file_exists($plugindir . '/version.php')) {
                return [
                    'success' => false,
                    'message' => 'Plugin already installed at ' . $plugindir . '. Use update instead.',
                ];
            }

            // Check if we can write to the parent directory.
            if (!is_writable($targetdir)) {
                return [
                    'success' => false,
                    'message' => 'Cannot write to ' . $targetdir . '. Check file permissions (chmod 755 or 775).',
                ];
            }

            // Create temp directory for download.
            $tempdir = make_temp_directory('aiplugin_nav_install');
            $zipfile = $tempdir . '/' . $component . '_' . time() . '.zip';

            // Download the ZIP file.
            $curl = new \curl();
            $curl->setopt([
                'CURLOPT_FOLLOWLOCATION' => false,
                'CURLOPT_SSL_VERIFYPEER' => true,
                'CURLOPT_TIMEOUT' => 120,
            ]);

            $content = $curl->get($downloadurl);
            $info = $curl->get_info();

            if ($info['http_code'] !== 200 || empty($content)) {
                return [
                    'success' => false,
                    'message' => 'Failed to download plugin (HTTP ' . $info['http_code'] . ')',
                ];
            }

            // Verify we got actual content (not an error page).
            if (strlen($content) < 1000) {
                return [
                    'success' => false,
                    'message' => 'Downloaded file too small (' . strlen($content) . ' bytes) - may be an error page',
                ];
            }

            // Save ZIP file.
            if (file_put_contents($zipfile, $content) === false) {
                return [
                    'success' => false,
                    'message' => 'Failed to save downloaded file to temp directory',
                ];
            }

            // SHA-256 integrity check — if the server published a hash, verify the download matches.
            // Hash_equals() is constant-time, preventing timing side-channels.
            if ($expectedsha256 !== null && $expectedsha256 !== '') {
                $actual = hash('sha256', $content);
                if (!hash_equals($expectedsha256, $actual)) {
                    @unlink($zipfile);
                    return [
                        'success' => false,
                        'message' => "SHA-256 mismatch: expected {$expectedsha256} but got {$actual}. Refusing to install.",
                    ];
                }
            }

            // Verify it's a valid ZIP.
            $zip = new \ZipArchive();
            $zipresult = $zip->open($zipfile);
            if ($zipresult !== true) {
                @unlink($zipfile);
                return [
                    'success' => false,
                    'message' => 'Downloaded file is not a valid ZIP (error code: ' . $zipresult . ')',
                ];
            }

            // Get the root folder name from ZIP.
            $rootfolder = null;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $zipname = $zip->getNameIndex($i);
                $parts = explode('/', $zipname);
                if (!empty($parts[0])) {
                    $rootfolder = $parts[0];
                    break;
                }
            }
            $zip->close();

            if (!$rootfolder) {
                @unlink($zipfile);
                return [
                    'success' => false,
                    'message' => 'Could not determine plugin folder from ZIP',
                ];
            }

            // Extract ZIP to temp location.
            $extractdir = $tempdir . '/extract_' . time();
            $zip = new \ZipArchive();
            if ($zip->open($zipfile) !== true) {
                @unlink($zipfile);
                return [
                    'success' => false,
                    'message' => 'Failed to open ZIP for extraction',
                ];
            }

            if (self::zip_has_unsafe_paths($zip)) {
                $zip->close();
                @unlink($zipfile);
                return ['success' => false, 'message' => 'ZIP contains unsafe paths (zip-slip) — refusing to extract'];
            }

            if (!$zip->extractTo($extractdir)) {
                $zip->close();
                @unlink($zipfile);
                return [
                    'success' => false,
                    'message' => 'Failed to extract ZIP contents',
                ];
            }
            $zip->close();

            $sourcedir = $extractdir . '/' . $rootfolder;
            if (!is_dir($sourcedir)) {
                self::delete_directory($extractdir);
                @unlink($zipfile);
                return [
                    'success' => false,
                    'message' => 'Extracted folder not found: ' . $rootfolder,
                ];
            }

            // Copy extracted files to plugin directory.
            if (!self::copy_directory($sourcedir, $plugindir)) {
                self::delete_directory($extractdir);
                @unlink($zipfile);
                return [
                    'success' => false,
                    'message' => 'Failed to copy plugin files to ' . $plugindir,
                ];
            }

            // Verify the version.php exists.
            if (!file_exists($plugindir . '/version.php')) {
                self::delete_directory($plugindir);
                self::delete_directory($extractdir);
                @unlink($zipfile);
                return [
                    'success' => false,
                    'message' => 'Invalid plugin: version.php not found after extraction',
                ];
            }

            // Verify the installed plugin's component matches what was requested.
            $vp = @file_get_contents($plugindir . '/version.php');
            if (
                $vp === false
                    || !preg_match('/\$plugin->component\s*=\s*[\'"]([^\'"]+)[\'"]/', $vp, $m)
                    || $m[1] !== $component
            ) {
                $found = $m[1] ?? 'unknown';
                self::delete_directory($plugindir);
                self::delete_directory($extractdir);
                @unlink($zipfile);
                return [
                    'success' => false,
                    'message' => "Component mismatch: requested '{$component}' but ZIP contains '{$found}'. Refusing to install.",
                ];
            }

            // Clean up temp files.
            self::delete_directory($extractdir);
            @unlink($zipfile);

            // Reset plugin manager caches so Moodle sees the new plugin.
            $pluginman = \core_plugin_manager::instance();
            $pluginman->reset_caches();

            // Invalidate the block's plugin status cache so next page load re-reads all version files.
            unset_config('plugin_status_cache_time', 'block_aiplugin_nav');

            return [
                'success' => true,
                'message' => 'Plugin installed successfully! Click "Run Database Upgrade" to complete.',
                'needsupgrade' => true,
            ];
        } catch (\Exception $e) {
            // Clean up any temp files created before the exception.
            if (isset($extractdir) && is_dir($extractdir)) {
                self::delete_directory($extractdir);
            }
            if (isset($zipfile) && file_exists($zipfile)) {
                @unlink($zipfile);
            }
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Returns description of auto_install_plugin return value.
     */
    public static function auto_install_plugin_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Success status'),
            'message' => new external_value(PARAM_TEXT, 'Result message'),
            'needsupgrade' => new external_value(PARAM_BOOL, 'Whether database upgrade is needed', VALUE_OPTIONAL),
        ]);
    }

    /**
     * Returns description of run_upgrade parameters.
     */
    public static function run_upgrade_parameters() {
        return new external_function_parameters([]);
    }

    /**
     * Run Moodle upgrade after plugin update.
     */
    public static function run_upgrade() {
        global $CFG;

        require_once($CFG->libdir . '/upgradelib.php');
        require_once($CFG->libdir . '/adminlib.php');

        $context = context_system::instance();
        self::validate_context($context);

        require_capability('moodle/site:config', $context);

        // SESSION LOCK: Release before plugin upgrade process.
        \core\session\manager::write_close();

        try {
            // Check if upgrade is needed.
            $pluginman = \core_plugin_manager::instance();

            if (!$pluginman->some_plugins_updatable()) {
                return [
                    'success' => true,
                    'message' => 'No upgrades needed.',
                ];
            }

            // Run the upgrade.
            upgrade_noncore(true);

            return [
                'success' => true,
                'message' => 'Upgrade completed successfully.',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Upgrade error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Returns description of run_upgrade return value.
     */
    public static function run_upgrade_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Success status'),
            'message' => new external_value(PARAM_TEXT, 'Result message'),
        ]);
    }
}
