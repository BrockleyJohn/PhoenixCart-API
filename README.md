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

Note that token is hard-coded to ABC123 for testing purposes.
This will *obviously* change in the future.

/api/v1/products/?token=ABC123
show details of all your products

/api/v1/products/8?token=ABC123
show details of single product id

/api/v1/search?q=orange&token=ABC123
show result of keyword "orange"

/api/v1/categories/?token=ABC123
categories

/api/v1/currencies/?token=ABC123
currencies

/api/v1/manufacturers/?token=ABC123
manufacturers

