=== Dashboard Widget Manager ===
Contributors: Viper007Bond
Donate link: http://www.viper007bond.com/donate/
Tags: dashboard, widgets
Requires at least: 2.5
Stable tag: trunk

Widget manager for the WordPress 2.5+ dashboard.

== Description ==

WordPress 2.5 introduces a widgetized dashboard, but unfortunately no manager for it to rearrange and remove widgets. This plugin fills that need by creating a new admin page very similiar to the new sidebar widget manager.

It also makes it so that both widget order and widget options are stored on a per-user basis rather than everyone sharing a common configuration. Great for those multi-user blogs.

Via this plugin's settings page, you can also choose a default dashboard for users without a customized dashboard of their own and disable non-admins from customizing their dashboard effectively forcing all non-admins to see the dashboard of your choosing.

== Installation ==

###Updgrading From A Previous Version###

To upgrade from a previous version of this plugin, delete the entire folder and files from the previous version of the plugin and then follow the installation instructions below.

###Installing The Plugin###

Extract all files from the ZIP file, making sure to keep the file structure intact, and then upload it to `/wp-content/plugins/`.

This should result in the following file structure:

`- wp-content
    - plugins
        - dashboard-widget-manager
            | dashboard-widget-manager.php
            | readme.txt
            | screenshot-1.png
            | screenshot-2.png`

Then just visit your admin area and activate the plugin.

**See Also:** ["Installing Plugins" article on the WP Codex](http://codex.wordpress.org/Managing_Plugins#Installing_Plugins)

###Using The Plugin###

Visit the new admin page. It's titled "Widgets" and is available from your dashboard page.

You can find the admin-only settings page at Settings -> Dashboard Widgets.

== Frequently Asked Questions ==

= Does this plugin support other languages? =

Yes, it does. See the [WordPress Codex](http://codex.wordpress.org/Translating_WordPress) for details on how to make a translation file. Then just place the translation file, named `dashwidman-[value in wp-config].mo`, into the plugin's folder.

= I love your plugin! Can I donate to you? =

Sure! I do this in my free time and I appreciate all donations that I get. It makes me want to continue to update this plugin. You can find more details on [my donate page](http://www.viper007bond.com/donate/).

== Screenshots ==

1. Widget management page, based on the normal WordPress widget manager
2. Dashboard with a custom widget order ("Other WordPress News" is normally at the very bottom)

== ChangeLog ==

**Version 1.3.0**

* Options page for setting defaults and permissions.
* Allow non-admins to rearrage their dashboard as well as edit the widgets on their dashboard. **Widget authors:** see plugin source for details on making your widget editable by non-admins.
* Custom text and RSS widgets until I get multi-instance widgets working (i.e. the default text and RSS widgets).
* Added widget descriptions.
* Lots of internal improvements.

**Version 1.2.1**

* I screwed up on the per-user widget order. When one user saved, it'd erase the other users' orders.
* Fixed bug that didn't allow for no widgets to be displayed on the dashboard. Thanks to [Kimmono](http://kservik.com/) for pointing this out.

**Version 1.2.0**

* Store dashboard widgets options on a per-user basis. Now you and all of your fellow administrators can have seperate widget options. Non-administrators will see the configuration of the last person to update. This how WordPress does it on it's own and I couldn't think of a better solution.

**Version 1.1.0**

* Store dashboard widgets order on a per-user basis.
* Make the sidebar widget count on the dashboard accurate (it was combining the sidebar and dashboard widget counts). Thanks to [Bob](http://www.nj-arp.org/blog/) for pointing this out.
* Missed translation string.

**Version 1.0.1**

* Accidental version bump when commiting a minor POST detection code improvement.

**Version 1.0.0**

* Initial release.