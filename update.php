<?php
session_start();
/**
 * update.php - GitHub Release Updater mit Backup-System + Upload-Support
 */

// CONFIGURATION
$githubRepo = "Cat17katze/ri-web";
$releaseApiUrl = "https://api.github.com/repos/$githubRepo/releases/latest";
$backupDir = __DIR__ . '/backups';
$versionFile = __DIR__ . '/version.txt';
$tmpDir = __DIR__ . '/tmp_release';
$tmpZip = __DIR__ . "/release.zip";
$uploadZip = __DIR__ . "/uploaded_release.zip";
$mandatoryFile = __DIR__ . '/mandatory.txt';

// Load current version
$currentVersion = file_exists($versionFile) ? trim(file_get_contents($versionFile)) : '0.0.0';

// Check update password
@include_once('config.php');
if (isset($update_pw)) {
    if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
        if (isset($_POST['pw']) && $_POST['pw'] === $update_pw) {
            $_SESSION['authenticated'] = true;
        } else {
            echo "<form method='post'>Passwort: <input type='password' name='pw'><input type='submit'></form>";
            exit;
        }
    }
}

// --- Utility Functions ---

function getLatestRelease($url) {
    $opts = ["http" => ["method" => "GET", "header" => "User-Agent: PHP"]];
    $context = stream_context_create($opts);
    $json = @file_get_contents($url, false, $context);
    if ($json === false) return null;
    return json_decode($json, true);
}

function extractZip($zipPath, $to) {
    $zip = new ZipArchive;
    if ($zip->open($zipPath) === TRUE) {
        $zip->extractTo($to);
        $zip->close();
        return true;
    }
    return false;
}

function createBackup($files, $name = null) {
    global $backupDir;
    if (!is_dir($backupDir)) mkdir($backupDir, 0755, true);
    $zip = new ZipArchive;
    $timestamp = date("Ymd_His");
    $name = $name ?: "backup_$timestamp.zip";
    $backupPath = "$backupDir/$name";
    if ($zip->open($backupPath, ZipArchive::CREATE) === TRUE) {
        foreach ($files as $file) {
            if (is_file($file)) {
                $zip->addFile($file, $file);
            } elseif (is_dir($file)) {
                $filesIterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($file));
                foreach ($filesIterator as $f) {
                    if ($f->isFile()) {
                        $zip->addFile($f->getPathname(), $f->getPathname());
                    }
                }
            }
        }
        $zip->close();
        return $backupPath;
    }
    return false;
}

function parseMandatoryFile($filePath) {
    $mandatory = [];
    if (!file_exists($filePath)) return $mandatory;
    foreach (file($filePath) as $line) {
        $line = trim($line);
        if (!$line) continue;
        if (str_starts_with($line, "//")) {
            $mandatory["__comment_" . md5($line)] = ['comment' => substr($line, 2)];
            continue;
        }
        $parts = explode("//", $line);
        $core = trim($parts[0]);
        $comment = isset($parts[1]) ? trim($parts[1]) : '';
        if (strpos($core, '=') !== false) {
            [$path, $type] = array_map('trim', explode('=', $core));
            $mandatory[$path] = ['type' => $type, 'comment' => $comment];
        }
    }
    return $mandatory;
}

function renderUpdateOptions($mandatory) {
    echo "<form method='post'><h2>Update Optionen</h2><ul>";
    foreach ($mandatory as $path => $info) {
        if (str_starts_with($path, "__comment_")) {
            echo "<li><em>" . htmlspecialchars($info['comment']) . "</em></li>";
            continue;
        }
        $label = htmlspecialchars($path);
        $type = $info['type'];
        $comment = $info['comment'] ? " - " . htmlspecialchars($info['comment']) : '';
        $isUrl = filter_var($path, FILTER_VALIDATE_URL);

        $desc = "($type$comment)" . ($isUrl ? " [Extern]" : "");
        $checked = in_array($type, ['o', 'm']) ? 'checked' : '';
        $disabled = ($type === 'o') ? 'disabled' : '';

        echo "<li><input type='checkbox' name='update[]' value='$path' $checked $disabled> $label $desc</li>";
    }
    echo "</ul><input type='submit' name='do_update' value='Update ausführen'></form>";

    echo "<h3>Hinweise zur Dateitypenkennung (mandatory.txt):</h3>
    <ul>
        <li><strong>m</strong> - Mandatory: Pflichtdatei, wird nicht überschrieben</li>
        <li><strong>e</strong> - Example: Beispieldateien, abwählbar</li>
        <li><strong>u</strong> - User: Benutzerdaten, werden standardmäßig nicht überschrieben</li>
        <li><strong>o</strong> - Overwrite: Muss aktualisiert werden, wird immer überschrieben</li>
        <li><strong>c</strong> - Config: Konfigurationen, neue Werte werden ergänzt, alte auskommentiert</li>
        <li><code>//</code> - Kommentar: Wird im Updater angezeigt</li>
    </ul>";
}

function handleWildcard($path, $base) {
    $expanded = [];
    $realPath = $base . '/' . rtrim($path, '*');
    if (is_dir($realPath)) {
        foreach (scandir($realPath) as $f) {
            if ($f === '.' || $f === '..') continue;
            $expanded[] = rtrim($path, '*') . $f;
        }
    }
    return $expanded;
}

function mergeConfig($local, $new) {
    $localVars = is_file($local) ? include($local) : [];
    $newVars = is_file($new) ? include($new) : [];

    if (!is_array($localVars)) $localVars = [];
    if (!is_array($newVars)) $newVars = [];

    $result = "<?php\n";
    foreach ($newVars as $k => $v) {
        if (!array_key_exists($k, $localVars)) {
            $result .= "\n\$$k = " . var_export($v, true) . "; // NEU";
        } else {
            $result .= "\n\$$k = " . var_export($localVars[$k], true) . ";";
        }
    }
    foreach ($localVars as $k => $v) {
        if (!array_key_exists($k, $newVars)) {
            $result .= "\n// \$$k = " . var_export($v, true) . "; // VERALTET";
        }
    }
    return $result;
}

// Backup-Auswahl UI mit <details>/<summary> Darstellung, details standardmäßig geschlossen
function renderFileTree($dir, $baseDir = '', $inputName = 'backup_files[]') {
    $fullPath = rtrim($baseDir, '/') . '/' . $dir;
    if (!is_dir($fullPath)) return;

    echo "<details style='margin-left:1em;'>"; // KEIN open -> standardmäßig geschlossen
    echo "<summary><label style='cursor:pointer;'><input type='checkbox' class='folder-checkbox' /> <strong>" . htmlspecialchars($dir ?: basename($baseDir)) . "</strong></label></summary>";
    echo "<ul style='list-style:none; margin-left:1em; padding-left:0;'>";

    $items = scandir($fullPath);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = ($dir ? $dir . '/' : '') . $item;
        $fullItemPath = rtrim($baseDir, '/') . '/' . $path;
        if (is_dir($fullItemPath)) {
            // Rekursiv Unterordner mit gleicher Struktur
            renderFileTree($path, $baseDir, $inputName);
        } else {
            // Datei-Checkbox mit value = relativer Pfad
            echo '<li><label><input type="checkbox" name="' . htmlspecialchars($inputName) . '" value="' . htmlspecialchars($path) . '" class="file-checkbox" /> ' . htmlspecialchars($item) . "</label></li>";
        }
    }

    echo "</ul></details>";
}


// Schreibt Datei aus Quellpfad (Update Release) auf Zielpfad mit Berücksichtigung der Regeln
function writeUpdateFile($sourceBase, $file, $type) {
    $targetPath = __DIR__ . '/' . $file;
    $sourcePath = $sourceBase . '/' . $file;

    if (!file_exists($sourcePath)) {
        echo "<p style='color:red;'>Datei im Release nicht gefunden: $file</p>";
        return false;
    }

    // m = mandatory, nicht überschreiben
    if ($type === 'm' && file_exists($targetPath)) {
        echo "<p>Überspringe Mandatory-Datei (existiert): $file</p>";
        return true;
    }

    // o = overwrite, immer überschreiben
    if ($type === 'o') {
        copy($sourcePath, $targetPath);
        echo "<p>Überschreibe Datei (overwrite): $file</p>";
        return true;
    }

    // u = user file, nur kopieren, wenn nicht vorhanden
    if ($type === 'u') {
        if (!file_exists($targetPath)) {
            copy($sourcePath, $targetPath);
            echo "<p>Kopiere User-Datei (neu): $file</p>";
        } else {
            echo "<p>Überspringe User-Datei (existiert): $file</p>";
        }
        return true;
    }

    // c = config, nur neue Werte hinzufügen, alte auskommentieren
    if ($type === 'c') {
        if (!file_exists($targetPath)) {
            copy($sourcePath, $targetPath);
            echo "<p>Konfig-Datei kopiert: $file</p>";
        } else {
            // Einfach zusammenführen (kann angepasst werden)
            $newConfig = file_get_contents($sourcePath);
            $localConfig = file_get_contents($targetPath);

            // Sehr einfache Merge-Strategie (kann man komplexer machen)
            $merged = "// --- Alte Konfig ---\n" . $localConfig . "\n// --- Neue Konfig ---\n" . $newConfig;
            file_put_contents($targetPath, $merged);
            echo "<p>Konfig-Datei zusammengeführt: $file</p>";
        }
        return true;
    }

    // e = example oder unbekannt: überschreiben wenn nicht vorhanden
    if ($type === 'e' || !$type) {
        if (!file_exists($targetPath)) {
            copy($sourcePath, $targetPath);
            echo "<p>Beispiel-Datei kopiert: $file</p>";
        } else {
            echo "<p>Überspringe Beispiel-Datei (existiert): $file</p>";
        }
        return true;
    }

    // Standard: überschreiben, wenn nicht vorhanden
    if (!file_exists($targetPath)) {
        copy($sourcePath, $targetPath);
        echo "<p>Datei kopiert: $file</p>";
    } else {
        echo "<p>Überspringe Datei (existiert): $file</p>";
    }
    return true;
}

// --- Main Logic ---

// Backup wiederherstellen
if (isset($_POST['do_restore']) && !empty($_POST['restore_file'])) {
    $restoreFile = basename($_POST['restore_file']);
    $restorePath = $backupDir . '/' . $restoreFile;
    if (file_exists($restorePath)) {
        echo "<h3>Backup Wiederherstellung gestartet: $restoreFile</h3>";
        $zip = new ZipArchive;
        if ($zip->open($restorePath) === TRUE) {
            for ($i=0; $i < $zip->numFiles; $i++) {
                $filename = $zip->getNameIndex($i);
                echo "Restoriere: $filename<br>";
            }
            $zip->extractTo(__DIR__);
            $zip->close();
            echo "<p>Backup wurde erfolgreich wiederhergestellt.</p>";
        } else {
            echo "<p style='color:red;'>Backup konnte nicht geöffnet werden.</p>";
        }
    } else {
        echo "<p style='color:red;'>Backup-Datei nicht gefunden.</p>";
    }
    exit;
}


// Backup löschen (wenn per POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_backup'])) {
    $fileToDelete = basename($_POST['delete_backup']); // Sicherheit: basename!
    $fullPath = $backupDir . '/' . $fileToDelete;
    if (file_exists($fullPath)) {
        unlink($fullPath);
        echo "<p style='color:green;'>Backup '$fileToDelete' wurde gelöscht.</p>";
    } else {
        echo "<p style='color:red;'>Backup nicht gefunden.</p>";
    }
}

// Backup Download (per GET mit ?download=filename.zip)
if (isset($_GET['download'])) {
    $fileToDownload = basename($_GET['download']);
    $fullPath = $backupDir . '/' . $fileToDownload;
    if (file_exists($fullPath)) {
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $fileToDownload . '"');
        header('Content-Length: ' . filesize($fullPath));
        readfile($fullPath);
        exit;
    } else {
        echo "<p style='color:red;'>Backup nicht gefunden.</p>";
    }
}

// Liste aller Backups
$backups = array_filter(scandir($backupDir), function($f) use ($backupDir) {
    return is_file($backupDir . '/' . $f) && strtolower(pathinfo($f, PATHINFO_EXTENSION)) === 'zip';
});


// Backup erstellen (vom lokalen System)
if (isset($_POST['do_backup']) && !empty($_POST['backup_files'])) {
    $filesToBackup = $_POST['backup_files'];
    echo "<h3>Backup erstellen für:</h3><ul>";
    foreach ($filesToBackup as $file) {
        echo "<li>" . htmlspecialchars($file) . "</li>";
    }
    echo "</ul>";
    $backupPath = createBackup($filesToBackup);
    if ($backupPath) {
        echo "<p>Backup erfolgreich erstellt: " . basename($backupPath) . "</p>";
    } else {
        echo "<p style='color:red;'>Backup konnte nicht erstellt werden.</p>";
    }
    exit;
}

// Update mit Auswahl aus mandatory.txt
if (isset($_POST['do_update']) && !empty($_POST['update'])) {
    $selectedFiles = $_POST['update'];
    echo "<h3>Update wird ausgeführt für:</h3><ul>";
    foreach ($selectedFiles as $f) {
        echo "<li>" . htmlspecialchars($f) . "</li>";
    }
    echo "</ul>";

    // Download Release
    $releaseData = getLatestRelease($releaseApiUrl);
    if (!$releaseData) {
        echo "<p style='color:red;'>Konnte Release-Daten nicht laden.</p>";
        exit;
    }
    $latestVersion = $releaseData['tag_name'];
    $asset = null;
    foreach ($releaseData['assets'] as $a) {
        if (str_ends_with($a['name'], '.zip')) {
            $asset = $a;
            break;
        }
    }
    if (!$asset) {
        echo "<p style='color:red;'>Kein ZIP-Asset im Release gefunden.</p>";
        exit;
    }

    echo "<p>Version $latestVersion wird heruntergeladen...</p>";
    file_put_contents($tmpZip, fopen($asset['browser_download_url'], 'r'));

    // Entpacken
    if (is_dir($tmpDir)) {
        system("rm -rf " . escapeshellarg($tmpDir));
    }
    mkdir($tmpDir);
    if (!extractZip($tmpZip, $tmpDir)) {
        echo "<p style='color:red;'>ZIP konnte nicht entpackt werden.</p>";
        exit;
    }

    // Files kopieren nach Regeln aus mandatory.txt
    $mandatory = parseMandatoryFile($mandatoryFile);
    foreach ($selectedFiles as $file) {
        $type = $mandatory[$file]['type'] ?? '';
        writeUpdateFile($tmpDir, $file, $type);
    }

    // Version speichern
    file_put_contents($versionFile, $latestVersion);
    echo "<p>Update abgeschlossen auf Version $latestVersion.</p>";
    exit;
}

// Zeige UI

echo "<h1>Updater für Repo $githubRepo</h1>";
echo "<p>Aktuelle Version: $currentVersion</p>";

$releaseData = getLatestRelease($releaseApiUrl);
if ($releaseData) {
    echo "<p>Neueste Version: " . htmlspecialchars($releaseData['tag_name']) . "</p>";
    echo "<p>Veröffentlicht am: " . htmlspecialchars($releaseData['published_at']) . "</p>";
} else {
    echo "<p style='color:red;'>Release-Daten konnten nicht geladen werden.</p>";
}

// Lade mandatory.txt und zeige Update Optionen
if (file_exists($mandatoryFile)) {
    $mandatory = parseMandatoryFile($mandatoryFile);
    renderUpdateOptions($mandatory);
} else {
    echo "<p style='color:red;'>mandatory.txt nicht gefunden.</p>";
}

// Backup erstellen UI
echo "<h2>Backup erstellen</h2>";
echo "<form method='post'>";
renderFileTree('', __DIR__);
echo "<input type='submit' name='do_backup' value='Backup erstellen'>";
echo "</form>";

// Backup wiederherstellen UI
?>
<body>
<h2>Backups Wiederherstellung</h2>
<?php if (empty($backups)): ?>
    <p>Keine Backups gefunden.</p>
<?php else: ?>
    <table border="1" cellpadding="5" cellspacing="0">
        <thead>
            <tr><th>Backup Datei</th><th>Datum</th><th>Download</th><th>Löschen</th></tr>
        </thead>
        <tbody>
        <?php foreach ($backups as $backup): 
            $filePath = $backupDir . '/' . $backup;
            $date = date("Y-m-d H:i:s", filemtime($filePath));
        ?>
            <tr>
                <td><?= htmlspecialchars($backup) ?></td>
                <td><?= $date ?></td>
                <td><a href="?download=<?= urlencode($backup) ?>">Herunterladen</a></td>
                <td>
                    <form method="post" onsubmit="return confirm('Backup <?= htmlspecialchars($backup) ?> wirklich löschen?');" style="margin:0;">
                        <input type="hidden" name="delete_backup" value="<?= htmlspecialchars($backup) ?>">
                        <button type="submit">Löschen</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<table>
    <th>Tools</th>
    <tr>
        <td><a href='edit.php'>Editor</a></td>
        <td><a href='update.php'>Updater</a></td>
        <td><a href='c.php'>Configurator</a></td>
    </tr>
</table>



<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('input.folder-checkbox').forEach(folderCheckbox => {
        folderCheckbox.addEventListener('change', () => {
            const details = folderCheckbox.closest('details');
            if (!details) return;
            const childCheckboxes = details.querySelectorAll('input[type="checkbox"]');
            childCheckboxes.forEach(cb => {
                if (cb !== folderCheckbox) {
                    cb.checked = folderCheckbox.checked;
                }
            });
        });
    });
});
</script>

</body>
</html>
