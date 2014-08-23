PHP TileServer
==============
Serves map tiles for interactive online maps à la Google Maps or Open Street Maps. You
can use any map created by TileMill (or other software that produces .mbtiles files).

This TileServer serves the following information:

1. Regular .PNG or .JPG image tiles
2. The "UTFGrid" json-tiles necessary to add interactivity to the map
3. The "TileJson" configuration file necessary to get your Javascript map viewer started
4. The XML files necessary to implement the Tile Map Service Specification

To see examples of what this TileServer can serve, you can go to the URL of where you
installed it.


Installation
============
Installation is simple: just put the .php file and the .htaccess file in the same 
directory as your .mbtiles file(s), and you're good to go! You can then use LeafLet 
or ModestMaps, or any other map viewer, to display your map.

If you're using Nginx instead of Apache, then you need to add the following lines to your
Nginx configuration file:

```Nginx
...
location /mbtiles-php/ {
    try_files $uri /mbtiles-php/tileserver.php$is_args$args;
}
...
```

This example assumes you have installed mbtiles-php in the '/mbtiles-php/' directory in the
document root. You have to replace '/mbtiles-php/' with the installation path to make this
work.

Requirements
------------
For this to work, you need to have the pdo_sqlite and GD extensions installed. Both should be
enabled by default in a standard PHP installation. You can check the output of phpinfo() to
see if they are enabled in your setup.

Retina maps
-----------
This server supports separate maps for high-resolution devices, such as phones with Retina
displays. If you want to deliver high resolution maps to these visitors, then you need to
create a separate high-res map, [as described here](https://www.mapbox.com/tilemill/docs/guides/high-resolution-tiles/)
and name it "\<mapname\>@2x.mbtiles" - where \<mapname\> is the name of your regular, normal
resolution map.

If such a file does not exist, then these visitors will be served tiles from the regular map.