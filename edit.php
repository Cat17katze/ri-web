<?php
session_start();

// === Konfiguration oben im Script ===
$allow_edit_self = false;  // true erlaubt Änderung der edit.php, sonst nein
$allowed_exts = ['php','html','htm','css','md','txt']; // Erlaubte Dateiendungen zum Editieren

// config.php laden falls vorhanden
$configFile = $_SERVER['DOCUMENT_ROOT'] . '/config.php';
$edit_pw = null;
$no_edit = null;
if (file_exists($configFile)) {
    include $configFile;
}

// $edit_pw aus config prüfen, sonst kein Speichern erlauben (readonly)
$readonly = (empty($edit_pw) || !is_string($edit_pw));

// $no_edit: falls nicht definiert, auf null setzen (kein Filterschutz)
if (!isset($no_edit)) {
    $no_edit = null;
}
// Wenn $no_edit leer array ist, dann alles erlaubt (keine Sperre)
if (is_array($no_edit) && count($no_edit) === 0) {
    $no_edit = null;
}

function is_protected($rel, $rules) {
    if ($rules === null) return false; // kein Schutz
    foreach ($rules as $rule) {
        // Wildcards '*' nur für Dateinamen oder Pfade am Ende erlaubt
        $pattern = '#^' . str_replace(['*', '/'], ['[^/]+', '\/'], $rule) . '$#';
        if (preg_match($pattern, $rel)) return true;
    }
    return false;
}

// Login prüfen
if (isset($_POST['unlock']) && isset($_POST['pw']) && $_POST['pw'] === $edit_pw) {
    $_SESSION['auth'] = true;
}

// Externes Syntax-Highlighting aktivieren
if (isset($_POST['highlight'])) {
    $_SESSION['highlight'] = true;
}

$doc_root = realpath($_SERVER['DOCUMENT_ROOT']);
$rel_path = $_GET['e'] ?? '';
$abs_path = realpath($doc_root . '/' . $rel_path);

// Funktion: Verzeichnisbaum auslesen
function list_dir($base, $rel_prefix = '', $level = 0) {
    global $no_edit, $allowed_exts;
    $items = @scandir($base);
    if ($items === false) return ['dirs'=>[], 'files'=>[]];
    $dirs = [];
    $files = [];

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $full = "$base/$item";
        $rel = ltrim("$rel_prefix/$item", '/');

        if (is_protected($rel, $no_edit)) continue;

        if (is_dir($full)) {
            $dirs[$item] = list_dir($full, $rel, $level + 1);
        } else {
            $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
            if (in_array($ext, $allowed_exts)) {
                $files[] = $rel;
            }
        }
    }
    return ['dirs' => $dirs, 'files' => $files];
}

// Funktion: Dateibrowser rendern mit Einrückung
function render_browser($tree, $level = 0) {
    $html = '';
    foreach ($tree['dirs'] as $folder => $sub) {
        $indent = $level * 1.5;
        $html .= "<details style='padding-left: {$indent}em;'><summary>" . htmlspecialchars($folder) . "</summary>";
        $html .= render_browser($sub, $level + 1);
        $html .= "</details>";
    }
    if (!empty($tree['files'])) {
        $indent = $level * 1.5;
        $html .= "<ul style='padding-left: {$indent}em;'>";
        foreach ($tree['files'] as $file) {
            $html .= "<li><a href='?e=" . urlencode($file) . "'>" . htmlspecialchars(basename($file)) . "</a></li>";
        }
        $html .= "</ul>";
    }
    return $html;
}

$error = '';
// Speichern prüfen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$readonly && !empty($_SESSION['auth']) && isset($_POST['content'], $_POST['e'])) {
    $target_rel = $_POST['e'];
    $target_abs = realpath($doc_root . '/' . $target_rel);

    if ($target_abs === false || strpos($target_abs, $doc_root) !== 0) {
        $error = "Ungültiger Pfad.";
    } elseif (is_protected($target_rel, $no_edit)) {
        $error = "Datei ist geschützt und kann nicht gespeichert werden.";
    } elseif (!$allow_edit_self && realpath(__FILE__) === $target_abs) {
        $error = "Das Ändern von edit.php ist nicht erlaubt.";
    } else {
        // Datei-Endung prüfen
        $ext = strtolower(pathinfo($target_abs, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed_exts)) {
            $error = "Dateityp nicht zum Bearbeiten erlaubt.";
        } else {
            // Speichern
            $writeResult = @file_put_contents($target_abs, $_POST['content']);
            if ($writeResult === false) {
                $error = "Fehler beim Speichern der Datei.";
            }
        }
    }
}

// Editor Content vorbereiten
$content = '';
$mode = 'plaintext';

if ($rel_path && $abs_path && is_file($abs_path) && strpos($abs_path, $doc_root) === 0) {
    if (!is_protected($rel_path, $no_edit)) {
        $content = file_get_contents($abs_path);
        $ext = strtolower(pathinfo($abs_path, PATHINFO_EXTENSION));
        $modes = [
            'php' => 'application/x-httpd-php',
            'html' => 'htmlmixed',
            'htm' => 'htmlmixed',
            'css' => 'css',
            'md' => 'markdown',
            'txt' => 'plaintext'
        ];
        $mode = $modes[$ext] ?? 'plaintext';
    } else {
        $error = "Die Datei ist geschützt und kann nicht angezeigt werden.";
        $content = '';
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>Editor</title>
<style>
    body { font-family: sans-serif; padding: 1em; }
    details summary { font-weight: bold; cursor: pointer; }
    ul { list-style: none; padding-left: 1em; }
    li { margin-bottom: 0.3em; }
    textarea { width: 100%; height: 70vh; font-family: monospace; }
    .CodeMirror { height: auto; border: 1px solid #ccc; margin-top: 1em; }
    .error { color: red; font-weight: bold; }
</style>
</head>
<body>
<?php if ($error): ?>
    <p class="error"><?=htmlspecialchars($error)?></p>
<?php endif; ?>

<?php if ($readonly): ?>
    <p><strong>Kein Passwort in der Konfiguration gefunden. Nur Lesemodus ist aktiv.</strong></p>
<?php endif; ?>

<?php if (!$readonly && empty($_SESSION['auth'])): ?>
    <!-- Passwort-Eingabeformular -->
    <form method="POST" style="margin-bottom: 2em;">
        <label>Passwort: <input type="password" name="pw" required autofocus></label>
        <button type="submit" name="unlock">Editor entsperren</button>
    </form>
<?php else: ?>
    
    <table>
    <th>Tools</th>
    <tr>
        <td><a href='edit.php'>Editor</a></td>
        <td><a href='update.php'>Updater</a></td>
        <td><a href='c.php'>Configurator</a></td>
    </tr>
    </table>
    
    <h1>Dateibrowser</h1>
    <?= render_browser(list_dir($doc_root)) ?>

    <?php if ($rel_path && $content !== ''): ?>
        <hr>
        <h2>Bearbeiten: <?=htmlspecialchars($rel_path)?></h2>

        <form method="POST">
            <textarea id="editor" name="content"><?=htmlspecialchars($content)?></textarea><br>
            <input type="hidden" name="e" value="<?=htmlspecialchars($rel_path)?>">
            <button type="submit" <?= $readonly ? 'disabled' : '' ?>>Speichern</button>
            <?php if ($readonly): ?>
                <small>(Speichern deaktiviert im Lesemodus)</small>
            <?php endif; ?>
        </form>

        <?php if (!$readonly): ?>
            <?php if (empty($_SESSION['highlight'])): ?>
                <form method="POST" style="margin-top:1em;">
                    <input type="hidden" name="e" value="<?=htmlspecialchars($rel_path)?>">
                    <button type="submit" name="highlight">Ext. SyntaxHighlight aktivieren</button>
                </form>
            <?php else: ?>
                <script>
                const files = [
                    "https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.14/codemirror.min.css",
                    "https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.14/codemirror.min.js",
                    "https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.14/mode/xml/xml.min.js",
                    "https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.14/mode/javascript/javascript.min.js",
                    "https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.14/mode/css/css.min.js",
                    "https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.14/mode/htmlmixed/htmlmixed.min.js",
                    "https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.14/mode/php/php.min.js",
                    "https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.14/mode/markdown/markdown.min.js"
                ];

                files.forEach(file => {
                    if (file.endsWith(".css")) {
                        const link = document.createElement('link');
                        link.rel = 'stylesheet';
                        link.href = file;
                        document.head.appendChild(link);
                    } else {
                        const script = document.createElement('script');
                        script.src = file;
                        document.body.appendChild(script);
                    }
                });

                const interval = setInterval(() => {
                    if (window.CodeMirror) {
                        clearInterval(interval);
                        CodeMirror.fromTextArea(document.getElementById('editor'), {
                            lineNumbers: true,
                            mode: "<?= $mode ?>",
                            theme: "default"
                        });
                    }
                }, 200);
                </script>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>

<?php endif; ?>

</body>
</html>
