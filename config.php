<?php
// Configuration file for the RiWeb file cms

//in - file configuration, only used, if no config file is found/ deactivated
//the variables with an Capital Letter define the name of the file or directory, the one with a lower one define, if the component is used
$external_css=false; //do you want to load external css. Currently not supported!
$External_css=''; //currently not supported

// components of the page. please place them into the specified folder
$Components_dir=__DIR__ . '/components/';

$navbar=true; //above everything, sticky on desktops, usefull for navigation
$Navbar='navbar.html';

$motd=true; //below the navbar, usefull for content (like logos or so) that should be on all sites
$Motd='motd.html';

$footer=true; //below the content
$Footer='foot.html';

$blog=true; //the 'b' extension, needs the config-b.php file to operate. Otherwise it will not work.
$Blog_dir=__DIR__ . '/blog/'; //blog directory, ./ means sub dir of doc root

$download=false; //the 'd' extension, usefull for download- page links. Needs the config-d.php file to work.
$Download_config='config-d.php'; //currently not used!!!

$page=true; //the 'p' extension. Needed, if you want to use the file based - cms - like system. You shouldt deactivate it. You need to configure the allowed pages.
$allowed_pages = ['home.php', 'something.php', 'otherthing.html']; //the allowed pages (php or html) to be loaded into the content section. Only affects 'p' module.
$Page_dir = __DIR__ . '/pages/'; // the allowed pages must be in this directory. No subfolders allowed. If you want to use sub- folders, please use the blog system.
$page_default = 'home.php'; //displayed home page (nothing is specified, must be in page directory)

$update_password = 'changeme!'; //password used for accessing the update.php
$github_index_url = 'https://raw.githubusercontent.com/Cat17katze/ri-web/refs/heads/main/index.php'; //update source, change if you maintain your own.
$update_log_emails = ['mail@example.com']; //Mail adresses for sending infos like log data

?>
