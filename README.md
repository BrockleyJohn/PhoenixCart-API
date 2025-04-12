# PhoenixCart-API

API access will allow the ability view and manipulate data in your Phoenix Shop.

The code is subject to lots of changes over the coming weeks and months, and MUST NOT be used on a live shop.

This is a bare bones start, nothing more.



Rewrite URL using /api/.htaccess
--------------------------------
Make sure to change this line to suit your needs.

`RewriteBase /api/`

For example if your shop is at yourshop.com/store/, change this line to

`RewriteBase /store/api/`


Installation
------------
Upload all. 


Use
-----
https://yourshop.com/{folder}/api/product
show details of all your products

https://yourshop.com/{folder}/api/product/8
show details of product id 8

https://yourshop.com/{folder}/api/product/search/apple
show details of keyword "apple"

