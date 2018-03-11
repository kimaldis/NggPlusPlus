NggPlusPlus
====
Publish to Nextgen plugin for Lightroom by Kim Aldis.

### Known Issues

* The Lightroom plugin has been well tested on Mac OS but not on Windows. It may work on Windows out of the box. If it doesn't it can be made to but I'll need feedback, please.

* When a publish service is created a Lightroom collection/gallery is created by Lightroom that doesn't register correctly in Nextgen. You should delete or ignore this collection. Ignore any error messages for now.

* The url to your wordpress site should include http:// or https://. The plugin won't add it for you.

* login details are sent to Wordpress using unencrypted plain text if you use http://. Use https:// wherever possible.

## Features

* speed: Ngg++ publishes at between two and three times faster than other similar plugins
* simplicity: Ngg++ requires only a Wordpress url, login name & password with admin or editor permissions.
* reliability: The plugin uses WP Rest for communication with & upload to Wordpress. Rest has proved faster and more reliable than XMLRPC & FTP combinations used by other plugins

## Installation

Ngg++ consists of two components; a Lightroom plugin and a Wordpress plugin.

## Lightroom plugin installation

* After unzipping the downloaded archive, copy the NggPlusPlus.lrdevplugin folder to a suitable location on your hard drive.

* Install in the usual way using the Lightroom Plugin Manager.
* Set the Wordpress login url, name & password in the plugin's settings. The url should include http:// or https://.

* you can test the connection with the test button in the service's settings.  It should catch most problemns. Contact me if you find something it doesn't.

*** note: login and password details are sent using plain text. For your security you should use https:// if possible.

## Wordpress Plugin Installation
* Copy the WP_NggPlusPlus folder to your Wordpress plugins folder
* Activate the plugin in the Wordpress dashboard plugin tab.

The Wordpress plugin has no settings.

## Logging
The wordpress plugin logs to __Log.log in the plugin's root folder
The Lightroom plugin logs NgPlusPlus_Log in ~/Documents folder in Mac OS, My Documents folder in Windows.

## 3rd Parties
Thanks to Jeffrey Friedl for his JSON encode/decode code. http://regex.info/blog/lua/json
