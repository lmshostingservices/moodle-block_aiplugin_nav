import assert from "node:assert/strict";
import { createHash } from "node:crypto";
import fs from "node:fs";
import os from "node:os";
import path from "node:path";
import { spawnSync } from "node:child_process";
import AdmZip from "adm-zip";

// Read-only acquisition; this runner never imports application storage or release activation.
const options = Object.fromEntries(process.argv.slice(2).map(arg => {
  const match = /^--([\w-]+)=(.+)$/.exec(arg);
  if (!match) throw new Error(`Expected --name=value, got ${arg}`);
  return [match[1], match[2]];
}));
for (const key of Object.keys(options)) {
  assert.ok(["moodle-source", "origin", "zip", "sha256", "php", "pg-bin"].includes(key), `Unknown option ${key}`);
}
assert.ok(options["moodle-source"], "Supply --moodle-source=/path/to/dependency-prepared/Moodle-{4.0,4.5,5.1}");
assert.ok(Boolean(options.origin) !== Boolean(options.zip), "Supply either --origin or --zip with --sha256");
const source = fs.realpathSync(options["moodle-source"]);
const publicRoot = fs.existsSync(path.join(source, "public/version.php")) ? "public" : "";
const core = path.join(source, publicRoot);
const branch = /\$branch\s*=\s*'(\d+)'/.exec(fs.readFileSync(path.join(core, "version.php"), "utf8"))?.[1];
const profiles = {
  "400": { label: "4.0", phpMin: 80000, phpMax: 80100, pgMin: 10, publicRoot: "" },
  "405": { label: "4.5", phpMin: 80100, phpMax: 80400, pgMin: 13, publicRoot: "" },
  "501": { label: "5.1", phpMin: 80200, phpMax: 80500, pgMin: 15, publicRoot: "public" },
};
const profile = profiles[branch];
assert.ok(profile, `Unsupported Moodle branch ${branch}; expected 400, 405 or 501`);
assert.equal(publicRoot, profile.publicRoot, "Unexpected Moodle core layout");
for (const file of ["lib/filestorage/zip_packer.php", "admin/cli/install_database.php"]) {
  assert.ok(fs.existsSync(path.join(file.startsWith("admin/") ? source : core, file)), `Missing core file: ${file}`);
}
const php = options.php || "php";
const pg = name => options["pg-bin"] ? path.join(options["pg-bin"], name) : name;
const hash = bytes => createHash("sha256").update(bytes).digest("hex");
const base = path.resolve("generated-artifacts/quicklinks-native-install");
fs.mkdirSync(base, { recursive: true });
const run = fs.mkdtempSync(path.join(base, "run-"));
const site = path.join(run, "moodle");
// PostgreSQL's socket path has a strict length limit.
const socket = fs.mkdtempSync(path.join(os.tmpdir(), "ql-native-"));
const dbdir = path.join(run, "postgres");
const report = { success: false, run, cases: [] };
report.moodle = {
  branch, label: profile.label, webRoot: publicRoot || ".",
  versionFileSha256: hash(fs.readFileSync(path.join(core, "version.php"))),
  coreFiles: Object.fromEntries(["lib/blocklib.php", "blocks/moodleblock.class.php",
    "lib/filestorage/zip_packer.php"].map(file => [file, hash(fs.readFileSync(path.join(core, file)))])),
};
let pgStarted = false;
function command(binary, args, name = path.basename(binary), timeout = 240000) {
  const result = spawnSync(binary, args, {
    encoding: "utf8", timeout, maxBuffer: 16 * 1024 * 1024,
    // Never inherit the application's database, SMTP, payment or provider credentials.
    env: {
      PATH: process.env.PATH, HOME: process.env.HOME, LANG: "C.UTF-8",
      LD_LIBRARY_PATH: process.env.LD_LIBRARY_PATH,
      PHP_INI_SCAN_DIR: process.env.PHP_INI_SCAN_DIR,
    },
  });
  fs.writeFileSync(path.join(run, `${name}.log`), `${result.stdout || ""}\n${result.stderr || ""}`);
  if (result.error || result.status !== 0) {
    throw new Error(`${name} failed (${result.status}): ${result.error || ""}\n${result.stdout}\n${result.stderr}`);
  }
  return result.stdout;
}
try {
  const runtime = JSON.parse(command(php, ["-r",
    "echo json_encode(['version' => PHP_VERSION, 'id' => PHP_VERSION_ID, 'extensions' => get_loaded_extensions()]);"],
  "php-runtime"));
  report.php = runtime;
  assert.ok(runtime.id >= profile.phpMin && runtime.id < profile.phpMax,
    `Moodle ${profile.label} fixture requires PHP ${profile.phpMin / 10000} <= version < ${profile.phpMax / 10000}; got ${runtime.version}`);
  for (const extension of ["pgsql", "zip", "intl", "mbstring", "dom", "xml", "curl", "sodium", "gd"]) {
    assert.ok(runtime.extensions.includes(extension), `PHP extension required: ${extension}`);
  }
  report.postgres = command(pg("postgres"), ["--version"], "postgres-version").trim();
  assert.ok(Number(/(\d+)\./.exec(report.postgres)?.[1]) >= profile.pgMin,
    `Moodle ${profile.label} requires PostgreSQL >= ${profile.pgMin}`);
  let bytes;
  let expectedSha = options.sha256;
  if (options.origin) {
    const origin = new URL(options.origin);
    assert.equal(origin.protocol, "https:", "Published acquisition must use HTTPS");
    const manifestResponse = await fetch(new URL("/api/plugin-versions", origin), { signal: AbortSignal.timeout(60000) });
    assert.ok(manifestResponse.ok, `Manifest HTTP ${manifestResponse.status}`);
    const manifest = await manifestResponse.json();
    const release = manifest.plugins?.block_aiplugin_nav;
    assert.ok(manifest.success && release?.zipExists, "Published Quick Links release required");
    expectedSha = release.sha256;
    const download = new URL(release.downloadUrl, origin);
    assert.equal(download.origin, origin.origin, "Refusing cross-origin acquisition");
    assert.equal(download.searchParams.get("sha256"), expectedSha, "Download must be SHA-bound");
    const response = await fetch(download, { signal: AbortSignal.timeout(60000), redirect: "error" });
    assert.ok(response.ok, `ZIP HTTP ${response.status}`);
    bytes = Buffer.from(await response.arrayBuffer());
    report.release = release;
    fs.writeFileSync(path.join(run, "manifest.json"), JSON.stringify(manifest, null, 2));
  } else {
    bytes = fs.readFileSync(options.zip);
  }
  assert.match(expectedSha || "", /^[a-f0-9]{64}$/, "An expected SHA-256 is mandatory");
  assert.equal(hash(bytes), expectedSha, "ZIP bytes do not match release SHA");
  report.sha256 = expectedSha;
  const zip = new AdmZip(bytes);
  const inventory = {};
  for (const entry of zip.getEntries()) {
    assert.ok(entry.entryName.startsWith("aiplugin_nav/"), `Unexpected archive root: ${entry.entryName}`);
    assert.ok(!entry.entryName.includes("\\") && !entry.entryName.split("/").includes(".."), "Unsafe archive path");
    if (entry.isDirectory) continue;
    const relative = entry.entryName.slice("aiplugin_nav/".length);
    assert.ok(relative && !Object.hasOwn(inventory, relative), `Duplicate/empty archive path: ${relative}`);
    inventory[relative] = hash(entry.getData());
  }
  for (const required of ["block_aiplugin_nav.php", "version.php", "lang/en/block_aiplugin_nav.php",
    "classes/local/ai_tools_registry_trait.php", "classes/external/plugin_updater.php"]) {
    assert.ok(inventory[required], `Required archive file missing: ${required}`);
  }
  for (const dir of ["amd/src/", "amd/build/", "classes/local/", "generated/"]) {
    assert.ok(Object.keys(inventory).some(file => file.startsWith(dir)), `Required archive directory empty: ${dir}`);
  }
  const archive = path.join(run, "published.zip");
  const inventoryPath = path.join(run, "inventory.json");
  fs.writeFileSync(archive, bytes);
  fs.writeFileSync(inventoryPath, JSON.stringify(inventory, null, 2));
  report.expectedFiles = Object.keys(inventory).length;

  // New code tree, data directory and PostgreSQL cluster for every invocation.
  // Exclude ONLY root config.php: Moodle has core classes named config.php too.
  fs.cpSync(source, site, { recursive: true, filter: src =>
    src !== path.join(source, "config.php") && src !== path.join(source, ".git") &&
    src !== path.join(core, "blocks/aiplugin_nav") });
  fs.mkdirSync(path.join(run, "data"));
  command(pg("initdb"), ["-D", dbdir, "-A", "trust", "-U", "qlfixture", "--no-locale", "--encoding=UTF8"], "initdb");
  // This new disposable cluster tests filesystem extraction, not crash durability.
  // Avoid serial WAL fsyncs dominating concurrent native core installations.
  command(pg("pg_ctl"), ["-D", dbdir, "-l", path.join(run, "postgres.log"),
    "-o", `-F -k ${socket} -p 55479 -h ''`, "-w", "start"], "postgres-start");
  pgStarted = true;
  command(pg("createdb"), ["-h", socket, "-p", "55479", "-U", "qlfixture", "qlfixture"], "createdb");
  const quote = value => `'${value.replaceAll("\\", "\\\\").replaceAll("'", "\\'")}'`;
  fs.writeFileSync(path.join(site, "config.php"), `<?php
unset($CFG); $CFG = new stdClass();
$CFG->dbtype = 'pgsql'; $CFG->dblibrary = 'native';
$CFG->dbhost = ${quote(socket)}; $CFG->dbname = 'qlfixture'; $CFG->dbuser = 'qlfixture';
$CFG->dbpass = ''; $CFG->prefix = 'ql_'; $CFG->dboptions = ['dbport' => 55479];
$CFG->wwwroot = 'http://quicklinks-fixture.invalid';
$CFG->dataroot = ${quote(path.join(run, "data"))};
$CFG->admin = 'admin'; $CFG->directorypermissions = 0777;
$CFG->noemailever = true; $CFG->disableupdatenotifications = true;
$CFG->updatenotifications = false; $CFG->cronclionly = true;
require_once(__DIR__ . '/${publicRoot ? "public/" : ""}lib/setup.php');
`);
  // No outbound transports from Moodle/PHP. Public artifact acquisition above is separate.
  const phpArgs = ["-d", "max_input_vars=5000", "-d", "allow_url_fopen=0",
    "-d", "disable_functions=curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,mail"];
  command(php, [...phpArgs, path.join(site, "admin/cli/install_database.php"),
    "--agree-license", "--adminpass=Disposable-Only-123!", "--adminemail=fixture@example.invalid",
    "--fullname=Quick Links native fixture", "--shortname=qlfixture"], "install-core", 900000);
  const worker = path.resolve("scripts/quicklinks-native-install.php");
  const workerArgs = [worker, site, archive, inventoryPath, run];
  // Native extraction and block test before the standard native plugin DB installation.
  const baseline = JSON.parse(command(php, [...phpArgs, ...workerArgs, "intact"], "intact"));
  assert.equal(baseline.accepted, true, JSON.stringify(baseline));
  assert.equal(baseline.branch, branch);
  assert.equal(baseline.webRoot, publicRoot || ".");
  assert.equal(baseline.selftest, true, "Inherited native self-test must pass");
  report.cases.push(baseline);
  command(php, [...phpArgs, path.join(site, "admin/cli/upgrade.php"), "--non-interactive"], "install-plugin");
  for (const scenario of ["installed", "missing-main", "missing-amd", "missing-helper", "missing-generated",
    "interrupted", "matching-subset", "changed-byte", "unexpected-file", "recovered"]) {
    const result = JSON.parse(command(php, [...phpArgs, ...workerArgs, scenario], scenario));
    assert.equal(result.branch, branch);
    assert.equal(result.webRoot, publicRoot || ".");
    assert.equal(result.accepted, ["installed", "recovered"].includes(scenario), JSON.stringify(result));
    if (!result.accepted) assert.ok(result.missing.length || result.changed.length || result.unexpected.length);
    assert.equal(result.actualFiles, result.expectedFiles - result.missing.length + result.unexpected.length);
    assert.equal(result.expectedFiles, report.expectedFiles);
    assert.ok(result.nativeInstalledVersion > 0, "Native Moodle upgrade must install the block");
    assert.equal(result.nativeInstalledVersion, result.nativeInstalledVersionAfter, "File checks must not upgrade/reset data");
    if (scenario === "missing-main") {
      assert.deepEqual(result.missing, ["block_aiplugin_nav.php"]);
      assert.equal(result.loaded, false);
    } else if (scenario === "missing-amd") {
      assert.ok(result.missing.length > 0 && result.missing.every(file => file.startsWith("amd/build/")));
      assert.equal(result.selftest, true, "Native self-test alone must not accept omitted AMD");
    } else if (scenario === "missing-helper") {
      assert.ok(result.missing.includes("classes/local/ai_tools_registry_trait.php"));
      assert.equal(result.loaded, false);
      assert.ok(result.loaderror);
    } else if (scenario === "missing-generated") {
      assert.ok(result.missing.length > 0 && result.missing.every(file => file.startsWith("generated/")));
    } else if (scenario === "interrupted") {
      assert.equal(result.interrupted, true, "Must interrupt native extraction, not simulate a successful extractor");
      assert.equal(result.extractionok, false);
      assert.ok(result.actualFiles > 0 && result.actualFiles < report.expectedFiles);
    } else if (scenario === "matching-subset") {
      assert.equal(result.missing.length, 21);
      assert.deepEqual(result.changed, []);
      assert.deepEqual(result.unexpected, []);
      assert.equal(result.selftest, true, "Matching-subset regression must demonstrate the native self-test gap");
    } else if (scenario === "changed-byte") {
      assert.deepEqual(result.changed, ["version.php"]);
      assert.deepEqual(result.missing, []);
    } else if (scenario === "unexpected-file") {
      assert.deepEqual(result.unexpected, ["unexpected.txt"]);
    }
    report.cases.push(result);
  }
  report.success = true;
  console.log(`PASS Moodle ${profile.label} / PHP ${runtime.version}: ${report.expectedFiles} SHA-verified files; ${report.cases.length} native scenarios`);
} finally {
  try {
    if (pgStarted) command(pg("pg_ctl"), ["-D", dbdir, "-m", "immediate", "-w", "stop"], "postgres-stop");
  } finally {
    fs.rmSync(socket, { recursive: true, force: true });
    fs.writeFileSync(path.join(run, "report.json"), JSON.stringify(report, null, 2));
    console.log(`Evidence: ${path.relative(process.cwd(), run)}/report.json`);
  }
}
