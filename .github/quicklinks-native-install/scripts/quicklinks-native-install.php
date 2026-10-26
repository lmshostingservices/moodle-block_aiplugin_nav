<?php
// Test harness only. Never loaded by the plugin or the release server.
define('CLI_SCRIPT', true);
[$script, $site, $archive, $inventorypath, $run, $scenario] = $argv;
$allowed = realpath(__DIR__ . '/../generated-artifacts/quicklinks-native-install');
if (!$allowed || !str_starts_with(realpath($run) . '/', $allowed . '/')
        || realpath($site) !== realpath($run) . '/moodle') {
    throw new Exception('A runner-created disposable fixture is required');
}
require($site . '/config.php');
$expectedroot = is_dir($site . '/public/lib') ? $site . '/public' : $site;
if ($CFG->dbname !== 'qlfixture' || $CFG->dbuser !== 'qlfixture'
        || !str_starts_with($CFG->dbhost, sys_get_temp_dir() . '/ql-native-')
        || $CFG->wwwroot !== 'http://quicklinks-fixture.invalid' || empty($CFG->noemailever)
        || realpath($CFG->dataroot) !== realpath($run) . '/data'
        || realpath($CFG->dirroot) !== realpath($expectedroot)) {
    throw new Exception('Refusing a non-disposable Moodle site');
}
require_once($CFG->libdir . '/blocklib.php');
require_once($CFG->libdir . '/filelib.php');
require_once($CFG->libdir . '/filestorage/file_progress.php');
$inventory = json_decode(file_get_contents($inventorypath), true, 512, JSON_THROW_ON_ERROR);
$destination = $CFG->dirroot . '/blocks/aiplugin_nav';
$installedversion = (int)get_config('block_aiplugin_nav', 'version');
require($CFG->dirroot . '/version.php');
$fixturebranch = $branch;

class quicklinks_interrupted_extraction extends RuntimeException {}
class quicklinks_progress implements file_progress {
    public function progress($progress = file_progress::INDETERMINATE, $max = file_progress::INDETERMINATE) {
        if ($progress >= 30) {
            throw new quicklinks_interrupted_extraction('Simulated interruption in native ZIP extraction');
        }
    }
}

function quicklinks_files($directory) {
    $files = [];
    if (is_dir($directory)) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
            $directory, FilesystemIterator::SKIP_DOTS
        ));
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $files[substr($file->getPathname(), strlen($directory) + 1)] = hash_file('sha256', $file->getPathname());
            }
        }
    }
    ksort($files);
    return $files;
}

$interrupted = false;
$extractionok = true;
if ($scenario !== 'installed') {
    // Delete only this fixture's previous test plugin, never an external site.
    if (is_dir($destination)) {
        remove_dir($destination);
    }
    $packer = get_file_packer('application/zip');
    $extractarchive = $archive;
    if ($scenario === 'missing-amd') {
        // Simulate an archive/extractor omitting built JS, using the trusted complete
        // release inventory as the oracle (never derive expectations from this mutant).
        $extractarchive = $run . '/omitted-amd.zip';
        copy($archive, $extractarchive);
        $mutant = new ZipArchive();
        if ($mutant->open($extractarchive) !== true) {
            throw new Exception('Cannot create omitted-AMD fixture');
        }
        foreach (array_keys($inventory) as $file) {
            if (str_starts_with($file, 'amd/build/')) {
                $mutant->deleteName('aiplugin_nav/' . $file);
            }
        }
        $mutant->close();
    }
    try {
        $processed = $packer->extract_to_pathname($extractarchive, $CFG->dirroot . '/blocks', null,
            $scenario === 'interrupted' ? new quicklinks_progress() : null);
        $extractionok = is_array($processed) && count(array_filter($processed, fn($status) => $status !== true)) === 0;
    } catch (quicklinks_interrupted_extraction $e) {
        $extractionok = false;
        $interrupted = true;
    }
    if ($scenario === 'missing-main') {
        unlink($destination . '/block_aiplugin_nav.php');
    } else if ($scenario === 'missing-helper') {
        remove_dir($destination . '/classes/local');
    } else if ($scenario === 'missing-generated') {
        remove_dir($destination . '/generated');
    } else if ($scenario === 'matching-subset' || $scenario === 'recovered') {
        // The incident lost 21 of 127 files, while every surviving byte matched.
        // Keep the main class and its trait so native _self_test alone can still pass.
        $removable = array_values(array_filter(array_keys($inventory), fn($file) =>
            $file !== 'block_aiplugin_nav.php' && $file !== 'version.php'
            && $file !== 'lang/en/block_aiplugin_nav.php'
            && $file !== 'classes/local/ai_tools_registry_trait.php'
        ));
        foreach (array_slice($removable, 0, 21) as $file) {
            unlink($destination . '/' . $file);
        }
        if ($scenario === 'recovered') {
            // Native extract missing files only; do not overwrite matching survivors.
            $before = quicklinks_files($destination);
            $missingpaths = array_map(fn($file) => 'aiplugin_nav/' . $file,
                array_keys(array_diff_key($inventory, $before)));
            $processed = $packer->extract_to_pathname($archive, $CFG->dirroot . '/blocks', $missingpaths);
            $extractionok = is_array($processed) && count(array_filter($processed, fn($status) => $status !== true)) === 0;
            foreach ($before as $file => $sha) {
                if (hash_file('sha256', $destination . '/' . $file) !== $sha) {
                    throw new Exception('Recovery changed a surviving file');
                }
            }
        }
    } else if ($scenario === 'changed-byte') {
        file_put_contents($destination . '/version.php', "\n// corrupted fixture byte\n", FILE_APPEND);
    } else if ($scenario === 'unexpected-file') {
        file_put_contents($destination . '/unexpected.txt', 'not in release');
    }
}
clearstatcache();
$actual = quicklinks_files($destination);
$missing = array_keys(array_diff_key($inventory, $actual));
$unexpected = array_keys(array_diff_key($actual, $inventory));
$changed = array_keys(array_filter($actual, fn($sha, $file) =>
    isset($inventory[$file]) && $inventory[$file] !== $sha, ARRAY_FILTER_USE_BOTH));
$loaded = false;
$selftest = false;
$loaderror = null;
// Fresh PHP process for every case: a cached class must never mask missing files.
ob_start();
try {
    $loaded = block_load_class('aiplugin_nav');
    if ($loaded) {
        $block = block_instance('aiplugin_nav');
        $method = new ReflectionMethod($block, '_self_test');
        if (realpath($method->getFileName()) !== realpath($CFG->dirroot . '/blocks/moodleblock.class.php')) {
            throw new Exception('Expected the native Moodle block self-test, not an override');
        }
        $selftest = $block && $block->_self_test();
    }
} catch (Throwable $e) {
    $loaderror = get_class($e) . ': ' . $e->getMessage();
}
$diagnostics = ob_get_clean();
$accepted = $extractionok && !$missing && !$unexpected && !$changed && $loaded && $selftest;
echo json_encode([
    'branch' => $fixturebranch, 'webRoot' => $expectedroot === $site ? '.' : 'public',
    'scenario' => $scenario, 'accepted' => $accepted, 'expectedFiles' => count($inventory),
    'actualFiles' => count($actual), 'missing' => $missing, 'changed' => $changed, 'unexpected' => $unexpected,
    'extractionok' => $extractionok, 'interrupted' => $interrupted, 'loaded' => $loaded,
    'selftest' => $selftest, 'loaderror' => $loaderror, 'diagnostics' => $diagnostics,
    'nativeInstalledVersion' => $installedversion,
    'nativeInstalledVersionAfter' => (int)get_config('block_aiplugin_nav', 'version'),
], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT) . "\n";
