<?php
// auto_updater.php — run this via cron

error_reporting(0);
ini_set('display_errors', 0);

$log = "";
$timestamp = date('[Y-m-d H:i:s] ');
$success = false;

try {
    $baseDir = __DIR__;
    include $baseDir . '/config.php';

    $localPath = $baseDir . '/index.php';
    $remoteUrl = $github_index_url;
    $backupDir = $baseDir . '/backups';

    if (!is_dir($backupDir)) mkdir($backupDir, 0755, true);

    // Fetch local and remote files
    $localCode = @file_get_contents($localPath);
    $remoteCode = @file_get_contents($remoteUrl);

    if (!$localCode || !$remoteCode) throw new Exception("Dateien konnten nicht geladen werden.");

    preg_match('/\$version\s*=\s*(\d+);/', $localCode, $lv);
    preg_match('/\$security\s*=\s*(\d+);/', $localCode, $ls);
    preg_match('/\$version\s*=\s*(\d+);/', $remoteCode, $rv);
    preg_match('/\$security\s*=\s*(\d+);/', $remoteCode, $rs);
    preg_match('/\/\/\s*Release Notes:\s*(https?:\/\/\S+)/', $remoteCode, $rn);

    if (!$lv || !$ls || !$rv || !$rs) throw new Exception("Versionsinformationen nicht gefunden.");

    $localVersion    = (int)$lv[1];
    $localSecurity   = (int)$ls[1];
    $remoteVersion   = (int)$rv[1];
    $remoteSecurity  = (int)$rs[1];
    $releaseNotesUrl = $rn[1] ?? '';

    if ($remoteSecurity > $localSecurity) {
        // Create backup ZIP
        $backupName = "index.php.bak_" . date('Ymd_His') . ".zip";
        $zip = new ZipArchive();
        $backupPath = $backupDir . '/' . $backupName;

        if ($zip->open($backupPath, ZipArchive::CREATE) !== true) {
            throw new Exception("Backup konnte nicht erstellt werden.");
        }

        $zip->addFile($localPath, 'index.php');
        $zip->close();

        // Write updated index.php
        if (file_put_contents($localPath, $remoteCode) === false) {
            throw new Exception("Fehler beim Schreiben der neuen index.php.");
        }

        $success = true;
        $log .= "{$timestamp}Automatisches Sicherheitsupdate durchgeführt: v$localVersion → v$remoteVersion (sec $localSecurity → $remoteSecurity)\n";
        if ($releaseNotesUrl) {
            $log .= "Release Notes: $releaseNotesUrl\n";
        }
    } else {
        $log .= "{$timestamp}Kein Sicherheitsupdate erforderlich.\n";
    }

} catch (Exception $e) {
    $log .= "{$timestamp}Fehler: " . $e->getMessage() . "\n";
}

// Write to log file
file_put_contents($baseDir . '/update.log', $log, FILE_APPEND);

// Send email log
if (!empty($update_log_emails)) {
    foreach ($update_log_emails as $email) {
        mail($email, "Auto-Update " . ($success ? "Erfolgreich" : "Fehlgeschlagen"), $log);
    }
}
