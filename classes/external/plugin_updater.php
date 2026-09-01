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
 * External services that download, install and upgrade plugins for this block.
 *
 * @package    block_aiplugin_nav
 * @copyright  2025 Essay Grader AI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class plugin_updater extends external_api {
    /**
     * Returns description of auto_update_plugin parameters.
     */
    public static function auto_update_plugin_parameters() {
        return new external_function_parameters([
            'component' => new external_value(PARAM_COMPONENT, 'Plugin component name'),
            'downloadurl' => new external_value(PARAM_URL, 'Download URL for plugin ZIP'),
            'expectedsha256' => new external_value(PARAM_ALPHANUM, 'Expected SHA-256 of the ZIP for integrity ve' .
                'rification', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Recursively delete a directory.
     *
     * @param string $dir The directory to remove.
     * @return bool True when the directory was removed.
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
     * @param string $src The directory to copy from.
     * @param string $dst The directory to copy to.
     * @return bool True when every file copied successfully.
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
     * @param \ZipArchive $zip The opened archive to inspect.
     * @return bool True when any entry name is unsafe.
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
     * @param string $component The frankenstyle component name.
     * @param string $downloadurl The URL of the plugin ZIP.
     * @param string|null $expectedsha256 The expected SHA-256 of the ZIP, when known.
     * @return array The web service result.
     */
    public static function auto_update_plugin($component, $downloadurl, $expectedsha256 = null) {
        global $CFG;

        require_once($CFG->libdir . '/filelib.php');
        require_once($CFG->libdir . '/upgradelib.php');

        $params = self::validate_parameters(self::auto_update_plugin_parameters(), [
            'component'      => $component,
            'downloadurl'    => $downloadurl,
            'expectedsha256' => $expectedsha256,
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

        // Allowlist: only accept downloads from our own servers.
        // This prevents SSRF and supply-chain attacks where a compromised admin
        // account or tampered DOM element could supply an attacker-controlled URL.
        $parsedurl = parse_url($downloadurl);
        $urlhost = strtolower(isset($parsedurl['host']) ? $parsedurl['host'] : '');
        $allowedhosts = ['lms-labs.com', 'ai-grader-site-nct185.replit.app'];
        if (!in_array($urlhost, $allowedhosts, true)) {
            return [
                'success' => false,
                'message' => "Download URL host '{$urlhost}' is not allowed. "
                    . "Only lms-labs.com and ai-grader-site-nct185.replit.app are permitted.",
            ];
        }

        // Validate component exists.
        $pluginman = \core_plugin_manager::instance();
        $plugininfo = $pluginman->get_plugin_info($component);

        if (!$plugininfo) {
            return [
                'success' => false,
                'message' => 'Plugin not found: ' . $component,
            ];
        }

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

            // SHA-256 integrity check — if the server published a hash, verify the download matches.
            // hash_equals() is constant-time, preventing timing side-channels.
            if ($expectedsha256 !== null && $expectedsha256 !== '') {
                $actual = hash('sha256', $content);
                if (!hash_equals($expectedsha256, $actual)) {
                    @unlink($zipfile);
                    return ['success' => false,
                        'message' => "SHA-256 mismatch: expected {$expectedsha256} but got {$actual}. "
                            . "Refusing to install."];
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
                    // plugin directory is owned by a different OS user than the web
                    // server (e.g. root-owned files, www-data web server).
                    // Fall back to overwriting files in-place: copy the new version
                    // over the existing directory without deleting first.  Old files
                    // that no longer exist in the new version are left behind, but
                    // all functional files (version.php, classes/, etc.) are correctly
                    // replaced, so Moodle runs the new code after the DB upgrade.
                    if (self::copy_directory($sourcedir, $plugindir)) {
                        $overwritefallback = true; // Copy already done — skip the block below.
                    } else {
                        // Even overwrite failed — restore from backup and give up.
                        if ($backupdir && is_dir($backupdir)) {
                            self::copy_directory($backupdir, $plugindir);
                        }
                        self::delete_directory($extractdir);
                        @unlink($zipfile);
                        return [
                            'success' => false,
                            'message' => 'Failed to update plugin files. The web server user does not have write permi' .
                                'ssion on: ' . $plugindir . '. Run: chown -R www-data:www-data ' . $plugindir,
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
                        'message' => "Component mismatch: requested '{$component}' "
                            . "but ZIP contains '{$foundcomponent}'. Refusing to install.",
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

            // Bump jsrev and the theme revision. Moodle serves every AMD module as one
            // cached bundle keyed on jsrev, and that key does not change when plugin files
            // do. Without this the browser keeps the bundle built from the previous files —
            // and a request that lands mid-extraction can cache a half-written bundle, which
            // then persists until someone purges caches by hand. Symptom seen in the wild:
            // "(0 , _jquery.default) is not a function" from lib/requirejs.php, cleared only
            // by turning Cache JavaScript off. Same reasoning for CSS via the theme revision.
            js_reset_all_caches();
            theme_reset_all_caches();

            return [
                'success' => true,
                'message' => 'Plugin files updated. Click "Run Database Upgrade" to complete.',
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
            'expectedsha256' => new external_value(PARAM_ALPHANUM, 'Expected SHA-256 of the ZIP for integrity ve' .
                'rification', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Auto-install a NEW plugin by downloading and extracting it.
     * Unlike auto_update_plugin, this works for plugins not yet installed.
     *
     * @param string $component The frankenstyle component name.
     * @param string $downloadurl The URL of the plugin ZIP.
     * @param string|null $expectedsha256 The expected SHA-256 of the ZIP, when known.
     * @return array The web service result.
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
                'message' => "Download URL host '{$urlhost}' is not allowed. "
                    . "Only lms-labs.com and ai-grader-site-nct185.replit.app are permitted.",
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
            // hash_equals() is constant-time, preventing timing side-channels.
            if ($expectedsha256 !== null && $expectedsha256 !== '') {
                $actual = hash('sha256', $content);
                if (!hash_equals($expectedsha256, $actual)) {
                    @unlink($zipfile);
                    return ['success' => false,
                        'message' => "SHA-256 mismatch: expected {$expectedsha256} but got {$actual}. "
                            . "Refusing to install."];
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
                return ['success' => false,
                    'message' => "Component mismatch: requested '{$component}' "
                        . "but ZIP contains '{$found}'. Refusing to install."];
            }

            // Clean up temp files.
            self::delete_directory($extractdir);
            @unlink($zipfile);

            // Reset plugin manager caches so Moodle sees the new plugin.
            $pluginman = \core_plugin_manager::instance();
            $pluginman->reset_caches();

            // Invalidate the block's plugin status cache so next page load re-reads all version files.
            unset_config('plugin_status_cache_time', 'block_aiplugin_nav');

            // Bump jsrev and the theme revision. Moodle serves every AMD module as one
            // cached bundle keyed on jsrev, and that key does not change when plugin files
            // do. Without this the browser keeps the bundle built from the previous files —
            // and a request that lands mid-extraction can cache a half-written bundle, which
            // then persists until someone purges caches by hand. Symptom seen in the wild:
            // "(0 , _jquery.default) is not a function" from lib/requirejs.php, cleared only
            // by turning Cache JavaScript off. Same reasoning for CSS via the theme revision.
            js_reset_all_caches();
            theme_reset_all_caches();

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

        // A database upgrade run from an ordinary web request needs two guards that
        // Moodle's own upgrade screen provides and a web service does not:
        //
        // 1. No execution time limit. Without this the request can be killed by PHP
        // part-way through a migration, leaving the schema half-applied. This is the
        // failure that actually damages a site.
        // 2. An exclusive lock, so two admins clicking at the same moment cannot run
        // the same upgrade concurrently.
        //
        // Maintenance mode is deliberately NOT used. It would evict every learner and
        // teacher for the duration, which on a live site mid-class is its own harm, and
        // these are small plugin upgrades. The lock is released on every exit path,
        // including on exception.
        \core_php_time_limit::raise();

        $lockfactory = \core\lock\lock_config::get_lock_factory('block_aiplugin_nav_upgrade');
        $lock = $lockfactory->get_lock('run_upgrade', 10);
        if (!$lock) {
            return [
                'success' => false,
                'message' => 'Another upgrade is already running on this site. Wait for it to finish, then try again.',
            ];
        }

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
            //
            // The parameter is Moodle's "verbose" flag: passing true makes upgrade_noncore()
            // PRINT HTML progress output. In a web service that output is prepended to the
            // JSON response, and the browser fails on it with "Unexpected token '<'" before
            // it ever sees the result. Run it quietly, and discard any output core emits
            // anyway, so the response is always valid JSON.
            ob_start();
            try {
                upgrade_noncore(false);
            } finally {
                ob_end_clean();
            }

            return [
                'success' => true,
                'message' => 'Upgrade completed successfully.',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Upgrade error: ' . $e->getMessage(),
            ];
        } finally {
            $lock->release();
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
