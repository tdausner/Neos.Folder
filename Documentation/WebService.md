## Folder Web Services API

The Neos.Folder Web Services API is JSON based. To enable the Neos.Folder Web Services the file `Settings.yaml`
contains:
```yaml
Neos:
  Flow:
    mvc:
      routes:
        'Neos.Folder': true
```
Each request of a service is defined by a request uri pattern. 
Each request is responded by an HTML status code and a JSON respond.

To utilize the Neos.Folder Web Services API a valid Neos session must be established in advance
(cookie `Neos_Session` must be set and valid).

#### Return value(s)

On request of one of the services the returned result depends on the service. The specific JSON response
is noted below, see [Requests](#Requests).

A regular JSON response is sent with HTML status code **200**.

#### Error handling

An error JSON response is sent with HTML status codes
- 401 (Unauthorized) if session invalid or 
- 400 (Bad request) for all other error conditions

The JSON error response has the form
```json
{
  "error": [
   1676315840,
    "No session"
  ]
}
```
For a list of error codes see [API.md section Error codes and messages](API.md#Error-codes-and-messages)

#### Dimensions

Dimensions are taken 
- from `$_SERVER['QUERY_STRING']` or
- from the session if `$_SERVER['QUERY_STRING']` is empty

Query string format is `<dimension name>=<dimension value>&<dimension name 2>=<dimension value 2>...`

## Restricting Folder Web Services API Access

To restrict access to the Folder Web Services API the Neos Flow
[Backend Permissions](https://docs.neos.io/guide/manual/backend-permissions) are utilized. The file
`Settings.yaml` must contain:
```yaml
Neos:
 Flow:
    security:
      authentication:
        providers:
          'Neos.Neos:Backend':
            provider: PersistedUsernamePasswordProvider
            requestPatterns:
              'Neos.Folder:Controllers':
                pattern: 'ControllerObjectName'
                patternOptions:
                  controllerObjectNamePattern: 'Neos\Folder\Controller\.*'

```
The associated file `Policy.yaml` holds all restriction information:
```yaml
privilegeTargets:
 
  'Neos\Flow\Security\Authorization\Privilege\Method\MethodPrivilege':
     'Neos.Folder:AnyAction':
      matcher: 'method(Neos\Folder\Controller\FolderServiceController->.*Action())'
 
  'Neos\Neos\Security\Authorization\Privilege\NodeTreePrivilege':
    # access to folder "/path" tree (or parts of)
    'Neos.Folder:Path':
      label: '/path folder access'
      matcher: 'isDescendantNodeOf("/path")'
    # access only to "sub-path" branch
    'Neos.Folder:SubPath':
      label: '/path/subpath folder access'
      matcher: 'isDescendantNodeOf("/path/sub-path")'

roles:
 
  'Some.Restricted:Role':
    privileges:
      - privilegeTarget: 'Neos.Folder:AnyAction'
        permission: GRANT
      - privilegeTarget: 'Neos.Folder:Path'
        permission: GRANT

  'Another.Restricted:Role':
   privileges:
    - privilegeTarget: 'Neos.Folder:AnyAction'
      permission: GRANT
    - privilegeTarget: 'Neos.Folder:SubPath'
      permission: GRANT
```
The supplied configuration file [`Policy.yaml`](../Configuration/Policy.yaml) contains more granulated
method privileges and path examples for the supplied test file [`fruit.json`](../Tests/fruit.json).

## Requests

### Add a folder to parent folder identified by token.
    
>`uriPattern: neos/folder/add/{parent}/{title}(/{nodeTypeName})`
>- `parent`: existing parent folder path or identifier
>- `title`: title of new folder. The Node name is generated from the title
>- `nodeTypeName`: optional node type name. If omitted the node type name is taken from configuration
`Neos.Folder.defaults.nodeType` (default: `Neos.Folder`)

If the query string contains the word `none` a folder without variants (dimensions) can be added.
This is useful for folders unique to all dimensions like root folders.

Response:
```json
{"ok": "folder added"}
```
### Get folder tree information
    
>`uriPattern: neos/folder/get/{token}(/{sortMode})?<dimensions>`
>- `token`: folder path or identifier
>- `sortMode`: `SORT_REGULAR | SORT_NUMERIC | SORT_STRING | SORT_LOCALE_STRING | SORT_NATURAL`.

On sort-modes `SORT_STRING|SORT_NATURAL` the flag `SORT_FLAG_CASE` is set (case-insensitive sort).
Default sort mode `SORT_NATURAL` is defined in `Routes.yaml`.

If the query string `<dimension>` contans the string `all`, folders of all dimensions are returned. 

On request to a non-existing folder AND `Neos.Folder.defaults.adoptOnEmpty: true` the requested
folder tree is adopted from the default dimension to the session's dimensions.

Response:
```json
{
  "path": "/path",
  "name": "path",
  "nodeType": "Neos.Folder:Folder",
  "variants": [
    {
      "identifier": "identifier of /path for dimension name 1",
      "dimensions": {
         "dimension name 1": [
           "dimension value 1"
         ]
      },
      "properties": {
        "title": "dimension 1 title of /path ",
        "titlePath": "dimension 1 title path of /path",
        "associations": []
      }
    }
  ],
  "children": [
  ]
}
```
### Set title at folder identified by token.
    
>`uriPattern: neos/folder/title/{token}(/{title})`
>- `token` folder path or identifier
>- `title` new title
    
On empty title the title is set to the folder node's name. The titlePath
for the folder node and children is adjusted recursively.

Response:
```json
{"ok": "folder title set to \"new title\""}
```
### Remove folder identified by token.
    
>`uriPattern: neos/folder/remove/{token}`
>- `token` folder path or identifier

Response:
```json
{"ok": "folder(s) removed"}
```
### Adopt folder(s) identified by token.

- source dimensions are default dimensions (`language=en_US` on a standard Neos installation)
- target dimensions are taken from session

>`uriPattern: neos/folder/adopt/{token}(/{recursive})`
>- `token` folder path or identifier
>- `recursive` is optional to evoke adopt of a folder tree

Response:
```json
{"ok": "folder(s) adopted"}
```
### Move a folder

Move a folder (and sub folders) identified by `<token>` to folder identified by `<target>`.
Folder **titles** and **titlePath** are kept. It is not possible to move a folder if a folder
with same title exists at `<target>`. The folder Node's path name may change.
    
>`uriPattern: neos/folder/move/{token}/{target}`
>- `token`: folder path or identifier
>- `target`: folder path or identifier of target = new parent folder

Response:
```json
{"ok": "<new folder node path>"}
```
### Set properties of a folder

Set properties at a folder. Standard folder properties (title, titlePath, associations) are
excluded. Clears properties on option `<--reset>` included and `<properties>` empty (`''`).

>`uriPattern: neos/folder/property/{token}(/{propertyString})`
>- `token`: folder path or identifier
>- `propertyString`: json encoded property string

Response:
```json
{"ok": "properties set/cleared"}
```
### Set or clear association

Set or clear association to `token` folder into <target> folder. Arguments work
like setting a symbolic link: `ln -s <token> <target>` or removing it: `rm <target>`

 >`uriPattern: neos/folder/associate/{token}/{target}(/{remove})`
 >- `token`: folder path or identifier
 >- `target`: folder path or identifier of target = new parent folder
 >- `remove`: on true dissociate <token> from <target>

Response:
```json
{"ok": "association set/cleared"}
```
