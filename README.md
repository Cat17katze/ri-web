# RiWeb - File- based cms.
This repo contains the RiWeb software written in php. It is developed for people who want a simple complete solution including a markdown enabled blog, a download center with license information and static sites, all in the same theme and looks.

## Features
- A file based static sites, compatible with html pages and php scripts.
- A markdown- enabled blog with sub- directory support. (requires Parsedown.php)
- auto- update scripts.

## planned Features
- The download center
- Blog: Integrated editor for Blog entries.

## Installation
- either download this repo completely, or the index.php and config.php.
- Create the required directories (if not already done)
- Change the config to your needs. (Disabling unwanted/ unused components, so you don't get any errors!)
- Fill it with your content.
[Optional]
- Add update.php and auto_updater.php to the same directory as index.php.
- Add a cronjob for auto_updater.php to execute it. It will automatically update your index.php when the security number is increased.

## Usage
- check regularly for updates with your updater password. 

## Additional Notes
- This is software under development! Features may and will change. Removal is not planned, but possible. Always read in my release notes, when important changes to the config system is made. Sometimes new keys are added.
- This software is provided as it. Please always update your data. I am not responsible for data loss.
- Place your config file safe, and do not let the open web read it with any sketchy file exposing script! It contains your password for the updater.
