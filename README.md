# AutomaticFFL Magento 2 Extension MetaPackage

This metapackage contains a collection of the extensions necessary to implement the Automatic FFL into a Magento 2 store.
Each one of these directories contain a Magento 2 extension:

```
 - admin: This extension contains all changes to the admin area and System Configurations.
 — checkout: All checkout customizations go here
 — checkout-multishipping: Modifications to the multi-shipping checkout
 — core: Contains all of the required elements that are shared across the AutoFfl extensions. 
```
## Installation
### Composer

```bash
composer config repositories.razoyo-ffl git git@bitbucket.org:razoyo/autoffl-m2-module.git
composer require razoyo/autoffl-m2-module
```
### Modman
Download [modman](https://github.com/colinmollenhour/modman) `bash < <(wget -q --no-check-certificate -O - https://raw.github.com/colinmollenhour/modman/master/modman-installer)`

Init modman in the Magento 2 project root:
```bash
modman init
```

Use modman to clone the remote repository into your local environment:
```bash
modman clone git@bitbucket.org:razoyo/autoffl-m2-module.git
```

To get the latest changes from upstream, you can run the following command:
```bash
modman update autoffl-m2-module
``` 

Also do not forget to clear the Magento cache:
```bash
bin/magento cache:flush
``` 
