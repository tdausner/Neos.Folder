## Flow commands

Most commands require `<token>` as argument. This can either be a folder (node) path or folder (node)
identifier. 

Nearly all commands require `<dimension>` as argument. This is a string of dimensions concatenated by an 
ampersand (`&`) sign. Dimensions are any of `Neos.ContentRepository.contentDimensions` (see configuration
file `Settings.yaml`). Be aware that the ampersand sign is a special character for the shell, so escape
it or put the whole dimension string in apostrophes (`'`).

#### List of commands

    folder:add           Add a folder
    folder:title         Set title at folder
    folder:remove        Remove a folder or folder structure
    folder:adopt         Adopt an existing folder by new dimensions
    folder:move          Move a folder
    folder:property      Set or clear properties at a folder
    folder:associate     Set or clear association
    folder:root          Show all root folders with dimensions
    folder:list          List folder structure
    folder:export        Export folders
    folder:import        Import folders

#### Add a folder

Add a folder to the folder system. The argument `<path>` is required (instead of `<token>`). The path
comprises [folder titles](../Readme.md/#Folder-title-and-folder-title-path) which can be natural language 
strings (including upper case, blanks and Umlauts).

    ./flow folder:add [<options>] <path> <node type name> <dimension>
    
    Arguments:
    --path               Folder path to add. Path segment names are set to 
                         property "title".
    --node-type-name     Node type (on empty: Neos.Folder:Folder).
    --dimension          Leave empty ('') for no dimensions..
    
    Options:
    --recursive          Recursive operation on creation.

#### Set title at folder

Set title at folder identified by `<token>` (path or identifier). `<title>` can be a natural
language strings (including upper case, blanks and Umlauts). On empty title the node name is
set to the title.

    ./flow folder:title <token> <title> <dimension>
    
    Arguments:
      --token              Folder path or identifier for new title
      --title              New title for folder
      --dimension          The folder's dimension(s)

#### Remove a folder or folder structure

Remove a folder or folder structure from folder system. On empty (`''`) `<dimension>` a folder
without dimensions can be removed.

    ./flow folder:remove [<options>] <token> <dimension>
    
    Arguments:
      --token              Folder path or identifier to remove
      --dimension          Dimension

    Options:
      --recursive          true: remove recursively

#### Adopt an existing folder by new dimensions

Adopt an existing folder from `<source dimension>` to `<target dimension>` if new-dimension
variant for folder does not exist. To adopt a folder tree the `<token>` can identify a root
folder and the `--recursive` option must be included.

    ./flow folder:adopt [<options>] <token> <source dimension> <target dimension>
    
    Arguments:
      --token              Folder path or identifier as start point for adopt of new dimensions
      --source-dimension   Dimension of existing folder
      --target-dimension   Dimension for folder to adopt
    
    Options:
      --recursive          true: adopt recursively

#### Move a folder

Move a folder (and sub folders) identified by `<token>` to folder identified by `<target>`.
Folder **titles** and **titlePath** are kept. It is not possible to move a folder if a folder
with same title exists at `<target>`. The folder Node's path name may change. 

    ./flow folder:move <token> <target> <dimension>
    
    Arguments:
      --token              Folder path or identifier to move
      --target             Target path or identifier
      --dimension          Dimension

#### Set or clear properties at a folder

Set properties at a folder. Standard folder properties (title, titlePath, associations) are
excluded. Clears properties on option `<--reset>` included and `<properties>` empty (`''`).

    ./flow folder:property [<options>] <token> <properties> <dimension>
    
    Arguments:
      --token              Folder path or identifier to update properties
      --properties         Folder properties (JSON formatted string)
      --dimension          Dimension
    
    Options:
      --reset              Reset all folder properties before property insertion

#### Set or clear association

Set or clear association to `<token>` folder into `<target>` folder. Arguments work
like setting a symbolic link: `ln -s <token> <target>` or removing it: `rm <target>`

    ./flow folder:associate [<options>] <token> <target> <dimension>
    
    Arguments:
      --token              Folder path or identifier (folder to associate)
      --target             Target path or identifier (where to set association)
      --dimension          Dimension
    
    Options:
      --remove             true: dissociate <token> from <target>

#### Show root folders

Show root folders with dimensions

    ./flow folder:root

#### List folder structure

List folders path names from folder system. On sort-modes SORT_STRING|SORT_NATURAL
the flag SORT_FLAG_CASE is set (case-insensitive sort).

    ./flow folder:list [<options>] <token> <dimension>

    Arguments:
      --token              Folder path or identifier (default: all)
      --dimension          Dimensions (ampersand separated list of). On empty ('')
                           takes default dimension(s)
    
    Options:
      --sort-mode          Sort mode (SORT_REGULAR | SORT_NUMERIC | SORT_STRING |
                           SORT_LOCALE_STRING | SORT_NATURAL => default)

#### Export folders

Export folders from folder system in JSON format

    ./flow folder:export [<options>] <token> <dimension>

    Arguments:
      --token              path or identifier of folder tree to export
      --dimension          Dimension
    
    Options:
      --sort-mode          Sort mode (SORT_REGULAR | SORT_NUMERIC | SORT_STRING | 
                           SORT_LOCALE_STRING | SORT_NATURAL => default)
      --pretty             Pretty-print output

#### Import folders

Import folders to folder system from file (JSON format)

    ./flow folder:import [<options>] <file>

    Arguments:
      --file               File to import folders from (JSON format)
    
    Options:
      --reset              Reset: remove all folders defined in import file (recursively)

