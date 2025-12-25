=== GP REST ===
Contributors: meloniq
Tags: glotpress, rest, api, endpoint, interface
Tested up to: 6.9
Stable tag: 0.1
License: GPLv2
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Extends GlotPress by adding REST API endpoints, enabling developers to integrate, extend, and build custom applications on top of the GlotPress translation system.

== Description ==

Extends GlotPress by adding REST API endpoints, enabling developers to integrate, extend, and build custom applications on top of the GlotPress translation system.

= Endpoints =
The plugin introduces several REST API endpoints that allow you to interact with GlotPress data programmatically.
Some of the key endpoints include:
* /gp/v0.1 - Base endpoint for GP REST.
* /gp/v0.1/formats - Retrieve available file formats.
* /gp/v0.1/glossaries - Retrieve glossaries.
* /gp/v0.1/glossaries/<id> - Retrieve a specific glossary by ID.
* /gp/v0.1/glossaries/<id>/entries - Retrieve entries for a specific glossary.
* /gp/v0.1/glossaries/<id>/entries/<entry_id> - Retrieve a specific glossary entry by ID.
* /gp/v0.1/languages - Retrieve available languages.
* /gp/v0.1/originals - Retrieve original strings.
* /gp/v0.1/originals/<id> - Retrieve a specific original string by ID.
* /gp/v0.1/projects - Retrieve projects.
* /gp/v0.1/projects/<id> - Retrieve a specific project by ID.
* /gp/v0.1/projects/<id>/permissions - Retrieve permissions for a specific project.
* /gp/v0.1/projects/<id>/permissions/<permission_id> - Retrieve a specific permission by ID.
* /gp/v0.1/profile/me - Retrieve the authenticated user's profile.
* /gp/v0.1/profile/<id> - Retrieve a specific user profile by ID.
* /gp/v0.1/translations - Retrieve translations.
* /gp/v0.1/translations/<id> - Retrieve a specific translation by ID.
* /gp/v0.1/translation-sets - Retrieve translation sets.
* /gp/v0.1/translation-sets/<id> - Retrieve a specific translation

= Configuration =

Once you have installed GP REST it is ready to use.


== Changelog ==

= 0.1 =
* Initial release.
