<?php
/**
 * Downloads the free DB-IP Lite country database (CC BY 4.0) when it is
 * missing or older than 30 days. Run by the container's entrypoint on start
 * and once a day after that; does nothing when the file is still fresh.
 *
 *   php update-geo.php [--force]
 */

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use MaxMind\Db\Reader;

$dataDir = rtrim(getenv('DATA_DIR') ?: '/data', '/\\');
$target  = $dataDir . '/geo/dbip-country-lite.mmdb';
$force   = in_array('--force', $argv, true);

if (!$force && is_file($target) && filemtime($target) > time() - 30 * 86400) {
    exit(0);
}
if (!is_dir(dirname($target))) {
    mkdir(dirname($target), 0775, true);
}

// A new file is published each month; early in the month it may not exist yet.
$months  = [date('Y-m'), date('Y-m', strtotime('first day of last month'))];
$context = stream_context_create(['http' => ['timeout' => 120, 'user_agent' => 'AssistantSupport geo updater']]);

foreach ($months as $month) {
    $url = "https://download.db-ip.com/free/dbip-country-lite-$month.mmdb.gz";
    $gz  = @file_get_contents($url, false, $context);
    if ($gz === false) {
        continue;
    }
    $data = @gzdecode($gz);
    if ($data === false) {
        continue;
    }

    $tmp = $target . '.tmp';
    file_put_contents($tmp, $data);
    try {
        // Make sure it is a valid database before replacing the old one.
        $reader = new Reader($tmp);
        $reader->get('8.8.8.8');
        $reader->close();
    } catch (Throwable) {
        @unlink($tmp);
        continue;
    }
    rename($tmp, $target);
    echo "Geo database updated (DB-IP Lite $month).\n";
    exit(0);
}

fwrite(STDERR, "WARNING: could not download the DB-IP Lite geo database; country checks are skipped until it is available.\n");
exit(1);
