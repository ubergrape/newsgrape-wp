=== Newsgrape Sync ===
Contributors: Newsgrape.com, sk7
Tags: newsgrape, newsgrape.com, ng, sync, crosspost
Requires at least: 3.2
Tested up to: 3.4.2
Stable tag: trunk

Syncs your Wordpress articles to Newsgrape.com

== Description ==

Newsgrape Sync automatically syncs WordPress articles to your Newsgrape account. Editing or deleting a post will be replicated as well. You can also crosspost all of your existing posts to Newsgrape.

This plugin also integrates the Newsgrape comment system inside your WordPress posts.

The plugin is developed on [Github] (https://github.com/newsgrape/newsgrape-wp/).

= Features =

Article Editing:

* Automatic syncing: create/edit/delete articles
* Choose Language, License, Article Type for each article
* Enter Newsgrape intro text for each article (this is also shown in your WordPress article)

Article Management:

* Exclude certain categories from syncing
* Set Newsgrape options for multiple posts at once
* Publish or delete multiple posts at once

Comments:

* Show Newsgrape's comment system inside your WordPress posts
* No need to edit your theme

More features:

* Recognizes your blog after a domainname change

= Thanks =

Thanks to the great [Newsgrape team](http://www.newsgrape.com/p/about-us/) for collaborating and providing its API.

Thanks to creators of [LiveJournal CrossPoster](http://wordpress.org/extend/plugins/lj-xp/): [sillybean](http://profiles.wordpress.org/users/sillybean/), [CorneliousJD](http://profiles.wordpress.org/users/CorneliousJD/), [freeatnet](http://profiles.wordpress.org/users/freeatnet/), Evan Broder - parts of their code has been used in the Newsgrape Sync Plugin

== Installation ==

1. Upload the "newsgrape-sync" directory to your "/wp-content/plugins/" directory
1. Activate the plugin through the "Plugins" menu in WordPress Admin
1. Click "Newsgrape" in the WordPress Admin menu and configure your settings.


== Changelog ==

= 1.4 =
* improved multisync ("fast edit articles")
* added "adult only" option to articles
* new: sync pages (optional)
* fixed bug that prevented adding images

= 1.3 =
* better handling of the <!--more--> tag
* added mark as promotional to fast edit articles

= 1.2.4 =
* use Newsgrape description as excerpt if provided

= 1.2.3 =
* sync comment counts from Newsgrape.com

== Upgrade Notice ==

= 1.4 =
Improved "Fast Edit Articles", various enhancements and bug fixes


== Screenshots ==

1. The Newsgrape options page
2. Fast Edit Articles - Sync all your articles at once
3. A post which was synced to newsgrape