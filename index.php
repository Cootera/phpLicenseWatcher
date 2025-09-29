<?php

require_once __DIR__ . "/common.php";
require_once __DIR__ . "/tools.php";
require_once __DIR__ . "/html_table.php";

function fast_status_check(string $conn, ?string $lmutil = null, int $timeout = 2): ?string {
    // According to user spec: use /opt/lmtools/lmutil lmstat -s -c <server>
    // UP only if output contains BOTH "Vendor daemon status" AND "UP" (case-insensitive).
    // Any timeout (>2s) or other output => DOWN.
    $server = trim($conn);
    if ($server === '') return 'DOWN';

    $lmutil = '/opt/lmtools/lmutil';
    if (!is_executable($lmutil)) {
        // if lmutil missing, treat as DOWN per spec
        return 'DOWN';
    }

    // Enforce timeout
    $timeoutBin = '';
    foreach (['/usr/bin/timeout', '/bin/timeout'] as $cand) {
        if (is_executable($cand)) { $timeoutBin = $cand; break; }
    }
    if ($timeoutBin !== '') {
        $cmd = escapeshellarg($timeoutBin) . " {$timeout}s " . escapeshellarg($lmutil) . " lmstat -s -c " . escapeshellarg($server) . " 2>&1";
        $output = @shell_exec($cmd);
        if ($output === null) return 'DOWN';
    } else {
        // Fallback without timeout utility: still run, but we can't guarantee the 2s; conservatively mark DOWN on failure
        $output = @shell_exec(escapeshellarg($lmutil) . " lmstat -s -c " . escapeshellarg($server) . " 2>&1");
        if ($output === null) return 'DOWN';
    }

    $out = strtolower((string)$output);
    $hasVendor = (strpos($out, 'vendor daemon status') !== false);
    $hasUp     = (strpos($out, 'up') !== false);

    return ($hasVendor && $hasUp) ? 'UP' : 'DOWN';
}

/**
 * Parse "PORT@HOST" → [port, host]
 */
function plw_parse_conn(string $conn): array {
    $conn = trim($conn);
    if ($conn === '' || strpos($conn, '@') === false) return [null, null];
    [$port, $host] = explode('@', $conn, 2);
    $port = is_numeric($port) ? (int)$port : null;
    $host = trim($host);
    if (!$port || $host === '') return [null, null];
    return [$port, $host];
}

function fast_status_check_mathlm(string $conn, int $timeout = 2): ?string {
    // Use /opt/lmtools/monitorlm <HOST>; UP only if ALL of these appear (case-insensitive):
    // "MathLM Version:", "MathLM Server:", "Licenses Usage Summary:" (or "License Usage Summary:"), "Licenses in Use:"
    // Any timeout (>2s) or missing markers => DOWN.
    $conn = trim($conn);
    if ($conn === '') return 'DOWN';

    // Extract host (accept HOST, HOST:PORT, PORT@HOST)
    if (strpos($conn, '@') !== false) {
        [, $host] = explode('@', $conn, 2);
    } else {
        $host = preg_replace('/:(\d+)$/', '', $conn);
    }
    $host = trim((string)$host);
    if ($host === '') return 'DOWN';

    $monitorlm = '/opt/lmtools/monitorlm';
    if (!is_executable($monitorlm)) return 'DOWN';

    // Enforce timeout
    $timeoutBin = '';
    foreach (['/usr/bin/timeout', '/bin/timeout'] as $cand) {
        if (is_executable($cand)) { $timeoutBin = $cand; break; }
    }
    if ($timeoutBin !== '') {
        $cmd = escapeshellarg($timeoutBin) . " {$timeout}s " . escapeshellarg($monitorlm) . ' ' . escapeshellarg($host) . " 2>&1";
        $output = @shell_exec($cmd);
        if ($output === null) return 'DOWN';
    } else {
        $output = @shell_exec(escapeshellarg($monitorlm) . ' ' . escapeshellarg($host) . " 2>&1");
        if ($output === null) return 'DOWN';
    }

    $out = strtolower((string)$output);
    $must = [
        'mathlm version',
        'mathlm server',
        'licenses in use',
    ];
    foreach ($must as $m) {
        if (strpos($out, $m) === false) return 'DOWN';
    }
    // handle both "license usage summary" and "licenses usage summary" per user phrasing/screenshot
    if (strpos($out, 'license usage summary') === false && strpos($out, 'licenses usage summary') === false) {
        return 'DOWN';
    }
    return 'UP';
}

// Get server list (all columns, all IDs)
db_connect($db);
if (!($db instanceof mysqli)) {
    error_log("[PLW][DB] db_connect did not return mysqli instance");
}

$servers = db_get_servers($db, array(), array(), "label");

// Prepared statements for updates (by id and — if id is missing — by name)
$updateById   = $db->prepare("UPDATE servers SET status = ?, is_active = ? WHERE id = ?");
if ($updateById === false) {
    error_log("[PLW][DB] prepare updateById failed: ".$db->error);
}
$updateByName = $db->prepare("UPDATE servers SET status = ?, is_active = ? WHERE name = ?");
if ($updateByName === false) {
    error_log("[PLW][DB] prepare updateByName failed: ".$db->error);
}

if (is_array($servers)) {
    foreach ($servers as $i => $srv) {
        if (!isset($srv['name']) || trim($srv['name']) === '') continue; // braucht "PORT@HOST"

        $lmType = isset($srv['license_manager']) ? strtolower((string)$srv['license_manager']) : 'flexlm';
        $live = null;

        $lmTypeRaw = $srv['license_manager'] ?? '';
        $lmType = strtolower((string)$lmTypeRaw);
        $isMathLM = (strpos($lmType, 'mathlm') !== false)
                 || (strpos($lmType, 'wolfram') !== false)
                 || (strpos($lmType, 'mathematica') !== false);
        if ($isMathLM) {
            // MathLM: monitorlm-only with positiv tokens
            $live = fast_status_check_mathlm($srv['name'], 2);
        } else {
            // FlexLM (Default): lmutil lmstat -s
            $live = fast_status_check($srv['name'], $GLOBALS['lmutil_loc'] ?? null, 1);
        }

        if ($live === 'UP' || $live === 'DOWN') {
            // Map to constant
            $status = ($live === 'UP') ? 'UP' : 'DOWN';
            $active = 1;

            // Assign array status to constants as well
            $servers[$i]['status'] = $status;

            // Persist in DB (preferably by ID, otherwise by name)
            if (isset($srv['id']) && $srv['id'] !== '' && is_numeric((string)$srv['id'])) {
                if ($updateById) {
                    $updateById->bind_param("sii", $status, $active, $srv['id']);
                    if (!$updateById->execute()) {
                        error_log("[PLW][DB] execute updateById failed for id={$srv['id']}: ".$db->error);
                    }
                }
            } else {
                if ($updateByName) {
                    $updateByName->bind_param("sis", $status, $active, $srv['name']);
                    if (!$updateByName->execute()) {
                        error_log("[PLW][DB] execute updateByName failed for name={$srv['name']}: ".$db->error);
                    }
                }
            }
        }
        // $live === null -> nothing changes (DB-Value stays)
    }
}

// Close Statement and DB-Connection
if ($updateById)   { $updateById->close(); }
if ($updateByName) { $updateByName->close(); }
$db->close();

// Retrieve server list.  All columns.  All IDs.
db_connect($db);
$servers = db_get_servers($db, array(), array(), "label");
$db->close();

// Start a new html_table
$table = new html_table(array('class'=>"table alt-rows-bgcolor"));

// Define the table header
$col_headers = array("Description", "License port@server", "Status", "Current Usage", "Available features/license", "Server Version", "Last Update");
$table->add_row($col_headers, array(), "th");

// Whether or not to display notice about setting up cron jobs.
// Notice is displayed when at least one server is configured and all servers
// have not been polled.  e.g. such as the first run.
$display_cron_notice = count($servers) > 0 ? true : false;
foreach ($servers as $server) {
    // $class refers to bootstrap, not local CSS.
    switch ($server['status']) {
    case null:
        $server['status']="Not Polled";
        $class = array('class' => "info"); // blue
        $detail_link="No Details";
        $listing_expiration_link="";
        break;
    case SERVER_UP:
        $display_cron_notice = false;
        $class = array('class' => "success"); // green
        $detail_link="<a href='details.php?listing=0&amp;server={$server['id']}' aria-label='usage details for {$server['label']})'>Usage Details</a>";
        $listing_expiration_link="<a href='details.php?listing=1&amp;server={$server['id']}' aria-label='listing and expiration dates for {$server['label']})'>Listing/Expiration Dates</a>";
        break;
    case SERVER_VENDOR_DOWN:
        $display_cron_notice = false;
        $class = array('class' => "warning"); // yellow
	    $detail_link="<a href='details.php?listing=0&amp;server={$server['id']}' aria-label='usage details for {$server['label']})'>Usage Details</a>";
	    $listing_expiration_link="<a href='details.php?listing=1&amp;server={$server['id']}' aria-label='listing and expiration dates for {$server['label']})'>Listing/Expiration dates</a>";
        break;
    case SERVER_DOWN:
    default:
        $display_cron_notice = false;
        $class = array('class' => "danger"); // red
        $detail_link="No Details";
        $listing_expiration_link="";
        break;
    }

    $table->add_row(array(
        $server['label'],
        $server['name'],
        $server['status'],
        $detail_link,
        $listing_expiration_link,
        $server['version'],
        date_format(date_create($server['last_updated']), "m/d/Y h:ia")
    ));

    // Set server label as row header
    $table->update_cell(($table->get_rows_count() - 1), 0, null, null, "th");

	// Set the background color of status cell via class attribute
	$table->update_cell(($table->get_rows_count() - 1), 2, $class);
}

$cron_notice = !$display_cron_notice ? "" : get_not_polled_notice();

// Output view.
print_header();
print <<< HTML
<h1>License Management Server Status</h1>
<hr />
<p>To get current usage for an individual server please click on the "Usage Details" link next to the server.  The "Listings/Expiration Dates" link will provide time-to expiration for a server's features.</p>
{$cron_notice}
{$table->get_html()}
HTML;

print_footer();
?>
