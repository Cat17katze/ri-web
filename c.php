<?php
session_start();

$config_path = __DIR__ . '/config.php';
$backup_dir = $_SERVER['DOCUMENT_ROOT'] . '/config_backups';
if (!is_dir($backup_dir)) mkdir($backup_dir, 0755, true);

$config_lines = file_exists($config_path) ? file($config_path) : [];
$config_data = [];
$line_map = [];
foreach ($config_lines as $i => $line) {
    if (preg_match('/^\s*\$(\w+)\s*=\s*(.*?);(?:\s*\/\/(.*))?$/', $line, $m)) {
        $config_data[$m[1]] = [
            'value' => trim($m[2]),
            'line' => $i,
            'comment' => isset($m[3]) ? ' // ' . trim($m[3]) : ''
        ];
        $line_map[$i] = ['type' => 'var', 'key' => $m[1]];
    } else {
        $text = rtrim($line);
        $inline_edit = false;
        if (preg_match("/['\"](.*?)['\"]/", $text, $match)) {
            $inline_edit = $match[1];
        }
        // Check if blank line
        $is_blank = trim($text) === '';
        $line_map[$i] = [
            'type' => $is_blank ? 'blank' : 'comment',
            'text' => $text,
            'before' => $i > 0 && trim($config_lines[$i-1]) === '',
            'after' => isset($config_lines[$i+1]) && trim($config_lines[$i+1]) === '',
            'inline_edit' => $inline_edit
        ];
    }
}

$pw = isset($config_data['config_pw']) ? trim($config_data['config_pw']['value'], "'\" ") : '';
$edit_mode = isset($_GET['edit']) && ($_SESSION['authenticated'] ?? false);
$editing_enabled = $edit_mode && ($_SESSION['editing_enabled'] ?? false);
$easy_edit_enabled = $editing_enabled && ($_SESSION['easy_edit'] ?? false);

if (isset($_POST['password']) && $pw !== '' && $_POST['password'] === $pw) {
    $_SESSION['authenticated'] = true;
    $_SESSION['editing_enabled'] = false;
    $_SESSION['easy_edit'] = false;
    header('Location: ?');
    exit;
}

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ?');
    exit;
}

if (isset($_POST['enable_edit'])) {
    if ($_SESSION['authenticated'] ?? false) {
        $_SESSION['editing_enabled'] = true;
        $_SESSION['easy_edit'] = false;
    }
    header('Location: ?edit=1');
    exit;
}

if (isset($_POST['disable_edit'])) {
    $_SESSION['editing_enabled'] = false;
    $_SESSION['easy_edit'] = false;
    header('Location: ?edit=1');
    exit;
}

if (isset($_POST['toggle_easy_edit'])) {
    if ($editing_enabled) {
        $_SESSION['easy_edit'] = !($_SESSION['easy_edit'] ?? false);
    }
    header('Location: ?edit=1');
    exit;
}

function backup_config() {
    global $config_path, $backup_dir;
    $ver = 'unknown';
    foreach (file($config_path) as $line) {
        if (preg_match('/\$config_version\s*=\s*([\'\"]?)(.*?)\1\s*;/', $line, $m)) {
            $ver = $m[2];
            break;
        }
    }
    $ts = date('Ymd_His');
    $filename = "$backup_dir/config_{$ver}_{$ts}.php";
    copy($config_path, $filename);
}

function infer_type($val) {
    $val_lc = strtolower($val);
    if ($val_lc === 'true' || $val_lc === 'false') return 'bool';
    if (is_numeric($val)) return 'number';
    if (preg_match('/^["\'].*["\']$/', $val)) return 'string';
    return 'unknown';
}

if ($editing_enabled && isset($_POST['save_key']) && isset($_POST['save_val'])) {
    $k = $_POST['save_key'];
    if ($k !== 'config_version' && isset($config_data[$k])) {
        backup_config();
        $new_val = trim($_POST['save_val']);
        // Replace newlines with space to avoid breaking config.php
        $new_val = str_replace(["\r", "\n"], ' ', $new_val);

        // If easy edit, process value depending on type
        if ($easy_edit_enabled && isset($_POST['type'])) {
            $type = $_POST['type'];
            if ($type === 'string') {
                // Escape single quotes inside input
                $escaped = str_replace("'", "\\'", $new_val);
                $new_val = "'$escaped'";
            } elseif ($type === 'bool') {
                $new_val = ($new_val === '1' || $new_val === 'true') ? 'true' : 'false';
            } elseif ($type === 'number') {
                // Just numeric as is, fallback to 0 if invalid
                $new_val = is_numeric($new_val) ? $new_val : '0';
            }
        }

        $comment = $config_data[$k]['comment'] ?? '';
        $config_lines[$config_data[$k]['line']] = "\$$k = $new_val;$comment\n";
        file_put_contents($config_path, implode("", $config_lines));
        header("Location: ?edit=1&saved=$k");
        exit;
    }
}

if ($editing_enabled && isset($_POST['save_all']) && isset($_POST['all_vars']) && is_array($_POST['all_vars'])) {
    backup_config();
    foreach ($_POST['all_vars'] as $k => $new_val) {
        if ($k !== 'config_version' && isset($config_data[$k])) {
            $new_val = str_replace(["\r", "\n"], ' ', trim($new_val));

            if ($easy_edit_enabled && isset($_POST['types'][$k])) {
                $type = $_POST['types'][$k];
                if ($type === 'string') {
                    $escaped = str_replace("'", "\\'", $new_val);
                    $new_val = "'$escaped'";
                } elseif ($type === 'bool') {
                    $new_val = ($new_val === '1' || $new_val === 'true') ? 'true' : 'false';
                } elseif ($type === 'number') {
                    $new_val = is_numeric($new_val) ? $new_val : '0';
                }
            }

            $comment = $config_data[$k]['comment'] ?? '';
            $config_lines[$config_data[$k]['line']] = "\$$k = $new_val;$comment\n";
        }
    }
    file_put_contents($config_path, implode("", $config_lines));
    header("Location: ?edit=1&saved=all");
    exit;
}

if ($editing_enabled && isset($_POST['comment_edit']) && isset($_POST['comment_line'])) {
    $idx = (int)$_POST['comment_line'];
    $new_val = str_replace(['"', "'"], '', $_POST['comment_edit']);
    $config_lines[$idx] = preg_replace("/(['\"])(.*?)(['\"])/", "'$new_val'", $config_lines[$idx]);
    file_put_contents($config_path, implode("", $config_lines));
    header("Location: ?edit=1&comment_saved=$idx");
    exit;
}

?><!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Config Editor</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        table { border-collapse: collapse; width: 100%; margin-top: 20px; }
        th, td { border: 1px solid #ccc; padding: 8px; vertical-align: top; }
        th { background: #eee; text-align: left; }
        i { font-style: italic; }
        h2 { text-align: center; margin: 20px 0 5px; }
        .center { text-align: center; }
        .blank-line-row td { height: 6px; background: transparent; border: none; padding: 0; }
        textarea, input[type="text"], input[type="number"] {
            width: 100%;
            box-sizing: border-box;
            font-family: monospace;
            font-size: 1em;
            resize: vertical;
        }
        textarea {
            min-height: 2.5em;
            max-height: 4em;
            overflow-y: auto;
        }
        .action-btn {
            padding: 5px 10px;
            font-size: 0.9em;
            cursor: pointer;
        }
        .toggle-btn {
            margin-left: 10px;
            font-size: 0.9em;
            cursor: pointer;
        }
        .login-form input[type="password"], .login-form input[type="submit"] {
            font-size: 1em;
            padding: 6px 10px;
        }
        form.inline-form {
            display: inline-block;
            margin: 0;
        }
        .logout-link {
            margin-left: 20px;
        }
    </style>
</head>
<body>

<?php if (!$pw): ?>
    <p>⚠️ No password is set. Configuration is read-only for security.</p>
<?php elseif (!($_SESSION['authenticated'] ?? false)): ?>
    <form method="post" class="login-form">
        <label>Password: <input type="password" name="password"></label>
        <input type="submit" value="Login">
    </form>
<?php else: ?>
    <p>
        Logged in.
        <table>
            <th>Tools</th>
            <tr>
                <td><a href='edit.php'>Editor</a></td>
                <td><a href='update.php'>Updater</a></td>
                <td><a href='c.php'>Configurator</a></td>
            </tr>
        </table>
        <?php if (!$editing_enabled): ?>
            <form method="post" style="display:inline;">
                <button type="submit" name="enable_edit">Enable Editing</button>
            </form>
        <?php else: ?>
            <form method="post" style="display:inline;">
                <button type="submit" name="disable_edit">Disable Editing</button>
            </form>
            <form method="post" style="display:inline;">
                <button type="submit" name="toggle_easy_edit"><?= $easy_edit_enabled ? 'Disable' : 'Enable' ?> Easy Edit</button>
            </form>
        <?php endif; ?>

        <a href="?logout=1" class="logout-link">Logout</a>
    </p>
<?php endif; ?>

<?php if ($_SESSION['authenticated'] ?? false): ?>
<form method="post">
<?php
$table_head = '<tr><th>Variable</th><th>Content</th><th>Comment</th>' . ($editing_enabled ? '<th>Action</th>' : '') . '</tr>';

echo "<table>";
echo $table_head;

foreach ($line_map as $i => $item) {
    if ($item['type'] === 'var') {
        $key = $item['key'];
        $val_raw = $config_data[$key]['value'];
        $com = $config_data[$key]['comment'];

        // Display values in input fields depending on mode
        echo "<tr><td><b>\$$key</b></td>";

        if ($editing_enabled && $key !== 'config_version') {
            $type = infer_type($val_raw);
            // Prepare value for easy edit (strip quotes for strings)
            $val_for_input = $val_raw;
            if ($type === 'string') {
                $val_for_input = preg_replace('/^["\'](.*)["\']$/', '$1', $val_raw);
                // unescape any \' inside string for display
                $val_for_input = str_replace("\\'", "'", $val_for_input);
            } elseif ($type === 'bool') {
                $val_for_input = (strtolower($val_raw) === 'true') ? '1' : '0';
            }

            if ($easy_edit_enabled) {
                if ($type === 'string') {
                    $safe_val = htmlspecialchars($val_for_input, ENT_QUOTES);
                    echo "<td><input type='text' name='all_vars[$key]' value='$safe_val'></td>";
                } elseif ($type === 'bool') {
                    $checked = ($val_for_input === '1') ? 'checked' : '';
                    echo "<td class='center'><input type='checkbox' name='all_vars[$key]' value='1' $checked></td>";
                } elseif ($type === 'number') {
                    $safe_val = htmlspecialchars($val_raw, ENT_QUOTES);
                    echo "<td><input type='number' name='all_vars[$key]' value='$safe_val'></td>";
                } else {
                    // fallback to textarea for unknown types
                    $safe_val = htmlspecialchars($val_raw, ENT_QUOTES);
                    echo "<td><textarea name='all_vars[$key]' rows='2' oninput='this.value=this.value.replace(/[\\r\\n]/g, \" \");'>$safe_val</textarea></td>";
                }
                echo "<td><i>" . htmlspecialchars($com) . "</i></td>";
                echo "<td><button class='action-btn' formaction='' formmethod='post' name='save_key' value='$key'>";
                echo "<input type='hidden' name='save_val' value=''>Save</button></td>";
                echo "<input type='hidden' name='types[$key]' value='$type'>";
            } else {
                // full edit mode: multiline textarea, replace newlines on input
                $safe_val = htmlspecialchars($val_raw, ENT_QUOTES);
                echo "<td><textarea name='all_vars[$key]' rows='2' oninput='this.value=this.value.replace(/[\\r\\n]/g, \" \");'>$safe_val</textarea></td>";
                echo "<td><i>" . htmlspecialchars($com) . "</i></td>";
                echo "<td><button class='action-btn' formaction='' formmethod='post' name='save_key' value='$key'>";
                echo "<input type='hidden' name='save_val' value=''>Save</button></td>";
            }
        } else {
            // read-only display
            echo "<td>" . htmlspecialchars($val_raw) . "</td>";
            echo "<td><i>" . htmlspecialchars($com) . "</i></td>";
            if ($editing_enabled) echo "<td></td>";
        }
        echo "</tr>";
    } elseif ($item['type'] === 'comment') {
        $txt = trim($item['text']);
        if ($txt === '') continue;

        if ($item['before'] && $item['after']) {
            echo "</table><h2>" . htmlspecialchars($txt) . "</h2><table>";
            echo $table_head;
        } else {
            echo "<tr><td colspan='" . ($editing_enabled ? 4 : 3) . "' class='center'>";
            if ($editing_enabled && $item['inline_edit']) {
                echo "<form method='post' class='inline-form'>";
                echo "<input type='hidden' name='comment_line' value='$i'>";
                $inline_val = htmlspecialchars($item['inline_edit'], ENT_QUOTES);
                echo "<input type='text' name='comment_edit' value='$inline_val' style='max-width:300px;'>";
                echo "<button type='submit'>Save</button>";
                echo "</form> ";
            }
            echo htmlspecialchars($txt);
            echo "</td></tr>";
        }
    } elseif ($item['type'] === 'blank') {
        echo "<tr class='blank-line-row'><td colspan='" . ($editing_enabled ? 4 : 3) . "'></td></tr>";
    }
}
echo "</table>";
?>

<?php if ($editing_enabled): ?>
    <p><button type="submit" name="save_all" value="1">Save All</button></p>
<?php endif; ?>
</form>
<?php endif; ?>

</body>
</html>
