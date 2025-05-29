<?php
//configuration area
//here you can configure, which extensions are used, and which are not used. The variable name means the component.
//if a configuration file 'ri-config.php' (or other specified) is found, the config here is overridden. Note, that the external config file should be complete!

//Version control
$major=1;
$minor=2;
$security=1;


$config_file = 'config.php';
$use_config_file = true;

function checkConfig($config_file, $use_config_file) {
    if (isset($external_css, $External_css, $navbar, $Navbar, $motd, $Motd, $footer, $Footer, $blog, $Blog_dir, $download, $Download_config, $page, $allowed_pages, $Page_dir)) {
        return true;
    } else {
        return false;
    }
}

if (file_exists($config_file) and $use_config_file) {
    include($config_file);
    if (checkConfig($config_file, $use_config_file)) {
        $error= true;
        $debug = $debug . "<p style='color:red; background-color:black;'>Error: Your config file has errors! Default config is now enabled. Fix your file or deactivate it!</p>";
        $use_config_file = false;
    }
}
elseif ((file_exists($config_file) == false) and $use_config_file) {
    $debug = $debug . "<p style='color:red; background-color:black;'>Error: You choose to use an external config file and did't provide it! Shame on you. Check your configuration. The config file is missing! System felt back to internal config!</p>";
    $use_config_file = false;
    $error = true;
}
if ($use_config_file ==false) {
    
    //in - file configuration, only used, if no config file is found/ deactivated
    //the variables with an Capital Letter define the name of the file, the one with a lower one define, if the component is used
    $external_css=false; //do you want to load external css. Currently not supported!
    $External_css='';
    
    // components of the page. please place them into the specified folder. In the config, the path must not be included, it is default.
    $Components_dir=__DIR__ . '/components/';
    
    $navbar=true; //above everything, sticky on desktops, usefull for navigation
    $Navbar='navbar.html';
    
    $motd=true; //below the navbar, usefull for content (like logos or so) that should be on all sites
    $Motd='motd.html';
    
    $footer=true; //below the content
    $Footer='foot.html';
    
    
    $blog=true; //the 'b' extension, usefull for displaying markdown files with sub- directories
    $Blog_dir=__DIR__ . '/blog/'; //blog directory,
    
    $download=false; //the 'd' extension, usefull for download- page links. Needs the config-d.php file to work.
    $Download_config='config-d.php';
    
    $page=true; //the 'p' extension. Needed, if you want to use the file based - cms - like system. You shouldt deactivate it. You need to configure the allowed pages.
    $allowed_pages = array('home.php', 'about.html', 'contact.html', 'changelog.php', 'spenden.html','archiv.html','service.php','status.php', 'ricloud-info.html', 'contact.php','green.html' , 'license.html'); //the allowed pages to be loaded into the content section via a link. php include is not affected!
    $Page_dir=__DIR__ . '/pages/'; // the allowed pages must be in this directory. No subfolders allowed. If you want to use sub- folders, please use the blog system.

}
if ((file_exists($External_css) == false) and $external_css) {
    echo "<p style='color:red; background-color:black;'>Error: You choose to use an external file and did't provide it! Shame on you. Check your configuration. The external css file is missing!</p>";
    $external_css = false;
    $error=true;
}
if ((file_exists($Components_dir . $Navbar) == false) and $navbar) {
    $debug = $debug . "<p style='color:red; background-color:black;'>Error: You choose to use an external file and did't provide it! Shame on you. Check your configuration. The external navbar file is missing!</p>";
    $navbar = false;
    $error=true;
}
if ((file_exists($Components_dir . $Motd) == false) and $motd) {
    $debug = $debug . "<p style='color:red; background-color:black;'>Error: You choose to use an external file and did't provide it! Shame on you. Check your configuration. The external motd file is missing!</p>";
    $motd = false;
    $error=true;
}
if ((file_exists($Components_dir . $Footer) == false) and $footer) {
    $debug = $debug . "<p style='color:red; background-color:black;'>Error: You choose to use an external file and did't provide it! Shame on you. Check your configuration. The external footer file is missing!</p>";
    $footer = false;
    $error=true;
}

if (false) {
    if ((file_exists($Blog_config) == false) and $blog) {
        $debug = $debug . "<p style='color:red; background-color:black;'>Error: You choose to use an external file and did't provide it! Shame on you. Check your configuration. The external blog config is missing!</p>";
        $blog = false;
        $error=true;
    }
}
if ((file_exists($Download_config) == false) and $download) {
    $debug = $debug . "<p style='color:red; background-color:black;'>Error: You choose to use an external file and did't provide it! Shame on you. Check your configuration. The external download config is missing!</p>";
    $download = false;
    $error=true;
}
if (checkConfig($config_file, $use_config_file)) {
        $error= true;
        $debug = $debug . "<p style='color:red; background-color:black;'>Error: Your in- file config has errors! please fix it asap! The configuration is in rescue mode now!</p>";
        
        $use_config_file = false;
        $external_css=false;
        $External_css='';
        $navbar=false;
        $Navbar='';
        $motd=false; 
        $Motd='';
        $footer=false; 
        $Footer='';
        $blog=false; 
        $Blog_dir='';
        $download=false;
        $Download_config='';
        $page=false;
        $allowed_pages = array();
    }
    
    //functions

    function GetExt($url) {
        $ch = curl_init();
    
        // cURL Optionen setzen
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);  // Rückgabe als String
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);           // Timeout setzen
    
        $result = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
    
        if ($antwort === false) {
            return "Fehler: $error";
        }
    
        return $result;
    }
    
    function loadNamesMap($dir)
    {
        $map = [];
        $namesFile = $dir . DIRECTORY_SEPARATOR . 'names.txt';
        if (!file_exists($namesFile)) return $map;
    
        $lines = file($namesFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos($line, '=') !== false) {
                list($filename, $label) = explode('=', $line, 2);
                $map[trim($filename)] = trim($label);
            }
        }
        return $map;
    }
    
    function listMdFilesWithDirs($dir, $relativePath = '')
    {
        $entries = scandir($dir);
        $dirs = [];
        $files = [];
    
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') continue;
    
            $fullPath = $dir . DIRECTORY_SEPARATOR . $entry;
            $relPath = ltrim($relativePath . '/' . $entry, '/');
    
            if (is_dir($fullPath)) {
                $dirs[] = ['name' => $entry, 'rel' => $relPath, 'full' => $fullPath];
            } elseif (is_file($fullPath) && strtolower(pathinfo($entry, PATHINFO_EXTENSION)) === 'md') {
                $files[] = ['name' => $entry, 'rel' => $relPath];
            }
        }
    
        sort($dirs);
        usort($files, fn($a, $b) => strcmp($a['name'], $b['name']));
    
        if (empty($dirs) && empty($files)) {
            return; // Nothing to display
        }
    
        $nameMap = loadNamesMap($dir);
    
        echo "<ul>";
    
        // Directories
        foreach ($dirs as $subdir) {
            $displayName = htmlspecialchars($nameMap[$subdir['name']] ?? $subdir['name']);
            echo "<li><details><summary>$displayName</summary>";
            listMdFilesWithDirs($subdir['full'], $subdir['rel']);
            echo "</details></li>";
        }
    
        // Files
        foreach ($files as $file) {
            $displayName = htmlspecialchars($nameMap[$file['name']] ?? $file['name']);
            echo "<li><a href=\"?b=" . urlencode($file['rel']) . "\">$displayName</a></li>";
        }
    
        echo "</ul>";
    }

    
    
    function displayMarkdownFile($file, $blog_path)
    {
        $safePath = realpath($blog_path . DIRECTORY_SEPARATOR . $file);
        if (!$safePath || strpos($safePath, $blog_path) !== 0 || pathinfo($safePath, PATHINFO_EXTENSION) !== 'md') {
            echo "<p><strong>Invalid file.</strong></p>";
            return;
        }
    
        $content = file_get_contents($safePath);
        $parsedown = new Parsedown();
        echo "<hr><h2>Viewing: $file</h2>";
        echo $parsedown->text($content);
    }
    
?>



<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rileys Home Page</title>
    <style>
        :root, [data-selected-theme="ri"] {
            --color-background-primary: #FFAAAA;
            --color-background-button: #DD9999;
            --color-background-second: #884444;
            
            --color-text-primary: #220000;
            --color-text-button: #551111;
            --color-text-second: #FFAAAA;
            
            --color-background-rt:#330000;
            --color-text-rt:#FFAAAA;
            
            --color-border:#882222;
            
            --color-accent: #AA8888;
        }
        
        [data-selected-theme="oled"] {
            --color-background-primary: #000000;
            --color-background-button: #220000;
            --color-background-second: #111111;
            
            --color-text-primary: #FFFFFF;
            --color-text-button: #FFBBBB;
            --color-text-second: #FFFFFF;
            
            --color-background-rt:#111111;
            --color-text-rt:#FFBBBB;
            
            --color-border:#AAAAAA;
            
            --color-accent: #AA8888;
        }
        
        [data-selected-theme="gray"] {
            --color-background-primary: #AAAAAA;
            --color-background-button: #FFCCCC;
            --color-background-second: #CCCCCC;
            
            --color-text-primary: #000000;
            --color-text-button: #441111;
            --color-text-second: #444444;
            
            --color-background-rt:#333333;
            --color-text-rt:#FFAAAA;
            
            --color-border:#222222;
            
            --color-accent: #AA8888;
        }
        
        [data-selected-theme="green"] {
            --color-background-primary: #002200;
            --color-background-button: #114411;
            --color-background-second: #00AA00;
            
            --color-text-primary: #22FF22;
            --color-text-button: #22FF22;
            --color-text-second: #003300;
            
            --color-background-rt:#115511;
            --color-text-rt:#11FF11;
            
            --color-border:#33FF33;
            
            --color-accent: #AA8888;
        }
        
        [data-selected-theme="red"] {
            --color-background-primary: #000000;
            --color-background-button: #881111;
            --color-background-second: #BB1111;
            
            --color-text-primary: #FF1111;
            --color-text-button: #FF1111;
            --color-text-second: #441111;
            
            --color-background-rt:#110000;
            --color-text-rt:#FF0000;
            
            --color-border:#CC2222;
            
            --color-accent: #AA8888;
        }
        .content {
            background-color:var(--color-background-primary);
            color:var(--color-text-primary);
            display: flex; 
            flex-direction: column; 
            justify-content: space-evenly;
            min-height:90vh;
        }
        
        body {
            font-family: sans-serif;
            background-color:#000000;
            margin: 0;
            padding: 0;
            color: var(--color-text-primary);
        }
        
        .h {
            visibility: collapse;
        }

        a, summary, button {
            color: var(--color-text-button);
            background-color:var(--color-background-button);
            border-style:solid;
            border-width:2px;
            border-color:var(--color-border);
            border-radius:3px; 
            text-decoration:none;
            min-height:3vh;
            padding:5px;
            padding-top:0px; 
            padding-bottom:0px;
            margin:10px;
        }
        
        a:hover, summary:hover, button:hover {
            background-color: var(--color-background-second);
            color: var(--color-text-second);
        }
        
        h1, h2, h3, h4, h5, h6 {
            padding-top:0px;
            padding-bottom:0px;
            
            margin:0px;
            
            font-weight:bold;
        }
        
        h1 {font-size:2.0em}
        h2 {font-size:1.9em}
        h3 {font-size:1.8em}
        h4 {font-size:1.7em}
        h5 {font-size:1.6em}
        h6 {font-size:1.5em}

        
        block {
            background-color:rgba(255,255,255,0.1);
            
            color:var(--color-text-primary);
            
            text-decoration:none;
            font-family: Verdana, Geneva, Tahoma, sans-serif;
            display: block;
            border: 2px hidden;
            border-radius: 5px;
            padding-bottom: 5px;
            padding-left: 5px;
            margin: 2px;
            text-align: center;
            width: auto;
        }

        nav {
            background-color: var(--color-background-second);
            padding: 10px;
        }

        nav ul {
            background-color: var(--color-background-primary);
            list-style: none;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: space-around;
            flex-wrap: wrap;
        }

        nav li {
            position: relative;
            width:auto;
        }

        nav a {
            color: var(--color-text-button);
            background-color:var(--color-background-button);
            text-decoration: none;
            padding: 5px 10px;
            display: block;
            width:auto;
        }

        nav a:hover {
            background-color: var(--color-background-second);
            color: var(--color-text-second);
        }

        nav ul ul {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            background-color: rgba(10,10,10,0.1);
            box-shadow: 2px 2px 5px rgba(0, 0, 0, 0.2);
            z-index: 1;
            flex-direction: column;
        }

        nav ul ul li {
            width: 100%;
        }

        nav ul ul a {
            padding: 10px;
        }

        nav ul ul a:hover {
            background-color: #ddd;
        }

        .has-submenu > a::after {
            content: " \21F2";
            margin-left: 5px;
        }

        .has-submenu.open > a::after {
            transform: rotate(90deg);
            transition: transform 0.2s ease;
        }

        .has-submenu.open > ul {
            display: flex;
        }

        #content {
            padding: 2px;
        }

        @media (max-width: 800px) {
            nav ul {
                flex-direction: column;
                align-items: center;
            }
            
            .rt-text {
                font-family: monospace;
                font-size: max(0.3vw, 0.2rem);
                max-width:90vw;
            }
            
            nav li {
                width: 100%;
                text-align: center;
            }

            nav ul ul {
                position: relative;
                top: 0;
                left: 0;
            }
            body {
                font-size: 1.2em;
            }
        }
        
        @media (min-width: 800px) {
            nav {
                position: sticky;
                top: 0;
                left: 0;
            }
            .rt-text {
                font-family: monospace;
                font-size: max(0.3vw, 0.3rem);
                max-width:90vw;
            }
        }
        
        
        .rt-text {
            color:var(--color-text-rt); 
            display: flex; 
            flex-direction: column; 
            justify-content: flex-end;
        }
        
        .theme-switcher {
            color:inherit;
            background-color: inherit;
            margin:5px;
            padding:10px;
            border: 2px;
            border-color: var(--color-border);
            border-radius:5px;
            text-align: center;
        }
        
        input, textarea {
            color:var(--color-text-second);
            background-color: var(--color-background-second);
            border: 2px;
            border-color:var(--color-border);
            border-radius:2px;
        }
        
        footer {
            text-align: center;
            left: 0;
            bottom: 0; 
            display: flex; 
            flex-direction: column; 
            justify-content: flex-end; 
            width:100%; 
            height:100%;
            color:var(--color-text-second);
            background-color: var(--color-accent);
            font-family: monospace;
        }

    </style>
</head>
<body>

    <nav>
        <?php 
        if($navbar) {
        include $Components_dir . $Navbar;
        }
        ?>
    </nav>
    <div style="background-color:var(--color-background-rt);">
    
    <?php 
        if($motd) {
        include $Components_dir . $Motd;
        $quote =htmlspecialchars(GetExt("https://riley-tech.de/w/api/site/return-161/"));
        echo "<p style='color:var(--color-text-rt);text-align: center;'> $quote </p>";
        }
    ?>
    
    <div class="theme-switcher">
        <p style="color:var(--color-text-rt)"> Thema: 
        <button data-theme="ri" aria-pressed="true">ri</button>
        <button data-theme="green" aria-pressed="false">green</button>
        <button data-theme="red" aria-pressed="false">red-black</button>
        <button data-theme="oled" aria-pressed="false">oled</button>
        <button data-theme="gray" aria-pressed="false">gray</button>
        </p>
    </div>
    
    </div>
    <div id="content" class="content">
        <?php
            if (file_exists($config_file) and $use_config_file) {
                include($config_file);
            }
            if (isset($_GET['p'])) { 
                $page = isset($_GET['p']) ? basename($_GET['p']) : $page_default;
                
                if ($page === 'sitemap') {
                    // Interne Sonderseite: Sitemap direkt anzeigen
                    echo "<h1>Sitemap</h1><ul>";
                    foreach ($allowed_pages as $entry) {
                        $label = ucfirst(basename($entry, '.' . pathinfo($entry, PATHINFO_EXTENSION)));
                        echo "<li><a href='?p=" . urlencode($entry) . "'>$label</a></li>";
                    }
                    echo "</ul>";
                
                } elseif (!in_array($page, $allowed_pages)) {
                    echo "<p style='color:red;'>Nicht erlaubte Seite: '$page'</p>";
                    echo "<h3>Verfügbare Seiten:</h3><ul>";
                    foreach ($allowed_pages as $allowed) {
                        echo "<li><a href='?p=" . urlencode($allowed) . "'>$allowed</a></li>";
                    }
                    echo "</ul>";
                } else {
                    $file_path = rtrim($Page_dir, '/') . '/' . $page;
                
                    if (file_exists($file_path)) {
                        $extension = pathinfo($file_path, PATHINFO_EXTENSION);
                
                        if ($extension === 'php') {
                            include $file_path; // PHP-Dateien werden ausgeführt
                        } elseif ($extension === 'html') {
                            readfile($file_path); // HTML-Dateien werden direkt ausgegeben
                        } else {
                            echo "<p style='color:red;'>Ungültige Dateiendung.</p>";
                        }
                    } else {
                        echo "<p style='color:red;'>Seite '$page' wurde nicht gefunden.</p>";
                    }
                }
            } elseif (isset($_GET['d']) and $download) {
                include $Download_config;
                $id = $_GET['d'];
                
                
            } elseif (isset($_GET['b']) and $blog) {
                $defaultIndexFile = 'index.md';
                $requestedFile = $_GET['b'] ?? (file_exists($defaultIndexFile) ? $defaultIndexFile : null);
                require_once 'Parsedown.php';

                if ($_GET['b']==='') {
                    $requestedFile = $defaultIndexFile;
                }

                
                echo '<div style="margin-left:5%;margin-right:5%">';
                echo '<h1>Rileys Blog</h1>';
                listMdFilesWithDirs($Blog_dir);
                if ($requestedFile) displayMarkdownFile($requestedFile, $Blog_dir);
                
                echo '</div>';
                
            } elseif (isset($_GET['d']) and $download ==false) {
                echo "<p style='color:red;'>This function is currently deactivated!</p>";
            } elseif (isset($_GET['b']) and $blog ==false) {
                echo "<p style='color:red;'>This function is currently deactivated!</p>";
            } else {
                //echo "<h1>Welcome!</h1><p style='color:red;'>This is the default content.</p>";
                include $Page_dir . $page_default;
            }
        ?>
    </div>
    
    <footer>
    <?php 
    if($footer) {
        include $Components_dir . $Footer;
        }
    if ($error) {
        echo $debug;
    }
    ?>
    </footer>
    
    <script>
        const submenuItems = document.querySelectorAll('.has-submenu');

        submenuItems.forEach(item => {
            const link = item.querySelector('a');

            link.addEventListener('click', (event) => {
                event.preventDefault();
                item.classList.toggle('open');
            });
        });
    </script>
    
    <script>
        document.getElementById("back-button").addEventListener("click", function() {
            window.history.back();
        });
    </script>
    
    <script>
        const pressedButtonSelector = '[data-theme][aria-pressed="true"]';
        const defaultTheme = 'ri';
        
        const applyTheme = (theme) => {
            const target = document.querySelector(`[data-theme="${theme}"]`);
            document.documentElement.setAttribute("data-selected-theme", theme);
            document.querySelector(pressedButtonSelector).setAttribute('aria-pressed', 'false');
            target.setAttribute('aria-pressed', 'true');
        };
        
        const handleThemeSelection = (event) => {
            const target = event.target;
            const isPressed = target.getAttribute('aria-pressed');
            const theme = target.getAttribute('data-theme');        
          
            if(isPressed !== "true") {
                applyTheme(theme);
                localStorage.setItem('selected-theme', theme);
                }
            }
        
        const setInitialTheme = () => {
            const savedTheme = localStorage.getItem('selected-theme');
            if(savedTheme && savedTheme !== defaultTheme) {
                applyTheme(savedTheme);
                }
            };
        
        setInitialTheme();
        
        const themeSwitcher = document.querySelector('.theme-switcher');
        const buttons = themeSwitcher.querySelectorAll('button');
        
        buttons.forEach((button) => {
            button.addEventListener('click', handleThemeSelection);
            });

    </script>
</body>
</html>
