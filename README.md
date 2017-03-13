# podquilt
PHP app for merging multiple podcast feeds into one personal feed. Everything is driven by the config.json file, which is not present in the repository. Please use config.json.example as a template to build your own file.

(The podquilt app requires `allow_url_fopen` to be enabled in your PHP configuration. You may also encounter issues if the DOM extension for PHP, also called `php-xml`, [is not installed](http://stackoverflow.com/questions/14395239/class-domdocument-not-found).)
