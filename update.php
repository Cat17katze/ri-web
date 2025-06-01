<?php
/**
 * update.php – Intelligentes Web-Update-System mit Vorschau, Wiederherstellung und Mailbenachrichtigung
 */

// Konfiguration
$updateServer = 'https://riley-tech.de/w/api/site/ri-web/';
$backupDir = __DIR__ . '/backups';
$versionFile = __DIR__ . '/version.txt';
$configFile = __DIR__ . '/config.php';
$mailTo = 'admin@example.com'; // Empfänger der Benachrichtigung

$admin_password = null;
if (file_exists($configFile)) require_once($configFile);

session_start();
if (isset($admin_password) && !empty($admin_password)) {
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
            if ($_POST['password'] === $admin_password) {
                $_SESSION['admin_logged_in'] = true;
                header("Location: update.php"); exit;
            } else {
                echo "<p>Falsches Passwort</p>";
            }
        }
        echo '<form method="post">Passwort: <input type="password" name="password" /><input type="submit" value="Login"></form>';
        exit;
    }
}

function ensureDirExists($dir) {
    if (!is_dir($dir)) mkdir($dir, 0777, true);
}

function getCurrentVersion() {
    global $versionFile;
    return file_exists($versionFile) ? trim(file_get_contents($versionFile)) : '00000';
}

function setCurrentVersion($newVersion) {
    global $versionFile;
    file_put_contents($versionFile, $newVersion);
}

function fetchNextVersion() {
    $current = intval(getCurrentVersion());
    return str_pad($current + 1, 5, '0', STR_PAD_LEFT);
}

function downloadAndExtractZip($version, &$tempPath) {
    global $updateServer;
    $url = "$updateServer/$version.zip";
    $zipPath = sys_get_temp_dir() . "/update_$version.zip";

    $zipContent = @file_get_contents($url);
    if (!$zipContent) return false;
    file_put_contents($zipPath, $zipContent);

    $tempPath = sys_get_temp_dir() . "/update_$version";
    ensureDirExists($tempPath);

    $zip = new ZipArchive;
    if ($zip->open($zipPath) === true) {
        $zip->extractTo($tempPath);
        $zip->close();
        return true;
    }
    return false;
}

function parseMandatory($path) {
    $mandatory = [];
    $file = "$path/mandatory.txt";
    if (!file_exists($file)) return $mandatory;
    $lines = file($file);
    foreach ($lines as $line) {
        if (preg_match('/^([^=\n]+)=\s*([umz])/', trim($line), $m)) {
            if (!empty(trim($m[1]))) {
                $mandatory[trim($m[1])] = trim($m[2]);
            }
        }
    }
    return $mandatory;
}

function applyBackupFiles($path) {
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($files as $file) {
        $relPath = substr($file, strlen($path) + 1);
        $dest = __DIR__ . '/' . $relPath;
        ensureDirExists(dirname($dest));
        copy($file, $dest);
    }
}

function restoreBackup($targetTimestampDir) {
    global $backupDir;

    $allBackups = array_filter(glob($backupDir . '/*'), 'is_dir');
    sort($allBackups);
    $targetPath = "$backupDir/$targetTimestampDir";
    $targetIndex = array_search($targetPath, $allBackups);
    if ($targetIndex === false) {
        echo "<p>Backup nicht gefunden.</p>";
        return;
    }

    $restoreBackupTime = date("Ymd_His");
    $restoreBackupPath = "$backupDir/restore_$restoreBackupTime";
    mkdir($restoreBackupPath, 0777, true);
    $filesToRestore = [];
    for ($i = 0; $i <= $targetIndex; $i++) {
        $path = $allBackups[$i];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $file) {
            if (!$file->isFile()) continue;
            $relPath = substr($file, strlen($path) + 1);
            $absPath = __DIR__ . '/' . $relPath;
            $filesToRestore[$relPath] = $absPath;
        }
    }

    foreach ($filesToRestore as $rel => $abs) {
        if (file_exists($abs)) {
            $backupFilePath = "$restoreBackupPath/$rel";
            ensureDirExists(dirname($backupFilePath));
            copy($abs, $backupFilePath);
        }
    }

    for ($i = 0; $i <= $targetIndex; $i++) {
        applyBackupFiles($allBackups[$i]);
    }

    echo "<p>Wiederherstellung bis $targetTimestampDir abgeschlossen.</p>";
    echo "<p>Sicherung des alten Zustands unter <strong>restore_$restoreBackupTime</strong>.</p>";
}

function showPreview($mandatory, $basePath) {
    echo "<h3>Update-Vorschau</h3><ul>";
    foreach ($mandatory as $rel => $type) {
        $local = __DIR__ . '/' . $rel;
        if ($type === 'u') {
            echo "<li>$rel wird <strong>überschrieben</strong>.</li>";
        } elseif ($type === 'm') {
            if (file_exists($local)) {
                echo "<li>$rel bleibt <strong>erhalten</strong>.</li>";
            } else {
                echo "<li>$rel fehlt lokal – wird <strong>neu installiert</strong>.</li>";
            }
        } elseif ($type === 'z') {
            echo "<li><label><input type='checkbox' name='install_optional[]' value='$rel'> $rel ist optional (Beispiel-Datei)</label></li>";
        }
    }
    echo "</ul>";

    if (file_exists("$basePath/changelog.txt")) {
        $link = trim(file_get_contents("$basePath/changelog.txt"));
        echo "<p><a href='$link' target='_blank'>Changelog anzeigen</a></p>";
    }
}

function mergeConfig($newConfigPath, $oldConfigPath) {
    $newLines = file($newConfigPath, FILE_IGNORE_NEW_LINES);
    $oldLines = file($oldConfigPath, FILE_IGNORE_NEW_LINES);
    $merged = [];
    $oldVars = [];
    foreach ($oldLines as $line) {
        if (preg_match('/\\$([a-zA-Z0-9_]+)\\s*=.*;/', $line, $m)) {
            $oldVars[$m[1]] = $line;
        }
    }
    foreach ($newLines as $line) {
        if (preg_match('/\\$([a-zA-Z0-9_]+)\\s*=.*;/', $line, $m)) {
            unset($oldVars[$m[1]]);
        }
        $merged[] = $line;
    }
    foreach ($oldVars as $var => $line) {
        $merged[] = "// $line // Nicht mehr verwendet";
    }
    file_put_contents($oldConfigPath, implode("\n", $merged));
}

function sendMailNotification($subject, $message) {
    global $mailTo;
    @mail($mailTo, $subject, $message);
}

function performUpdate() {
    $nextVersion = fetchNextVersion();
    if (!downloadAndExtractZip($nextVersion, $tmp)) {
        echo "<p>Keine neue Version verfügbar.</p>";
        return;
    }
    $mandatory = parseMandatory($tmp);
    $timestamp = date("Ymd_His");
    $backupPath = "$GLOBALS[backupDir]/$timestamp";
    mkdir($backupPath, 0777, true);

    foreach ($mandatory as $rel => $type) {
        if (empty($rel)) continue; // Schutz vor leeren Einträgen

        $source = "$tmp/$rel";
        $target = __DIR__ . "/$rel";

        if (file_exists($target)) {
            $backupFile = "$backupPath/$rel";
            ensureDirExists(dirname($backupFile));
            copy($target, $backupFile);
        }

        if ($type === 'u' || ($type === 'm' && !file_exists($target)) || ($type === 'z' && isset($_POST['install_optional']) && in_array($rel, $_POST['install_optional']))) {
            ensureDirExists(dirname($target));
            if (file_exists($source)) {
                copy($source, $target);
            }
        }
    }

    if (file_exists("$tmp/config.php") && file_exists($GLOBALS['configFile'])) {
        mergeConfig("$tmp/config.php", $GLOBALS['configFile']);
    }

    setCurrentVersion($nextVersion);
    echo "<p>Update auf Version $nextVersion erfolgreich.</p>";
    sendMailNotification("Update abgeschlossen", "Das System wurde erfolgreich auf Version $nextVersion aktualisiert.");
}

if (isset($_GET['apply_backup'])) {
    restoreBackup($_GET['apply_backup']);
    echo "<p><a href='update.php'>Zurück</a></p>";
    exit;
}

if (isset($_GET['preview_update'])) {
    $nextVersion = fetchNextVersion();
    if (downloadAndExtractZip($nextVersion, $tmp)) {
        $mandatory = parseMandatory($tmp);
        echo "<form method='post' action='?perform_update=1'>";
        showPreview($mandatory, $tmp);
        echo "<input type='submit' value='Jetzt installieren'></form>";
        echo "<p><a href='update.php'>Zurück</a></p>";
    } else {
        echo "<p>Update nicht gefunden.</p>";
    }
    exit;
}

if (isset($_GET['perform_update']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    performUpdate();
    echo "<p><a href='update.php'>Zurück</a></p>";
    exit;
}

// Web-GUI

echo "<h2>Update-System</h2>";
echo "<p>Aktuelle Version: <strong>" . getCurrentVersion() . "</strong></p>";
echo "<p><a href='?preview_update=1'>Vorschau des nächsten Updates anzeigen</a></p>";

echo "<h3>Wiederherstellung</h3>";
$backups = array_filter(glob($backupDir . '/*'), 'is_dir');
usort($backups, fn($a, $b) => strcmp(basename($a), basename($b)));
foreach ($backups as $b) {
    $ts = basename($b);
    $isRestore = str_starts_with($ts, 'restore_');
    echo "<p><a href='?apply_backup=$ts'>" .
        ($isRestore ? "<strong>Restore-Zustand:</strong> " : "Wiederherstellen bis: ") .
        htmlspecialchars($ts) . "</a></p>";
}
?>
