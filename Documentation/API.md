# API

The Neos.Folder package offers a PHP API and a [Web Service API](#Web-Service-API).

In case of errors the PHP API throws exceptions.

### PHP API

The Folders Repository API offers methods for processing of folders and folder trees as well as lookup of
folder trees.

Use [Flow Injection](https://flowframework.readthedocs.io/en/stable/TheDefinitiveGuide/PartIII/Caching.html#using-dependency-injection)
in your class(es) to get access to the folder repository classes.

### Web Service API

Documentation of [Web Service API](WebService.md) see [here](WebService.md).

### Error codes and messages

This list does not claim to be complete. It's a list of defined error codes and messages from `Neos.Folder`
package as well as some error codes and messages I've stumbled upon from package `Neos.Neos.ContentRepository`.

#### Package Neos.Folder
```
Error code  Error Message
----------  ---------------------------------------------------------------------------
0000000000  Undefined constant...
1676315236  Invalid dimension(s) "<dimensions>"
1676315508  Empty dimension(s)
1676315820  Cannot adopt.
1676315838  Folder for token "<token>" has no variant for dimensions "<dimension>"
1676315840  No session
1676388552  Folder token "<token>" invalid.
1676547040  Node type "<node type name>" invalid.
1676547042  Folder "<path>" exists.
1676547044  Parent folder "<parent path>" dimensions "<dimensions>" not found.
1676631409  Folder token "<token>" dimensions <dimensions>" not found
1676996654  Variant "<dimensions>" of folder "<token>" exists.
1676996656  Folder "<token>" dimensions \"$dimension\" not empty.
1677233927  Source token "<token>" and target token "<token>" are identical. OR 
            Source token "<token>" is ancestor of target token "<token>".
1677233930  Target token "<token>" is parent of source token "<token>".
1677237583  Folder with same title exists
1677263000  Invalid JSON folder file "<filename>", JSON error: <json error message>
1677264338  Invalid file properties: <properties>
1677404051  NodeType "<node type name>" missing default folder properties.
1680977700  Access inhibited by privileges.
```
#### Package Neos.Neos.ContentRepository
```
Error code  Error Message
----------  ---------------------------------------------------------------------------
1292503471  Node with path "<path>" already exists in workspace...
1430075362  Given path "<path>" is not the beginning of "<sub path>", cannot get...
1431281250  The given string was not a valid contextPath.
```