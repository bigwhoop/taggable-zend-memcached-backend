# Zend_Cache (ZF 1) memcached backend (not libmemcached) with support for tags

## Features

Basic support for tags. You can invalidate entries with the following cleaning modes:
 
* `Zend_Cache::CLEANING_MODE_ALL`
* `Zend_Cache::CLEANING_MODE_MATCHING_TAG`
* `Zend_Cache::CLEANING_MODE_MATCHING_ANY_TAG`

There is also support to prefix your cache entries. Just pass the `prefix_key` option in the ctor.

## Requirements

- PHP 5.3+
- PSR-0 autoloading

## Installation

`compser require "trekksoft/taggable-zend-memcached-backend":"dev-master"`

## Usage

When using the cache manager, make sure to set `'customBackendNaming' => true, 'frontendBackendAutoload' => true`. Otherwise ZF will fail to load the backend.

Same when using `Zend_Cache::factory()`. Set `$customBackendNaming => true, $autoload => true`.
