<?php
set_time_limit(0);
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ======= CONFIG: Set this to the absolute path of your repo root =======
$repoSource = '/path/to/your/local/repo'; // << CHANGE this!!

if (!is_dir($repoSource)) {
    die("Repo source path does not exist or is not a directory. Please set \$repoSource correctly.");
}

$targetDir = __DIR__;

// Try to get $update_password from config.php if exists
$update_password = null;
if (file_exists($targetDir . '/config.php')) {
    // Suppress errors if config.php has errors
    @include $targetDir . '/config.php';
    if (isset($update_password)) {
        $update_password = trim($update_password);
        if ($update_password === '') $update_password = null;
    }
}

session_start();

function redirectToSelf($params = []) {
    $url = $_SERVER['PHP_SELF'];
    if (!empty($params)) {
        $url .= '?' . http_build_query($params);
    }
    header("Location: $url");
    exit;
}

// Handle logout action
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    redirectToSelf();
}

// Password required check
if ($update_password !== null) {
    if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
            if ($_POST['password'] === $update_password) {
                $_SESSION['authenticated'] = true;
                redirectToSelf();
            } else {
                $login_error = "Ungültiges Passwort.";
            }
        }

        // Show login form and exit
        ?>
        <!DOCTYPE html>
        <html lang="de">
        <head><meta charset="UTF-8" /><title>Login</title></head>
        <body>
        <h2>Bitte Passwort eingeben</h2>
        <?php if (!empty($login_error)): ?>
            <p style="color:red;"><?=htmlspecialchars($login_error)?></p>
        <?php endif; ?>
        <form method="POST">
            <input type="password" name="password" autofocus required />
            <button type="submit">Anmelden</button>
        </form>
        </body>
        </html>
        <?php
        exit;
    }
}

// Helper functions

function getAllRepoFiles($dir, $baseDir) {
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $fullPath = $file->getRealPath();
            $relative = substr($fullPath, strlen($baseDir) + 1);
            $files[] = $relative;
        }
    }
    return $files;
}

function copyRepoFile($repoSource, $targetDir, $relativePath) {
    $srcPath = $repoSource . DIRECTORY_SEPARATOR . $relativePath;
    $dstPath = $targetDir . DIRECTORY_SEPARATOR . $relativePath;

    $dstDir = dirname($dstPath);
    if (!is_dir($dstDir)) {
        mkdir($dstDir, 0755, true);
    }

    return copy($srcPath, $dstPath);
}

// State messages
$message = '';
$error = false;

// Confirm actions flow: step 1: user clicks button => show confirm page => step 2: user confirms => execute

// Read action + confirmation
$action = $_POST['action'] ?? null;
$confirm = isset($_POST['confirm']) && $_POST['confirm'] === 'yes';

if ($action && !$confirm) {
    // Show confirmation form
    ?>
    <!DOCTYPE html>
    <html lang="de">
    <head>
        <meta charset="UTF-8" />
        <title>Aktion bestätigen</title>
        <style>
            body { font-family: sans-serif; max-width: 600px; margin: 2em auto; }
            button { font-size: 1.1em; padding: 0.5em 1em; margin: 0.5em 0; }
        </style>
    </head>
    <body>
        <h1>Aktion bestätigen</h1>
        <p>Bitte bestätigen Sie, dass Sie die Aktion <strong><?=htmlspecialchars($action)?></strong> ausführen möchten.</p>
        <form method="POST">
            <input type="hidden" name="action" value="<?=htmlspecialchars($action)?>" />
            <input type="hidden" name="confirm" value="yes" />
            <button type="submit">Ja, ausführen</button>
            <button type="button" onclick="window.history.back()">Abbrechen</button>
        </form>
    </body>
    </html>
    <?php
    exit;
}

if ($action && $confirm) {
    try {
        if ($action === 'full_install') {
            $repoFiles = getAllRepoFiles($repoSource, $repoSource);

            foreach ($repoFiles as $file) {
                if (!copyRepoFile($repoSource, $targetDir, $file)) {
                    throw new Exception("Fehler beim Kopieren von $file");
                }
            }
            $message = "✅ Vollständige Installation abgeschlossen. Alle Repo-Dateien wurden kopiert und ggf. überschrieben.";
        } elseif ($action === 'rescue') {
            $filesToRestore = ['index.php', 'config.php'];
            foreach ($filesToRestore as $file) {
                if (!copyRepoFile($repoSource, $targetDir, $file)) {
                    throw new Exception("Fehler beim Wiederherstellen von $file");
                }
            }
            $message = "✅ Rettungsmodus ausgeführt: index.php und config.php wurden erfolgreich ersetzt.";
        } elseif ($action === 'remove_installer') {
            $self = __FILE__;
            if (unlink($self)) {
                echo "<p>✅ Installer-Skript erfolgreich entfernt.</p>";
                echo "<p><a href=\"./\">Zurück zur Hauptseite</a></p>";
                exit;
            } else {
                throw new Exception("Installer-Skript konnte nicht gelöscht werden.");
            }
        } else {
            throw new Exception("Unbekannte Aktion.");
        }
    } catch (Exception $e) {
        $message = "❌ Fehler: " . $e->getMessage();
        $error = true;
    }
}

?>

<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8" />
<title>Installer mit Passwortschutz</title>
<style>
    body { font-family: sans-serif; max-width: 600px; margin: 2em auto; }
    button { font-size: 1.1em; padding: 0.5em 1em; margin: 0.5em 0; }
    .message { margin: 1em 0; font-weight: bold; }
    .error { color: red; }
    .success { color: green; }
    form.inline { display: inline; }
    .logout { float: right; font-size: 0.9em; }
</style>
</head>
<body>

<div class="logout"><form method="GET"><button name="action" value="logout" type="submit">Abmelden</button></form></div>

<h1>Installation / Rettungsmodus</h1>

<?php if ($message): ?>
    <div class="message <?= $error ? 'error' : 'success' ?>">
        <?=htmlspecialchars($message)?>
    </div>
<?php endif; ?>

<form method="POST" class="inline">
    <input type="hidden" name="action" value="full_install" />
    <button type="submit">🚀 Vollständige Installation (alle Repo-Dateien kopieren und ersetzen)</button>
</form>

<form method="POST" class="inline">
    <input type="hidden" name="action" value="rescue" />
    <button type="submit">🛠️ Rettungsmodus (nur index.php und config.php ersetzen)</button>
</form>

<form method="POST" class="inline" onsubmit="return confirm('Möchten Sie das Installer-Skript wirklich löschen? Diese Aktion kann nicht rückgängig gemacht werden.')">
    <input type="hidden" name="action" value="remove_installer" />
    <button type="submit" style="background:#cc4444; color:#fff;">❌ Installer entfernen</button>
</form>

<p><em>Hinweis:</em>  
<ul>
    <li>Nur Repo-Dateien werden kopiert oder ersetzt.</li>
    <li>Existierende Dateien mit anderen Namen bleiben erhalten.</li>
    <li>Bitte überprüfen Sie, dass <code>$repoSource</code> im Skript korrekt gesetzt ist.</li>
    <li>Wenn in <code>config.php</code> ein <code>$update_password</code> definiert ist, ist ein Login erforderlich.</li>
</ul>
</p>

</body>
</html>
