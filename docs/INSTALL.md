[![ebay logo](static/logo-vert.png)](http://www.ebayenterprise.com/)

**Magento Couchbase Session Driver**
# Installation and Configuration Guide

The intended audience for this guide is Magento system integrators and core development. You should review the [Zend Framework Couchbase Backend Driver Overview](README.md) before attempting to install and configure the driver.

Knowledge of Magento installation and configuration, [PHP Composer](https://getcomposer.org/) and Magento XML Configuration is assumed in this document.

## Contents

1. [Requirements](#requirements)
2. [Installation](#installation)
3. [Local XML Configuration](#local-xml-configuration)

## Requirements

### System Requirements

- Magento Enterprise Edition 1.14.2 ([system requirements](http://magento.com/resources/system-requirements))
- [Magento Composer Installer](https://github.com/Cotya/magento-composer-installer)
- [libcouchbase - 2.5.X](https://github.com/couchbase/libcouchbase)
	Ensure the following dependencies are installed as well:
		-[libevent](http://libevent.org/)
		-[openssl libraries](https://www.openssl.org/docs/manmaster/crypto/crypto.html)
		-[CMake >= 2.8.9](https://cmake.org/)
- [PHP libcouchbase extension >= 2.1.0](https://github.com/couchbase/php-couchbase)
- [PHP 5.6](http://php.net)
- [Couchbase Server >= 3.0.X](http://www.couchbase.com/)

## Installation

1. Copy all of the files from the src/app to DocRoot of the Magento installation, example: /var/www/magento
2. Examine the src/app/etc/local.xml.additional.couchbase file and copy over the corresponding XML to local.xml in your Magento installation app/etc directory.
3. Clear all caches, by flushing each of the Couchbase Buckets

## Local XML Configuration
```xml
<config>
    <global>
	<session_save><![CDATA[couchbase]]></session_save>      <!-- set to couchbase to invoke the driver -->
        <session_save_couchbase>
            <!-- The Connection String Below Contains Options Passed in by URL to Configure libcouchbase
                 ** Config Cache ** - Useful for Enterprise Level Websites with 10000+ active sessions -- set the Config Cache File as Below **RECOMMENDED**
                 ** HTTP Poolsize ** - Maintain Persistent Connections w/ Couchbase -- Set to 0 to turn off, > 0 to make use off... recommended set to 10 **RECOMMENDED**
            -->
            <consoleConnectionString><![CDATA[couchbase://127.0.0.1?config_cache=/tmp/phpcb_cache_session&http_poolsize=10]]></consoleConnectionString>
            <consoleUser><![CDATA[test]]></consoleUser>
            <consolePassword><![CDATA[test]]></consolePassword>
            <consoleSessionBucket><![CDATA[session]]></consoleSessionBucket>  <!-- Name this to whatever your session bucket is called -->
        </session_save_couchbase>
    </global>
</config>
```
