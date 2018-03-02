Composer package scripts
########################

Composer plugin that provides a way for packages to expose custom scripts
to the root project. These scripts work similarly to the root-only
scripts_ option.

.. image:: https://travis-ci.org/kuria/composer-pkg-scripts.svg?branch=master
   :target: https://travis-ci.org/kuria/composer-pkg-scripts

.. contents::
   :depth: 2


Requirements
************

- PHP 7.1+
- Composer 1.6+


Terminology
***********

root package
  the main package (project)

root script
  a script defined in the root package's scripts_ option

package script
  a script defined in a package's ``extra.package-scripts`` option


Installation
************

Specify ``kuria/composer-pkg-scripts`` as a dependency in your ``composer.json``.

This can be done either in the root package or in one of the required packages
(perhaps a `metapackage? <https://getcomposer.org/doc/04-schema.md#type>`_).
That depends entirely on your use case.

.. code:: javascript

   {
       // ...
       "require": {
           "kuria/composer-pkg-scripts": "^1.0"
       }
   }


Defining package scripts
************************

Package scripts can be defined in the *composer.json*'s extra_ option.

The syntax is identical to the root-only scripts_ option.
See `Composer docs - defining scripts <https://getcomposer.org/doc/articles/scripts.md#defining-scripts>`_.

.. code:: javascript

   {
       "name": "acme/example",
       // ...
       "extra": {
           "package-scripts": {
               "hello-world": "echo Hello world!",
               "php-version": "php -v"
           }
       }
   }

The final script names are automatically prefixed by the package name.

The example above will define the following scripts:

- ``acme:example:hello-world``
- ``acme:example:php-version``

To define shorter aliases, see `Specifying aliases and help`_.

.. NOTE::

   Package scripts will **not** override root scripts_ with the same name.

.. NOTE::

   Package scripts defined in the root package will not be loaded.
   Use scripts_ instead.


Referencing other scripts
=========================

In addition to the root scripts_, package scripts may reference other package
scripts defined in the same file.

See `Composer docs - referencing scripts <https://getcomposer.org/doc/articles/scripts.md#referencing-scripts>`_.

.. code:: javascript

   {
       "name": "acme/example",
       // ...
       "extra": {
           "package-scripts": {
               "all": ["@first", "@second", "@third"],
               "first": "echo first",
               "second": "echo second",
               "third": "echo third"
           }
       }
   }

Package scripts of other packages may be referenced using their full name
or alias (if it exists). Using the full name should be preferred.

.. code:: javascript

   {
       "name": "acme/example",
       // ...
       "extra": {
           "package-scripts": {
               "another-foo": "@acme:another:foo"
           }
       }
   }


Specifying aliases and help
===========================

Package script aliases and help can be defined in the *composer.json*'s extra_
option.

.. code:: javascript

   {
       "name": "acme/example",
       // ...
       "extra": {
           "package-scripts": {
               "hello-world": "echo Hello world!",
               "php-version": "php -v"
           },
           "package-scripts-meta": {
               "hello-world": {"aliases": "hello", "help": "An example command"},
               "php-version": {"aliases": ["phpv", "pv"], "help": "Show PHP version"}
           }
       }
   }

Unlike script names, aliases are not automatically prefixed by the package name.

The example above will define the following scripts:

- ``acme:example:hello-world``
- ``acme:example:php-version``
- ``hello``
- ``phpv``
- ``pv``

.. NOTE::

   Package script aliases will **not** override root scripts_ or other aliases
   with the same name.


Specifying aliases in the root package
--------------------------------------

If a package doesn't provide suitable aliases, the root package may define them
in its scripts_ option.

.. code:: javascript

   {
       "name": "acme/project",
       // ...
       "scripts": {
           "acme-hello": "@acme:example:hello-world"
       }
   }


Using variables
===============

Unlike root scripts_, package scripts may use variable placeholders.

The syntax of the placeholder is:

::

  {$variable-name}

- variable name can consist of any characters other than "}"
- nonexistent variables resolve to an empty string
- the final value is escaped by ``escapeshellarg()``
- array variables will be imploded and separated by spaces, with each
  value escaped by ``escapeshellarg()``


Composer configuration
----------------------

All Composer configuration directives are available through variables.

See `Composer docs - config <https://getcomposer.org/doc/06-config.md>`_.

.. code:: javascript

   {
       "name": "acme/example",
       // ...
       "extra": {
           "package-scripts": {
               "list-vendors": "ls {$vendor-dir}"
           }
       }
   }


Package variables
-----------------

Packages may define their own variables in the *composer.json*'s extra_ option.


.. code:: javascript

   {
       "name": "acme/example",
       // ...
       "extra": {
           "package-scripts": {
               "hello": "echo {$name}"
           },
           "package-scripts-vars": {
               "name": "Bob"
           }
       }
   }

These defaults may then be overriden in the root package, if needed:

.. code:: javascript

   {
       "name": "acme/project",
       // ...
       "extra": {
           "package-scripts-vars": {
               "acme/example": {
                   "name": "John"
               }
           }
       }
   }


Referencing other variables
^^^^^^^^^^^^^^^^^^^^^^^^^^^

Package variables may reference `composer configuration directives <https://getcomposer.org/doc/06-config.md>`_
or other package variables belonging to the same package.

.. code:: javascript

   {
       "name": "acme/example",
       // ...
       "extra": {
           "package-scripts": {
               "hello": "echo Hello {$names}",
               "show-paths": "echo {$paths}"
           },
           "package-scripts-vars": {
               "names": ["Bob", "{$other-names}"],
               "other-names": ["John", "Nick"],
               "paths": ["{$vendor-dir}", "{$bin-dir}"]
           }
       }
   }

.. code:: bash

   composer acme:example:hello

::

  > echo Hello "Bob" "John" "Nick"
  Hello Bob John Nick


.. code:: bash

  composer acme:example:show-paths

::

  > echo "/project/vendor" "/project/vendor/bin"
  /project/vendor /project/vendor/bin


.. NOTE::

   Array variables must be referenced directly, e.g. ``"{$array-var}"``,
   not embedded in the middle of a string.

   Nested array variable references are flattened into a simple list, as seen
   in the examples above.


Running package scripts
***********************

Package scripts can be invoked the same way root scripts_ can:

1. ``composer run-script acme:example:hello-world``
2. ``composer acme:example:hello-world``

See `Composer docs - running scripts manually <https://getcomposer.org/doc/articles/scripts.md#running-scripts-manually>`_.


Using package scripts in events
*******************************

Package scripts may be used in event scripts (provided the plugin is loaded
at that point).

.. code:: javascript

   {
       "name": "acme/project",
       // ...
       "scripts": {
           "post-install-cmd": "@acme:example:hello-world"
       }
   }


Listing package scripts
***********************

This plugin provides a command called ``package-scripts:list``, which lists both
active and inactive package scripts and aliases.

.. code:: bash

    composer package-scripts:list

::

  Available package scripts:
    acme:example:hello-world (hello)    An example command
    acme:example:php-version (phpv, pv) Show PHP version

Enabling verbose mode will show additonal information:

.. code:: bash

  composer package-scripts:list -v

::

  Available package scripts:
    acme:example:hello-world Run the "hello-world" script from acme/example
     - package: acme/example
     - definition: "echo Hello world!"
     - aliases:
    acme:example:php-version Run the "php-version" script from acme/example
     - package: acme/example
     - definition: "php -v"
     - aliases:

You may use the ``psl`` alias instead of the full command name.


Debugging package scripts and variables
***************************************

This plugin provides a command called ``package-scripts:dump``, which dumps
compiled scripts (including root scripts) or package script variables.

.. code:: bash

  composer package-scripts:dump

Specifying the ``--vars`` flag will dump compiled package script variables
instead:

.. code:: bash

  composer package-scripts:dump --vars

You may use the ``psd`` alias instead of the full command name.


.. _scripts: https://getcomposer.org/doc/04-schema.md#scripts
.. _extra: https://getcomposer.org/doc/04-schema.md#extra
