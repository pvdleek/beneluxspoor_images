# Image upload for BeneluxSpoor.net

This repository contains a very simple Symfony project with a [Dropzone](https://www.dropzonejs.com/) integration.

## Local development installation

To run this project locally, execute the following steps:
- [Download or install composer](https://getcomposer.org/download/) (note: do not commit composer.phar)
- Run `php composer.phar install` or `composer install` in the root of this project
- [Install Yarn](https://classic.yarnpkg.com/en/docs/install#debian-stable)
- Run `yarn install`
- Run `yarn encore dev` in the root of this project
- [Install Symfony](https://symfony.com/download)
- Run `symfony server:start` in the root of this project

## Local development with docker

If you have docker-compose (or docker desktop) installed, you can run everything
automatically:

    $ docker-compose build
    $ docker-compose up

This runs the project on http://localhost:9000 . The advantage is that all the build
steps are done in a clean environment. The disadvantage is that it is "just" a build
to get everything working. To re-create the yarn/composer lock files or for quick 
development, you probably need to install everything locally. For quickly checking 
if a change works, the docker-compose ought to help you get everything running.


## Technical details

- Allowed filetypes are jpg, png and pdf.
- Maximum filesize is 5MB.
- Files are stored as-is, they are not resized. One thing that is done is that 
  the exif data is stripped (with imagemagick).

