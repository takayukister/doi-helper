=== Double Opt-In Helper ===
Contributors: takayukister
Tags: privacy, consent, opt-in
Requires at least: 5.7
Tested up to: 6.0
Stable tag: trunk
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Double Opt-In Helper is a WordPress plugin that helps developers implement the double opt-in process in their plugins.

== Description ==

Double Opt-In Helper is a WordPress plugin that helps developers implement the double opt-in process in their plugins.

= What is double opt-in? =

Double opt-in is a procedure used to confirm user's consent. Today, service providers are required to obtain clear consent from users prior to collecting or using their personal data. In some situations, however, asking the user to tick an "I agree." checkbox is not sufficient. Double opt-in serves a useful role in such situations.

A typical double opt-in process starts by the service provider sending an email message to the user. The message includes a URL link to the provider's website and asks the user to click the link if they agree on conditions. Usually, the URL includes some sort of unique random code that works as a token to confirm the user's consent.

By doing this, the service provider can confirm that the real user (not a bot or someone else) has consented, because only the user should be able to access messages to their email address.

= I'm a developer. How can I use this plugin? =

First, register an "agent" who can handle double opt-in sessions for you, and knows what to do when a user opts-in.

To register an agent, use the `doihelper_register_agent()` function. `doihelper_register_agent()` takes two parameters: the name of the agent (required), and an optional associative array of arguments. The available arguments are:

* `acceptance_period` — The length of time (in seconds) for how long a double opt-in session remains live. Default value: 86400 (24 hours)
* `optin_callback` — The callback function that will be called when a user opts-in.
* `email_callback` — The callback function that will be called to send a confirmation email.

After registering an agent, start a double opt-in session by calling the `doihelper_start_session()` function. `doihelper_start_session()` takes two parameters: the name of the agent (required), and an optional associative array of arguments. The available arguments are:

* `email_to` — The recipient's email address, used for the confirmation email. If you omit this argument, no email will be sent. If you do not provide this argument, you will need to provide the user with the confirmation link another way.
* `properties` — The properties array of the session. This array is to be passed to the `optin_callback` function as its only parameter. While you can include any information into this, the primary purpose of it is to pass user-related data to the opt-in callback.

The session data will be stored in the database until the user opts-in, or the acceptance period (from `doihelper_register_agent()`) expires.

== Installation ==

1. Upload the entire `doi-helper` folder to the `/wp-content/plugins/` directory.
1. Activate the plugin through the **Plugins** screen (**Plugins > Installed Plugins**).

== Frequently Asked Questions ==

== Screenshots ==

== Changelog ==

= 0.73 =

Initial release.

== Upgrade Notice ==
