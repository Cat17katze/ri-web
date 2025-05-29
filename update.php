<?php
set_time_limit(0);
error_reporting(E_ALL);
ini_set('display_errors', 1);

$targetDir = __DIR__;

// CONFIGURATION
// GitHub repo info:
$githubUser = 'Cat17katze';
$githubRepo = 'ri-web';
$githubBranch = 'main';  // or master, or tag/branch name

// Password from config.php if defined
$update_password = null;
if (file_exists($targetDir . '/config.php')) {
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

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    redirectToSelf();
}

// Password protection
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

        ?>
        <!DOCTYPE html>
        <html lang="de"><head><meta charset="UTF-8" /><title>Login</title></head><body>
        <h2>Bitte Passwort eingeben</h2>
        <?php if (!empty($login_error)): ?>
            <p style="color:red;"><?=htmlspecialchars($login_error)?></p>
        <?php endif; ?>
        <form method="POST">
            <input type="password" name="password" autofocus required />
            <button type="submit">Anmelden</button>
        </form>
        </body></html>
        <?php
        exit;
    }
}

// GitHub API base URL for contents:
$apiBase = "https://api.github.com/repos/$githubUser/$githubRepo/contents/";

// User-Agent header required by GitHub API
$userAgent = "PHP Installer Script";

// Optional: add your GitHub token here to increase rate limits (leave empty if none)
$githubToken = '';

// Helper: do HTTP GET with headers and return decoded JSON or false on error
function githubApiGet($url, $token = '') {
    global $userAgent;
    $headers = [
        "User-Agent: $userAgent",
        "Accept: application/vnd.github.v3+json"
    ];
    if ($token !== '') {
        $headers[] = "Authorization: token $token";
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_FAILONERROR, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $err = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $code >= 400) {
        return false;
    }

    $json = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return false;
    }

    return $json;
}

// Recursively get all files in the repo at a path
function getRepoFilesRecursively($path = '') {
    global $apiBase, $githubToken;

    $fullUrl = $apiBase . ($path === '' ? '' : $path);
    $items = githubApiGet($fullUrl, $githubToken);
    if ($items === false) {
        throw new Exception("Fehler beim Abrufen der Repo-Inhalte für Pfad: $path");
    }
    $files = [];
    foreach ($items as $item) {
        if ($item['type'] === 'file') {
            $files[] = $item['path'];
        } elseif ($item['type'] === 'dir') {
            $files = array_merge($files, getRepoFilesRecursively($item['path']));
        }
        // ignore symlinks and submodules for simplicity
    }
    return $files;
}

// Download raw file content by repo path
function downloadRawFile($path) {
    global $githubUser, $githubRepo, $githubBranch;

    // Raw content URL format:
    // https://raw.githubusercontent.com/{user}/{repo}/{branch}/{path}
    $url = "https://raw.githubusercontent.com/$githubUser/$githubRepo/$githubBranch/$path";

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_FAILONERROR, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $content = curl_exec($ch);
    $err = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($content === false || $code >= 400) {
        throw new Exception("Fehler beim Herunterladen der Datei $path vom Raw-URL.");
    }
    return $content;
}

// Save file content to target dir with folder creation
function saveFile($targetDir, $relativePath, $content) {
    $dstPath = $targetDir . DIRECTORY_SEPARATOR . $relativePath;
    $dstDir = dirname($dstPath);
    if (!is_dir($dstDir)) {
        if (!mkdir($dstDir, 0755, true)) {
            throw new Exception("Konnte Verzeichnis $dstDir nicht erstellen.");
        }
    }
    if (file_put_contents($dstPath, $content) === false) {
        throw new Exception("Fehler beim Schreiben der Datei $dstPath");
    }
}

// State messages
$message = '';
$error = false;

// Confirm actions flow: step 1: user clicks button => show confirm page => step 2: user confirms => execute

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
            $files = getRepoFilesRecursively('');
            $count = 0;
            foreach ($files as $file) {
                $content = downloadRawFile($file);
                saveFile($targetDir, $file, $content);
                $count++;
            }
            $message = "✅ Vollständige Installation abgeschlossen. $count Dateien kopiert und ggf. ersetzt.";
        } elseif ($action === 'rescue') {
            $filesToRestore = ['index.php', 'config.php'];
            $count = 0;
            foreach ($filesToRestore as $file) {
                $content = downloadRawFile($file);
                saveFile($targetDir, $file, $content);
                $count++;
            }
            $message = "✅ Rettungsmodus ausgeführt: index.php und config.php wurden ersetzt.";
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
<title>Installer mit GitHub Repo - Direkt Download</title>
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
    <button type="submit">🚀 Vollständige Installation (alle Dateien aus GitHub Repo kopieren und ersetzen)</button>
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
    <li>Die Dateien werden direkt per GitHub API heruntergeladen (keine ZIP-Dateien).</li>
    <li>Nur Repo-Dateien werden kopiert oder ersetzt.</li>
    <li>Existierende Dateien mit anderen Namen bleiben erhalten.</li>
    <li>Bitte überprüfen Sie, dass <code>$githubUser</code>, <code>$githubRepo</code> und <code>$githubBranch</code> korrekt gesetzt sind.</li>
    <li>Wenn in <code>config.php</code> ein <code>$update_password</code> definiert ist, ist ein Login erforderlich.</li>
</ul>
</p>

</body>
</html>
