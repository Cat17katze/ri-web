<?php
session_start();
include __DIR__ . '/config.php';

$localPath = __DIR__ . '/index.php';
$remoteUrl = $github_index_url;
$backupDir = __DIR__ . '/backups';

if (!is_dir($backupDir)) mkdir($backupDir);

function extractVersions($code) {
    preg_match('/\$version\s*=\s*(\d+);/', $code, $v);
    preg_match('/\$security\s*=\s*(\d+);/', $code, $s);
    return ($v && $s) ? ['version' => (int)$v[1], 'security' => (int)$s[1]] : null;
}

function extractReleaseNotes($code) {
    preg_match('/\/\/\s*Release Notes:\s*(https?:\/\/\S+)/', $code, $m);
    return $m[1] ?? null;
}

function getBackupList($dir) {
    $files = glob($dir . '/index.php.bak_*.zip');
    usort($files, fn($a, $b) => filemtime($b) - filemtime($a));
    return $files;
}

function createBackupZip($indexPath, $backupDir) {
    $timestamp = date('Ymd_His');
    $zipPath = "$backupDir/index.php.bak_{$timestamp}.zip";

    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE) !== true) return false;

    $zip->addFile($indexPath, 'index.php');
    $zip->close();
    return $zipPath;
}

function restoreBackupZip($zipPath, $targetPath) {
    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) return false;

    $contents = $zip->getFromName('index.php');
    if (!$contents) return false;

    $zip->close();
    return file_put_contents($targetPath, $contents) !== false;
}

$localCode  = @file_get_contents($localPath);
$remoteCode = @file_get_contents($remoteUrl);

if (!$localCode || !$remoteCode) die("Fehler beim Laden.");

$local  = extractVersions($localCode);
$remote = extractVersions($remoteCode);
$notes  = extractReleaseNotes($remoteCode);

if (!$local || !$remote) die("Versionsfehler.");

$updateAvailable = $remote['version'] > $local['version'];
$securityUpdate  = $remote['security'] > $local['security'];

$log = "";
$message = "";

// --- Login ---
if (!isset($_SESSION['logged_in'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['password'] === $update_password) {
        $_SESSION['logged_in'] = true;
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
    ?>
    <h2>🔐 Update Login</h2>
    <form method="POST">
        Passwort: <input type="password" name="password">
        <button>Login</button>
    </form>
    <?php exit;
}

// --- Restore from backup ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore_backup'])) {
    $zipName = basename($_POST['restore_backup']);
    $zipPath = $backupDir . '/' . $zipName;

    if (file_exists($zipPath) && strpos($zipName, 'index.php.bak_') === 0) {
        $preRestore = $backupDir . '/index.php.pre_restore_' . date('Ymd_His') . '.zip';
        createBackupZip($localPath, $backupDir); // backup before restore

        if (restoreBackupZip($zipPath, $localPath)) {
            $message = "<p style='color:green;'>Backup wiederhergestellt: $zipName</p>";
            $log = "[" . date('Y-m-d H:i:s') . "] Wiederhergestellt von Backup: $zipName\n";
            file_put_contents(__DIR__ . '/update.log', $log, FILE_APPEND);
            foreach ($update_log_emails as $to) {
                mail($to, "Backup restored", $log);
            }
        } else {
            $message = "<p style='color:red;'>Wiederherstellung fehlgeschlagen.</p>";
        }
    }
}

// --- Manual update ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do_update'])) {
    if (!createBackupZip($localPath, $backupDir)) {
        $message = "<p style='color:red;'>Backup fehlgeschlagen.</p>";
    } elseif (file_put_contents($localPath, $remoteCode) === false) {
        $message = "<p style='color:red;'>Update fehlgeschlagen.</p>";
    } else {
        $message = "<p style='color:green;'>Update erfolgreich!</p>";
        $log = "[" . date('Y-m-d H:i:s') . "] Manual update auf v{$remote['version']} (sec {$remote['security']})\n";
        if ($notes) $log .= "Release Notes: $notes\n";
        file_put_contents(__DIR__ . '/update.log', $log, FILE_APPEND);
        foreach ($update_log_emails as $to) {
            mail($to, "Update erfolgreich installiert", $log);
        }

        $local = $remote;
        $updateAvailable = $securityUpdate = false;
    }
}

// --- Logout ---
if (isset($_POST['logout'])) {
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// --- GUI ---
$allBackups = getBackupList($backupDir);
$newestBackups = array_slice($allBackups, 0, 10);
$olderBackups  = array_slice($allBackups, 10);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8"><title>Updater</title>
    <style>
        .hidden { display: none; }
        .backup-list form { display:inline; }
    </style>
    <script>
        function toggleOldBackups() {
            let el = document.getElementById("olderBackups");
            el.classList.toggle("hidden");
        }
    </script>
</head>
<body>
<h2>🛠 Update System</h2>

<p><strong>Lokale Version:</strong> <?= $local['version'] ?> (Security <?= $local['security'] ?>)</p>
<p><strong>Remote Version:</strong> <?= $remote['version'] ?> (Security <?= $remote['security'] ?>)</p>

<?php if ($notes): ?>
    <p><strong>🔗 Release Notes:</strong> <a href="<?= htmlspecialchars($notes) ?>" target="_blank"><?= htmlspecialchars($notes) ?></a></p>
<?php endif; ?>

<?= $message ?>

<?php if ($securityUpdate): ?>
    <p style="color:red;">⚠️ Sicherheitsupdate verfügbar!</p>
    <form method="POST"><button name="do_update">Jetzt installieren</button></form>
<?php elseif ($updateAvailable): ?>
    <p>Neues Update verfügbar.</p>
    <form method="POST"><button name="do_update">Update auf <?= $remote['version'] ?> durchführen</button></form>
<?php else: ?>
    <p>✅ System aktuell.</p>
<?php endif; ?>

<h3>🔙 Backups (letzte 10 ZIPs)</h3>
<ul class="backup-list">
<?php foreach ($newestBackups as $zipFile): ?>
    <li>
        <?= basename($zipFile) ?>
        <form method="POST">
            <input type="hidden" name="restore_backup" value="<?= htmlspecialchars(basename($zipFile)) ?>">
            <button>Wiederherstellen</button>
        </form>
    </li>
<?php endforeach; ?>
</ul>

<?php if (!empty($olderBackups)): ?>
    <a href="javascript:void(0);" onclick="toggleOldBackups()">📂 Weitere Backups anzeigen</a>
    <ul id="olderBackups" class="backup-list hidden">
    <?php foreach ($olderBackups as $zipFile): ?>
        <li>
            <?= basename($zipFile) ?>
            <form method="POST">
                <input type="hidden" name="restore_backup" value="<?= htmlspecialchars(basename($zipFile)) ?>">
                <button>Wiederherstellen</button>
            </form>
        </li>
    <?php endforeach; ?>
    </ul>
<?php endif; ?>

<form method="POST" style="margin-top:2em;">
    <button name="logout">Abmelden</button>
</form>
</body>
</html>
