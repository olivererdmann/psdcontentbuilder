# psdcontentbuilder

PSD Content Builder Extension for eZ Publish. Currently targeted for eZ Publish 4.x only.
It allows rapid creation of defined content-structures, which in e.g. can be used for pre-filling a development-system
with content or creating defined content for driving system-tests.
Out of the box, this extension allows for mirroring a lot of native eZ Publish-features, but it's flexible enough to
enable the creation of custom datatype-builders with great complexity (as long as the representation fits into YAML).

# Features:

* Define content-structures as YAML-files which are created in eZ Publish.
* Structures can consist of any available content-class, with most data-types supported by the sqliimport-extension.
* Supported data-types can easily be extended, by writing additional Datatype-Builders (based on psdAbstractDatatypeBuilder-class, regisitered in psdcontentbuilder.ini)
* There is currently a small collection of run-time functions, which can enhance the YAML-structure or can pull information into it.
* Currently built-in functions are:
  * `ezpublish/fetch/MODULE/FUNCTION` access any eZ Publish fetch (defined by module/function) and re-map the attributes of results as needed. Return arrays or single objects.
  * `ezpublish/locate/node|object` locate existing or recently created nodes. Location can be done by nodeID (integer), remoteID (string) or a path (starts with a slash).
  * Functions can also be extended or overridden in `psdcontentbuilder.ini`.
* After applying a yaml-file to an eZ Publish site-access, an undo-string is out put. This can be used to automatically remove all created nodes, undoing the action.

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
                         Requires the --site-access option set.
--remove       NODE-LIST List of NODE-LOCATIONs separated by commas.
--help                   This text.
                         defined in the package.xml-structure. Will overwrite existing classes, unless
                         the option --ignore-version is specified.
--site-access  STRING    Site-Access that will be needed to perform database-actions. If left blank, the
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

  Apply a structure to the default site-access:
  $ php psdcontentbuildercli.php --apply="path/to/structure.yaml"

  Apply a structure to a defined site-access:
  $ php psdcontentbuildercli.php --apply="path/to/structure.yaml" --site-access=NAME-OF-SITEACCESS


  Undo a recent application:
  > Undo String:
  > 123456,456778

  $ php psdcontentbuildercli.php --remove=123456,456778 --site-access=NAME-OF-SITEACCESS
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