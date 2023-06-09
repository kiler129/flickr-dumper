# Flickr-Sync


### What is this?

It is a tool allowing you to make a backup of Flickr photo collections. It preserves all possible metadata and auto-organizes
collections locally. It offers you not only a way to *just* downlaod photos in their highest quality, but continuously
update them and browse them locally.


### What it can do?

The tool is capable of syncing:

- User albums (containing user's own photos)
- User galleries (containing other user's photos)
- Photos favorited by a user


Internally, the tool also contains provisioning for syncing public photo pools, as well as user photostreams syncing. 
However, as of now these two aren't usable.


### How do I use it?

In the current state, the tool is in it's "beta" stage intended for the developers, and other more advanced users to 
test it. It works perfectly and is used to sync 100k+ photos for 3+ years. To use it you need to have PHP 8.2+. No other 
things are required. To customize location of the database file and photos storage check `.env` file (or use environment 
variables).


### Web interface

In order to operate the web interface you can use the Symfony devlopment server (`symfony serve`), or the PHP's built-in
one (`cd public ; php -S 127.0.0.1:8080`).
