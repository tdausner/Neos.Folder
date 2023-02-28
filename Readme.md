# The Neos.Folder package

**A Content Repository based folder system**

This package implements a strictly hierarchical folder system based on the Neos Content Repository (**CR**).

## Neos/Flow Content Repository

If you are familiar with the [Neos/Flow Content Repository](https://docs.neos.io/guide/manual/content-repository)
you may [skip this section](#neosfolder-system).

The Neos/Flow Content Repository (CR) is an abstraction layer to whatsoever is implemented as permanent storage
(usually a database system). This brief introduction does not claim completeness. It covers the aspects
required for the understanding of the CR based **Neos.Folder** system. 

When you set up the first site in Neos, the CR contains a hierarchical structure of entries starting
with the path `/sites`.

The CR is capable of holding many such hierarchical structures. Some examples:

- `/sites` defined in class `SiteService` from package `Neos.Neos`
- `/assets` defined in class `MetaDataRepository` from package `Neos.MetaData.ContentRepositoryAdapter`
- `/taxonomies` defined in file `Settings.yaml` from package `Sitegeist.Taxonomy`

#### Nodes

The basic unit in the CR is a **Node**. Each Node is identified by

- `path` example: `/var/www/html` - you know this from directory trees, or
- `identifier` example: `c255046d-4215-456f-b2f9-017db059cf55`

But that's not all. The CR can hold the same Node in different **variants** to keep different properties
for different languages and/or countries. In the CR the different variants are called **dimensions**.

As of this a **Node** is unique by `path` or `identifier` AND `dimensions`.

#### Dimensions

Dimensions are defined in file `Settings.yaml` in `Neos.ContentRepository.contentDimensions` section (see
for example the site package `Neos.Demo`). In the settings file there are defined `constraints` as not all
combinations of the defined dimensions (`language` and `country`) do make sense. You can define any `dimension`
in your project's `Configuration/Settings.yaml` file in addition to the standard dimenions. For a details 
have a look at the [**NEOS Content Dimensions**](https://docs.neos.io/guide/manual/content-repository/content-dimensions)
documentation.

#### Properties

Any Node in the CR can hold properties. One Node in the CR can hold variants for any combination of dimensions
and hence different property `key => value` pairs (properties).

## Neos.Folder system

### Dimensions

The Neos.Folder system follows the CR in sense of different variants by dimensions. For root folders (like 
`/assets` in the example above) a folder may be added without dimensions.

### Properties

#### Default properties

The Neos.Folder package offers a strictly hierarchical system of folders. It extends the CR by three 
default properties:

- folder title
- folder title path
- associations

##### Folder title and folder title path

If you want to utilize Neos.Folder displaying the path name (or parts of it, a path segment or CR `Node` name)
of the folder, it is not possible to stick to the CR's path names, as a CR path name consists of lower case 
characters, numbers and slashes. No upper case, no spaces and, what's most important, no diacritics (Umlaut).

Hence, a natural language string is kept as folder title and constructed from all titles of the parent folders
a folder title path.

##### Associations

To keep information of associated Neos objects the `associations` property array is utilized. A reference
is stored as a Node's identifier. The API offers a method to associate or dissociate an object to a folder
following the syntax of setting and clearing a symbolic link:

- setting a symbolic link: `ln -s <token> <target>`
- removing it: `rm <target>`

#### Other properties

The API contains methods for setting, clearing and retrieving properties. The Neos.Folder property API filters
the [default properties](#default-properties).

### Adoption

Imagine a folder system where your application sets up a base folder (like `/your-application/folder/`).
Next the backend user(s) extend this folder system with sub folders (and sub sub folders...). This ends up
in a folder tree. The title of each folder is specific for the user's session dimensions (see 
[Folder title and folder title path](#Folder-title-and-folder-title-path)).

If the site is set up for multi dimensions, the backend user can copy the whole site to some new dimensions 
by change of dimension in the backend. The whole site tree is copied to the new dimensions. The same applies to 
the Neos.Folder system. After the adoption of the folder tree by the new dimension the same folder tree is
available for the new dimensions. If the change of dimensions goes along with a language switch, the backend
may translate and set the titles of the folders.

### Configuration

Some default parameters are defined in the package's file [`Settings.yaml`](Configuration/Settings.yaml):

```yaml
Neos:
  Folder:
    defaults:
      nodeType: 'Neos.Folder:Folder'
      titlePropertyKey: 'title'
      titlePathPropertyKey: 'titlePath'
      associationsPropertyKey: 'associations'
      adoptOnEmpty: true
```
The first four properties (`nodeType`, `titlePropertyKey`, `titlePathPropertyKey` and associationsPropertyKey)
need no further explanation.

The propertiy `adoptOnEmpty` controls the behaviour of the service API. This property determines the behaviour of the
`get` service, if there is no folder (tree) available for the (possibly new) session dimensions. On `true` the folder tree
is adopted from the default dimensions.

The node type `Neos.Folder:Folder` is defined in the package's file
[`NodeTypes.Folder.yaml`](Configuration/NodeTypes.Folder.yaml):

```yaml
'Neos.Folder:Folder':
  label: 'Neos Folder'
  superTypes:
    'Neos.Neos:Node': TRUE
  constraints:
    nodeTypes:
      '*': FALSE
  properties:
    title:
      type: string
    titlePath:
      type: string
    associations:
      type: references
```
### Command line interface and API

The Command line flow interface provides commands to add, set title, remove, adopt, move, set or clear property, 
associate folders and show all root folder. Commands to list, export and import folder trees are also available. 
For more information either use `./flow help folder` command or see 
[**Flow commands** documentation](Documentation/FlowCommands.md).

The Neos.Folder package has two APIs:
- [**PHP API**](Documentation/API.md#PHP-API)
- [**Web Service API**](Documentation/API.md#Web-Service-API).

For more information follow one of the links above.

### Requirements

- PHP 8.x
- Neos/Flow 8.x
