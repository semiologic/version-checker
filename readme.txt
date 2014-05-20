=== Version Checker ===
Contributors: Denis-de-Bernardy, Mike_Koepke
Donate link: http://www.semiologic.com/partners/
Tags: semiologic
Requires at least: 2.8
Tested up to: 3.9
Stable tag: trunk

Lets you update plugins, themes, and Semiologic Pro using packages from semiologic.com.


== Description ==

Lets you update plugins, themes, and Semiologic Pro using packages from semiologic.com.


= Help Me! =

The [Semiologic forum](http://forum.semiologic.com) is the best place to report issues.


== Installation ==

1. Upload the plugin folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress


== Change Log ==

= 2.7 =

- Updated the Semiologic Legacy theme notification message
- Use more full proof WP version check to alter plugin behavior instead of relying on $wp_version constant.

= 2.6 =

- Code refactoring
- WP 3.9 compat

= 2.5 =

- Call upgrader_process_complete filter when Mass update function completes
- Upgrade notification suppressed when conflicting plugin names between wordpress.org and external plugin sources
- Fix upgrade notification nag message with 3.8 dashboard
- WP 3.8 compat

= 2.4.1 =

- Fix Plugin Mass Installer that got broken in 2.4

= 2.4 =

- WP 3.6 compat
- PHP 5.4 compat

= 2.3 =

- Roll up misc. bug fixes in past releases.

= 2.2.4 =

- Fixed bug that could cause the Tools->Semiologic screen to show empty plugin list

= 2.2.3 =

- Fix positioning of Semiologic Development News for WP 3.4
- Update contact information in API screen
- Change WP version detection

= 2.2.2 =

- Fix positioning of Semiologic Development News in WP 3.5

= 2.2.1 =

- Fix condition were future updates can fail if previous api call returned empty results

= 2.2 =

- Fix unknown index warnings
- Remove hardcoded WP version from API call as decoded logic fixed on server

= 2.1.8 =

- Hard code WordPress version to 3.2.1 in call to Semiologic API to get plugin and themes lists populated.
- Added fix for WP3.2+ to pass args parameter in call to _get_first_available_transport.

= 2.1.7 =

- Improve disabling curl ssl verification.
- WP 3.1/3.2 fixes for the theme installer.
- WP 3.2 fixes.

= 2.1.6 =

- Fix curl w/ ssl
- WP 3.1 ready

= 2.1.5 =

- Finish fixing minor crash (affects PHP 5.3 platforms)

= 2.1.4 =

- Fix minor crash

= 2.1.3 =

- Fix plugin mass-upgrader for old WP installs

= 2.1.2 =

- Fix php notice

= 2.1.1 =

- Prevent news feed from hiding instructions during installs

= 2.1 =

- WP 3.0 compat
- Dump WP 3.0.x update nags for WP 2.9.2 installs

= 2.0.4 =

- Pre-flight WP 3.0 fixes
- Dodge WP transports API bugs

= 2.0.3 =

- Fix the broken theme update link
- Play well with Ozh's Admin Drop Down Menu plugin

= 2.0.2 =

- Disable nagging for new (i.e. not bug fixed) major versions of WP
- Improve "php 4 is outdated" nagging

= 2.0.1 =

- Fix erroneous link in admin area
- Allow for bulk upgrade of WP.org plugins with inconsistent file names

= 2.0 =

- Use the new API service
- Allow for theme and plugin updates
- Allow for bleeding edge package updates across the board
- Implement theme and plugin installer