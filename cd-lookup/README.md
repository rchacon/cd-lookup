# CD Lookup

Look up your U.S. senators and House representative by street address, right from a WordPress page or post.

Visitors enter a street address into a form and get back their senators and representative — name, party, role, phone, official website, and headshot.

## Installation

1. Download the plugin zip from the [Releases page](https://github.com/rchacon/cd-lookup/releases).
2. In your WordPress admin, go to **Plugins → Add New Plugin → Upload Plugin** and upload the zip.
3. Activate the plugin.
4. Go to **Settings → CD Lookup** and paste in your cd-platform API key (obtain
   one from whoever manages your cd-platform deployment).

## Usage

Add the `[cd_lookup]` shortcode to any page or post.

### Voting record topics

Under **Settings → CD Lookup → Voting record topics**, list topics one per
line (e.g. `immigration enforcement`). Each voting House Representative's card
then shows "See how *name* voted on [topic]", which opens that member's
[CivicDog](https://app.civicdog.com) voting record searched for the chosen
topic. Senators (coming soon on CivicDog) and non-voting Delegates / the
Resident Commissioner don't get the dropdown. Leave the box empty to hide it.

Lookups are served by [cd-platform](https://github.com/rchacon/cd-platform)'s
`cd-api`, not by scraping a third-party site.

## Requirements

- WordPress 6.0+
- PHP 8.0+
