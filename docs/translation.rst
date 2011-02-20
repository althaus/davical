***********
Translation
***********

DAViCAL uses the standard translation infrastructure of many projects. 
It is based on ``gettext`` (http://www.gnu.org/software/gettext/).

Translators
===========

Translating DAViCal
-------------------

All translation of DAViCal is done by the Transifex tool (http://transifex.org/).
We are using the Transifex service (http://www.transifex.net/) to translate the software.

To translate DAViCal go to http://www.transifex.net/projects/p/davical.
You can register an account there and then translate DAViCal in the webbrowser to your language.
Furthermore you can improve existing translations and join the team of your language.

Developers
==========

Adding translatable string to the PHP-Source
--------------------------------------------

DAViCal currently does not use ``_()`` but has two functions to do the translation.
These functions are named ``i18n()`` and ``translate()``.

With ``translate()`` a message can be directly translated.

.. code-block:: php

   <?php
   print translate('TEST to be displayed in different languages');

In case a variable is passed all candidates can be marked by ``i18n()``.

.. code-block:: php

   <?php
   $message_to_be_localized = i18n('TEST to be displayed in different languages');
   print translate($message_to_be_localized);

Adding context as a help for the translators
--------------------------------------------

There are cases in which a translation might be ambigous for the translators at these places hints may be inserted.

Consider the following code fragment.

.. code-block:: php

   <?php
   # Translators: short for 'Number'
   i18n("No.");
   
   # Translators: not 'Yes'
   i18n("No");

With the ``Translators:`` keyword in a comment directly in front of the line with the string to be translated such hints can be marked.

Generating the .pot file from source
------------------------------------

To generate a .pot file for translators on has to use the following command:

.. code-block:: bash

  $ xgettext -f po/pofilelist.txt --keyword=i18n --keyword=translate \
   --add-comments=Translators
  $ sed -i 's.^"Content-Type: text/plain; charset=CHARSET\\n".\
  "Content-Type: text/plain; charset=UTF-8\\n".' messages.po
  $ mv messages.po messages.pot

This will generate a file named ``messages.pot``.

Accessing the Transifex files
=============================

Transifex does not have direct repository integration anymore.
They have created a command line client namend ``tx``::

   $ tx init
   Creating .tx folder...
   Transifex instance [http://www.transifex.net]: 
   Creating skeleton...
   Creating config file...
   Done.
   $ tx set --auto-remote http://www.transifex.net/projects/p/davical/
   Auto configuring local project from remote URL...
   Getting details for project davical
   1 resources found. Configuring...
   Configuring resource davical.messagespot.
   Done.
   $ tx pull -a
   Pulling translations for resource davical.messagespot (source: None)
   New translations found for the following languages: nl, et, fr, de, hu, it, ja, nb_NO, pl, ru, es_AR, sv
   Pulling translations for resource davical.messagespot (source: None)
    -> nl: translations/davical.messagespot/nl.po
    -> et: translations/davical.messagespot/et.po
    -> fr: translations/davical.messagespot/fr.po
    -> de: translations/davical.messagespot/de.po
    -> hu: translations/davical.messagespot/hu.po
    -> it: translations/davical.messagespot/it.po
    -> ja: translations/davical.messagespot/ja.po
    -> nb_NO: translations/davical.messagespot/nb_NO.po
    -> pl: translations/davical.messagespot/pl.po
    -> ru: translations/davical.messagespot/ru.po
    -> es_AR: translations/davical.messagespot/es_AR.po
    -> sv: translations/davical.messagespot/sv.po
   Done.

