# hubicWebApp
hubiC with encryption layer web application

## What's for?
This application allows to display folders and files stored in hubiC cloud storage by OVH.
But it allows to encrypt the content before adding it to the storage. Crypted files are automatically decrypted when called. 
Encryption is AES (Rijndael) with a 256 bits key.

## How to install:

- Modify the app/config.json 
  - Define hubiC configuration 
  - Define base URL
  - Choose a part of the encryption key
- Deploy all files
  - www/ folder should be the root of the web site
  - app/ folder should not be accessible using HTTP
  
## Used libraries:
 - Server side (PHP)
  - Guzzle
  - Monolog
  - oauth2-client from The League of Extraordinary Packages
  - Phroute
 - Client side
  - AngularJS 1.4.4
    - AngularJS Toaster (https://github.com/jirikavi/AngularJS-Toaster)
    - ng-file-upload (https://github.com/danialfarid/ng-file-upload)
    - FileSaver (https://github.com/eligrey/FileSaver.js)
  - Bootstrap 3.3.5
  - Font Awesome 4.4.0