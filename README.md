# psdcontentbuilder

PSD Content Builder Extension for eZ Publish. Currently targeted for eZ Publish 4.x only.
It allows rapid creation of defined content-structures, which in e.g. can be used for pre-filling a development-system
with content or creating defined content for driving system-tests.
Out of the box, this extension allows for mirroring a lot of native eZ Publish-features, but it's flexible enough to
enable the creation of custom datatype-builders with great complexity (as long as the representation fits into YAML).

# Requirements:

* Extensions:
  * sqliimport

# Features:

* Define content-structures as YAML-files which are created in eZ Publish.
* Structures can consist of any available content-class, with most data-types supported by the sqliimport-extension.
* Built can be created in different languages.
* Supported data-types can easily be extended, by writing additional Datatype-Builders (based on psdAbstractDatatypeBuilder-class, registered in psdcontentbuilder.ini)
* There is currently a small collection of run-time functions, which can enhance the YAML-structure or can pull information into it.
* Currently built-in functions are:
  * `ezpublish/fetch/MODULE/FUNCTION` access any eZ Publish fetch (defined by module/function) and re-map the attributes of results as needed. Return arrays or single objects.
  * `ezpublish/locate/node|object` locate existing or recently created nodes. Location can be done by nodeID (integer), remoteID (string) or a path (starts with a slash).
  * Functions can also be extended or overridden in `psdcontentbuilder.ini`.
* After applying a yaml-file to an eZ Publish siteaccess, an undo-string is out put. This can be used to automatically remove all created nodes, undoing the action.

# Command-Line Interface:

Inside your htdocs-folder, call:
```
php extension/psdcontentbuilder/bin/psdcontentbuildercli.php --apply="path/to/structure.yaml"
```

## CLI Help-Screen:

call:
```
php extension/psdcontentbuilder/bin/psdcontentbuildercli.php --help
```

get:

```
PSD ContentBuilder CLI.

Commandline-Interface for building content from YAML-files.

ARGUMENTS
--apply        PATH      Applies the structure defined in the file to the content-tree.
                         Requires the --siteaccess option set.
--remove       NODE-LIST List of NODE-LOCATIONs separated by commas.
--help                   This text.
                         defined in the package.xml-structure. Will overwrite existing classes, unless
                         the option --ignore-version is specified.
--siteaccess  STRING     siteaccess that will be needed to perform database-actions. If left blank, the
                         DefaultAccess is taken from site.ini.
--verbose                Keeps the script telling about what it\'s doing.

DEFINITIONS:
  PATH:                  Points to a folder or file and may contain wild-cards (eg. "*").
                         Wild-cards are resolved and allow the script to process multiple files at once.
                         In order to use wild-cards, you have to put the path in single- or
                         double-quotes.
  NODE-LOCATION          Either a Node-Id, Path-String (starting with "/") or a Remote-ID.

EXAMPLES:

  FYI: Run all commands relative to the root of your eZ Publish installation!

  Apply a structure to the default siteaccess:
  $ php psdcontentbuildercli.php --apply="path/to/structure.yaml"

  Apply a structure to a defined siteaccess:
  $ php psdcontentbuildercli.php --apply="path/to/structure.yaml" --siteaccess=NAME-OF-SITEACCESS


  Undo a recent application:
  > Undo String:
  > 123456,456778

  $ php psdcontentbuildercli.php --remove=123456,456778 --siteaccess=NAME-OF-SITEACCESS
```

# YAML-Structure:

Nodes can be referenced by nodeID (integer), remoteID (string), path (starts with a slash)

## Basic Structure

```
--- #YAML Document
# Optional. Default prefix for object remote-ids, it is appended by a random hash.
# If omitted, the name of the input-file is used.
remoteIdPrefix: mytree

# The "assets"-key is a metaphoric namespace (you may call it what you like) for collecting re-usable
# elements and providing references (merge-keys).
# Remember, you can put functions in here, which get evaluated the moment, they are included somewhere
# below the "content:"-definition.
assets:

  # Queue below NodeID #2 of class "clip".
  children: &children
    - name: Child 1
      class: article
    - name: Child 2
      class: article
    - name: Child 3
      class: article

# The definition below this node only affects eZ's content-tree.
# Use merge-keys in order to re-use data and functions for being some kind of dynamic. Or combine both.
content:

  - name:       MyFrontpage
    parentNode: 2
    class:      frontpage
    remote_id:  mytree
    <<: *children
  - name:       Sub-Tree
    class:      folder
      - name:       Articles 1
        class:      folder
        <<: *children
      - name:       Articles 2
        class:      folder
        <<: *children
```

Creates a strukture like:
* MyFrontpage (frontpage)
  * Child 1 (article)
  * Child 2 (article)
  * Child 3 (article)
  Sub-Tree (folder)
  * Articles 1 (folder)
    * Child 1 (article)
    * Child 2 (article)
    * Child 3 (article)
  * Articles 2 (folder)
    * Child 1 (article)
    * Child 2 (article)
    * Child 3 (article)

## Placeholder Variables
Content-Builder supports Symfony's ParameterBag-feature to some extend, which allows you to use variable-placeholders inside of YAML-files.
Variables are defined in psdcontentbuilder.ini, section "Variables". Each key is recognized as variable-name.
In the YAML-files, you wrap the variable-name between two "%", just like you would in a Symfony configuration-file (eg. `%my_name%`).
Unlike Symfony, you can't use the dot-syntax for variables (as eZ doesn't support dots in Ini-keys).
Keep in mind, that this is a pre-processing feature. Variables are resolved from the raw file-content *before* parsing the YAML-file, no structural validation is performed at this point. 
This allows for placeholders to be used in keys and also structural enhancements like raw YAML-Strings are possible. On the other hand this should be use with care.
Error-handling is limited to undefined variables (Symfony will tell you when a YAML-file requests an undefined variable), and YAML-validation of the resulting code.

By defining different values for different site-accesses, you can, for example, re-use a single Content-definition for multiple projects or stages.

### Examples:
*psdcontentbuilder.ini*
```ini
[Variables]
test_name     = Contentbuilder Test
test_key      = short_title
test_children = [{name: Test Article, class: article, remote_id: test_article}]
```

*YAML-file*
```
content:
  - name:        %test_name%
    class:       frontpage
    parentNode:  /
    remote_id:   test
    %test_key%: Short Test
    children: %test_children%
```

*Result*
```
content:
  - name:        Contentbuilder Test
    class:       frontpage
    parentNode:  /
    remote_id:   test
    short_title: Short Test
    children: 
      - name:      Test Article
        class:     article
        remote_id: test_article
```

## Multi-language nodes
The Content-Builder provides with a simple, but flexible mechanism for creating translations, which,
for example covers the following scenarios:
* create a single default translation
* a translation basing off the default translation, overriding only a few different attributes.
* completely different set of attributes for each translation
* Create only specific translations, without a default translation.

In general, you always specify attributes for the default language, with no additional information required.
In order to create a translation you simply wrap the differing attributes into a language-code-key (eg. `ger-DE:`).
Translations always base off the default language, they receive all default definitions which can be overridden with language-specific values.
You only define attributes where you need them, this allows for defining values exclusively for translations.
Also, you may omit any attributes in your default definition, in that case, the default translation is also omitted. This allows you to
create nodes, which are only available in certain languages, but have no default language.
Please be aware, that the keys `class` and `remote_id` are only available at default-language level, as they can't be translated.
The default language is internally mapped to it's specific language code. If the system's default language and a specified
language code match, the keys are merged. This allows you to omit the default definition, but specify individual translations without basing off each other.

### Examples
Translation (ger-DE) based off the default (eng-US).
Creates 2 translations, both share the image, but have their own names and descriptions.
```
name: Default Name
class: frontpage
remote_id: translated
image: intro.jpg
description: Default Text
ger-DE:
    name: Standardname
    description: Standardtext
```

Different translations ger-DE and eng-US.
Creates 2 translations, both share nothing.
```
class: frontpage
remote_id: translated
eng-US:
    name: Default Name
    image: intro-en.jpg
    description: Default Text
ger-DE:
    image: intro-de.jpg
    name: Standardname
    description: Standardtext
```

Different a single translation ger-DE, omitting the default translation eng-US.
Creates 2 translations, both share nothing.
```
class: frontpage
remote_id: single-translation
ger-DE:
    image: intro-de.jpg
    name: Standardname
    description: Standardtext
```

Different translations ger-DE and eng-US.
Creates 3 unique translations, all override name and description. Image is only defined for english and german.
```
class: frontpage
remote_id: translated
name: Default Name
description: Default Text
eng-US:
    image: intro-en.jpg
ger-DE:
    image: intro-de.jpg
    name: Standardname
    description: Standardtext
fr-FR:
    name: Nom
    description: texte de description
```


## Include-function (built-in)

Allows to split large structures and reuse code by including other YAML-files. The inclusion is raw, which means that
the included content is parsed by the YAML-Parser, but not validated beyond that point. In contrast to the initial
structure, which requires a top-level key "content", included files are appended as-is to the function's parent.
The function requires a parameter `file` which either points to an absolute path or a path relative to the initial
structure-file, specified during the CLI-call.
The return-value is the un-serialized content of that file, which is appended to the parent-node.

Assuming a file called `my-frontpage.yaml` with this content:
```
name:  MyFrontpage
class: frontpage
body:  Lorem ipsum dolor sit amet, consectetur adipiscing elit.
```

The include-call might look like this:
```
children:
  - function: include
    file:     path/to/my-frontpage.yaml
```

The result will be:
```
children:
  - name:  MyFrontpage
    class: frontpage
    body:  Lorem ipsum dolor sit amet, consectetur adipiscing elit.
```

## Locate-function (built-in)

This locates the root-node by providing the path-string "/" to the `ezpublish/locate/node`-function.
The value of `path` can be a nodeID (integer), remoteID (string) or a path (starts with a slash).

```
content:

  - name:       MyFrontpage
    parentNode:
      function: ezpublish/locate/node
      path:     /
    class:      frontpage
```

## Fetch-function (built-in)

Imagine, you're creating an article in the beginning, which you want to re-use in an object-relation on several objects.
In this example, the attribute `related` is of the data-type `ezobjectrelation`.

```
assets:
    - related: &related
        function:           ezpublish/fetch/content/list
        parent_node_id:     2
        class_filter_array: [article]
        class_filter_type:  include
        limit:              3
        depth:              10
        as:                 {node_id: node_id, contentobject_id: object_id}

content:
    # Create content to fetch for.
    - name: Article 1
      class: article
    - name: Article 2
      class: article
    - name: Article 3
      class: article

    # Fetch content and add as relation.
    - name: Frontpage 1
      class: frontpage
      <<: *related
    - name: Frontpage 2
      class: frontpage
      <<: *related
```

This structure first creates 3 nodes of article. Then it creates 2 frontpages, expecting an object-relation-list,
which is assigned to the fetched articles, created in the first step.
Functions are always evaluated, as encountered. This means, unlike native YAML-datetype functions, you can create nodes
and content in the beginning. Then, when creating some kind of overview-pages, you can re-fetch those nodes, putting them
in place where they're needed.

# Magic keys

## children (child-nodes created below)

## parentNode (place the node below this location)
Location. If value is a path-string and this path or parts of it don't exist, the missing levels are created as folder.
Remote-ids, node ids need to exist, otherwise an error is thrown.

## postPublish (delayed publishing of content attributes)

Sometimes it's helpful to fill an object's attribute after all children have been created an published. For example, if you need to reference children in an object-relation or a flow-layout.
In order to do so, you can use the key `postPublish`, available to every content-tree node. The result is, that the object is being published twice: first before creating the children, with all attributes mentioned in the postPublish-array omitted, and finally after all children were created, this time all attrbiutes are published.
In order to have this working, children must be defined using the `children`-key word. Creating children afterwards eg. by setting the parentNode-key, won't work.

Sample-code:
```
postPublish: [related]  # Omit attribute related from initial publish.
related:                # Define an object-relation by referencing children
  - child1
  - child2
  - child3
children:               # define child-nodes
  - remote_id: child1
  - remote_id: child2
  - remote_id: child3
```

This code instructs the content-builder to create the parent node, without setting the `related`-attribute, which we assume to be an object-relation. It then continues to create the children and afterwards publishes the parent-node again, this time, by also setting the `related`-attribute.

At the moment you can not postPublish required fields, as you won't be able to publish the node for the first time.

# Basic Datattypes:

Datatypes are recognized and created based on the current class's definition. Every datatype can have it's own builder registered,
which receives an array-structure from which the content is generated.
If there is no builder is registered for a data-type, the sqliimport-extension is used to create the content from a string.
For details on sqliimport's fromString-import, see [https://github.com/lolautruche/sqliimport/blob/master/doc/fromString.txt].

## eZDateTime
Sample-code:
```
start_date:   today
end_date:     2014-01-24
connect_date: 1386081255
```
Time-values can be represented by time-stamps (integers) or (valid PHP-timestrings)[http://us2.php.net/manual/en/datetime.formats.php].


## eZObjectRelation
Sample-code:

```
simple_relation: /path/to/node # Relation by path-string.

fetched_relation      # Result from fetch.
  function:           ezpublish/fetch/content/tree
  parent_node_id:     2
  class_filter_array: [image]
  class_filter_type:  include
  limit:              1
  depth:              10

```
Relations are defined by valid locations. Values can be directly assigned to the key (example 1) or specified as an array
(example 2, e.g. as a result from an fetch or a shared merge-key).

## eZImage
Sample-code:

```
image: /vagrant/fixtures/images/350x150.jpg
```
Image-files are specified by an absolute local path.

## eZText
Sample-code:

```
single_line: Lorem ipsum dolor sit amet, consectetur adipiscing elit.
multi_line: |
  Lorem ipsum dolor sit amet, consectetur adipiscing elit.
  Aenean vel placerat tortor.

  Duis luctus nibh sit amet nulla fringilla sed hendrerit eros vestibulum.
  In scelerisque tincidunt sapien, at molestie ante lacinia eu.
```
Plain-text is supported as default YAML-string either as inline or multi-line notation.

## eZXMLText
Sample-code:

```
single_line: Lorem ipsum dolor sit amet, <b>consectetur adipiscing elit</b>.
multi_line: |
  Lorem ipsum dolor sit amet, <b>consectetur adipiscing elit</b>.
  <h1>Aenean vel placerat tortor. </h1>

  <p>Duis luctus nibh sit amet nulla fringilla sed hendrerit eros vestibulum.
  In scelerisque tincidunt sapien, at molestie ante lacinia eu.</p>
```
Rich-text is supported as simplified HTML, all input-tags provided by eZOEInputParser are supported.

## eZObjectRelationList
Sample-code:

```
# Result from fetch.
content:
  function:           ezpublish/fetch/content/tree
  parent_node_id:     2
  class_filter_array: [format]
  class_filter_type:  include
  limit:              10
  depth:              10

# Relation by path-string.
images:
  - /media/images/image-01
  - /media/images/image-02
  - /media/images/image-03
  - /media/images/image-04
```
Relation-lists require an array with valid locations.

## ezTags
Sample-code:
```
tags:                                           # YAML ordered list.
  - Tag 1                                       # Tag on root-level.
  - Root Tag/Sub Tag                            # Multilevel-Tag.
  - /Tags/Another Tag/                          # Optional Slashes at the beginning and end.

inline_tags: [Tag 1, Root Tag/Sub Tag, Tag 2]   # YAML Inline list.
```
Tags are defined as an array. Every entry represents the absolute path of a tag, different levels are separated by slashes.
The slashes at the beginning or end of the path are optional. Unknown paths are created.

## xrowMetaData
Sample-code:

```
metadata:
  title:       String
  keywords:    String
  description: String
  priority:    Float   # 0.0 .. 1.0
  change:      String  # always|hourly|daily|weekly|monthly|yearly|never
  googlemap:   Boolean # 0|1
  canonical:   String
  robots:      String  # See xrowmetadata.ini/EditorInputSettings/RobotsTagOptions
  extraMeta:   String
```
All values are optional.


