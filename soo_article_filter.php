<?php

$plugin['name'] = 'soo_article_filter';
$plugin['version'] = '0.3.1';
$plugin['author'] = 'Jeff Soo';
$plugin['author_uri'] = 'http://ipsedixit.net/txp/';
$plugin['description'] = 'Create filtered list of articles before sending to txp:article or txp:article_custom';

// Plugin types:
// 0 = regular plugin; loaded on the public web side only
// 1 = admin plugin; loaded on both the public and admin side
// 2 = library; loaded only when include_plugin() or require_plugin() is called
$plugin['type'] = 0; 

@include_once('zem_tpl.php');

# --- BEGIN PLUGIN CODE ---

function soo_article_filter( $atts, $thing ) {

	global $pretext;
	if ( $pretext['q'] ) return parse($thing);

	$customFields = getCustomFields();						// field names
	$customAtts = array_null(array_flip($customFields));
	$standardAtts = array(
		'expires'		=> null,	// accept 'any', 'past', 'future', or 0
		'article_image'	=> null,	// boolean: 0 = no image, 1 = has image
		'multidoc'		=> null,	// for soo_multidoc compatibility
		'index_ignore'	=> 'a,an,the',	// leading words to move in index titles
		'index_field'	=> null,	// custom field name for index-style title
		'update_set'	=> null,	// SET clause for custom update query
		'update_where'	=> null,	// WHERE clause for custom update query
		'where'			=> null,	// raw WHERE expression for filter
		'limit'			=> null,	// LIMIT value
		'offset'		=> null,	// OFFSET value
		'sort'			=> null,	// ORDER BY expression
	);
	extract(lAtts($standardAtts + $customAtts, $atts));
	if ( ! is_null($expires) )
		switch ( $expires ) {
			case 'any':
				$where_exp[] = 'Expires > 0';
				break;
			case 'past':
				$where_exp[] = 'Expires <= now() and Expires > 0';
				break;
			case 'future':
				$where_exp[] = 'Expires > now()';
				break;
			case 0:
				$where_exp[] = 'Expires = 0';
		}

	if ( ! is_null($article_image) )
		$where_exp[] = 'Image ' . ( $article_image ? '!' : '' ) . "= ''";

	if ( $customFields )
		foreach( $customFields as $i => $field )
				// to prevent conflicts between named atts and custom fields
			if ( ! array_key_exists($field, $standardAtts) and isset($atts[$field]) ) {
				$value = $atts[$field];
				switch ( $value ) {
					case '':
						$where_exp[] = "custom_$i = ''";
						break;
					default:
						$where_exp[] = "custom_$i regexp '$value'";
				}
			}
	
	if ( $multidoc and _soo_multidoc_ids_init() ) {
		global $soo_multidoc;
		$where_exp[] = "ID not in (" . implode(',', $soo_multidoc['noindex']) . ")";
	}
	
	if ( $where ) $where_exp[] = $where;
	
	$select = '*';
	$table = safe_pfx('textpattern');
	$where_exp = isset($where_exp) ? ' where ' . implode(' and ', $where_exp) : '';
	$sort = $sort ? ' order by ' . doSlash($sort) : '';
	$limit = $limit ? ' limit ' . intval($offset) . ',' . intval($limit) : '';
	
	if ( $index_field ) {
		$i = array_search($index_field, $customFields);
		if ( $i ) {
			$regexp = "'^(" . implode('|', do_list($index_ignore)) . ")$'";
			$select .= ", trim(Title) as index_title, substring_index(trim(Title),' ',1) as first_word, substring(trim(Title), locate(' ',trim(Title))+1) as remaining_words";
			$update[] = array(
				'set' => "custom_$i = concat(remaining_words, ', ', first_word)",
				'where' => "first_word regexp $regexp and custom_$i = ''",
			);
			$update[] = array(
				'set' => "custom_$i = trim(Title)",
				'where' => "custom_$i = ''",
			);
			
		}
	}
		
	if ( ! safe_query("create temporary table $table select $select from $table" . $where_exp . $sort . $limit) )
		return;
	
	if ( $update_set )
		$update[] = array(
			'set' => $update_set,
			'where' => $update_where ? $update_where : '1=1',
		);
	
	if ( ! empty($update) )
		foreach ( $update as $u )
			safe_update('textpattern', $u['set'], $u['where']);
	
	$out = parse($thing);
	safe_query("drop temporary table $table");
	return $out;

}

# --- END PLUGIN CODE ---

if (0) {
?>
<!-- CSS SECTION
# --- BEGIN PLUGIN CSS ---
<style type="text/css">
div#sed_help pre {padding: 0.5em 1em; background: #eee; border: 1px dashed #ccc;}
div#sed_help h1, div#sed_help h2, div#sed_help h3, div#sed_help h3 code {font-family: sans-serif; font-weight: bold;}
div#sed_help h1, div#sed_help h2, div#sed_help h3 {margin-left: -1em;}
div#sed_help h2, div#sed_help h3 {margin-top: 2em;}
div#sed_help h1 {font-size: 2.4em;}
div#sed_help h2 {font-size: 1.8em;}
div#sed_help h3 {font-size: 1.4em;}
div#sed_help h4 {font-size: 1.2em;}
div#sed_help h5 {font-size: 1em;margin-left:1em;font-style:oblique;}
div#sed_help h6 {font-size: 1em;margin-left:2em;font-style:oblique;}
div#sed_help li {list-style-type: disc;}
div#sed_help li li {list-style-type: circle;}
div#sed_help li li li {list-style-type: square;}
div#sed_help li a code {font-weight: normal;}
div#sed_help li code:first-child {background: #ddd;padding:0 .3em;margin-left:-.3em;}
div#sed_help li li code:first-child {background:none;padding:0;margin-left:0;}
div#sed_help dfn {font-weight:bold;font-style:oblique;}
div#sed_help .required, div#sed_help .warning {color:red;}
div#sed_help .default {color:green;}
</style>
# --- END PLUGIN CSS ---
-->
<!-- HELP SECTION
# --- BEGIN PLUGIN HELP ---
 <div id="sed_help">

h1. soo_article_filter

 <div id="toc">

h2. Contents

* "Requirements":#requirements
* "Overview":#overview
* "Usage":#usage
* "Attributes":#attributes
* "Examples":#examples
** "Expired articles":#expired
** "Custom field not empty":#custom_set
** "Custom field empty":#custom_not_set
** "Custom field includes ...":#custom_includes
** "Custom field matches regular expression":#custom_regexp
** "Articles with an assigned image":#image
** "Latest article with an assigned image":#latest_image
** "Select on a numeric range":#range
** "Alphabetical index":#index
** "Change article status":#update
* "Technical notes":#notes
** "Safety":#safety
** "Troubleshooting":#troubleshooting
** "Performance considerations":#performance
** "Multidoc compatibility":#multidoc
* "History":#history

 </div>

h2(#requirements). Requirements

This plugin relies on the trick of creating a temporary database table in memory. So the MySQL user you have assigned to Textpattern (indicated in config.php) must have @CREATE@ privileges. If table creation fails, the plugin will return blank (as of version 0.2.5).

h2(#overview). Overview

Contains one tag, @soo_article_filter@. It allows you to pre-select articles to limit the scope of an @article@ or @article_custom@ tag, using selection criteria not offered by those tags. In short, it's like adding selection attributes to one of those tags.

As of version 0.2.4 there is also the option to add index-style titles to a custom field. E.g., "The First Article" becomes "First Article, The". You can then access that custom field with the usual Txp custom field tags, allowing you to create alphabetical indexes.

As of version 0.2.6 you can do an additional @UPDATE@ query on the temporary table, by using the @update_set@ and @update_where@ attributes. One use for this is to change article status from "Draft" to "Live" or "Sticky", allowing you to display draft articles publicly.

Thanks to "net-carver":http://txp-plugins.netcarving.com/ for clueing me in to MySQL temporary tables.

h2(#usage). Usage

@soo_article_filter@ is a container tag, intended as a wrapper for @article@ or @article_custom@.

pre. <txp:soo_article_filter>
<txp:article />
</txp:soo_article_filter>

*Note:* the filter will not be applied to search results.

h2(#attributes). Attributes

None %(required)required%, but without attributes the tag doesn't do anything useful.

* @expires@ _("past", "future", "any", or 0)_
If set to "past", "future", or "any", only include articles with an @Expires@ value. If  set to "0", only include articles without an @Expires@ value. (%(default)Default% is @null@; no filter on @Expires@.)
* @customfieldname@ _(empty or regexp pattern)_ replace "customfieldname" with any custom field name as set in site preferences. If the attribute value is empty, only articles with an empty value for this custom field will be included. Otherwise the attribute value will be treated as a "MySQL regular expression pattern":http://dev.mysql.com/doc/refman/5.1/en/regexp.html.
* @article_image@ _(boolean)_ If @1@, only show articles with an article image. If @0@, only show articles without an article image. If not set (the %(default)default%), article image has no effect.
* @multidoc@ _(boolean)_ %(default)default% false
For use with the "soo_multidoc":http://ipsedixit.net/txp/24/multidoc plugin. See "note":#multidoc below.
* @index_ignore@ _(list)_ Comma-separated list of leading articles to transpose, when used in combination with @index_field@ (%(default)Default% "A,An,The")
* @index_field@ _(custom field name)_ Set this to the name of an existing custom field to hold index-style titles (e.g. "The Title" becomes "Title, The"). %(default)Default% empty.
* @update_set@ _(SQL clause)_ %(default)default% empty. Set this (and, optionally, @update_where@) to run an @UPDATE@ query on the temporary table.
* @update_where@ _(SQL clause)_ %(default)default% "1=1". Use this in conjunction with @update_where@ to run an @UPDATE@ query on the temporary table.
* @where@ _(SQL clause)_ %(default)default% empty. Any text here will be added to the end of the array of @WHERE@ expressions used for article selection.
* @limit@ _(integer)_ Number of articles to select. %(default)default% unset, no limit.
* @offset@ _(integer)_ Number of articles to skip. %(default)default% unset, no offset.
* @sort@ _(Column [direction])_ Sort column (and optional sort direction), e.g. @Posted asc@. %(default)default% unset.

h2(#complex). Complex selection

The selection attributes (@expires@, @customfieldname@, @article_image@, @multidoc@, and @where@) may be used in combination. The result is a conjunctive (@AND@) search, i.e., each additional attribute makes the filter more restrictive.

The @where@ attribute allows you to enter MySQL expressions and functions for even greater power.

h2(#examples). Examples

h3(#expired). Only show expired articles

You must set "Publish expired articles" to "Yes" in advanced site preferences.

pre. <txp:soo_article_filter expires="past">
<txp:article />
</txp:soo_article_filter>

h3(#custom_set). Only show articles with "my-custom-field" set

Note the value, @".+"@. This will match any character. Note also that you can accomplish the same thing without a plugin, using the technique discussed in "this Txp forum thread":http://forum.textpattern.com/viewtopic.php?pid=210453#p210453.

pre. <txp:soo_article_filter my-custom-field=".+">
<txp:article />
</txp:soo_article_filter>

h3(#custom_not_set). Only show articles with "my-custom-field" not set

pre. <txp:soo_article_filter my-custom-field="">
<txp:article />
</txp:soo_article_filter>

h3(#custom_includes). Only show articles where "my-custom-field" includes "blue"

Note: this is not case sensitive. Would match "Blues", "true blue", etc. @article@ and @article_custom@ already work this way if you add the wildcard character @%@ (e.g., @my-custom-field="%blue%"@), so you don't need this plugin just to do this.

pre. <txp:soo_article_filter my-custom-field="blue">
<txp:article />
</txp:soo_article_filter>

h3(#custom_regexp). Only show articles where "my-custom-field" contains only digits

Note the value, @"^[[:digit:]]+$"@. This is a "MySQL regexp pattern":http://dev.mysql.com/doc/refman/5.1/en/regexp.html, *not* a "PCRE pattern":http://us.php.net/manual/en/reference.pcre.pattern.syntax.php.

pre. <txp:soo_article_filter my-custom-field="^[[:digit:]]+$">
<txp:article />
</txp:soo_article_filter>

h3(#image). Only show articles that have an article image

pre. <txp:soo_article_filter article_image="1">
<txp:article>
<txp:permlink><txp:article_image thumbnail="1" /></txp:permlink>
</txp:article>
</txp:soo_article_filter>

h3(#latest_image). Only show the most recent article with an article image

pre. <txp:soo_article_filter article_image="1" limit="1" sort="Posted desc">
<txp:article>
<txp:permlink><txp:article_image thumbnail="1" /></txp:permlink>
</txp:article>
</txp:soo_article_filter>

(Note: while you could declare the @limit@ in the @article@ tag, this method uses less memory.)

h3(#range). Use @where@ to select on a numeric range

pre. <txp:soo_article_filter where="image BETWEEN 4 AND 27">
<txp:article />
</txp:soo_article_filter>

h3(#index). An alphabetical index using @index_ignore@ and @index_field@

pre. <txp:soo_article_filter index_field="index_title">
<txp:article sort="custom_3 asc" wraptag="ul" break="li">
<txp:permlink><txp:custom_field name="index_title" /></txp:permlink>
</txp:article>
</txp:soo_article_filter>

When @index_field@ is set to the name of an existing custom field, this field will receive an index-style version of the article title, e.g. "The Title" becomes "Title, The". You can then sort on and/or display the index-style title with standard Textpattern custom field tags and attributes, as shown.

Leading words getting special treatment are those listed in @index_ignore@. This defaults to English articles, i.e. "A,An,The".

The custom field has to be one you have actually created in site prefs. In the example it is called "index_title", and it is custom field #3. When saving articles leave this field blank, otherwise @soo_article_filter@ will leave it unchanged.

The index-style title is created only within the @soo_article_filter@ container &mdash; corresponding custom field in the database remains blank.

h3(#update). Add an @UPDATE@ query to change article status

The @update_set@ attribute allows you to run an @UPDATE@ query on the temporary table. For example, to temporarily change the status of all "draft" articles in the "News" section to "live" (Textpattern's status code for "live" is 4 and for "draft" is 1):

pre. <txp:soo_article_filter update_set="Status=4" update_where="Status=1 AND Section='News'">
<txp:article />
</txp:soo_article_filter>

h2(#notes). Technical notes

h3(#safety). Safety

This plugin attempts to run @CREATE@, @DROP@, and, optionally, @UPDATE@ queries. There is a safety check: if the initial @CREATE TEMPORARY TABLE@ query fails, the plugin exits immediately, without parsing the tag contents. However, the plugin has not been tested extensively, and you should certainly keep regular database backups if you are doing anything especially interesting with this plugin. Of course, you should keep regular database backups in any event.

One thing you should absolutely %(warning)NOT% do is assume that it is safe to do anything you like within the tag contents. The main issue is that if the page context is search results (i.e., the @q@ query parameter is set), the tag will simply parse its contents and return them as is. If you stuck a raw query into the tag contents on the assumption the query would only run when the temporary textpattern table exists, you'd regret it. Maybe not today, maybe not tomorrow, but soon, and for the rest of your life.

h3(#troubleshooting). Troubleshooting

You might get an error like this: @Textpattern Warning: Not unique table/alias: 'textpattern'@. It seems that some configurations allow the shortcut of creating the temporary table and selecting from the actual table of the same name in the same statement, but some don't. Performance-wise it is certainly better to use a single statement, but if this doesn't work for you, -use the alternate version of the plugin, included in the download- please "contact me":http://ipsedixit.net/info/2/contact.

If you use "Multidoc":http://ipsedixit.net/txp/24/multidoc and see an error message including @Table 'textpattern' already exists@, see the "note on Multidoc compatibility":#multidoc, below.

h3(#performance). Performance considerations

Most plugins that give you a souped-up equivalent of @article@ or @article_custom@ have to duplicate and modify @doArticles()@ plus a couple of other core Txp functions. Less than ideal, at least in terms of ease of plugin authoring, because @doArticles()@ is quite a lot of code to duplicate and edit in a plugin.

This plugin takes a very different approach--it creates a temporary table holding a filtered set of articles, thus allowing you to use @article@ and/or @article_custom@ normally. Code-wise this is very simple; performance-wise it might be inferior (I don't know). In my limited and informal testing the extra time required for dealing with the temporary table is a non-issue. This could change with a very large @textpattern@ (articles) table. But as long as you set @soo_article_filter@'s attributes so as to produce a relatively small number of articles, there shouldn't be a problem in most cases. (Version 0.3.1 adds @limit@, @offset@, and @sort@ attributes to make it easier to target the exact articles you want.)

Might be another story on a highly-optimized, large, high-traffic website where maximum performance is a major concern.

h3(#multidoc). Multidoc compatibility

The "soo_multidoc":http://ipsedixit.net/txp/24/multidoc plugin also uses the temporary table trick to filter articles, which can only be done once per page load. To achieve compatibility between *soo_article_filter* and *soo_multidoc*, follow these steps:

* In Multidoc prefs (click *soo_multidoc*'s "Options" link in the plugin list) set the "Show all" preference to "yes".
* As needed, use @soo_article_filter@, with @multidoc="1"@, to filter Multidoc interior pages. 

Note that, unlike Multidoc's built-in filter, @soo_article_filter@ does not distinguish between list and individual article context, so if your Multidoc setup uses the same @article@ tag for lists and individual articles you will have change this. (This is deliberate; it allows you to use @soo_article_filter@ for an @article_custom@ list on an individual article page.)

h2(#history). Version history

h3. 0.3.1 (Dec 9, 2010)

Added @limit@, @offset@, and @sort@ attributes.

h3. 0.3.0 (Jul 11, 2010)

New attribute: @where@, allows you to add a raw @WHERE@ expression to the selection criteria. (Thanks to Victor for the idea.)

h3. 0.2.7 (Jun 6, 2010)

Fixed bug in @index_ignore@ attribute. (Thanks to th3lonius for spotting it.)

h3. 0.2.6 (Mar 8, 2010)

New attributes, @update_set@ and @update_where@, allowing an additional @UPDATE@ query on the temporary table.

h3. 0.2.5 (Feb 19, 2010)

Checks that temporary table creation was successful, else returns nothing.

h3. 0.2.4 (Feb 19, 2010)

Create properly-alphabetized indexes with the new @index_ignore@ and @index_field@ attributes.

h3. 0.2.3 (Jan 20, 2010)

Fixed custom field bug

h3. 0.2.2 (Sept 28, 2009)

New @article_image@ attribute

h3. 0.2.1 (July 7, 2009)

"soo_multidoc":http://ipsedixit.net/txp/24/multidoc compatibility.

h3. 0.2 (July 4, 2009)

Fixed behavior of @expires@ attribute.

h3. 0.1 (July 3, 2009)

Initial release. Allows filtering on the @Expires@ field, and regexp pattern matching on any custom field.


 </div>
# --- END PLUGIN HELP ---
-->
<?php
}

?>