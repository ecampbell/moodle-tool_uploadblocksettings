# Upload block settings #

Upload block settings from a CSV file into a range of courses

## Description ##

The Upload block settings plugin for Moodle allows you to add 
block settings to a range of courses at the same time. 
You can also delete existing blocks from a course. 

## Requirements ##

This plugin requires Moodle 2.9+ from https://moodle.org


## Installation and Update ##

Install the plugin, like any other plugin, to the following folder:

    /admin/tool/uploadblocksettings

See http://docs.moodle.org/33/en/Installing_plugins for details on installing Moodle plugins.

There are no special considerations required for updating the plugin.

### Uninstallation ###

Uninstall the plugin by going into the following:

__Administration &gt; Site administration &gt; Plugins &gt; Plugins overview__

...and click Uninstall. You may also need to manually delete the following folder:

    /admin/tool/uploadblocksettings

## Usage &amp; Settings ##

There are no configurable settings for this plugin.

Use the command __Administration &gt; Site administration &gt; Courses &gt; Upload block settings__
to upload a CSV file containing lines of the form:

    operation, course shortname, block name, region, weight

Lines beginning with a '#' or ';' character are comments, and skipped.  
Each line of the file contains one record.  
Each record is a series of data in a fixed order separated by commas.  
The required fields are operation, course shortname, block, region, weight.  
The allowed operations are add, del(ete), res(et), upd(ate).  
The allowed regions are side-pre and side-post.  
The allowed weights are -10 to 10 (0 is neutral)';

## License ##

2018 Eoin Campbell

This program is free software: you can redistribute it and/or modify it under
the terms of the GNU General Public License as published by the Free Software
Foundation, either version 3 of the License, or (at your option) any later
version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY
WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
PARTICULAR PURPOSE.  See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with
this program.  If not, see <http://www.gnu.org/licenses/>.
