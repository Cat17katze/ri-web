<?php
// ========== CONFIG ==========
$githubUser = 'Cat17katze'; // replace with your GitHub username/org
$githubRepo = 'ri-web';       // replace with your GitHub repo name
$githubBranch = 'main';                // usually 'main' or 'master'

$configFile = __DIR__ . '/config.php';
$installerFilename = basename(__FILE__);

// Load config.php if exists to get $update_password
$update_password = null;
if (file_exists($configFile)) {
    include_once $configFile;
}

// Check for password if set
if (isset($update_password) && !empty($update_password)) {
    session_start();
    if (!isset($_SESSION['installer_authenticated'])) {
        if (isset($_POST['password'])) {
            if ($_POST['password'] === $update_password) {
                $_SESSION['installer_authenticated'] = true;
            } else {
                echo "<h2>Falsches Passwort.</h2>";
                showPasswordForm();
                exit;
            }
        } else {
            showPasswordForm();
            exit;
        }
    }
}

function showPasswordForm() {
    global $installerFilename;
    echo <<<HTML
    <h2>Passwort erforderlich</h2>
    <form method="post" action="$installerFilename">
      Passwort: <input type="password" name="password" required>
      <button type="submit">Login</button>
    </form>
HTML;
}

// GitHub API user agent (required)
$userAgent = 'PHP Installer Script';

// ------------------
// HELPER FUNCTIONS
// ------------------

function githubApiGet($url, $token = '') {
    global $userAgent;

    $headers = [
        "User-Agent: $userAgent",
        "Accept: application/vnd.github.v3+json"
    ];
    if ($token !== '') {
        $headers[] = "Authorization: token $token";
    }

    $opts = [
        "http" => [
            "method" => "GET",
            "header" => implode("\r\n", $headers),
            "timeout" => 30
        ]
    ];
    $context = stream_context_create($opts);

    $response = @file_get_contents($url, false, $context);
    if ($response === false) {
        return false;
    }

    $json = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return false;
    }
    return $json;
}

function downloadRawFile($path) {
    global $githubUser, $githubRepo, $githubBranch;

    $url = "https://raw.githubusercontent.com/$githubUser/$githubRepo/$githubBranch/$path";

    $opts = [
        "http" => [
            "method" => "GET",
            "header" => "User-Agent: PHP Installer Script\r\n",
            "timeout" => 30
        ]
    ];
    $context = stream_context_create($opts);

    $content = @file_get_contents($url, false, $context);
    if ($content === false) {
        throw new Exception("Fehler beim Herunterladen der Datei $path vom Raw-URL.");
    }
    return $content;
}

// Recursively get all files in GitHub repo at given path
function getRepoFilesRecursively($path = '') {
    global $githubUser, $githubRepo, $githubBranch;

    $url = "https://api.github.com/repos/$githubUser/$githubRepo/contents/$path?ref=$githubBranch";

    $items = githubApiGet($url);
    if ($items === false) {
        throw new Exception("Fehler beim Abrufen der Repo-Dateiliste von GitHub API: $url");
    }

    $files = [];
    foreach ($items as $item) {
        if ($item['type'] === 'file') {
            $files[] = $item['path'];
        } elseif ($item['type'] === 'dir') {
            $files = array_merge($files, getRepoFilesRecursively($item['path']));
        }
    }
    return $files;
}

function copyFileFromRepo($filePath) {
    $content = downloadRawFile($filePath);

    $destPath = __DIR__ . '/' . $filePath;
    $dir = dirname($destPath);
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0777, true)) {
            throw new Exception("Fehler beim Erstellen des Verzeichnisses: $dir");
        }
    }

    if (file_put_contents($destPath, $content) === false) {
        throw new Exception("Fehler beim Schreiben der Datei: $destPath");
    }
}

function confirmForm($action) {
    global $installerFilename;
    $msg = '';
    switch ($action) {
        case 'install':
            $msg = 'Alle Dateien aus dem Repo werden installiert und bestehende Dateien im Repo-Set werden ersetzt. Fortfahren?';
            break;
        case 'rescue':
            $msg = 'Nur index.php und config.php werden aus dem Repo ersetzt. Fortfahren?';
            break;
        case 'remove':
            $msg = 'Dieser Installer wird dauerhaft entfernt. Fortfahren?';
            break;
        default:
            $msg = 'Fortfahren?';
    }

    echo <<<HTML
    <h2>Bestätigung benötigt</h2>
    <p>$msg</p>
    <form method="post" action="$installerFilename">
      <input type="hidden" name="confirm_action" value="$action">
      <button type="submit" name="confirm" value="yes">Ja, fortfahren</button>
      <button type="submit" name="confirm" value="no">Abbrechen</button>
    </form>
HTML;
}

// -------------
// MAIN LOGIC
// -------------

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle confirmation forms first
    if (isset($_POST['confirm_action'])) {
        $action = $_POST['confirm_action'];
        if (!isset($_POST['confirm']) || $_POST['confirm'] !== 'yes') {
            echo "<p>Aktion '$action' wurde abgebrochen.</p>";
            echo "<p><a href='$installerFilename'>Zurück</a></p>";
            exit;
        }

        try {
            switch ($action) {
                case 'install':
                    $files = getRepoFilesRecursively();
                    foreach ($files as $file) {
                        copyFileFromRepo($file);
                    }
                    echo "<p>Installation abgeschlossen! Alle Dateien wurden installiert.</p>";
                    break;

                case 'rescue':
                    $filesToRestore = ['index.php', 'config.php'];
                    foreach ($filesToRestore as $file) {
                        copyFileFromRepo($file);
                    }
                    echo "<p>Rettung abgeschlossen! index.php und config.php wurden ersetzt.</p>";
                    break;

                case 'remove':
                    // Remove this installer script file
                    if (unlink(__FILE__)) {
                        echo "<p>Installer wurde entfernt.</p>";
                        exit;
                    } else {
                        echo "<p>Fehler beim Entfernen des Installers.</p>";
                    }
                    break;

                default:
                    echo "<p>Unbekannte Aktion.</p>";
            }
        } catch (Exception $e) {
            echo "<p><strong>Fehler:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
        }

        echo "<p><a href='$installerFilename'>Zurück zur Startseite</a></p>";
        exit;
    }

    // If user clicked a main button, show confirmation form
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        confirmForm($action);
        exit;
    }
}

// Show main UI
?>

<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8" />
<title>Installer / Rettungs-Script</title>
<style>
    body { font-family: Arial, sans-serif; margin: 2em; }
    button { margin: 0.5em 0; padding: 0.5em 1em; font-size: 1em; }
    form { margin-bottom: 1em; }
</style>
</head>
<body>
<h1>Installer / Rettungs-Script</h1>
<p>GitHub Repo: <strong><?= htmlspecialchars("$githubUser/$githubRepo") ?></strong> (Branch: <?= htmlspecialchars($githubBranch) ?>)</p>
<form method="post" action="<?= $installerFilename ?>">
  <button type="submit" name="action" value="install">Volle Installation (alle Repo-Dateien kopieren, ersetzen)</button>
</form>

<form method="post" action="<?= $installerFilename ?>">
  <button type="submit" name="action" value="rescue">Rettung: Nur index.php und config.php ersetzen</button>
</form>

<form method="post" action="<?= $installerFilename ?>">
  <button type="submit" name="action" value="remove" style="color: red;">Installer entfernen</button>
</form>

</body>
</html>
