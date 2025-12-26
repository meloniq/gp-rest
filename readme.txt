=== GP REST ===
Contributors: meloniq
Tags: glotpress, rest, api, endpoint, interface
Tested up to: 6.9
Stable tag: 0.1
License: GPLv2
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Extends GlotPress with REST API endpoints for programmatic access to translation data.

== Description ==

GP REST extends GlotPress by exposing a REST API, allowing developers to integrate GlotPress with external systems, automate workflows, and build custom applications on top of the GlotPress translation platform.

### Experimental Notice

This plugin is currently in an **experimental stage**.

API endpoints and features may change between releases.
Version `0.1` is a foundational release intended for early adopters and developers.

**Versions between 0.1 and 1.0 do not guarantee backward compatibility.**

== Available Endpoints ==

The plugin introduces REST API endpoints for interacting with GlotPress data:

* `/gp/v0.1` – Base endpoint
* `/gp/v0.1/formats` – Retrieve available file formats
* `/gp/v0.1/glossaries` – Retrieve glossaries
* `/gp/v0.1/glossaries/{id}` – Retrieve a specific glossary
* `/gp/v0.1/glossaries/{id}/entries` – Retrieve glossary entries
* `/gp/v0.1/glossaries/{id}/entries/{entry_id}` – Retrieve a specific glossary entry
* `/gp/v0.1/languages` – Retrieve available languages
* `/gp/v0.1/originals` – Retrieve original strings
* `/gp/v0.1/originals/{id}` – Retrieve a specific original string
* `/gp/v0.1/projects` – Retrieve projects
* `/gp/v0.1/projects/{id}` – Retrieve a specific project
* `/gp/v0.1/projects/{id}/permissions` – Retrieve project permissions
* `/gp/v0.1/projects/{id}/permissions/{permission_id}` – Retrieve a specific permission
* `/gp/v0.1/profile/me` – Retrieve the authenticated user profile
* `/gp/v0.1/profile/{id}` – Retrieve a specific user profile
* `/gp/v0.1/translations` – Retrieve translations
* `/gp/v0.1/translations/{id}` – Retrieve a specific translation
* `/gp/v0.1/translation-sets` – Retrieve translation sets
* `/gp/v0.1/translation-sets/{id}` – Retrieve a specific translation set

== Configuration ==

After installation, GP REST is ready to use.

To explore the API, you may use tools like **Postman**.
Postman collection and environment files are included with the plugin.

Please note that some endpoints require authentication.
Suggested authentication method for testing is using **Basic Authentication with WordPress Application Passwords**.


== Changelog ==

= 0.1 =
* Initial release.
